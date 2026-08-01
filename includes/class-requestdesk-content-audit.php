<?php
/**
 * RequestDesk Content Audit
 *
 * Internal audit dashboard (RequestDesk → Content Audit).
 *
 * A pluggable registry of per-page CHECKS (pass/fail ✓/✗ columns) and INFO
 * columns (values, e.g. AEO score, freshness), rendered as a unified rollup:
 * one row per published Page. This is the read-only "what needs work" surface;
 * every row cross-links to the Bulk Optimizer (the remediation workbench).
 *
 * JSON-aware: CC's landing pages render from landing-data/{slug}.json rather
 * than post_content, so every content check reads the JSON for those pages
 * instead of the (empty) post body. Without this, ~77% of pages would false-
 * flag on every check.
 *
 * Add a check → register_check(); add a value column → register_info().
 *
 * @since 2.29.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class RequestDesk_Content_Audit {

    const MENU_SLUG = 'requestdesk-content-audit';

    /** Word floor below which a page is "thin". */
    const THIN_WORDS = 300;

    /** Days since last modified beyond which a page is "stale". */
    const STALE_DAYS = 180;

    /**
     * Theme templates that render from landing-data/{slug}.json instead of
     * post_content. A page on one of these keeps its content in the JSON.
     */
    const JSON_TEMPLATES = array(
        'page-guide-json.php',
        'page-landing-json.php',
        'page-landing-json-no-nav.php',
        'page-pillar-json.php',
        'page-listicle-json.php',
        'page-landing-growth.php',
        'template-landing-standalone.php',
    );

    /** @var array<string,array> id => pass/fail check definition */
    private $checks = array();

    /** @var array<string,array> id => info column definition */
    private $infos = array();

    public function __construct() {
        // Priority 11: parent 'requestdesk-aeo-analytics' menu is built at 10.
        add_action('admin_menu', array($this, 'register_menu'), 11);
        $this->register_default_checks();
    }

    /* ------------------------------------------------------------ Registry */

    /**
     * @param string   $id       Stable slug.
     * @param string   $label    Column header.
     * @param string   $desc     One-line explanation (shown as legend).
     * @param callable $callback fn(WP_Post) => array{pass:bool, detail:string}
     */
    public function register_check($id, $label, $desc, $callback, $tile = null) {
        $tile = $tile ?: 'missing ' . strtolower($label);
        $this->checks[$id] = compact('id', 'label', 'desc', 'callback', 'tile');
    }

    /**
     * @param callable $callback fn(WP_Post) => array{text:string, flag:bool}
     */
    public function register_info($id, $label, $callback) {
        $this->infos[$id] = compact('id', 'label', 'callback');
    }

    private function register_default_checks() {
        $this->register_check(
            'images',
            'Images',
            'No featured image, no in-content <img>, and (JSON pages) no image referenced in landing-data JSON.',
            array($this, 'check_missing_images'),
            'missing images'
        );
        $this->register_check(
            'thin',
            'Thin',
            'Fewer than ' . self::THIN_WORDS . ' words of body text (read from the JSON for landing pages).',
            array($this, 'check_thin_content'),
            'thin pages'
        );
        $this->register_check(
            'schema',
            'FAQ schema',
            'No FAQ section — JSON pages need a "faq" block; standard pages need FAQ markup in the body.',
            array($this, 'check_faq'),
            'missing FAQ'
        );

        $this->register_info('freshness', 'Updated', array($this, 'info_freshness'));
        $this->register_info('aeo', 'AEO score', array($this, 'info_aeo'));
    }

    /* -------------------------------------------------------------- Checks */

    /**
     * Has an image if: featured image, OR (JSON page) its landing-data JSON
     * references an image, OR (standard page) post_content has an <img>.
     *
     * @return array{pass:bool, detail:string}
     */
    public function check_missing_images($post) {
        if (has_post_thumbnail($post->ID)) {
            return array('pass' => true, 'detail' => 'Has featured image');
        }
        $json_file = $this->resolve_landing_json($post);
        if ($json_file !== null) {
            if (!file_exists($json_file)) {
                return array('pass' => false, 'detail' => 'JSON data file missing (' . basename($json_file) . ')');
            }
            if ($this->json_has_image($json_file)) {
                return array('pass' => true, 'detail' => 'JSON references image(s)');
            }
            return array('pass' => false, 'detail' => 'No image in ' . basename($json_file));
        }
        if (preg_match('/<img[^>]*>/i', (string) $post->post_content)) {
            return array('pass' => true, 'detail' => 'Has in-content <img>');
        }
        return array('pass' => false, 'detail' => 'No in-content <img> and no featured image');
    }

    /**
     * Thin if body word count < THIN_WORDS. JSON pages: words come from the
     * JSON's text values; standard pages: from stripped post_content.
     *
     * @return array{pass:bool, detail:string}
     */
    public function check_thin_content($post) {
        $words = $this->word_count_for($post);
        if ($words === null) {
            return array('pass' => false, 'detail' => 'JSON data file missing');
        }
        if ($words >= self::THIN_WORDS) {
            return array('pass' => true, 'detail' => $words . ' words');
        }
        return array('pass' => false, 'detail' => 'Only ' . $words . ' words (min ' . self::THIN_WORDS . ')');
    }

    /**
     * Has FAQ if: JSON page carries a non-empty "faq"/"faqs" block, OR a
     * standard page's body contains FAQ schema / an FAQ section.
     *
     * @return array{pass:bool, detail:string}
     */
    public function check_faq($post) {
        $json_file = $this->resolve_landing_json($post);
        if ($json_file !== null) {
            if (!file_exists($json_file)) {
                return array('pass' => false, 'detail' => 'JSON data file missing');
            }
            $data = json_decode((string) file_get_contents($json_file), true);
            $faq  = is_array($data) ? ($data['faq'] ?? $data['faqs'] ?? null) : null;
            if (!empty($faq)) {
                return array('pass' => true, 'detail' => 'JSON has faq block');
            }
            return array('pass' => false, 'detail' => 'No faq block in JSON');
        }
        $content = (string) $post->post_content;
        if (stripos($content, 'FAQPage') !== false || preg_match('/faq/i', $content)) {
            return array('pass' => true, 'detail' => 'FAQ found in body');
        }
        return array('pass' => false, 'detail' => 'No FAQ in body');
    }

    /* --------------------------------------------------------------- Infos */

    /** @return array{text:string, flag:bool} */
    public function info_freshness($post) {
        $modified = get_post_modified_time('U', true, $post);
        $days     = (int) floor((time() - (int) $modified) / DAY_IN_SECONDS);
        return array('text' => $days . 'd', 'flag' => $days > self::STALE_DAYS);
    }

    /** @return array{text:string, flag:bool} */
    public function info_aeo($post) {
        $score = get_post_meta($post->ID, '_requestdesk_aeo_score', true);
        if ($score === '' || $score === false || (int) $score === 0) {
            return array('text' => '—', 'flag' => false);
        }
        return array('text' => (int) $score . '%', 'flag' => (int) $score < 40);
    }

    /* ----------------------------------------------------- JSON-page helpers */

    /**
     * Body word count for a page: null if it's a JSON page whose file is
     * missing, otherwise an int.
     */
    private function word_count_for($post) {
        $json_file = $this->resolve_landing_json($post);
        if ($json_file !== null) {
            if (!file_exists($json_file)) {
                return null;
            }
            $data = json_decode((string) file_get_contents($json_file), true);
            return is_array($data) ? str_word_count($this->collect_text($data)) : 0;
        }
        return str_word_count(wp_strip_all_tags((string) $post->post_content));
    }

    /**
     * Concatenate human-readable text from a decoded JSON tree, skipping
     * values that are image paths/URLs so they don't inflate the word count.
     */
    private function collect_text($node, &$acc = '') {
        foreach ($node as $value) {
            if (is_array($value)) {
                $this->collect_text($value, $acc);
            } elseif (is_string($value)) {
                $v = trim($value);
                if ($v === '' || preg_match('/\.(jpe?g|png|webp|svg|gif|avif)(\?.*)?$/i', $v)) {
                    continue;
                }
                if (preg_match('#^https?://#i', $v)) {
                    continue; // bare URL, not prose
                }
                $acc .= ' ' . wp_strip_all_tags($v);
            }
        }
        return $acc;
    }

    /**
     * Absolute path to a page's landing-data JSON (may not exist), or null if
     * the page is not JSON-driven. Mirrors the theme templates.
     */
    private function resolve_landing_json($post) {
        $data_dir = get_stylesheet_directory() . '/landing-data/';

        $front = (int) get_option('page_on_front');
        if ($front === (int) $post->ID) {
            return $data_dir . 'homepage.json';
        }
        // Polylang translation of the front page → homepage-{lang}.json.
        if ($front && function_exists('pll_get_post')) {
            $english_front = pll_get_post($post->ID, 'en');
            if ($english_front && (int) $english_front === $front) {
                $lang      = function_exists('pll_get_post_language') ? pll_get_post_language($post->ID, 'slug') : '';
                $localized = $data_dir . 'homepage' . (($lang && $lang !== 'en') ? '-' . $lang : '') . '.json';
                return file_exists($localized) ? $localized : $data_dir . 'homepage.json';
            }
        }

        $template = get_post_meta($post->ID, '_wp_page_template', true);
        if (!in_array($template, self::JSON_TEMPLATES, true)) {
            return null;
        }

        $slug        = get_post_field('post_name', $post->ID);
        $lang_suffix = '';
        if (function_exists('pll_get_post_language')) {
            $lang = pll_get_post_language($post->ID, 'slug');
            if ($lang && $lang !== 'en') {
                $lang_suffix = '-' . $lang;
                if (function_exists('pll_get_post')) {
                    $english_id = pll_get_post($post->ID, 'en');
                    if ($english_id) {
                        $slug = get_post_field('post_name', $english_id);
                    }
                }
            }
        }

        $candidate = $data_dir . $slug . $lang_suffix . '.json';
        if ($lang_suffix !== '' && !file_exists($candidate)) {
            $candidate = $data_dir . $slug . '.json';
        }
        return $candidate;
    }

    private function json_has_image($json_file) {
        $data = json_decode((string) file_get_contents($json_file), true);
        return is_array($data) ? $this->array_has_image_value($data) : false;
    }

    private function array_has_image_value($node) {
        foreach ($node as $value) {
            if (is_array($value)) {
                if ($this->array_has_image_value($value)) {
                    return true;
                }
            } elseif (is_string($value) && preg_match('/\.(jpe?g|png|webp|svg|gif|avif)(\?.*)?$/i', trim($value))) {
                return true;
            }
        }
        return false;
    }

    /* ---------------------------------------------------------------- View */

    public function register_menu() {
        add_submenu_page(
            'requestdesk-aeo-analytics',
            'Content Audit',
            'Content Audit',
            'manage_options',
            self::MENU_SLUG,
            array($this, 'render_page')
        );
        add_submenu_page(
            'requestdesk-aeo-analytics',
            'Video Opportunities',
            'Video Opportunities',
            'manage_options',
            'requestdesk-video-opportunities',
            array($this, 'render_video_opportunities')
        );
    }

    /* --------------------------------------------------- Video detection */

    /**
     * Video state for a post/page: none | external | cc, plus a note.
     * "cc" requires an explicit ownership marker (JSON "video_source":"cc",
     * or post meta _cc_video_source = cc) — a bare YouTube embed is "external"
     * because a borrowed video is not the same AEO win as CC's own.
     *
     * @return array{state:string, note:string}
     */
    public function detect_video($post) {
        $json_file = $this->resolve_landing_json($post);
        if ($json_file !== null && file_exists($json_file)) {
            $data = json_decode((string) file_get_contents($json_file), true);
            $j = is_array($data) ? wp_json_encode($data) : '';
            if ($j && preg_match('~"youtube_id"|"type"\s*:\s*"video-embed"|youtube\.com|youtu\.be|vimeo~i', $j)) {
                if (preg_match('~"video_source"\s*:\s*"cc"~i', $j)) {
                    if (preg_match('~"video_note"\s*:\s*"([^"]+)"~i', $j, $m)) {
                        return array('state' => 'cc', 'note' => $m[1]);
                    }
                    return array('state' => 'cc', 'note' => 'CC-created video');
                }
                return array('state' => 'external', 'note' => 'JSON video embed — source unlabeled');
            }
        }

        $content = (string) $post->post_content;
        if (preg_match('~youtube\.com|youtu\.be|youtube-nocookie|vimeo\.com|<video[\s>]|wp-block-embed-youtube~i', $content)) {
            if (get_post_meta($post->ID, '_cc_video_source', true) === 'cc') {
                $note = get_post_meta($post->ID, '_cc_video_note', true);
                return array('state' => 'cc', 'note' => $note !== '' ? $note : 'CC-created video');
            }
            return array('state' => 'external', 'note' => 'Embedded video — source unlabeled');
        }

        return array('state' => 'none', 'note' => '');
    }

    /* --------------------------------------------------- GSC traffic cache */

    private $traffic_cache = null;

    /** Load the GSC traffic cache (data/gsc-traffic.json), keyed by URL path. */
    private function traffic_map() {
        if ($this->traffic_cache !== null) {
            return $this->traffic_cache;
        }
        $this->traffic_cache = array();
        $f = REQUESTDESK_PLUGIN_DIR . 'data/gsc-traffic.json';
        if (file_exists($f)) {
            $j = json_decode((string) file_get_contents($f), true);
            if (isset($j['paths']) && is_array($j['paths'])) {
                $this->traffic_cache = $j['paths'];
            }
        }
        return $this->traffic_cache;
    }

    private function traffic_for($post) {
        $path = trim(str_replace(home_url(), '', get_permalink($post->ID)), '/');
        $map  = $this->traffic_map();
        return isset($map[$path]) ? $map[$path] : array('clicks' => 0, 'impr' => 0, 'pos' => 0);
    }

    private function get_pages() {
        return get_posts(array(
            'post_type'      => 'page',
            'post_status'    => 'publish',
            'posts_per_page' => -1,
            'orderby'        => 'title',
            'order'          => 'ASC',
        ));
    }

    public function render_page() {
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('You do not have permission to view this page.', 'requestdesk-connector'));
        }

        $pages    = $this->get_pages();
        $rows     = array();
        $fail_tot = array_fill_keys(array_keys($this->checks), 0);

        foreach ($pages as $post) {
            $cells = array();
            $any_fail = false;
            foreach ($this->checks as $id => $check) {
                $res = call_user_func($check['callback'], $post);
                $cells[$id] = $res;
                if (empty($res['pass'])) {
                    $fail_tot[$id]++;
                    $any_fail = true;
                }
            }
            $info = array();
            foreach ($this->infos as $id => $col) {
                $info[$id] = call_user_func($col['callback'], $post);
            }
            $rows[] = array('post' => $post, 'cells' => $cells, 'info' => $info, 'any_fail' => $any_fail);
        }

        // Failing pages first, then alphabetical (already sorted by title).
        usort($rows, function ($a, $b) {
            return ($b['any_fail'] <=> $a['any_fail']);
        });

        $total = count($pages);
        $optimizer_base = admin_url('admin.php?page=requestdesk-aeo-bulk-optimizer');
        ?>
        <div class="wrap">
            <h1>Content Audit</h1>
            <p style="color:#646970;max-width:820px;">
                Read-only health rollup of all published Pages — JSON-aware, so landing pages are judged on their
                <code>landing-data</code> JSON, not their empty post body. Fix from the
                <a href="<?php echo esc_url($optimizer_base); ?>">Bulk Optimizer</a>.
            </p>

            <?php // Per-check summary tiles ?>
            <div style="display:flex;gap:14px;margin:18px 0;flex-wrap:wrap;">
                <div style="background:#fff;border:1px solid #c3c4c7;border-radius:6px;padding:14px 20px;">
                    <div style="font-size:26px;font-weight:600;line-height:1;"><?php echo (int) $total; ?></div>
                    <div style="color:#646970;margin-top:4px;">pages scanned</div>
                </div>
                <?php foreach ($this->checks as $id => $check): ?>
                    <div style="background:#fff;border:1px solid #c3c4c7;border-radius:6px;padding:14px 20px;">
                        <div style="font-size:26px;font-weight:600;line-height:1;color:<?php echo $fail_tot[$id] ? '#d63638' : '#00a32a'; ?>;">
                            <?php echo (int) $fail_tot[$id]; ?>
                        </div>
                        <div style="color:#646970;margin-top:4px;"><?php echo esc_html($check['tile']); ?></div>
                    </div>
                <?php endforeach; ?>
            </div>

            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th style="width:26%;">Page</th>
                        <?php foreach ($this->checks as $check): ?>
                            <th style="width:9%;text-align:center;" title="<?php echo esc_attr($check['desc']); ?>">
                                <?php echo esc_html($check['label']); ?>
                            </th>
                        <?php endforeach; ?>
                        <?php foreach ($this->infos as $col): ?>
                            <th style="width:9%;"><?php echo esc_html($col['label']); ?></th>
                        <?php endforeach; ?>
                        <th style="width:14%;">Action</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($rows as $row):
                    $p        = $row['post'];
                    $edit_url = get_edit_post_link($p->ID, 'raw');
                    $view_url = get_permalink($p->ID);
                    $opt_url  = $optimizer_base . '&breakdown_post_id=' . $p->ID;
                ?>
                    <tr>
                        <td>
                            <strong><a href="<?php echo esc_url($edit_url); ?>"><?php echo esc_html(get_the_title($p) ?: '(no title)'); ?></a></strong>
                            <div style="font-size:12px;">
                                <a href="<?php echo esc_url($view_url); ?>" target="_blank" rel="noopener" style="color:#646970;">
                                    <?php echo esc_html(str_replace(home_url(), '', $view_url)); ?>
                                </a>
                            </div>
                        </td>
                        <?php foreach ($this->checks as $id => $check):
                            $res = $row['cells'][$id];
                            $ok  = !empty($res['pass']);
                        ?>
                            <td style="text-align:center;" title="<?php echo esc_attr($res['detail']); ?>">
                                <span style="color:<?php echo $ok ? '#00a32a' : '#d63638'; ?>;font-weight:600;font-size:15px;">
                                    <?php echo $ok ? '✓' : '✗'; ?>
                                </span>
                            </td>
                        <?php endforeach; ?>
                        <?php foreach ($this->infos as $id => $col):
                            $cell = $row['info'][$id];
                        ?>
                            <td style="<?php echo !empty($cell['flag']) ? 'color:#d63638;font-weight:600;' : 'color:#646970;'; ?>">
                                <?php echo esc_html($cell['text']); ?>
                            </td>
                        <?php endforeach; ?>
                        <td>
                            <a href="<?php echo esc_url($edit_url); ?>" class="button button-small">Edit</a>
                            <a href="<?php echo esc_url($opt_url); ?>" class="button button-small">Optimize</a>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>

            <p style="color:#8c8f94;margin-top:16px;font-size:12px;">
                ✓ pass · ✗ needs work (hover a mark for detail). AEO score is informational — it is computed from
                post_content, so JSON landing pages read “—” until the score engine is JSON-aware.
                Thresholds: thin &lt; <?php echo (int) self::THIN_WORDS; ?> words, stale &gt; <?php echo (int) self::STALE_DAYS; ?> days.
            </p>
        </div>
        <?php
    }
    /**
     * Video Opportunities — every published post + page, its video state
     * (none / external / CC), ranked by real GSC search traffic, so the top of
     * the list is "add a CC video here first." Video is the highest-leverage
     * add: per Ahrefs' AI-visibility study, video/YouTube presence is one of
     * the strongest correlations with getting cited by answer engines.
     */
    public function render_video_opportunities() {
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('You do not have permission to view this page.', 'requestdesk-connector'));
        }

        $posts = get_posts(array(
            'post_type'      => array('post', 'page'),
            'post_status'    => 'publish',
            'posts_per_page' => -1,
        ));

        $rows = array();
        $counts = array('none' => 0, 'external' => 0, 'cc' => 0);
        $none_traffic = 0; // impressions sitting on pages with no video

        foreach ($posts as $p) {
            $v = $this->detect_video($p);
            $t = $this->traffic_for($p);
            $counts[$v['state']]++;
            if ($v['state'] === 'none') {
                $none_traffic += (int) $t['impr'];
            }
            $rows[] = array('post' => $p, 'v' => $v, 't' => $t);
        }

        // Rank by impressions desc — traffic is the "where it wins most" signal.
        usort($rows, function ($a, $b) {
            return ((int) $b['t']['impr']) <=> ((int) $a['t']['impr']);
        });

        $has_cache = !empty($this->traffic_map());
        $show = 150; // cap the table; summary counts span everything
        $badge = array(
            'none'     => array('#d63638', 'No video'),
            'external' => array('#e08a00', 'External'),
            'cc'       => array('#00a32a', 'CC video'),
        );
        ?>
        <div class="wrap">
            <h1>Video Opportunities</h1>
            <p style="color:#646970;max-width:840px;">
                Every published post and page, ranked by <strong>real Search Console traffic</strong>, showing whether it has a
                <strong>CC-created</strong> video, a borrowed <strong>external</strong> embed, or <strong>none</strong>.
                Video is the highest-leverage add — per Ahrefs, video presence is one of the strongest signals answer engines
                use to decide who to cite. Start at the top: high-traffic pages with no CC video.
            </p>

            <?php if (!$has_cache): ?>
                <div class="notice notice-warning inline"><p>
                    GSC traffic cache not found (<code>data/gsc-traffic.json</code>) — ranking falls back to zero. Ask Claude to refresh it.
                </p></div>
            <?php endif; ?>

            <div style="display:flex;gap:14px;margin:18px 0;flex-wrap:wrap;">
                <div style="background:#fff;border:1px solid #c3c4c7;border-radius:6px;padding:14px 20px;">
                    <div style="font-size:26px;font-weight:600;line-height:1;color:#d63638;"><?php echo (int) $counts['none']; ?></div>
                    <div style="color:#646970;margin-top:4px;">no video</div>
                </div>
                <div style="background:#fff;border:1px solid #c3c4c7;border-radius:6px;padding:14px 20px;">
                    <div style="font-size:26px;font-weight:600;line-height:1;color:#e08a00;"><?php echo (int) $counts['external']; ?></div>
                    <div style="color:#646970;margin-top:4px;">external only</div>
                </div>
                <div style="background:#fff;border:1px solid #c3c4c7;border-radius:6px;padding:14px 20px;">
                    <div style="font-size:26px;font-weight:600;line-height:1;color:#00a32a;"><?php echo (int) $counts['cc']; ?></div>
                    <div style="color:#646970;margin-top:4px;">CC video</div>
                </div>
                <div style="background:#f6f7f7;border:1px solid #c3c4c7;border-radius:6px;padding:14px 20px;">
                    <div style="font-size:26px;font-weight:600;line-height:1;"><?php echo number_format($none_traffic); ?></div>
                    <div style="color:#646970;margin-top:4px;">monthly impressions on no-video pages</div>
                </div>
            </div>

            <p style="color:#646970;font-size:12px;">Top <?php echo (int) $show; ?> by 3-month impressions. Video state: “CC video” needs an ownership marker (<code>video_source: "cc"</code> in JSON, or the <code>_cc_video_source</code> meta) — a bare embed reads as “External”.</p>

            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th style="width:30%;">Page</th>
                        <th style="width:11%;">Video</th>
                        <th style="width:9%;text-align:right;">Impr (3mo)</th>
                        <th style="width:8%;text-align:right;">Clicks</th>
                        <th style="width:22%;">Video note</th>
                        <th style="width:12%;">Action</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach (array_slice($rows, 0, $show) as $row):
                    $p = $row['post'];
                    $v = $row['v'];
                    $t = $row['t'];
                    list($color, $label) = $badge[$v['state']];
                    $edit_url = get_edit_post_link($p->ID, 'raw');
                    $view_url = get_permalink($p->ID);
                ?>
                    <tr>
                        <td>
                            <strong><a href="<?php echo esc_url($edit_url); ?>"><?php echo esc_html(get_the_title($p) ?: '(no title)'); ?></a></strong>
                            <div style="font-size:12px;"><a href="<?php echo esc_url($view_url); ?>" target="_blank" rel="noopener" style="color:#646970;"><?php echo esc_html(str_replace(home_url(), '', $view_url)); ?></a></div>
                        </td>
                        <td><span style="display:inline-block;padding:2px 9px;border-radius:10px;background:<?php echo $color; ?>;color:#fff;font-size:11px;font-weight:600;"><?php echo esc_html($label); ?></span></td>
                        <td style="text-align:right;font-variant-numeric:tabular-nums;"><?php echo number_format((int) $t['impr']); ?></td>
                        <td style="text-align:right;font-variant-numeric:tabular-nums;color:#646970;"><?php echo number_format((int) $t['clicks']); ?></td>
                        <td style="color:#646970;font-size:12px;"><?php echo esc_html($v['note']); ?></td>
                        <td><a href="<?php echo esc_url($edit_url); ?>" class="button button-small">Edit</a></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php
    }
}

// Initialize (CC-only — see requestdesk_is_cc_site() in main plugin file).
if (function_exists('requestdesk_is_cc_site') && requestdesk_is_cc_site()) {
    new RequestDesk_Content_Audit();
}
