<?php
/**
 * ------------------------------------------------------------
 * Файл: wp-content/plugins/booking-room/bookings-export-html.php
 *
 * Назначение:
 *  - Экспорт бронирований в HTML
 *  - Открывается в новой вкладке
 *  - Использует текущие фильтры
 *  - Никакого AJAX
 * ------------------------------------------------------------
 */

defined('ABSPATH') || exit;

/**
 * ------------------------------------------------------------
 * Export HTML handler
 * URL: wp-admin/admin-post.php?action=br_export_bookings_html
 * ------------------------------------------------------------
 */
add_action('admin_post_br_export_bookings_html', function () {

    // --- access check
    if ( ! current_user_can('manage_options') ) {
        wp_die('Access denied');
    }

    // --- nonce check
    if (
        empty($_REQUEST['_wpnonce']) ||
        ! wp_verify_nonce($_REQUEST['_wpnonce'], 'br_bookings_bulk_action')
    ) {
        wp_die('Invalid nonce');
    }

    // --- required
    $object_id = isset($_REQUEST['object_id']) ? (int) $_REQUEST['object_id'] : 0;
    if ($object_id <= 0) {
        wp_die('Invalid object');
    }

    // --- normalize dates (DD.MM.YYYY → Y-m-d)
    $date_from = '';
    if (!empty($_REQUEST['date_from'])) {
        $dt = DateTime::createFromFormat('d.m.Y', $_REQUEST['date_from']);
        if ($dt) {
            $date_from = $dt->format('Y-m-d');
        }
    }

    $date_to = '';
    if (!empty($_REQUEST['date_to'])) {
        $dt = DateTime::createFromFormat('d.m.Y', $_REQUEST['date_to']);
        if ($dt) {
            $date_to = $dt->format('Y-m-d');
        }
    }

    // --- build args
    $args = [
        'object_id'     => $object_id,
        'status_filter' => sanitize_text_field($_REQUEST['status'] ?? 'all'),
        'date_from'     => $date_from,
        'date_to'       => $date_to,
        'client_search' => sanitize_text_field($_REQUEST['client'] ?? ''),
        'company_filter'=> !empty($_REQUEST['company']) ? 'exact' : '',
        'company_value' => sanitize_text_field($_REQUEST['company'] ?? ''),
    ];

    // --- fetch data
    $bookings = br_get_bookings($args);

    // --- load object (for title)
    global $wpdb;
    $object = $wpdb->get_row(
        $wpdb->prepare(
            "SELECT name FROM {$wpdb->prefix}1br_objects WHERE id = %d",
            $object_id
        )
    );

    // --- headers
    nocache_headers();
    header('Content-Type: text/html; charset=utf-8');

    // --- variables for template
    $export_object_name = $object ? $object->name : '';
    $export_bookings    = $bookings;

    // --- render template
    require BR_PLUGIN_PATH . 'bookings-export-template.php';
    exit;
});
