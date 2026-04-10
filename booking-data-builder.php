<?php
/**
 * ------------------------------------------------------------
 * Файл: wp-content/plugins/booking-room/booking-data-builder.php
 *
 * Назначение:
 *  - сбор и нормализация данных бронирования из формы (все шаги)
 *  - вычисление производных значений (slot_end, интервалы)
 *  - подготовка единого массива данных для:
 *      • сохранения в БД
 *      • email
 *      • Google Sheets
 *      • Telegram
 *
 * ВАЖНО:
 *  - файл НИЧЕГО не сохраняет
 *  - файл НИЧЕГО не отправляет
 *  - файл НИЧЕГО не выводит
 *  - только сбор и расчёт данных
 * ------------------------------------------------------------
 */

if ( ! defined('ABSPATH') ) {
    exit;
}

/**
 * ============================================================
 * Build booking data
 * ============================================================
 *
 * @param array $raw  Данные формы (обычно $_POST)
 * @return array
 */
function br_build_booking_data( array $raw ) {

    global $wpdb;

    /* ========================================================
       1. Базовые значения из формы
       ======================================================== */

    $object_id = (int) ($raw['object_id'] ?? 0);

$event_date_raw = sanitize_text_field(
    trim($raw['event_date'] ?? '')
);

$event_date = null;

if ($event_date_raw !== '') {

    $timestamp = strtotime($event_date_raw);

    if ($timestamp !== false) {
       $event_date = gmdate('Y-m-d', $timestamp);

    }
}


    $slot_start      = (int) ($raw['slot_start'] ?? 0);
    $duration_slots  = (int) ($raw['duration_slots'] ?? 0);

    $slot_end = $slot_start + $duration_slots;


    /* ========================================================
   3. Интервалы (Lite — без разделения дня)
   ======================================================== */

$interval1_slot_start = $slot_start;
$interval1_slot_end   = $slot_end;
$interval1_slots      = $duration_slots;

$interval2_slot_start = $slot_start;
$interval2_slot_end   = $slot_end;
$interval2_slots      = $duration_slots;

    /* ========================================================
   4. Параметры мероприятия (шаг 2)
   ======================================================== */

$event_type_raw    = $raw['event_type_id'] ?? '';
$event_type_id     = 0; // IMPORTANT: default = 0, NOT NULL
$event_type_custom = null;

if ($event_type_raw === 'other') {

    $event_type_custom = sanitize_text_field(
    trim($raw['event_type_other'] ?? '')
);


    if ($event_type_custom === '') {
        // обязательность контролируется формой, но защита на сервере
        $event_type_custom = null;
    }

} else {

    $event_type_id = (int) $event_type_raw;
    $event_type_custom = null;
}

    /* ========================================================
       5. Клиент (шаг 3)
       ======================================================== */

    $client_name     = sanitize_text_field( trim($raw['client_name'] ?? '') );
	$client_surname  = sanitize_text_field( trim($raw['client_surname'] ?? '') );
	$client_company  = sanitize_text_field( trim($raw['client_company'] ?? '') );
	$client_phone    = sanitize_text_field( trim($raw['client_phone'] ?? '') );
	$client_email    = sanitize_email( trim($raw['client_email'] ?? '') );

	
	/* ========================================================
	   5.1 Дополнительные параметры формы
	   ======================================================== */

	$visitors_count_id = (int) ($raw['visitors_count_id'] ?? 0);

	$services_ids = isset($raw['services']) && is_array($raw['services'])
		? array_map('intval', $raw['services'])
		: [];


	$order_comment = sanitize_textarea_field(
    trim($raw['additional_info'] ?? '')
);



    /* ========================================================
       6. Стоимость
       ======================================================== */

    $price_net = isset($raw['rental_cost'])
    ? (float) sanitize_text_field($raw['rental_cost'])
    : 0.0;

    // Пока без НДС
    $price_gross = $price_net;

    /* ========================================================
       7. Системные значения
       ======================================================== */

    $payment_method_id = 1;
    $status            = 'pending';

    /* ========================================================
       8. Финальный массив (1:1 под wp_1br_bookings)
       ======================================================== */

    return [
        // объект
        'object_id' => $object_id,

        // дата и время
        'event_date'     => $event_date,
        'slot_start'     => $slot_start,
        'slot_end'       => $slot_end,
        'duration_slots' => $duration_slots,

        // интервалы
        'interval1_slot_start' => $interval1_slot_start,
        'interval1_slot_end'   => $interval1_slot_end,
        'interval1_slots'      => $interval1_slots,

        'interval2_slot_start' => $interval2_slot_start,
        'interval2_slot_end'   => $interval2_slot_end,
        'interval2_slots'      => $interval2_slots,

        // параметры мероприятия
        'participants_option_id' => $visitors_count_id,
        'event_type_id'          => $event_type_id,
        'event_type_custom'      => $event_type_custom,
        'equipment_ids'          => implode(',', $services_ids),
        'order_comment'          => $order_comment,

        // клиент
        'client_name'    => $client_name,
        'client_surname' => $client_surname,
        'client_company' => $client_company ?: null,
        'client_phone'   => $client_phone,
        'client_email'   => $client_email,

        // цена
        'price_net'   => $price_net,
        'price_gross' => $price_gross,

        // системное
        'payment_method_id' => $payment_method_id,
        'status'            => $status,
        'created_at'        => current_time('mysql'),
    ];
}
