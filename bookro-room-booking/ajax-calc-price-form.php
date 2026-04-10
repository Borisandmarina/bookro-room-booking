<?php
/**
 * ------------------------------------------------------------
 * Файл: wp-content/plugins/booking-room/ajax-calc-price-form.php
 * Назначение:
 *  - серверный расчет стоимости аренды
 *  - используется формой бронирования (Step 2)
 *  - вызывается автоматически через AJAX
 * ------------------------------------------------------------
 */

if ( ! defined('ABSPATH') ) {
    exit;
}

/**
 * ============================================================
 * CORE: Calculate rental price
 * ============================================================
 */
function br_calculate_rental_price( array $data ) {

    global $wpdb;

    $object_id       = (int) ($data['object_id'] ?? 0);
    $duration        = (int) ($data['duration_slots'] ?? 0);
    $slot_start      = (int) ($data['slot_start'] ?? 0);
    $event_date_raw  = $data['event_date'] ?? '';
    $visitors_id     = (int) ($data['visitors_count_id'] ?? 0);
    $services_ids    = $data['services'] ?? [];

    if ( ! $object_id || ! $duration || ! $event_date_raw ) {
        return 0.0;
    }

    // Нормализация даты
    $timestamp = strtotime($event_date_raw);
    if ( ! $timestamp ) {
        return 0.0;
    }

    $event_date = wp_date('Y-m-d', $timestamp);


    /* ========================================================
       BASE RATE (WORKDAY)
       ======================================================== */
    $base_rate = (float) $wpdb->get_var(
        $wpdb->prepare(
            "SELECT price
             FROM {$wpdb->prefix}1br_rental_rate
             WHERE object_id = %d
             LIMIT 1",
            $object_id
        )
    );

    if ( ! $base_rate ) {
        return 0.0;
    }

    $current_rate = $base_rate;

    /* ========================================================
       GLOBAL SETTINGS (weekends only)
       ======================================================== */
    $settings = $wpdb->get_row(
        $wpdb->prepare(
            "SELECT weekends
             FROM {$wpdb->prefix}1br_global_settings
             WHERE object_id = %d
             LIMIT 1",
            $object_id
        ),
        ARRAY_A
    );

    $weekday = wp_date('l', strtotime($event_date));

    $weekends = $settings && $settings['weekends']
        ? array_map('trim', explode(',', $settings['weekends']))
        : [];

    /* ========================================================
       DAY RATE SELECTION (HOLIDAY → WEEKEND → WORKDAY)
       ======================================================== */

    // 🎉 Holiday has top priority
    $holiday = $wpdb->get_row(
        $wpdb->prepare(
            "
            SELECT id
            FROM {$wpdb->prefix}1br_holidays
            WHERE object_id = %d
              AND date = %s
            LIMIT 1
            ",
            $object_id,
            $event_date
        ),
        ARRAY_A
    );

    if ( $holiday ) {

        $holiday_rate = (float) $wpdb->get_var(
            $wpdb->prepare(
                "
                SELECT coefficient
                FROM {$wpdb->prefix}1br_day_holiday_coeff
                WHERE object_id = %d
                LIMIT 1
                ",
                $object_id
            )
        );

        if ( $holiday_rate > 0 ) {
            $current_rate = $holiday_rate;
        }

    } elseif ( in_array($weekday, $weekends, true) ) {

        $weekend_rate = (float) $wpdb->get_var(
            $wpdb->prepare(
                "
                SELECT coefficient
                FROM {$wpdb->prefix}1br_day_weekend_coeff
                WHERE object_id = %d
                LIMIT 1
                ",
                $object_id
            )
        );

        if ( $weekend_rate > 0 ) {
            $current_rate = $weekend_rate;
        }
    }

    /* ========================================================
       VISITORS COUNT → COEFFICIENT
       ======================================================== */
    $visitors_coef = 1.0;

    if ( $visitors_id ) {
        $visitors_coef = (float) $wpdb->get_var(
            $wpdb->prepare(
                "SELECT coef
                 FROM {$wpdb->prefix}1br_visitors_count
                 WHERE id = %d AND is_visible = 1",
                $visitors_id
            )
        );

        if ( $visitors_coef <= 0 ) {
            $visitors_coef = 1.0;
        }
    }

    $rate_with_visitors = $current_rate * $visitors_coef;

    /* ========================================================
       BASE RENTAL TOTAL
       ======================================================== */
    $base_total = $rate_with_visitors * $duration;

   /* ========================================================
       SERVICES
       ======================================================== */
    $services_total = 0.0;

    if ( is_array($services_ids) && $services_ids ) {

        $ids = array_map('intval', $services_ids);

        $placeholders = implode(',', array_fill(0, count($ids), '%d'));

        $rows = $wpdb->get_results(
            $wpdb->prepare(
                "
                SELECT price, price_unit
                FROM {$wpdb->prefix}1br_services
                WHERE id IN ($placeholders)
                  AND is_visible = 1
                ",
                $ids
            ),
            ARRAY_A
        );

        foreach ( $rows as $row ) {
            if ( $row['price_unit'] === 'per_hour' ) {
                $services_total += (float) $row['price'] * $duration;
            } else {
                $services_total += (float) $row['price'];
            }
        }

    }

    return round(
        $base_total + $services_total,
        2
    );
}



/**
 * ============================================================
 * AJAX wrapper
 * ============================================================
 */
add_action('wp_ajax_br_calculate_price', 'br_ajax_calculate_price');
add_action('wp_ajax_nopriv_br_calculate_price', 'br_ajax_calculate_price');

function br_ajax_calculate_price() {
if (
    isset($_POST['_wpnonce']) &&
    ! wp_verify_nonce($_POST['_wpnonce'], 'br_calculate_price_nonce')
) {
    wp_send_json_error('Invalid nonce');
}



    global $wpdb;

    $data = $_POST ?? [];
    $object_id = (int) ($data['object_id'] ?? 0);

    $total = br_calculate_rental_price($data);

    $currency = '';
    if ( $object_id ) {
        $currency = (string) $wpdb->get_var(
            $wpdb->prepare(
                "SELECT currency
                 FROM {$wpdb->prefix}1br_rental_rate
                 WHERE object_id = %d
                 LIMIT 1",
                $object_id
            )
        );
    }

    wp_send_json_success([
        'total'    => number_format($total, 2, '.', ''),
        'currency' => $currency
    ]);
}
