<?php
/**
 * RequestDesk A/B Test
 *
 * One A/B test at a time on the front page. Variant A is the page as the theme
 * renders it. Variant B replaces the inner HTML of up to three elements, each
 * found by a CSS selector (headline, lede, button label).
 *
 * The pick happens in the browser, not in PHP. Behind a full-page cache
 * (Flywheel, for one) a server-side pick would be cached once and every
 * visitor would get the same variant. The same reason the ad rotator
 * picks in the browser (see assets/js/ad-rotator.js).
 *
 * How a visit runs:
 *   1. wp_head (priority 1) prints a tiny inline script. It reads or sets the
 *      visitor's bucket cookie and puts data-rd-ab="a|b" on <html>. For B it
 *      also hides the configured elements so the visitor never sees A flash.
 *   2. assets/js/ab-test.js swaps in the B content and un-hides it, then counts
 *      the view once per browser session and the goal once per visitor.
 *   3. Counts go to POST /requestdesk/v1/ab-test/event and are shown on the
 *      A/B Test settings tab. Each event also goes to window.dataLayer for GTM.
 *
 * ?rd_ab=a or ?rd_ab=b forces a variant for checking the page. A forced view
 * is never counted.
 *
 * The counter is a REST call the browser makes after the page has rendered,
 * not a write on the render path (the 2.48.0 rule). It is open to anonymous
 * visitors by necessity, so the numbers can be inflated by anyone who posts
 * to it. They are a guide, not an audit.
 *
 * @package RequestDesk
 */

if (!defined('ABSPATH')) {
    exit;
}

class RequestDesk_AB_Test {

    const OPTION_SETTINGS = 'requestdesk_ab_test_settings';
    const OPTION_COUNTS   = 'requestdesk_ab_test_counts';
    const MAX_CHANGES     = 3;

    public function __construct() {
        add_action('wp_head', array($this, 'print_bootstrap'), 1);
        add_action('wp_enqueue_scripts', array($this, 'enqueue_script'));
        add_action('rest_api_init', array($this, 'register_routes'));
    }

    /**
     * Saved settings merged over empty defaults.
     */
    public static function get_settings() {
        $defaults = array(
            'enabled'       => false,
            'test_id'       => '',
            'split_b'       => 50,
            'changes'       => array(),
            'goal_selector' => '',
            'goal_hubspot'  => false,
        );
        $settings = wp_parse_args(get_option(self::OPTION_SETTINGS, array()), $defaults);
        if (!is_array($settings['changes'])) {
            $settings['changes'] = array();
        }
        return $settings;
    }

    /**
     * Why the saved test cannot run, or '' when it can. The settings tab shows
     * this, so a test that is switched on but broken says so instead of
     * quietly showing everyone Variant A.
     */
    public static function config_error($settings = null) {
        if ($settings === null) {
            $settings = self::get_settings();
        }
        if (empty($settings['enabled'])) {
            return '';
        }
        if ($settings['test_id'] === '') {
            return 'The test has no name. Give it a name to start it.';
        }
        if (empty($settings['changes'])) {
            return 'Variant B has no changes. Add at least one selector with its Variant B content.';
        }
        if ($settings['goal_selector'] === '' && empty($settings['goal_hubspot'])) {
            return 'The test has no goal. Set a goal selector or turn on HubSpot form submissions.';
        }
        return '';
    }

    /**
     * The running test, or null when there is none. A test runs only on the
     * front page, only when switched on, and only when config_error() is empty.
     */
    public static function active_test() {
        if (is_admin() || !is_front_page()) {
            return null;
        }
        $settings = self::get_settings();
        if (empty($settings['enabled']) || self::config_error($settings) !== '') {
            return null;
        }
        return $settings;
    }

