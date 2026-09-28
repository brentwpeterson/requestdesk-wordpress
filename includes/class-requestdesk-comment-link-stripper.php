<?php
/**
 * RequestDesk Comment Link Stripper
 *
 * WHY THIS EXISTS: approving a comment in wp-admin is a judgment call on the
 * text, not on every link buried inside it. A comment can read like a real
 * reader and still carry an anchor tag pointing at a spam/SEO destination --
 * moderators approve the former and don't notice the latter. This strips any
 * `<a>...</a>` phrase out of a comment the moment it transitions to approved,
 * so the approved comment can never carry an outbound link, regardless of who
 * or what approved it (a manual click, a bulk action, the REST API, or a
 * plugin like Akismet auto-approving a previously-legit commenter).
 *
 * Two passes. A regex strips real `<a href>` tags first -- free, instant,
 * always runs. If a Claude API key is configured (RequestDesk > Settings),
 * a second pass hands the regex-stripped text to Claude to catch link-shaped
 * text the regex can't: bare URLs, spelled-out or obfuscated domains ("example
 * dot com"). See RequestDesk_Claude_Integration::strip_remaining_links(). No
 * key configured, or the API call fails/times out/misbehaves, and moderation
 * proceeds on the regex-only result -- approving a comment must never depend
 * on an external API call succeeding.
 *
 * Off by default. Toggle: RequestDesk > Settings > Plugin Settings > Strip
 * links from approved comments (requestdesk_settings[strip_comment_links]).
 *
 * @package RequestDesk_Connector
 * @since 2.51.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class RequestDesk_Comment_Link_Stripper {

    /** Guards against re-entering wp_update_comment() from inside our own hook. */
    private static $updating = false;

    public function __construct() {
        add_action('transition_comment_status', array($this, 'maybe_strip_links'), 10, 3);
    }

    public static function is_enabled() {
        $settings = get_option('requestdesk_settings', array());
        return !empty($settings['strip_comment_links']);
    }

    /**
     * Fires on every comment_approved change, including the 'new' -> 'approved'
     * transition an auto-approved comment takes on first insert.
     *
     * @param string     $new_status 'approved', 'unapproved', 'spam', 'trash', or 'deleted'.
     * @param string     $old_status Same vocabulary.
     * @param WP_Comment $comment
     */
    public function maybe_strip_links($new_status, $old_status, $comment) {
        // Re-entrancy guard first -- cheapest check, and it must run even if
        // the setting or approval state changed underneath us mid-request.
        if (self::$updating) {
            return;
        }

        if ($new_status !== 'approved' || $old_status === 'approved') {
            return;
        }

        if (!self::is_enabled()) {
            return;
        }

        // Pingbacks/trackbacks ARE a link back to the source post -- stripping
        // their content would delete the entire point of the comment. Only
        // ordinary reader comments are in scope.
        if (!empty($comment->comment_type) && $comment->comment_type !== 'comment') {
            return;
        }

        $original = (string) $comment->comment_content;
        $regex_pass = self::strip_links($original);
        $final = self::maybe_apply_claude_pass($regex_pass);

        if ($final === $original) {
            return;
        }

        self::$updating = true;
        wp_update_comment(array(
            'comment_ID'      => $comment->comment_ID,
            'comment_content' => $final,
        ));
        self::$updating = false;

        // Kept so a moderator can see what was removed, not to restore it --
        // restoring the link is exactly what this feature exists to prevent.
        update_comment_meta($comment->comment_ID, '_requestdesk_links_stripped_original', $original);
        update_comment_meta($comment->comment_ID, '_requestdesk_links_stripped_at', current_time('mysql'));
        update_comment_meta(
            $comment->comment_ID,
            '_requestdesk_links_stripped_method',
            ($final !== $regex_pass) ? 'regex+claude' : 'regex'
        );
    }

    /**
     * Second pass: hand the regex-stripped content to Claude to catch
     * link-shaped text the regex can't (bare URLs, spelled-out/obfuscated
     * domains). Runs whether or not the regex pass changed anything, since
     * that's exactly the gap it exists to fill -- a comment that is PURE
     * bare-URL spam never touches an <a> tag at all.
     *
     * Never blocks or breaks moderation: no API key configured, a failed
     * request, a timeout, or a response that comes back suspiciously LONGER
     * than what went in (a light edit only removes text, so it should never
     * grow) all fall back to the regex-only result. Failures are logged, not
     * surfaced to the moderator -- the comment still gets approved either way.
     */
    private static function maybe_apply_claude_pass($content) {
        if ($content === '' || !class_exists('RequestDesk_Claude_Integration')) {
            return $content;
        }

        $claude = new RequestDesk_Claude_Integration();
        if (!$claude->is_available()) {
            return $content;
        }

        $result = $claude->strip_remaining_links($content);

        if (is_wp_error($result)) {
            if (function_exists('error_log')) {
                error_log('RequestDesk: Claude link-strip pass skipped on comment approval -- ' . $result->get_error_message());
            }
            return $content;
        }

        if (strlen($result) > strlen($content) + 10) {
            if (function_exists('error_log')) {
                error_log('RequestDesk: Claude link-strip pass rejected on comment approval -- output longer than input, keeping regex-only result.');
            }
            return $content;
        }

        // Claude's own light edit can leave the same kind of double-space gap
        // the regex pass guards against (removing "example dot com" out of the
        // middle of a sentence, for instance) -- tidy it the same way.
        return self::tidy_whitespace($result);
    }

    /**
     * Remove every `<a>...</a>` phrase -- tag and its anchor text -- from
     * comment content. Bare, un-linked URLs are left alone; this only removes
     * what was deliberately wrapped in a link.
     *
     * Public + static so it can be unit tested and reused (e.g. a WP-CLI
     * backfill command) without standing up the whole hook.
     */
    public static function strip_links($content) {
        $stripped = preg_replace('#<a\b[^>]*>.*?</a>#is', '', $content);
        if ($stripped === null) {
            // preg_replace failed (e.g. PCRE backtrack limit on pathological
            // input) -- never destroy the comment because of that.
            return $content;
        }

        return self::tidy_whitespace($stripped);
    }

    /**
     * Collapse whitespace a removed link phrase left behind, so "Great post
     * <a ...>check this</a> thanks" doesn't become "Great post  thanks" (or,
     * from the Claude pass, "visit example dot com for more" doesn't become
     * "visit  for more"). Also drops a trailing space a removed link leaves
     * dangling before a linebreak.
     */
    private static function tidy_whitespace($content) {
        $tidied = preg_replace('/[ \t]{2,}/', ' ', $content);
        $tidied = preg_replace('/[ \t]+(?=\n)/', '', $tidied);
        $tidied = preg_replace('/\n{3,}/', "\n\n", $tidied);

        return trim($tidied);
    }
}
