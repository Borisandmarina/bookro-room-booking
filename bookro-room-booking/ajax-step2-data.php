<?php
/**
 * ------------------------------------------------------------
 * Файл: wp-content/plugins/booking-room/ajax-step2-data.php
 * Назначение:
 *  - загрузка данных Step 2 по object_id
 * ------------------------------------------------------------
 */

defined('ABSPATH') || exit;

add_action('wp_ajax_br_get_step2_data', 'br_get_step2_data');
add_action('wp_ajax_nopriv_br_get_step2_data', 'br_get_step2_data');

function br_get_step2_data() {

    global $wpdb;

    $object_id = isset($_POST['object_id']) ? (int) $_POST['object_id'] : 0;

    if ( ! $object_id ) {
        wp_send_json_error('Invalid object_id');
    }

    $visitors = $wpdb->get_results(
        $wpdb->prepare(
            "SELECT id, label
             FROM {$wpdb->prefix}1br_visitors_count
             WHERE is_visible = 1 AND object_id = %d
             ORDER BY sort_order ASC",
            $object_id
        ),
        ARRAY_A
    );

    $services = $wpdb->get_results(
        $wpdb->prepare(
            "SELECT id, label
             FROM {$wpdb->prefix}1br_services
             WHERE is_visible = 1 AND object_id = %d
             ORDER BY sort_order ASC",
            $object_id
        ),
        ARRAY_A
    );

    $event_types = $wpdb->get_results(
        $wpdb->prepare(
            "SELECT id, label
             FROM {$wpdb->prefix}1br_event_types
             WHERE is_visible = 1 AND object_id = %d
             ORDER BY sort_order ASC",
            $object_id
        ),
        ARRAY_A
    );

    wp_send_json_success([
        'visitors'    => $visitors,
        'services'    => $services,
        'event_types' => $event_types,
    ]);
}
