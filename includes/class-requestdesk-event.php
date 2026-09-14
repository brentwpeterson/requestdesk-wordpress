<?php
/**
 * RequestDesk Events
 *
 * Registers the rd_event post type: one conference or show the team attends,
 * with its dates, its place, the copy for its page, and an optional homepage
 * takeover. Headless only, built the same way as RequestDesk_Video: an admin
 * screen, no front-end URL, no archive, no rewrite rules.
 *
 * WHY THIS EXISTS. Talk Commerce kept its events in a TypeScript array plus one
 * hand-built Astro page per event, and the homepage headline was computed from
 * that array at build time. The headline only changed when someone deployed, so
 * eTail Boston stayed on the homepage after the show had ended. With events in
 * WordPress the site reads them per request: an event added here appears with
 * no deploy, and it leaves the homepage the day after its end date.
 *
 * Status (upcoming or past) is never stored. It is derived from end_date on
 * every read, because a stored flag is a flag somebody forgets to flip.
 *
 * @package RequestDesk
 * @since 2.45.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class RequestDesk_Event {

    const POST_TYPE    = 'rd_event';
    const META_PREFIX  = '_rd_event_';
    const NONCE_ACTION = 'requestdesk_event_save';
    const NONCE_FIELD  = 'requestdesk_event_nonce';

    public function __construct() {
        add_action('init', array($this, 'register_cpt'));
        add_action('add_meta_boxes', array($this, 'add_meta_boxes'));
        add_action('save_post_' . self::POST_TYPE, array($this, 'save_meta'), 10, 2);
        add_filter('manage_' . self::POST_TYPE . '_posts_columns', array($this, 'admin_columns'));
        add_action('manage_' . self::POST_TYPE . '_posts_custom_column', array($this, 'admin_column_content'), 10, 2);
        add_action('pre_get_posts', array($this, 'admin_default_order'));
    }

    /**
     * Every editable field, in the order the meta box shows them.
     *
     * One table drives the meta box, the save handler and the API, so a field
     * cannot be rendered without being saved, or saved without being returned.
     */
    public static function fields() {
        return array(
            // When and where
            'start_date' => array('section' => 'when', 'type' => 'date', 'label' => 'Start date',
                'description' => 'Required. An event with no start date is left out of the API.'),
            'end_date' => array('section' => 'when', 'type' => 'date', 'label' => 'End date',
                'description' => 'Same as the start date for a one-day event. The event counts as past from the day after this date.'),
            'venue' => array('section' => 'when', 'type' => 'text', 'label' => 'Venue', 'placeholder' => 'Music City Center'),
            'street' => array('section' => 'when', 'type' => 'text', 'label' => 'Street address'),
            'city' => array('section' => 'when', 'type' => 'text', 'label' => 'City',
                'description' => 'Required. An event with no city is left out of the API.'),
            'region' => array('section' => 'when', 'type' => 'text', 'label' => 'State or region', 'placeholder' => 'TN'),
            'postal_code' => array('section' => 'when', 'type' => 'text', 'label' => 'Postal code'),
            'country' => array('section' => 'when', 'type' => 'text', 'label' => 'Country', 'placeholder' => 'US'),

            // Who and how
            'short_name' => array('section' => 'who', 'type' => 'text', 'label' => 'Short name', 'placeholder' => 'Shoptalk Fall',
                'description' => 'The name used in headlines. Leave empty to use the title without its year.'),
            'who' => array('section' => 'who', 'type' => 'text', 'label' => 'Recording', 'placeholder' => 'Brent Peterson and Isaac Morey',
                'description' => 'Who from the team is on the floor.'),
            'role' => array('section' => 'who', 'type' => 'text', 'label' => 'Our role', 'placeholder' => 'Official media partner',
                'description' => 'Leave empty when the team is attending. Only name a partnership that is held.'),
            'organizer_name' => array('section' => 'who', 'type' => 'text', 'label' => 'Organizer'),
            'organizer_url' => array('section' => 'who', 'type' => 'url', 'label' => 'Organizer website'),
            'speakers' => array('section' => 'who', 'type' => 'textarea', 'label' => 'Speakers',
                'description' => 'For a session or workshop. One per line: Name | Title'),

            // Event page
            'headline' => array('section' => 'page', 'type' => 'text', 'label' => 'Headline override',
                'description' => 'Replaces the default headline built from the short name. Leave empty for almost every event.'),
            'lead' => array('section' => 'page', 'type' => 'textarea', 'label' => 'Lead paragraph',
                'description' => 'The paragraph under the headline. The main editor above holds the rest of the page.'),
            'banner_image' => array('section' => 'page', 'type' => 'url', 'label' => 'Banner image',
                'description' => 'Full-width image above the headline. A site path such as /images/events/logo.png, or a full URL.'),
            'banner_background' => array('section' => 'page', 'type' => 'color', 'label' => 'Banner background', 'placeholder' => '#0033FF',
                'description' => 'Optional hex color for a band behind the banner, for a logo that needs one (a white logo). Leave empty to show the image on its own.'),
            'bg_video' => array('section' => 'page', 'type' => 'url', 'label' => 'Background video',
                'description' => 'Looping header video, muted. Also plays behind the homepage takeover.'),
            'bg_poster' => array('section' => 'page', 'type' => 'url', 'label' => 'Background video poster'),
            'card_upcoming' => array('section' => 'page', 'type' => 'textarea', 'label' => 'Events list copy, upcoming',
                'description' => 'The line on the events list while the event is ahead.'),
            'card_past' => array('section' => 'page', 'type' => 'textarea', 'label' => 'Events list copy, past',
                'description' => 'Replaces the line above once the event has ended.'),
            'videos_title' => array('section' => 'page', 'type' => 'text', 'label' => 'Video library heading', 'placeholder' => 'Watch the Interviews'),
            'videos_description' => array('section' => 'page', 'type' => 'textarea', 'label' => 'Video library intro'),
            'recap_complete' => array('section' => 'page', 'type' => 'checkbox', 'label' => 'Recap complete',
                'description' => 'Tick once the interviews or write-up are on the page. Until then a past event is listed as owing a recap.'),
            'seo_title' => array('section' => 'page', 'type' => 'text', 'label' => 'SEO title'),
            'seo_description' => array('section' => 'page', 'type' => 'textarea', 'label' => 'SEO description'),
            'schema_type' => array('section' => 'page', 'type' => 'select', 'label' => 'Schema type', 'default' => 'Event',
                'options' => array('Event' => 'Event', 'EducationEvent' => 'Education event (workshop, training)')),

            // Form
            'form_mode' => array('section' => 'form', 'type' => 'select', 'label' => 'Form', 'default' => 'booking',
                'options' => array(
                    'booking' => 'Booking form, shown while the event is upcoming',
                    'always'  => 'Always shown (slides, downloads)',
                    'none'    => 'No form',
                )),
            'form_id' => array('section' => 'form', 'type' => 'text', 'label' => 'HubSpot form id',
                'description' => 'Leave empty to use the site\'s default booking form.'),
            'form_heading' => array('section' => 'form', 'type' => 'text', 'label' => 'Form heading', 'placeholder' => 'Book Your Interview'),
            'form_intro' => array('section' => 'form', 'type' => 'textarea', 'label' => 'Form intro'),

            // Homepage takeover
            'hero_enabled' => array('section' => 'hero', 'type' => 'checkbox', 'label' => 'Homepage takeover',
                'description' => 'Headline the homepage while this is the next upcoming event with this ticked.'),
            'hero_badge' => array('section' => 'hero', 'type' => 'text', 'label' => 'Badge', 'placeholder' => 'Live Interviews • Shoptalk Fall 2026'),
            'hero_blurb' => array('section' => 'hero', 'type' => 'textarea', 'label' => 'Blurb'),
            'hero_cta_text' => array('section' => 'hero', 'type' => 'text', 'label' => 'Button text', 'placeholder' => 'Book an Interview'),
            'hero_cta_url' => array('section' => 'hero', 'type' => 'url', 'label' => 'Button link',
                'description' => 'Leave empty to link to the event page.'),
        );
    }

    public static function sections() {
        return array(
            'when' => 'When and where',
            'who'  => 'Who and how',
            'page' => 'Event page',
            'form' => 'Form',
            'hero' => 'Homepage takeover',
        );
    }

    /**
     * public => false with show_ui => true, as for rd_video: a normal admin
     * screen and no /rd_event/ URL to leak, index, or 404. 'editor' is on
     * because the page body lives in post_content.
     */
    public function register_cpt() {
        $labels = array(
            'name'               => 'Events',
            'singular_name'      => 'Event',
            'menu_name'          => 'Events',
            'add_new'            => 'Add New',
            'add_new_item'       => 'Add New Event',
            'edit_item'          => 'Edit Event',
            'new_item'           => 'New Event',
            'view_item'          => 'View Event',
            'search_items'       => 'Search Events',
            'not_found'          => 'No events found',
            'not_found_in_trash' => 'No events found in Trash',
            'all_items'          => 'All Events',
        );

        register_post_type(self::POST_TYPE, array(
            'labels'        => $labels,
            'public'        => false,
            'show_ui'       => true,
            'show_in_menu'  => true,
            'has_archive'   => false,
            'rewrite'       => false,
            'supports'      => array('title', 'editor', 'thumbnail'),
            'menu_icon'     => 'dashicons-calendar-alt',
            'menu_position' => 27,
            'show_in_rest'  => true,
        ));
    }

    public function add_meta_boxes() {
        add_meta_box(
            'requestdesk_event_details',
            'Event Details',
            array($this, 'render_meta_box'),
            self::POST_TYPE,
            'normal',
            'high'
        );
    }

    public static function get($post_id, $key) {
        return (string) get_post_meta($post_id, self::META_PREFIX . $key, true);
    }

    public function render_meta_box($post) {
        wp_nonce_field(self::NONCE_ACTION, self::NONCE_FIELD);

        $fields = self::fields();
        foreach (self::sections() as $section => $section_label) {
            echo '<h3 style="margin:1.5em 0 0;">' . esc_html($section_label) . '</h3>';
            echo '<table class="form-table" role="presentation">';
            foreach ($fields as $key => $field) {
                if ($field['section'] !== $section) {
                    continue;
                }
                $this->render_field($post->ID, $key, $field);
            }
            echo '</table>';
        }
    }

    private function render_field($post_id, $key, $field) {
        $name  = self::META_PREFIX . $key;
        $id    = 'rd_event_' . $key;
        $value = self::get($post_id, $key);
        if ($value === '' && isset($field['default'])) {
            $value = $field['default'];
        }
        $placeholder = isset($field['placeholder']) ? ' placeholder="' . esc_attr($field['placeholder']) . '"' : '';

        echo '<tr><th><label for="' . esc_attr($id) . '">' . esc_html($field['label']) . '</label></th><td>';

        switch ($field['type']) {
            case 'textarea':
                echo '<textarea class="large-text" rows="3" id="' . esc_attr($id) . '" name="' . esc_attr($name) . '"' . $placeholder . '>'
                    . esc_textarea($value) . '</textarea>';
                break;
            case 'select':
                echo '<select id="' . esc_attr($id) . '" name="' . esc_attr($name) . '">';
                foreach ($field['options'] as $option => $option_label) {
                    echo '<option value="' . esc_attr($option) . '"' . selected($value, $option, false) . '>'
                        . esc_html($option_label) . '</option>';
                }
                echo '</select>';
                break;
            case 'checkbox':
                echo '<label><input type="checkbox" id="' . esc_attr($id) . '" name="' . esc_attr($name) . '" value="1"'
                    . checked($value, '1', false) . ' /> ' . esc_html($field['label']) . '</label>';
                break;
            case 'date':
                echo '<input type="date" id="' . esc_attr($id) . '" name="' . esc_attr($name) . '" value="' . esc_attr($value) . '" />';
                break;
            default:
                // 'url' renders as text on purpose: site paths such as
                // /videos/x.mp4 are valid here and type="url" rejects them.
                echo '<input type="text" class="regular-text" id="' . esc_attr($id) . '" name="' . esc_attr($name) . '" value="'
                    . esc_attr($value) . '"' . $placeholder . ' />';
        }

        if (!empty($field['description'])) {
            echo '<p class="description">' . esc_html($field['description']) . '</p>';
        }
        echo '</td></tr>';
    }

    public function save_meta($post_id, $post) {
        if (!isset($_POST[self::NONCE_FIELD])
            || !wp_verify_nonce($_POST[self::NONCE_FIELD], self::NONCE_ACTION)) {
            return;
        }
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }
        if (!current_user_can('edit_post', $post_id)) {
            return;
        }

        $values = array();
        foreach (self::fields() as $key => $field) {
            $raw = isset($_POST[self::META_PREFIX . $key]) ? wp_unslash($_POST[self::META_PREFIX . $key]) : null;
            $values[$key] = self::sanitize_value($field, $raw);
        }
        self::save_values($post_id, $values);
    }

    /**
     * Write a full set of values. Shared by the meta box and by importers, so a
     * script that seeds events goes through the same rules as the editor.
     */
    public static function save_values($post_id, array $values) {
        $fields = self::fields();

        // A missing or earlier end date means a one-day event, not a broken one.
        $start = isset($values['start_date']) ? $values['start_date'] : '';
        $end   = isset($values['end_date']) ? $values['end_date'] : '';
        if ($start !== '' && ($end === '' || $end < $start)) {
            $values['end_date'] = $start;
        }

        foreach ($fields as $key => $field) {
            if (!array_key_exists($key, $values)) {
                continue;
            }
            update_post_meta($post_id, self::META_PREFIX . $key, self::sanitize_value($field, $values[$key]));
        }
    }

    public static function sanitize_value($field, $raw) {
        switch ($field['type']) {
            case 'checkbox':
                return !empty($raw) ? '1' : '';
            case 'date':
                $raw = trim((string) $raw);
                if (preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $raw, $m) && checkdate((int) $m[2], (int) $m[3], (int) $m[1])) {
                    return $raw;
                }
                return '';
            case 'url':
                return esc_url_raw(trim((string) $raw));
            case 'color':
                return (string) sanitize_hex_color(trim((string) $raw));
            case 'textarea':
                return sanitize_textarea_field((string) $raw);
            case 'select':
                $raw = (string) $raw;
                return array_key_exists($raw, $field['options']) ? $raw : (isset($field['default']) ? $field['default'] : '');
            default:
                return sanitize_text_field((string) $raw);
        }
    }

    /** Past from the day after end_date, in UTC. The Astro side uses the same rule. */
    public static function is_upcoming($end_date) {
        return $end_date >= gmdate('Y-m-d');
    }

    public function admin_columns($columns) {
        $new = array();
        foreach ($columns as $key => $label) {
            $new[$key] = $label;
            if ($key === 'title') {
                $new['rd_event_dates']  = 'Dates';
                $new['rd_event_status'] = 'Status';
            }
        }
        unset($new['date']);
        return $new;
    }

    public function admin_column_content($column, $post_id) {
        $start = self::get($post_id, 'start_date');
        $end   = self::get($post_id, 'end_date') ?: $start;

        if ($column === 'rd_event_dates') {
            echo esc_html($start === '' ? '—' : ($start === $end ? $start : $start . ' to ' . $end));
            return;
        }
        if ($column !== 'rd_event_status') {
            return;
        }

        // An incomplete event is dropped from the API. Say so here, in red,
        // rather than letting it vanish from the site with no explanation.
        if ($start === '' || self::get($post_id, 'city') === '') {
            echo '<span style="color:#b32d2e;">missing start date or city, hidden from the site</span>';
            return;
        }
        $upcoming = self::is_upcoming($end);
        $label = $upcoming ? 'Upcoming' : 'Past';
        if ($upcoming && self::get($post_id, 'hero_enabled') === '1') {
            $label .= ' · homepage';
        }
        if (!$upcoming && self::get($post_id, 'recap_complete') !== '1') {
            $label .= ' · recap owed';
        }
        echo esc_html($label);
    }

    /** Newest event first in the admin list unless the editor picked a sort. */
    public function admin_default_order($query) {
        if (!is_admin() || !$query->is_main_query() || $query->get('post_type') !== self::POST_TYPE) {
            return;
        }
        if ($query->get('orderby')) {
            return;
        }
        $query->set('meta_key', self::META_PREFIX . 'start_date');
        $query->set('orderby', 'meta_value');
        $query->set('order', 'DESC');
    }

    /**
     * Shape one event for the headless API.
     *
     * Returns null for an event with no start date or city. The caller drops
     * those, so a half-filled draft cannot reach a page with no dates on it.
     * Titles and meta come back raw (not through the_title), so a consumer that
     * escapes its own output does not print &#8217; entities.
     */
    public static function format_for_api($post, $include_content = true) {
        $id    = $post->ID;
        $start = self::get($id, 'start_date');
        $city  = self::get($id, 'city');
        if ($start === '' || $city === '') {
            return null;
        }
        $end = self::get($id, 'end_date') ?: $start;

        $short_name = self::get($id, 'short_name');
        if ($short_name === '') {
            $short_name = trim(preg_replace('/\s+\d{4}$/', '', $post->post_title));
        }

        $speakers = array();
        foreach (preg_split('/\r\n|\r|\n/', self::get($id, 'speakers')) as $line) {
            $parts = array_map('trim', explode('|', $line, 2));
            if ($parts[0] === '') {
                continue;
            }
            $speakers[] = array('name' => $parts[0], 'title' => isset($parts[1]) ? $parts[1] : '');
        }

        $organizer_name = self::get($id, 'organizer_name');
        $hero = null;
        if (self::get($id, 'hero_enabled') === '1') {
            $hero = array(
                'badge'   => self::get($id, 'hero_badge'),
                'blurb'   => self::get($id, 'hero_blurb'),
                'ctaText' => self::get($id, 'hero_cta_text'),
                'ctaUrl'  => self::get($id, 'hero_cta_url'),
            );
        }

        $featured_image = get_the_post_thumbnail_url($id, 'full');

        $data = array(
            'id'           => $id,
            'slug'         => $post->post_name,
            'title'        => $post->post_title,
            'shortName'    => $short_name,
            'startDate'    => $start,
            'endDate'      => $end,
            'upcoming'     => self::is_upcoming($end),
            'venue'        => self::get($id, 'venue'),
            'street'       => self::get($id, 'street'),
            'city'         => $city,
            'region'       => self::get($id, 'region'),
            'postalCode'   => self::get($id, 'postal_code'),
            'country'      => self::get($id, 'country') ?: 'US',
            'who'          => self::get($id, 'who'),
            'role'         => self::get($id, 'role'),
            'organizer'    => $organizer_name === '' ? null : array(
                'name' => $organizer_name,
                'url'  => self::get($id, 'organizer_url'),
            ),
            'speakers'     => $speakers,
            'headline'     => self::get($id, 'headline'),
            'lead'         => self::get($id, 'lead'),
            'bannerImage'  => self::get($id, 'banner_image'),
            'bannerBackground' => self::get($id, 'banner_background'),
            'bgVideo'      => self::get($id, 'bg_video'),
            'bgPoster'     => self::get($id, 'bg_poster'),
            'cardUpcoming' => self::get($id, 'card_upcoming'),
            'cardPast'     => self::get($id, 'card_past'),
            'videosTitle'  => self::get($id, 'videos_title'),
            'videosDescription' => self::get($id, 'videos_description'),
            'recapComplete' => self::get($id, 'recap_complete') === '1',
            'seoTitle'     => self::get($id, 'seo_title'),
            'seoDescription' => self::get($id, 'seo_description'),
            'schemaType'   => self::get($id, 'schema_type') ?: 'Event',
            'form'         => array(
                'mode'    => self::get($id, 'form_mode') ?: 'booking',
                'id'      => self::get($id, 'form_id'),
                'heading' => self::get($id, 'form_heading'),
                'intro'   => self::get($id, 'form_intro'),
            ),
            'hero'         => $hero,
            'featuredImage' => $featured_image ? $featured_image : null,
            'publishedAt'  => get_the_date('c', $post),
            'modifiedAt'   => get_the_modified_date('c', $post),
        );

        if ($include_content) {
            $data['content'] = apply_filters('the_content', $post->post_content);
        }

        return $data;
    }

    /** Upcoming events soonest first, then past events most recent first. */
    public static function sort_for_api(array $events) {
        $upcoming = array_values(array_filter($events, function ($e) { return $e['upcoming']; }));
        $past     = array_values(array_filter($events, function ($e) { return !$e['upcoming']; }));
        usort($upcoming, function ($a, $b) { return strcmp($a['startDate'], $b['startDate']); });
        usort($past, function ($a, $b) { return strcmp($b['startDate'], $a['startDate']); });
        return array_merge($upcoming, $past);
    }
}
