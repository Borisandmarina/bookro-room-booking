<?php
/**
 * ------------------------------------------------------------
 * Файл: wp-content/plugins/booking-room/admin-ajax-bookings.php
 *
 * Назначение:
 *  - Backend для страницы Bookings
 *  - Массовая смена статуса
 *  - Массовое удаление
 *  - Фильтрация таблицы (AJAX → HTML <tr>)
 *  - Только AJAX / POST
 * ------------------------------------------------------------
 */
defined('ABSPATH') || exit;
require_once BR_PLUGIN_PATH . 'admin-bookings-query.php';

/**
 * Проверка базовых прав и nonce
 */
function br_bookings_ajax_guard() {

    if ( ! current_user_can('manage_options') ) {
        wp_send_json_error(['message' => 'Access denied'], 403);
    }

    if (
        empty($_POST['_wpnonce']) ||
        ! wp_verify_nonce($_POST['_wpnonce'], 'br_bookings_bulk_action')
    ) {
        wp_send_json_error(['message' => 'Invalid nonce'], 403);
    }
}

/**
 * Получить и нормализовать IDs бронирований
 */
function br_get_booking_ids_from_request(): array {

    if ( empty($_POST['booking_ids']) || ! is_array($_POST['booking_ids']) ) {
        return [];
    }

    return array_values(
        array_filter(
            array_map('intval', $_POST['booking_ids']),
            fn($id) => $id > 0
        )
    );
}

/**
 * ------------------------------------------------------------
 * AJAX: Массовая смена статуса
 * ------------------------------------------------------------
 */
add_action('wp_ajax_br_bookings_bulk_status', function () {

    global $wpdb;

    br_bookings_ajax_guard();

    $booking_ids = br_get_booking_ids_from_request();
    $new_status  = sanitize_text_field($_POST['new_status'] ?? '');

    if ( empty($booking_ids) ) {
        wp_send_json_error(['message' => 'No bookings selected']);
    }

    $allowed_statuses = ['pending','confirmed','cancelled','archive'];

    if ( ! in_array($new_status, $allowed_statuses, true) ) {
        wp_send_json_error(['message' => 'Invalid status']);
    }

    $placeholders = implode(',', array_fill(0, count($booking_ids), '%d'));

$params = array_merge([$new_status], $booking_ids);

$wpdb->query(
    $wpdb->prepare(
        "
    UPDATE {$wpdb->prefix}1br_bookings
    SET status = %s
    WHERE id IN ($placeholders)
",
        $params
    )
);


    wp_send_json_success([
        'updated_ids' => $booking_ids,
        'status'      => $new_status
    ]);
});


/**
 * ------------------------------------------------------------
 * AJAX: Массовое удаление бронирований
 * ------------------------------------------------------------
 */
add_action('wp_ajax_br_bookings_bulk_delete', function () {

    global $wpdb;

    br_bookings_ajax_guard();

    $booking_ids = br_get_booking_ids_from_request();

    if ( empty($booking_ids) ) {
        wp_send_json_error(['message' => 'No bookings selected']);
    }

   $placeholders = implode(',', array_fill(0, count($booking_ids), '%d'));

$wpdb->query(
    $wpdb->prepare(
        "
    DELETE FROM {$wpdb->prefix}1br_bookings
    WHERE id IN ($placeholders)
",
        $booking_ids
    )
);


    wp_send_json_success([
        'deleted_ids' => $booking_ids
    ]);
});


/**
 * ------------------------------------------------------------
 * AJAX: Фильтрация таблицы бронирований
 * Action: br_bookings_filter
 * Return: HTML <tr>
 * ------------------------------------------------------------
 */
add_action('wp_ajax_br_bookings_filter', function () {

    br_bookings_ajax_guard();

    global $wpdb;

    $object_id = (int) ($_POST['object_id'] ?? 0);
    if ($object_id <= 0) {
        wp_send_json_error(['message' => 'Invalid object_id'], 400);
    }

    // даты: DD.MM.YYYY → Y-m-d
    $date_from = '';
    if (!empty($_POST['date_from'])) {
        $dt = DateTime::createFromFormat('d.m.Y', $_POST['date_from']);
        if ($dt) {
            $date_from = $dt->format('Y-m-d');
        }
    }

    $date_to = '';
    if (!empty($_POST['date_to'])) {
        $dt = DateTime::createFromFormat('d.m.Y', $_POST['date_to']);
        if ($dt) {
            $date_to = $dt->format('Y-m-d');
        }
    }

    $args = [
        'object_id'     => $object_id,
        'status_filter' => $_POST['status'] ?? 'all',
        'date_from'     => $date_from,
        'date_to'       => $date_to,
        'client_search' => trim($_POST['client'] ?? ''),
        'company_filter'=> !empty($_POST['company']) ? 'exact' : '',
        'company_value' => trim($_POST['company'] ?? ''),
    ];

    $rows = br_get_bookings($args);

    $event_types = $wpdb->get_results(
    $wpdb->prepare(
        "SELECT id, label FROM {$wpdb->prefix}1br_event_types WHERE object_id = %d",
        $object_id
    ),
    OBJECT_K
);


    $participants = $wpdb->get_results(
    $wpdb->prepare(
        "SELECT id, label FROM {$wpdb->prefix}1br_visitors_count WHERE object_id = %d",
        $object_id
    ),
    OBJECT_K
);


    $services = $wpdb->get_results(
    $wpdb->prepare(
        "SELECT id, label FROM {$wpdb->prefix}1br_services WHERE object_id = %d",
        $object_id
    ),
    OBJECT_K
);


    ob_start();

    if ( empty($rows) ) {
        echo '<tr><td colspan="14">No bookings found.</td></tr>';
    } else {

        foreach ($rows as $row) {

            $event_type_label = !empty($row['event_type_custom'])
                ? $row['event_type_custom']
                : ($event_types[$row['event_type_id']]->label ?? '');

            $participants_label = $participants[$row['participants_option_id']]->label ?? '';

            $service_labels = [];
            if (!empty($row['equipment_ids'])) {
                foreach (array_map('intval', explode(',', $row['equipment_ids'])) as $sid) {
                    if (isset($services[$sid])) {
                        $service_labels[] = $services[$sid]->label;
                    }
                }
            }
            ?>
            <tr>
                <td><input type="checkbox" class="br-booking-checkbox" value="<?php echo (int)$row['id']; ?>"></td>
                <td><?php echo (int)$row['id']; ?></td>
                <td><?php echo esc_html(ucfirst($row['status'])); ?></td>
                <td><?php echo esc_html(gmdate('d.m.Y', strtotime($row['event_date']))); ?></td>
                <td><?php printf('%02d:00 – %02d:00', (int)$row['slot_start'], (int)$row['slot_end']); ?></td>
                <td><?php echo (int)$row['duration_slots']; ?>h</td>
                <td><?php echo esc_html($event_type_label); ?></td>
                <td><?php echo esc_html($participants_label); ?></td>
                <td><?php echo esc_html(implode(', ', $service_labels)); ?></td>
                <td><?php echo esc_html(number_format((float)$row['price_net'], 2)); ?></td>
                <td><?php echo esc_html($row['client_name'].' '.$row['client_surname']); ?></td>
                <td><?php echo esc_html($row['client_company']); ?></td>
                <td><?php echo esc_html($row['client_phone']); ?></td>
                <td><?php echo esc_html($row['client_email']); ?></td>
            </tr>
            <?php
        }
    }

    wp_send_json_success([
        'html' => ob_get_clean()
    ]);
});
