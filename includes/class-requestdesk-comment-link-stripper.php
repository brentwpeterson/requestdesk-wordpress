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
 * Every link surface -- a real `<a href>` tag, the Claude second pass, and
 * the comment's Website field (comment_author_url) -- is checked against an
 * allowlist (see DEFAULT_ALLOWED_LINK_DOMAINS) rather than stripped
 * unconditionally. A link that resolves to one of RequestDesk's own domains
 * survives; anything else doesn't, INCLUDING a link that looks like a
 * perfectly legitimate personal site with no spam markers at all. The policy
 * is "everything gets stripped except our own properties," deliberately not
 * "spam gets stripped, real links survive" -- an outbound link's legitimacy
 * doesn't change what it costs the site to host it. The Website field is
 * checked on its own, independent of whether the author's NAME looked spammy
 * -- found live: a comment from "Hava Durumu Uşak" (an ordinary-looking
 * name) had its Website field pointing at an unrelated .com.tr domain with
 * no target="_blank", so one click took a visitor off the site. When a
 * Website field gets repointed, it goes to RequestDesk's own explainer post
 * for this feature rather than going dead, filterable via
 * requestdesk_author_placeholder_link.
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

    /** True only while code that MEANS to change a comment's IP is writing. */
    private static $allow_ip_change = false;

    /**
     * The IP the one-time repair means to write. Set only around its own
     * wp_update_comment() call, because the IP in $data has already been
     * through pre_comment_user_ip by the time keep_stored_author_ip() sees it.
     */
    private static $intended_ip = null;

    /**
     * Stand-in for a comment author name that turned out to be a domain.
     * Filterable so a site can use its own wording without touching code:
     *     add_filter('requestdesk_stripped_author_placeholder', fn() => 'Guest');
     */
    const AUTHOR_PLACEHOLDER = 'Reader';

    /**
     * Where the placeholder name links to instead of going dead. Points at
     * RequestDesk's own post explaining this exact feature -- transparent
     * (a moderator or reader who clicks "Reader" finds out why the name
     * changed) rather than promotional by stealth. hardcode-ok: this is
     * RequestDesk's own vendor URL for a RequestDesk-branded feature this
     * connector plugin ships, the same category the vendor-URL gate already
     * carves out an exception for -- but still filterable, same as the QR
     * Redirect default and the author placeholder above, so an install that
     * doesn't want an outbound link here at all can set this to ''.
     *     add_filter('requestdesk_author_placeholder_link', fn() => '');
     */
    const EXPLAINER_URL = 'https://requestdesk.ai/blog/requestdesks-wordpress-module-strips-spam-links-from-comments#comment-link-stripper'; // hardcode-ok: RequestDesk's own explainer post for this RequestDesk-branded feature

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

    /**
     * The only destinations a link is allowed to survive pointing at.
     * Everything else gets stripped, on the reasoning that a comment link's
     * default state is spam until proven otherwise -- found live: a comment
     * from "Hava Durumu Uşak" (an unremarkable-looking name, doesn't match
     * looks_like_domain() at all) had its Website field pointing at
     * spam-domain.example with no target="_blank", so one click took a
     * visitor off the site entirely. hardcode-ok: these are RequestDesk's
     * own family of properties (this plugin's vendor, plus the two brands
     * that ship it) -- but still filterable, so an install that wants a
     * DIFFERENT allowlist (or none at all) can set its own:
     *     add_filter('requestdesk_link_strip_allowlist', fn() => array('example.com'));
     */
    const DEFAULT_ALLOWED_LINK_DOMAINS = array(
        'contentcucumber.com', // hardcode-ok: RequestDesk's own family of properties, see docblock above
        'talk-commerce.com',   // hardcode-ok: RequestDesk's own family of properties, see docblock above
        'requestdesk.ai',      // hardcode-ok: this plugin's own vendor domain, see docblock above
    );

    public function __construct() {
        add_action('transition_comment_status', array($this, 'maybe_strip_links'), 10, 3);

        // A comment approved AT INSERT never fires transition_comment_status:
        // wp_insert_comment() / wp_new_comment() only fire wp_insert_comment
        // and comment_post. That is the path WordPress takes when the
        // "Comment author must have a previously approved comment" option
        // auto-approves a returning commenter, or when Akismet passes one.
        // Found live 2026-11-05: a comment on a production site came in
        // from a commenter whose first comment (7778) had been stripped and
        // approved, so core auto-approved the second one with its spam
        // Website field intact and this class never saw it.
        add_action('wp_insert_comment', array($this, 'maybe_strip_on_insert'), 10, 2);

        // Keep the commenter's IP when a moderator edits or approves through
        // the REST API. Core's WP_REST_Comments_Controller::prepare_item_for_database()
        // fills comment_author_IP from $_SERVER['REMOTE_ADDR'] on EVERY request,
        // update included, so approving from the WordPress mobile app or any
        // REST client overwrites the spammer's IP with the moderator's own.
        // Found live 2026-11-05: 22 of 35 approved comments on a production
        // site carried the owner's home IP, while Akismet's
        // akismet_as_submitted meta still held the real submitting IPs.
        // Runs whether or not link stripping is enabled: this is data
        // integrity, not link policy.
        add_filter('rest_preprocess_comment', array(__CLASS__, 'keep_author_ip_on_rest_update'), 10, 2);

        // Last line of defense for the same data: every wp_update_comment()
        // keeps the IP already stored, whoever calls it. Found live
        // 2026-11-06: an edit through this plugin's own /comments/{id} route
        // (plain wp_update_comment, no REST controller) stamped the calling
        // server's IP on three comments on a production site, while the same
        // call on the local copy kept the IP. Something on that host rewrites
        // the IP during wp_filter_comment(); wp_update_comment_data runs after
        // it, right before the database write, so this holds whatever it is.
        add_filter('wp_update_comment_data', array(__CLASS__, 'keep_stored_author_ip'), 999, 3);

        // One-time repair for comments already stamped with a moderator's IP
        // by the REST bug above. Runs once per site on the first admin page
        // load after this version lands; the result is kept in an option.
        add_action('admin_init', array(__CLASS__, 'maybe_restore_author_ips_once'));

        // A link that survives (the author link, or an allowlisted content
        // link) must never navigate a reader away from the page in the same
        // tab -- found live: an author link opened in the same window, so
        // one click took a visitor off the site entirely with no way back
        // except the browser's back button. Applies to every comment, not
        // just ones this feature has touched, so it also covers a comment
        // approved before this fix existed.
        if (self::is_enabled()) {
            add_filter('get_comment_author_link', array($this, 'force_new_tab_on_author_link'));
        }
    }

    /**
     * Force target="_blank" rel="noopener noreferrer" onto the comment
     * author's link. Runs on every comment display, not just ones this
     * class has stripped -- a comment approved before this fix existed (or
     * before the site had this feature enabled at all) still gets it.
     */
    public function force_new_tab_on_author_link($link_html) {
        return self::add_new_tab_attrs($link_html);
    }

    /**
     * Add target="_blank" rel="noopener noreferrer" to an <a> tag's HTML,
     * without duplicating either attribute if already present. Shared by
     * the author-link filter above and the content-link survivor path in
     * strip_link_tag_unless_allowed() below.
     */
    private static function add_new_tab_attrs($tag_html) {
        if (strpos($tag_html, 'target=') === false) {
            $tag_html = preg_replace('/<a\b/i', '<a target="_blank"', $tag_html, 1);
        }
        if (strpos($tag_html, 'rel=') === false) {
            $tag_html = preg_replace('/<a\b/i', '<a rel="noopener noreferrer"', $tag_html, 1);
        } elseif (!preg_match('/rel=(["\'])[^"\']*noopener/i', $tag_html)) {
            // A rel attribute exists (e.g. WordPress's own "external nofollow
            // ugc") but doesn't carry noopener -- append rather than clobber
            // it, so existing rel values (and their SEO/security meaning)
            // are preserved.
            $tag_html = preg_replace('/rel=(["\'])([^"\']*)\1/i', 'rel=$1$2 noopener noreferrer$1', $tag_html, 1);
        }
        return $tag_html;
    }

    public static function is_enabled() {
        $settings = get_option('requestdesk_settings', array());
        return !empty($settings['strip_comment_links']);
    }

    /**
     * Fires on every comment_approved change made after insert (a wp-admin
     * click, a bulk action, the REST API, wp_set_comment_status()). A comment
     * approved at insert does NOT come through here -- see maybe_strip_on_insert().
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

        // The Website field gets checked independently of the name -- a
        // spam link doesn't need a spam-shaped name to go with it. Repointed
        // to the explainer post rather than cleared, so the link goes
        // somewhere useful rather than dead. A URL that already resolves to
        // one of our own domains (see DEFAULT_ALLOWED_LINK_DOMAINS) is the
        // ONLY kind that survives -- a real commenter's OTHER personal site
        // gets repointed too. Policy is deliberately "everything gets
        // stripped except our own properties," not "spam gets stripped, real
        // links survive": an outbound link's legitimacy doesn't change what
        // it costs the site to host it.
        $original_url = (string) $comment->comment_author_url;
        $url_is_allowed = ($original_url === '') || self::is_allowed_link_host(self::url_host($original_url));
        $explainer_url = (string) apply_filters('requestdesk_author_placeholder_link', self::EXPLAINER_URL);
        // Also populate the link when the name alone was flagged and there
        // was nothing in the Website field to begin with -- "Reader" still
        // deserves somewhere useful to point, even with nothing to strip.
        $needs_link_fix = !$url_is_allowed || ($author_changed && $original_url === '');
        $url_changed = ($needs_link_fix && $explainer_url !== $original_url);

        if (!$content_changed && !$author_changed && !$url_changed) {
            return;
        }

        $update = array('comment_ID' => $comment->comment_ID);
        if ($content_changed) {
            $update['comment_content'] = $final;
        }
        if ($author_changed) {
            $update['comment_author'] = $final_author;
        }
        if ($url_changed) {
            $update['comment_author_url'] = $explainer_url;
        }

        self::$updating = true;
        wp_update_comment($update);
        self::$updating = false;

        // Kept so a moderator can see what was removed, not to restore it --
        // restoring the link (or the domain-as-name, or the author URL) is
        // exactly what this feature exists to prevent.
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
        if ($url_changed) {
            update_comment_meta($comment->comment_ID, '_requestdesk_author_url_original', $original_url);
        }
        update_comment_meta($comment->comment_ID, '_requestdesk_links_stripped_at', current_time('mysql'));
    }

    /**
     * On a REST update of an existing comment, drop the comment_author_IP that
     * core filled from the moderator's REMOTE_ADDR, unless the caller set
     * author_ip explicitly. With the key gone, a status-only request falls
     * through to core's status-only path and the stored IP is left as is.
     * Creates (no comment id on the request) are untouched.
     *
     * @param array           $prepared_comment
     * @param WP_REST_Request $request
     * @return array
     */
    public static function keep_author_ip_on_rest_update($prepared_comment, $request) {
        if (!is_array($prepared_comment) || !($request instanceof WP_REST_Request)) {
            return $prepared_comment;
        }
        if (empty($request['id'])) {
            return $prepared_comment;
        }
        if (isset($request['author_ip'])) {
            // An explicit author_ip is a deliberate change; let it through
            // keep_stored_author_ip() for this request.
            self::$allow_ip_change = true;
            return $prepared_comment;
        }

        unset($prepared_comment['comment_author_IP']);

        return $prepared_comment;
    }

    /**
     * wp_update_comment_data filter: put the stored IP back unless the write
     * is one that means to change it (the one-time repair, or a REST request
     * that passed author_ip). $comment is the row as it was before the update.
     *
     * @param array $data       Columns about to be written.
     * @param array $comment    Original comment row (slashed).
     * @param array $commentarr Merged update arguments.
     * @return array
     */
    public static function keep_stored_author_ip($data, $comment, $commentarr) {
        if (!is_array($data) || !is_array($comment)) {
            return $data;
        }
        if (self::$intended_ip !== null) {
            // Found live 2026-11-06: the 2.65.3 repair reported 7 IPs restored
            // and every one read back as the IP of the admin whose page load
            // ran it. The host rewrites the IP in pre_comment_user_ip, which
            // runs before this filter, so the value has to be set here.
            $data['comment_author_IP'] = self::$intended_ip;
            return $data;
        }
        if (self::$allow_ip_change) {
            return $data;
        }
        if (isset($comment['comment_author_IP']) && $comment['comment_author_IP'] !== '') {
            $data['comment_author_IP'] = $comment['comment_author_IP'];
        }

        return $data;
    }

    /**
     * Option that records the one-time IP repair ran, and what it changed.
     * _v2 (2.65.3), _v3 (2.65.5): earlier runs could not hold their writes
     * against a host rewrite in pre_comment_user_ip, so it runs again.
     */
    const IP_RESTORE_OPTION = 'requestdesk_comment_ip_restore_v3';

    /**
     * Put back each comment's original IP from Akismet's akismet_as_submitted
     * meta, which Akismet writes once at submission and never rewrites. Only a
     * valid IP that differs from the stored one is written. Comments without
     * that meta (posted before Akismet kept it) are left alone: there is no
     * record of their real IP to restore from.
     */
    public static function maybe_restore_author_ips_once() {
        if (get_option(self::IP_RESTORE_OPTION)) {
            return;
        }
        if (!current_user_can('moderate_comments')) {
            return;
        }

        $changed = array();
        $comments = get_comments(array(
            'status'   => 'all',
            'number'   => 0,
            'meta_key' => 'akismet_as_submitted',
        ));

        foreach ($comments as $comment) {
            $submitted = get_comment_meta($comment->comment_ID, 'akismet_as_submitted', true);
            $original = (is_array($submitted) && isset($submitted['comment_author_IP'])) ? (string) $submitted['comment_author_IP'] : '';
            if ($original === '' || !filter_var($original, FILTER_VALIDATE_IP) || $original === $comment->comment_author_IP) {
                continue;
            }

            self::$updating = true;
            self::$intended_ip = $original;
            $result = wp_update_comment(array(
                'comment_ID'        => $comment->comment_ID,
                'comment_author_IP' => $original,
            ), true);
            self::$intended_ip = null;
            self::$updating = false;

            if (!is_wp_error($result)) {
                $changed[$comment->comment_ID] = array('from' => $comment->comment_author_IP, 'to' => $original);
            }
        }

        update_option(self::IP_RESTORE_OPTION, array(
            'ran_at'  => current_time('mysql'),
            'checked' => count($comments),
            'changed' => $changed,
        ), false);
    }

    /**
     * Run the same strip on a comment that is already approved when it is
     * inserted (returning-commenter auto-approval, Akismet, a plugin calling
     * wp_insert_comment() with comment_approved = 1). Held, spam and trashed
     * inserts are left alone; they reach maybe_strip_links() if and when
     * they are approved later.
     *
     * @param int        $comment_id
     * @param WP_Comment $comment
     */
    public function maybe_strip_on_insert($comment_id, $comment) {
        if (!($comment instanceof WP_Comment)) {
            $comment = get_comment($comment_id);
        }
        if (!$comment || (string) $comment->comment_approved !== '1') {
            return;
        }

        $this->maybe_strip_links('approved', 'new', $comment);
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
     * True when $host is (or is a subdomain of) one of the allowed domains.
     * Suffix-matched on a leading dot, not a substring match -- an allowed
     * domain of "example.com" matches "blog.example.com" but not
     * "example.com.evil.example", which is exactly the bypass a plain
     * substring check would open up.
     */
    private static function is_allowed_link_host($host) {
        $host = strtolower((string) $host);
        if ($host === '') {
            return false;
        }

        $allowed = apply_filters('requestdesk_link_strip_allowlist', self::DEFAULT_ALLOWED_LINK_DOMAINS);
        if (!is_array($allowed)) {
            return false;
        }

        foreach ($allowed as $domain) {
            $domain = strtolower(trim((string) $domain));
            if ($domain === '') {
                continue;
            }
            if ($host === $domain || substr($host, -(strlen($domain) + 1)) === '.' . $domain) {
                return true;
            }
        }

        return false;
    }

    /** Host of a URL, or '' if it can't be parsed -- never lets a bad URL fall through as "allowed". */
    private static function url_host($url) {
        $host = wp_parse_url((string) $url, PHP_URL_HOST);
        return is_string($host) ? $host : '';
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

        $result = $claude->strip_remaining_links($content, apply_filters('requestdesk_link_strip_allowlist', self::DEFAULT_ALLOWED_LINK_DOMAINS));

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

        // A light edit only removes text. Model output that carries more angle
        // brackets than the input did is not a light edit: a commenter can write
        // instructions that make Claude emit markup, and wp_update_comment() stores
        // it unfiltered when a moderator with unfiltered_html approves. Reject it,
        // then filter whatever is kept the way core filters a comment.
        if (substr_count($result, '<') > substr_count($content, '<')) {
            if (function_exists('error_log')) {
                error_log('RequestDesk: Claude link-strip pass rejected on comment approval -- output added markup, keeping regex-only result.');
            }
            return $content;
        }
        $result = wp_kses($result, wp_kses_allowed_html('pre_comment_content'));

        // Claude's own light edit can leave the same kind of double-space gap
        // the regex pass guards against (removing "example dot com" out of the
        // middle of a sentence, for instance) -- tidy it the same way.
        return self::tidy_whitespace($result);
    }

    /**
     * Remove every `<a>...</a>` phrase -- tag and its anchor text -- whose
     * href does NOT resolve to an allowed domain, then every bare URL
     * (a scheme or www. prefix) whose host is not allowed.
     * A link pointing at one of our own domains (see
     * DEFAULT_ALLOWED_LINK_DOMAINS) survives untouched, tag and text both.
     *
     * Public + static so it can be unit tested and reused (e.g. a WP-CLI
     * backfill command) without standing up the whole hook.
     */
    public static function strip_links($content) {
        $stripped = preg_replace_callback(
            '#<a\b[^>]*>.*?</a>#is',
            array(__CLASS__, 'strip_link_tag_unless_allowed'),
            $content
        );
        if ($stripped === null) {
            // preg_replace failed (e.g. PCRE backtrack limit on pathological
            // input) -- never destroy the comment because of that.
            return $content;
        }

        // Bare URLs too. WordPress turns a plain scheme-prefixed URL in comment text
        // into a live link at display time (make_clickable), so a URL with no
        // <a> tag is still a link on the page. Found live 2026-11-06: a comment
        // carried a product URL in parentheses through approval because only the
        // Claude pass looked for bare URLs, and that pass was not available.
        $bare = preg_replace_callback(
            '#(?<![\w"\'=/@.-])(?:https?://|www\.)[^\s<>"\']+#i',
            array(__CLASS__, 'strip_bare_url_unless_allowed'),
            $stripped
        );
        if ($bare !== null && $bare !== $stripped) {
            // "Tool (URL), more" -> "Tool (), more" -> "Tool, more".
            $bare = preg_replace('/[ \t]*\(\s*\)/', '', $bare);
            $bare = preg_replace('/[ \t]+([,.;:!?])/', '$1', $bare);
            $stripped = $bare;
        }

        return self::tidy_whitespace($stripped);
    }

    /**
     * preg_replace_callback handler for a bare URL: keep it if its host is
     * allowed, else drop it. Trailing sentence punctuation and a closing
     * parenthesis are kept outside the URL so the sentence still reads.
     */
    private static function strip_bare_url_unless_allowed($matches) {
        $url = $matches[0];
        $tail = '';
        if (preg_match('/[.,;:!?)\]]+$/', $url, $tail_match)) {
            $tail = $tail_match[0];
            $url = substr($url, 0, -strlen($tail));
        }

        $parse = (stripos($url, 'www.') === 0) ? 'http://' . $url : $url;
        if (self::is_allowed_link_host(self::url_host($parse))) {
            return $matches[0];
        }

        return $tail;
    }

    /**
     * preg_replace_callback handler: keep an `<a>` tag as-is (plus force
     * target="_blank" rel="noopener noreferrer" -- a surviving link must
     * still never navigate the reader away in the same tab) if its href is
     * allowed, else drop the whole phrase.
     */
    private static function strip_link_tag_unless_allowed($matches) {
        $tag = $matches[0];

        if (preg_match('/href\s*=\s*(["\'])(.*?)\1/is', $tag, $href_match)
            && self::is_allowed_link_host(self::url_host($href_match[2]))) {
            return self::add_new_tab_attrs($tag);
        }

        return '';
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
