<?php
/**
 * RequestDesk Comparison Table Shortcode
 *
 * [requestdesk_comparison_table] - Platform comparison grid with check/X icons.
 *
 * Data is stored in WP options (requestdesk_comparison_table_settings).
 * Styling comes from assets/css/comparison-table.css (no inline styles).
 *
 * @package RequestDesk
 * @version 1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class RequestDesk_Comparison_Table {

    /** @var bool Track whether assets have been enqueued for this request */
    private $assets_enqueued = false;

    public function __construct() {
        add_shortcode('requestdesk_comparison_table', array($this, 'comparison_table_shortcode'));
    }

    /**
     * Get table settings from WP options with defaults
     */
    private function get_settings() {
        $defaults = array(
            'columns' => array('Speed', 'Quality', 'Support', 'Cost'),
            'rows' => array(
                array(
                    'name'      => 'CONTENT CUCUMBER',
                    'highlight' => true,
                    'values'    => array(true, true, true, true),
                ),
                array(
                    'name'      => 'In-House Team',
                    'highlight' => false,
                    'values'    => array(false, false, true, true),
                ),
                array(
                    'name'      => 'Freelance Contractors',
                    'highlight' => false,
                    'values'    => array(false, false, false, false),
                ),
                array(
                    'name'      => 'Agency Partners',
                    'highlight' => false,
                    'values'    => array(false, false, true, true),
                ),
                array(
                    'name'      => 'DIY Solutions',
                    'highlight' => false,
                    'values'    => array(true, true, false, false),
                ),
            ),
            'note_title' => 'Why Choose Content Cucumber?',
            'note_text'  => 'Content Cucumber stands out as the only platform that excels across all key metrics: speed, quality, support, and cost-effectiveness. While other solutions may perform well in specific areas, Content Cucumber delivers comprehensive value for businesses seeking reliable content creation at scale.',
        );

        $saved = get_option('requestdesk_comparison_table_settings', array());
        return wp_parse_args($saved, $defaults);
    }

    /**
     * Enqueue CSS (once per request)
     */
    private function enqueue_assets() {
        if ($this->assets_enqueued) {
            return;
        }

        wp_enqueue_style(
            'requestdesk-comparison-table',
            REQUESTDESK_PLUGIN_URL . 'assets/css/comparison-table.css',
            array(),
            REQUESTDESK_VERSION
        );

        $this->assets_enqueued = true;
    }

    /**
     * [requestdesk_comparison_table] - Renders the comparison table
     */
    public function comparison_table_shortcode($atts) {
        $settings = $this->get_settings();
        $this->enqueue_assets();

        $columns = $settings['columns'];
        $rows    = $settings['rows'];

        if (empty($rows)) {
            return '<!-- requestdesk_comparison_table: no rows configured -->';
        }

        ob_start();
        ?>
        <div class="cc-comparison-table">
            <div class="cc-table-wrapper">
                <table role="table" aria-label="Comparison of content creation platforms across different metrics">
                    <thead>
                        <tr role="row">
                            <th scope="col">Platform</th>
                            <?php foreach ($columns as $col) : ?>
                            <th scope="col"><?php echo esc_html($col); ?></th>
                            <?php endforeach; ?>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($rows as $row) :
                            $row_class = !empty($row['highlight']) ? ' class="cc-highlight-row"' : '';
                        ?>
                        <tr role="row"<?php echo $row_class; ?>>
                            <th scope="row" class="cc-platform-name"><?php echo esc_html($row['name']); ?></th>
                            <?php foreach ($row['values'] as $val) : ?>
                            <td>
                                <?php if ($val) : ?>
                                <span class="cc-icon cc-icon-check" aria-hidden="true"></span><span class="screen-reader-text">Yes</span>
                                <?php else : ?>
                                <span class="cc-icon cc-icon-x" aria-hidden="true"></span><span class="screen-reader-text">No</span>
                                <?php endif; ?>
                            </td>
                            <?php endforeach; ?>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php if (!empty($settings['note_title']) || !empty($settings['note_text'])) : ?>
            <div class="cc-comparison-note">
                <?php if (!empty($settings['note_title'])) : ?>
                <h3><?php echo esc_html($settings['note_title']); ?></h3>
                <?php endif; ?>
                <?php if (!empty($settings['note_text'])) : ?>
                <p><?php echo esc_html($settings['note_text']); ?></p>
                <?php endif; ?>
            </div>
            <?php endif; ?>
        </div>
        <?php
        return ob_get_clean();
    }
}

// Initialize
new RequestDesk_Comparison_Table();
