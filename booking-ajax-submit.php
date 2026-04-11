<?php

/**
 * ------------------------------------------------------------
 * Файл: wp-content/plugins/booking-room/booking-ajax-submit.php
 *
 * Назначение:
 *  - Прием данных формы бронирования
 *  - Сбор данных через booking-data-builder.php
 *  - Сохранение бронирования в wp_1br_bookings
 * ------------------------------------------------------------
 */

defined('ABSPATH') || exit;

add_action('wp_ajax_br_submit_booking', 'br_submit_booking');
add_action('wp_ajax_nopriv_br_submit_booking', 'br_submit_booking');

function br_submit_booking() {

    global $wpdb;
        check_ajax_referer('br_submit_booking', '_wpnonce');
	$privacy_consent = isset( $_POST['privacy_consent'] )
    ? sanitize_text_field( wp_unslash( $_POST['privacy_consent'] ) )
    : '';

if ( '' === $privacy_consent ) {
    wp_send_json_error([
        'message' => 'Consent required'
    ]);
}

    require_once __DIR__ . '/booking-data-builder.php';


    /* ============================================================
       1. СБОР И НОРМАЛИЗАЦИЯ ДАННЫХ
       ============================================================ */

    $raw_post = wp_unslash( $_POST );
$booking_data = br_build_booking_data( $raw_post );
	if (!is_email($booking_data['client_email'])) {
    wp_send_json_error([
        'message' => 'Invalid email'
    ]);
}

    /* ============================================================
       2. МИНИМАЛЬНАЯ ВАЛИДАЦИЯ
       ============================================================ */

    if (
        empty($booking_data['object_id']) ||
        empty($booking_data['event_date']) ||
        empty($booking_data['duration_slots']) ||
        empty($booking_data['client_email'])
    ) {
        wp_send_json_error([
            'message' => 'Required fields are missing'
        ]);
    }

    /* ============================================================
       3. INSERT В БД
       ============================================================ */

    $result = $wpdb->insert(
        $wpdb->prefix . '1br_bookings',
        $booking_data
    );

    if ( $result === false ) {

    error_log('Booking insert error: ' . $wpdb->last_error);

    wp_send_json_error([
        'message' => 'Database error'
    ]);
}


    /* === ВАЖНО: фиксируем booking_id СРАЗУ после INSERT === */
    $booking_id = (int) $wpdb->insert_id;

	/* ============================================================
       3.1 SAVE / UPDATE USER CONTACT (BY EMAIL)
       Таблица: wp_1br_user_contacts
       ============================================================ */

$user_contacts_table = $wpdb->prefix . '1br_user_contacts';

// нормализация email
$email = sanitize_email($booking_data['client_email'] ?? '');
$email = strtolower(trim($email));

$wpdb->query(
    $wpdb->prepare(
        "
        INSERT INTO {$user_contacts_table}
            (email, company, first_name, last_name, phone)
        VALUES
            (%s, %s, %s, %s, %s)
        ON DUPLICATE KEY UPDATE
            company    = VALUES(company),
            first_name = VALUES(first_name),
            last_name  = VALUES(last_name),
            phone      = VALUES(phone),
            updated_at = CURRENT_TIMESTAMP
        ",
        $email,
        $booking_data['client_company'] ?? null,
		$booking_data['client_name'] ?? '',
		$booking_data['client_surname'] ?? '',
		$booking_data['client_phone'] ?? ''
    )
);

    /* ============================================================
       4. POST-SAVE EXPORTS
       ============================================================ */

if ( $booking_id > 0 ) {


     // Email
    require_once __DIR__ . '/booking-export-email.php';

    // Администраторы — всегда (если send_mail = ON)
    br_export_booking_to_email_admin($booking_id);

    // Клиент — подтверждение получения формы
    br_export_booking_to_email_client_received($booking_id);
}


/* ============================================================
   5. SUCCESS RESPONSE
   ============================================================ */


wp_send_json_success([
    'booking_id' => $booking_id
]);
}
/* ============================================================
   AJAX: Update booking status (admin)
   Таблица: wp_1br_bookings
   ============================================================ */

add_action('wp_ajax_br_update_booking_status', 'br_update_booking_status');

function br_update_booking_status() {

    global $wpdb;
    if ( ! current_user_can('manage_options') ) {
        wp_send_json_error('Access denied', 403);
    }

    check_ajax_referer('br_admin_nonce', '_wpnonce');


    if (
        empty($_POST['booking_id']) ||
        empty($_POST['status'])
    ) {
        wp_send_json_error('Missing params');
    }

    $booking_id = absint( wp_unslash( $_POST['booking_id'] ) );
$status     = sanitize_text_field( wp_unslash( $_POST['status'] ) );

    if ( $booking_id <= 0 ) {
        wp_send_json_error('Invalid booking_id');
    }

    // допустимые статусы
    $allowed_statuses = ['pending', 'confirmed', 'cancelled'];

    if ( ! in_array($status, $allowed_statuses, true) ) {
        wp_send_json_error('Invalid status');
    }

    $result = $wpdb->update(
        $wpdb->prefix . '1br_bookings',
        ['status' => $status],
        ['id' => $booking_id],
        ['%s'],
        ['%d']
    );

    if ( $result === false ) {
        wp_send_json_error($wpdb->last_error);
    }

    wp_send_json_success();
}

