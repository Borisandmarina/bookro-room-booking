<?php
/**
 * ------------------------------------------------------------
 * Файл: wp-content/plugins/booking-room/booking-export-builder.php
 *
 * Назначение:
 *  - Построение человеко-читаемого массива данных бронирования
 *  - Используется для экспорта в:
 *      • Google Sheets
 *      • Telegram
 *      • Email
 *  - Файл НЕ сохраняет данные
 *  - Файл НЕ отправляет данные
 *  - Только чтение и преобразование
 * ------------------------------------------------------------
 */

defined('ABSPATH') || exit;

/**
 * Build human-readable booking export data
 *
 * @param int $booking_id
 * @return array|null
 */
function br_build_booking_export_data( int $booking_id ) {

    global $wpdb;

    /* ============================================================
       1. Получение записи бронирования
       ============================================================ */

    $booking = $wpdb->get_row(
        $wpdb->prepare(
            "SELECT *
             FROM {$wpdb->prefix}1br_bookings
             WHERE id = %d
             LIMIT 1",
            $booking_id
        ),
        ARRAY_A
    );

    if ( ! $booking ) {
        return null;
    }

    /* ============================================================
   2. Объект бронирования
   ============================================================ */

$object = $wpdb->get_row(
    $wpdb->prepare(
        "SELECT name, address, phone, email
         FROM {$wpdb->prefix}1br_objects
         WHERE id = %d
         LIMIT 1",
        (int) $booking['object_id']
    ),
    ARRAY_A
);

$object_name    = $object['name']    ?? '';
$object_address = $object['address'] ?? '';
$object_phone   = $object['phone']   ?? '';
$object_email   = $object['email']   ?? '';


    /* ============================================================
       3. Тип мероприятия
       ============================================================ */

    $event_type_label = '';
    if ( (int) $booking['event_type_id'] > 0 ) {
        $event_type_label = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT label
                 FROM {$wpdb->prefix}1br_event_types
                 WHERE id = %d
                 LIMIT 1",
                (int) $booking['event_type_id']
            )
        );
    }

    /* ============================================================
       4. Количество участников
       ============================================================ */

    $participants_label = '';
    if ( (int) $booking['participants_option_id'] > 0 ) {
        $participants_label = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT label
                 FROM {$wpdb->prefix}1br_visitors_count
                 WHERE id = %d
                 LIMIT 1",
                (int) $booking['participants_option_id']
            )
        );
    }

    /* ============================================================
       5. Услуги (equipment_ids → labels)
       ============================================================ */

    $services_labels = [];

    if ( ! empty($booking['equipment_ids']) ) {

        $ids = array_filter(
            array_map('intval', explode(',', $booking['equipment_ids']))
        );

        if ( ! empty($ids) ) {

            $placeholders = implode(',', array_fill(0, count($ids), '%d'));

            $services = $wpdb->get_col(
                $wpdb->prepare(
                    "
                    SELECT label
                    FROM {$wpdb->prefix}1br_services
                    WHERE id IN ($placeholders)
                    ORDER BY sort_order ASC, id ASC
                    ",
                    $ids
                )
            );

            if ( $services ) {
                $services_labels = $services;
            }
        }
    }

    /* ============================================================
       6. Валюта
       ============================================================ */

    $currency = $wpdb->get_var(
        "SELECT currency
         FROM {$wpdb->prefix}1br_rental_rate
         LIMIT 1"
    );

    /* ============================================================
       7. Форматирование дат и времени
       ============================================================ */

    // event_date: день недели / дата / месяц / год
    $event_date_human = '';
    if ( ! empty($booking['event_date']) ) {
        $dt = DateTime::createFromFormat('Y-m-d', $booking['event_date']);
        if ( $dt ) {
            $event_date_human = $dt->format('l  d.m.Y');
        }
    }

    // created_at
    $created_at_human = '';
    if ( ! empty($booking['created_at']) ) {
        $dt = DateTime::createFromFormat('Y-m-d H:i:s', $booking['created_at']);
        if ( $dt ) {
            $created_at_human = $dt->format('d.m.Y H:i');
        }
    }

    // slot → time
    $time_start = sprintf('%02d:00', (int) $booking['slot_start']);
    $time_end   = sprintf('%02d:00', (int) $booking['slot_end']);

    /* ============================================================
       8. Формирование финального массива
       ============================================================ */

    return [

        // служебное
		'booking_id' => (int) $booking['id'],
		
		// объект
		'object_id'   => (int) $booking['object_id'],
		'object_name' => $object_name ?: '',

		// дата
		'event_date'  => $event_date_human,



        // время
        'time_start'          => $time_start,
        'time_end'            => $time_end,
        'duration'            => (int) $booking['duration_slots'] . ' h',
        'interval2_duration'  => (int) $booking['interval2_slots'] . ' h',

        // параметры мероприятия
        'event_type'          => $event_type_label ?: '',
        'event_type_custom'   => $booking['event_type_custom'] ?: '',
        'participants'        => $participants_label ?: '',
        'services'            => implode(', ', $services_labels),

        // клиент
        'client_company'      => $booking['client_company'] ?: '',
        'client_name'         => $booking['client_name'],
        'client_surname'      => $booking['client_surname'],
        'client_phone'        => $booking['client_phone'],
        'client_email'        => $booking['client_email'],

        // стоимость
        'price'               => number_format((float) $booking['price_net'], 2, '.', ''),
        'currency'            => $currency ?: '',

        // служебное
        'order_comment'       => $booking['order_comment'] ?: '',
        'created_at'          => $created_at_human,
        'status'              => $booking['status'],
		
		// объект
		'object_id'      => (int) $booking['object_id'],
		'object_name'    => $object_name,
		'object_address' => $object_address,
		'object_phone'   => $object_phone,
		'object_email'   => $object_email,
    ];
}
