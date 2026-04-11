<?php
/**
 * ------------------------------------------------------------
 * Файл: wp-content/plugins/booking-room/bookings-export-excel.php
 *
 * Назначение:
 *  - Экспорт бронирований в Excel (XLS)
 *  - Использует HTML-шаблон таблицы
 *  - Открывается как загрузка файла
 *  - Никакого AJAX
 * ------------------------------------------------------------
 */

defined('ABSPATH') || exit;

/**
 * ------------------------------------------------------------
 * Export Excel handler
 * URL: wp-admin/admin-post.php?action=br_export_bookings_excel
 * ------------------------------------------------------------
 */
add_action('admin_post_br_export_bookings_excel', function () {

    // --- access check
    if ( ! current_user_can('manage_options') ) {
        wp_die('Access denied');
    }

    // --- nonce check
        if (
        ! isset( $_REQUEST['_wpnonce'] ) ||
        ! wp_verify_nonce(
            sanitize_text_field( wp_unslash( $_REQUEST['_wpnonce'] ) ),
            'br_bookings_bulk_action'
        )
    ) {
        wp_die('Invalid nonce');
    }

    // --- required
        $object_id = isset( $_REQUEST['object_id'] )
        ? absint( wp_unslash( $_REQUEST['object_id'] ) )
        : 0;
    if ($object_id <= 0) {
        wp_die('Invalid object');
    }

    // --- normalize dates (DD.MM.YYYY → Y-m-d)
        $date_from = '';
    if ( ! empty( $_REQUEST['date_from'] ) ) {
        $date_from_raw = sanitize_text_field( wp_unslash( $_REQUEST['date_from'] ) );
        $dt = DateTime::createFromFormat( 'd.m.Y', $date_from_raw );
        if ( $dt ) {
            $date_from = $dt->format( 'Y-m-d' );
        }
    }

        $date_to = '';
    if ( ! empty( $_REQUEST['date_to'] ) ) {
        $date_to_raw = sanitize_text_field( wp_unslash( $_REQUEST['date_to'] ) );
        $dt = DateTime::createFromFormat( 'd.m.Y', $date_to_raw );
        if ( $dt ) {
            $date_to = $dt->format( 'Y-m-d' );
        }
    }

    // --- build args
        $status_filter = isset( $_REQUEST['status'] )
        ? sanitize_text_field( wp_unslash( $_REQUEST['status'] ) )
        : 'all';

    $client_search = isset( $_REQUEST['client'] )
        ? sanitize_text_field( wp_unslash( $_REQUEST['client'] ) )
        : '';

    $company_value = isset( $_REQUEST['company'] )
        ? sanitize_text_field( wp_unslash( $_REQUEST['company'] ) )
        : '';

    $args = [
        'object_id'      => $object_id,
        'status_filter'  => $status_filter,
        'date_from'      => $date_from,
        'date_to'        => $date_to,
        'client_search'  => $client_search,
        'company_filter' => $company_value !== '' ? 'exact' : '',
        'company_value'  => $company_value,
    ];

    // --- fetch data
    $bookings = br_get_bookings($args);

    // --- load object (for filename)
    global $wpdb;
    $object = $wpdb->get_row(
        $wpdb->prepare(
            "SELECT name FROM {$wpdb->prefix}1br_objects WHERE id = %d",
            $object_id
        )
    );

    $filename = 'bookings';
    if ($object && !empty($object->name)) {
        $filename .= '-' . sanitize_title($object->name);
    }
    $filename .= '.xls';

    // --- headers
    nocache_headers();
    header('Content-Type: application/vnd.ms-excel; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');

    // --- render template
    require BR_PLUGIN_PATH . 'bookings-export-template.php';
    exit;
});