    /**
     * Inline head script: pick the bucket and hide B's elements before paint.
     */
    public function print_bootstrap() {
        $test = self::active_test();
        if (!$test) {
            return;
        }

        $selectors = array();
        foreach ($test['changes'] as $change) {
            $selectors[] = 'html[data-rd-ab="b"]:not([data-rd-ab-ready]) ' . $change['selector'];
        }

        $boot = array(
            'test'  => $test['test_id'],
            'split' => intval($test['split_b']),
        );
        ?>
<style id="rd-ab-test-hide"><?php echo implode(',', array_map('wp_strip_all_tags', $selectors)); ?>{visibility:hidden!important}</style>
<script id="rd-ab-test-boot">
(function(c){
  var name = 'rd_ab_' + c.test, v = null, forced = false;
  var q = /[?&]rd_ab=([ab])(?:&|$)/.exec(location.search);
  if (q) { v = q[1]; forced = true; }
  if (!v) {
    var m = new RegExp('(?:^|; )' + name + '=([ab])').exec(document.cookie); // hardcode-ok: document.cookie, not a URL
    if (m) { v = m[1]; }
  }
  if (!v) {
    v = Math.random() * 100 < c.split ? 'b' : 'a';
    document.cookie = name + '=' + v + '; path=/; max-age=7776000; SameSite=Lax'; // hardcode-ok: document.cookie, not a URL
  }
  document.documentElement.setAttribute('data-rd-ab', v);
  window.rdAbTest = { test: c.test, variant: v, forced: forced };
  // If the swap script has not run after 2s, un-hide the elements and say so.
  // They show Variant A until the script runs, if it ever does.
  setTimeout(function(){
    if (!document.documentElement.hasAttribute('data-rd-ab-ready')) {
      document.documentElement.setAttribute('data-rd-ab-ready', 'timeout');
      if (window.console) { console.error('[rd-ab-test] swap script did not run within 2s; elements un-hidden showing Variant A.'); } // hardcode-ok: window.console, not a URL
    }
  }, 2000);
})(<?php echo wp_json_encode($boot); ?>);
</script>
        <?php
    }

    /**
     * Footer script that swaps content and counts events.
     */
    public function enqueue_script() {
        $test = self::active_test();
        if (!$test) {
            return;
        }

        wp_enqueue_script(
            'requestdesk-ab-test',
            REQUESTDESK_PLUGIN_URL . 'assets/js/ab-test.js',
            array(),
            REQUESTDESK_VERSION,
            true
        );

        $changes = array();
        foreach ($test['changes'] as $change) {
            $changes[] = array(
                'selector' => $change['selector'],
                'html'     => $change['html'],
            );
        }

        wp_localize_script('requestdesk-ab-test', 'rdAbTestConfig', array(
            'eventUrl'     => esc_url_raw(rest_url('requestdesk/v1/ab-test/event')),
            'changes'      => $changes,
            'goalSelector' => $test['goal_selector'],
            'goalHubspot'  => !empty($test['goal_hubspot']),
        ));
    }

    public function register_routes() {
        register_rest_route('requestdesk/v1', '/ab-test/event', array(
            'methods'             => 'POST',
            'callback'            => array($this, 'record_event'),
            // Visitors are anonymous; see the class doc on what that means
            // for the numbers.
            'permission_callback' => '__return_true',
            'args'                => array(
                'test'    => array('type' => 'string', 'required' => true),
                'variant' => array('type' => 'string', 'required' => true, 'enum' => array('a', 'b')),
                'type'    => array('type' => 'string', 'required' => true, 'enum' => array('view', 'goal')),
            ),
        ));
    }

    /**
     * Count one view or goal for the running test. An event for any other test
     * name is refused with 409, so a page cached before a rename cannot add to
     * the new test's numbers.
     */
    public function record_event(WP_REST_Request $request) {
        $settings = self::get_settings();
        $test     = (string) $request->get_param('test');

        if (empty($settings['enabled']) || self::config_error($settings) !== '' || $test !== $settings['test_id']) {
            return new WP_Error(
                'rd_ab_test_not_running',
                'No running A/B test is named "' . $test . '".',
                array('status' => 409)
            );
        }

        $variant = $request->get_param('variant');
        $key     = $request->get_param('type') === 'goal' ? 'goals' : 'views';

        $counts = get_option(self::OPTION_COUNTS, array());
        if (!isset($counts[$test])) {
            $counts[$test] = self::empty_counts();
        }
        $counts[$test][$variant][$key]++;
        update_option(self::OPTION_COUNTS, $counts, false);

        return rest_ensure_response(array('ok' => true));
    }

    public static function empty_counts() {
        return array(
            'a' => array('views' => 0, 'goals' => 0),
            'b' => array('views' => 0, 'goals' => 0),
        );
    }

    /**
     * Counts for one test name.
     */
    public static function get_counts($test_id) {
        $counts = get_option(self::OPTION_COUNTS, array());
        return isset($counts[$test_id]) ? $counts[$test_id] : self::empty_counts();
    }

    public static function reset_counts($test_id) {
        $counts = get_option(self::OPTION_COUNTS, array());
        unset($counts[$test_id]);
        update_option(self::OPTION_COUNTS, $counts, false);
    }
}

new RequestDesk_AB_Test();
