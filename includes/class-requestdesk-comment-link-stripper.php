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
        $stripped = self::strip_links($original);

        if ($stripped === $original) {
            return;
        }

        self::$updating = true;
        wp_update_comment(array(
            'comment_ID'      => $comment->comment_ID,
            'comment_content' => $stripped,
        ));
        self::$updating = false;

        // Kept so a moderator can see what was removed, not to restore it --
        // restoring the link is exactly what this feature exists to prevent.
        update_comment_meta($comment->comment_ID, '_requestdesk_links_stripped_original', $original);
        update_comment_meta($comment->comment_ID, '_requestdesk_links_stripped_at', current_time('mysql'));
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

        // Collapse whitespace the removed phrase left behind so "Great post
        // <a ...>check this</a> thanks" doesn't become "Great post  thanks",
        // and so a link removed at the end of a line doesn't leave a trailing
        // space dangling before the linebreak.
        $stripped = preg_replace('/[ \t]{2,}/', ' ', $stripped);
        $stripped = preg_replace('/[ \t]+(?=\n)/', '', $stripped);
        $stripped = preg_replace('/\n{3,}/', "\n\n", $stripped);

        return trim($stripped);
    }
}
