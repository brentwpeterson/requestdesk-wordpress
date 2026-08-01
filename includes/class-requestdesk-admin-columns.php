<?php
/**
 * Admin list-table column corrections.
 *
 * WordPress core's Posts list "Date" column labels itself "Last Modified" for
 * any post that is not published or scheduled (see WP_Posts_List_Table::column_date),
 * but the value it prints comes from get_the_time(), which reads post_date. So a
 * draft shows the label "Last Modified" above its PUBLISH date, and the two
 * disagree. On a scheduled draft the gap is glaring: post 21554 read
 * "Last Modified 2027/02/17" while it was actually last edited 2026/03/25.
 *
 * This makes the value match the label core already chose. Published and
 * scheduled posts are untouched, because for those core labels the column
 * "Published" / "Scheduled" and post_date is the correct value.
 */

if (!defined('ABSPATH')) {
    exit;
}

class RequestDesk_Admin_Columns {

    public function __construct() {
        add_filter('post_date_column_time', array($this, 'correct_modified_date'), 10, 3);
    }

    /**
     * @param string  $t_time      Formatted time core is about to print.
     * @param WP_Post $post        Post object.
     * @param string  $column_name Column being rendered.
     * @return string
     */
    public function correct_modified_date($t_time, $post, $column_name) {
        if ($column_name !== 'date' || !is_object($post)) {
            return $t_time;
        }

        // Core labels these "Published" / "Scheduled" and post_date is right.
        if (in_array($post->post_status, array('publish', 'future'), true)) {
            return $t_time;
        }

        if (empty($post->post_modified) || $post->post_modified === '0000-00-00 00:00:00') {
            return $t_time;
        }

        $date = get_post_modified_time(__('Y/m/d'), false, $post, true);
        $time = get_post_modified_time(__('g:i a'), false, $post, true);

        if (!$date || !$time) {
            return $t_time;
        }

        /* translators: 1: Post date, 2: Post time. */
        return sprintf(__('%1$s at %2$s'), $date, $time);
    }
}
