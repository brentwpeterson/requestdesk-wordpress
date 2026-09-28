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
 * Same pass also catches a domain-shaped author name ("spam-domain.example" typed
 * into the Name field) -- spammers set it deliberately so it reads like
 * anchor text next to every comment they get approved, link or no link.
 * A name shaped like a real domain (label + dot + a recognized TLD) is
 * replaced with a neutral placeholder. See maybe_strip_domain_name().
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

    /**
     * Stand-in for a comment author name that turned out to be a domain.
     * Filterable so a site can use its own wording without touching code:
     *     add_filter('requestdesk_stripped_author_placeholder', fn() => 'Guest');
     */
    const AUTHOR_PLACEHOLDER = 'Reader';

    /**
     * Common TLDs a spam name is actually built from. Not exhaustive -- there
     * are 1500+ real TLDs -- but a bounded, recognizable list is what keeps
     * this from flagging an ordinary name typed without a space after a
     * period ("Mr.Anderson"). Extend via the requestdesk_domain_like_tlds
     * filter rather than editing this list.
     */
    const COMMON_TLDS = array(
        'com', 'net', 'org', 'io', 'co', 'biz', 'info', 'xyz', 'online',
        'site', 'store', 'shop', 'club', 'top', 'vip', 'pro', 'me', 'tv',
        'cc', 'ai', 'app', 'dev', 'tech', 'agency', 'company', 'solutions',
        'services', 'us', 'uk', 'ca', 'de', 'cn', 'ru', 'in', 'eu', 'asia',
        'live', 'blog', 'news', 'email', 'cloud', 'digital', 'media',
        'group', 'world', 'work',
    );

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
        $content_changed = ($final !== $original);

        $original_author = (string) $comment->comment_author;
        $final_author = self::maybe_strip_domain_name($original_author);
        $author_changed = ($final_author !== $original_author);

        if (!$content_changed && !$author_changed) {
            return;
        }

        $update = array('comment_ID' => $comment->comment_ID);
        if ($content_changed) {
            $update['comment_content'] = $final;
        }
        if ($author_changed) {
            $update['comment_author'] = $final_author;
        }

        self::$updating = true;
        wp_update_comment($update);
        self::$updating = false;

        // Kept so a moderator can see what was removed, not to restore it --
        // restoring the link (or the domain-as-name) is exactly what this
        // feature exists to prevent.
        if ($content_changed) {
            update_comment_meta($comment->comment_ID, '_requestdesk_links_stripped_original', $original);
            update_comment_meta(
                $comment->comment_ID,
                '_requestdesk_links_stripped_method',
                ($final !== $regex_pass) ? 'regex+claude' : 'regex'
            );
        }
        if ($author_changed) {
            update_comment_meta($comment->comment_ID, '_requestdesk_author_name_original', $original_author);
        }
        update_comment_meta($comment->comment_ID, '_requestdesk_links_stripped_at', current_time('mysql'));
    }

    /**
     * Swap a domain-shaped author name for a neutral placeholder. Only the
     * whole name is replaced, not just the matched substring -- a name a
     * spam tool generated ("Best spam-domain.example Deals") is not a real name
     * with an embedded typo, it's manufactured text, so a partial edit would
     * leave an equally awkward result.
     */
    private static function maybe_strip_domain_name($author) {
        if ($author === '' || !self::looks_like_domain($author)) {
            return $author;
        }

        return (string) apply_filters('requestdesk_stripped_author_placeholder', self::AUTHOR_PLACEHOLDER);
    }

    /**
     * True when $text contains a label + dot + recognized TLD with no
     * whitespace around the dot -- "spam-domain.example", not "Mr. Anderson"
     * (space after the period keeps an ordinary sentence/name out of this).
     */
    private static function looks_like_domain($text) {
        $tlds = apply_filters('requestdesk_domain_like_tlds', self::COMMON_TLDS);
        if (!is_array($tlds) || empty($tlds)) {
            return false;
        }

        $pattern = '/[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?\.(?:' . implode('|', array_map('preg_quote', $tlds)) . ')\b/i';

        return (bool) preg_match($pattern, $text);
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
