<?php
/**
 * ------------------------------------------------------------
 * Файл: wp-content/plugins/booking-room/ajax-availability.php
 *
 * Назначение:
 *  - AJAX-слой для формы бронирования (frontend)
 *  - проверка: есть ли в дне хотя бы один допустимый интервал
 *    под выбранную длительность
 *  - READ-ONLY, использует расчётный слой slot-calculation.php
 * ------------------------------------------------------------
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * ------------------------------------------------------------
 * AJAX: проверка доступности дня под длительность
 * ------------------------------------------------------------
 *
 * POST:
 *  - object_id (int)
 *  - date (Y-m-d)
 *  - duration_slots (int)
 *
 * RESPONSE:
 *  {
 *    success: true,
 *    data: {
 *      available: bool
 *    }
 *  }
 */

add_action( 'wp_ajax_br_has_available_day', 'br_ajax_has_available_day' );
add_action( 'wp_ajax_nopriv_br_has_available_day', 'br_ajax_has_available_day' );

function br_ajax_has_available_day() {

    if (
        ! isset(
            $_POST['object_id'],
            $_POST['date'],
            $_POST['duration_slots']
        )
    ) {
        wp_send_json_error( 'Missing params' );
    }

    $object_id = absint( wp_unslash( $_POST['object_id'] ) );
$date = sanitize_text_field( wp_unslash( $_POST['date'] ) );
$duration_slots = absint( wp_unslash( $_POST['duration_slots'] ) );

    if ( $object_id <= 0 || $duration_slots <= 0 ) {
        wp_send_json_error( 'Invalid params' );
    }

    /**
     * Используем РЕАЛЬНУЮ функцию
     * из slot-calculation.php
     */
    if ( ! function_exists( 'br_calc_has_available_range' ) ) {
        wp_send_json_error( 'Calculation layer not loaded' );
    }

    $available = br_calc_has_available_range(
        $object_id,
        $date,
        $duration_slots
    );

    wp_send_json_success( [
        'available' => (bool) $available
    ] );
}
/**
 * ------------------------------------------------------------
 * AJAX: получение доступных стартовых слотов для формы бронирования
 * ------------------------------------------------------------
 */

add_action('wp_ajax_br_get_available_start_slots', 'br_get_available_start_slots');
add_action('wp_ajax_nopriv_br_get_available_start_slots', 'br_get_available_start_slots');

function br_get_available_start_slots() {

    if (
        ! isset($_POST['object_id'], $_POST['date'], $_POST['duration_slots'])
    ) {
        wp_send_json_error('Missing params');
    }

    $object_id = absint( wp_unslash( $_POST['object_id'] ) );
$date      = sanitize_text_field( wp_unslash( $_POST['date'] ) );
$duration  = absint( wp_unslash( $_POST['duration_slots'] ) );

    // используем существующий READ-ONLY расчёт
    $available_slots = br_calc_get_available_start_slots(
        $object_id,
        $date,
        $duration
    );

    wp_send_json_success([
        'available_slots' => $available_slots
    ]);
}

/**
 * ------------------------------------------------------------
 * AJAX: получение доступных дат месяца для календаря формы
 *
 * POST:
 *  - object_id        (int)
 *  - year             (int)
 *  - month            (int) 1–12
 *  - duration_slots   (int)
 *
 * RESPONSE:
 *  {
 *    success: true,
 *    data: {
 *      dates: string[] // Y-m-d
 *    }
 *  }
 * ------------------------------------------------------------
 */
add_action('wp_ajax_br_get_available_dates', 'br_ajax_get_available_dates');
add_action('wp_ajax_nopriv_br_get_available_dates', 'br_ajax_get_available_dates');

function br_ajax_get_available_dates() {

    if (
        ! isset(
            $_POST['object_id'],
            $_POST['year'],
            $_POST['month'],
            $_POST['duration_slots']
        )
    ) {
        wp_send_json_error('Missing params');
    }

    $object_id = absint( wp_unslash( $_POST['object_id'] ) );
$year      = absint( wp_unslash( $_POST['year'] ) );
$month     = absint( wp_unslash( $_POST['month'] ) );
$duration  = absint( wp_unslash( $_POST['duration_slots'] ) );

    if ( $object_id <= 0 || $year < 2000 || $month < 1 || $month > 12 || $duration <= 0 ) {
        wp_send_json_error('Invalid params');
    }

    $dates = [];

    $start = new DateTime(sprintf('%04d-%02d-01', $year, $month));
    $end   = clone $start;
    $end->modify('last day of this month');

    while ( $start <= $end ) {
        $date = $start->format('Y-m-d');

        if ( br_calc_has_available_range($object_id, $date, $duration) ) {
            $dates[] = $date;
        }

        $start->modify('+1 day');
    }

    wp_send_json_success([
        'dates' => $dates
    ]);
}
/**
 * ------------------------------------------------------------
 * AJAX: карта доступных дней (горизонт планирования)
 * ------------------------------------------------------------
 *
 * POST:
 *  - object_id
 *  - duration_slots
 *
 * RESPONSE:
 *  {
 *    success: true,
 *    data: {
 *      days: { "Y-m-d": true|false }
 *    }
 *  }
 * ------------------------------------------------------------
 */
add_action('wp_ajax_br_get_available_days_horizon', 'br_ajax_get_available_days_horizon');
add_action('wp_ajax_nopriv_br_get_available_days_horizon', 'br_ajax_get_available_days_horizon');

function br_ajax_get_available_days_horizon() {

    if (
        !isset($_POST['object_id'], $_POST['duration_slots'])
    ) {
        wp_send_json_error('Missing params');
    }

    $object_id = absint( wp_unslash( $_POST['object_id'] ) );
$duration  = absint( wp_unslash( $_POST['duration_slots'] ) );

    if ($object_id <= 0 || $duration <= 0) {
        wp_send_json_error('Invalid params');
    }

    if (!function_exists('br_calc_get_available_days_horizon')) {
        wp_send_json_error('Calculation layer not loaded');
    }

    $days = br_calc_get_available_days_horizon(
        $object_id,
        $duration,
        3
    );

    wp_send_json_success([
        'days' => $days
    ]);
}
