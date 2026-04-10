<?php
/**
 * ------------------------------------------------------------
 * Файл: wp-content/plugins/booking-room/slot-calculation.php
 *
 * Назначение:
 *  - READ-ONLY слой расчётов для формы и отчётов
 *  - интерпретация текущего состояния БД
 *  - НИЧЕГО не пишет в БД
 *  - НЕ использует бизнес-логику админки
 *
 * Модель:
 *  - 1 слот = 1 час
 *  - слоты: 0–23
 *
 * Приоритеты (снизу вверх):
 *  1) global settings (фон)
 *  2) breaks / overrides (равный приоритет)
 *  3) bookings (всегда поверх всего)
 * ------------------------------------------------------------
 */

if ( ! defined( 'ABSPATH' ) ) {
    return;
}


/**
 * ------------------------------------------------------------
 * Получение состояния суток (24 слота)
 * ------------------------------------------------------------
 *
 * @param int    $object_id
 * @param string $date Y-m-d
 *
 * @return array{
 *   date: string,
 *   slots: array<int,string>,
 *   free_slots: int[],
 *   has_free_slots: bool
 * }
 */
function br_calc_get_day_state( int $object_id, string $date ): array {

    global $wpdb;

    // --------------------------------------------------------
// 1. Инициализация суток: всё недоступно
// --------------------------------------------------------
$slots = [];
for ( $i = 0; $i < 24; $i++ ) {
    $slots[$i] = 'unavailable';
}

// --------------------------------------------------------
// 1.1 Праздник (ТОЛЬКО если allow_booking = 0)
// --------------------------------------------------------
$holiday = $wpdb->get_row(
    $wpdb->prepare(
        "
        SELECT allow_booking
        FROM {$wpdb->prefix}1br_holidays
        WHERE object_id = %d
          AND date = %s
        LIMIT 1
        ",
        $object_id,
        $date
    ),
    ARRAY_A
);

// ❌ праздник и бронирование запрещено → день полностью закрыт
if ( $holiday && (int) $holiday['allow_booking'] === 0 ) {
    return [
        'date'           => $date,
        'slots'          => $slots, // все unavailable
        'free_slots'     => [],
        'has_free_slots' => false,
    ];
}

// ✅ если allow_booking = 1 — продолжаем обычный расчёт


// 2. Глобальные настройки (фон)
// --------------------------------------------------------
$settings = $wpdb->get_row(
    $wpdb->prepare(
        "
        SELECT work_start_slot, work_end_slot, weekends
        FROM {$wpdb->prefix}1br_global_settings
        WHERE object_id = %d
        LIMIT 1
        ",
        $object_id
    ),
    ARRAY_A
);

if ( $settings ) {

    $dt = new DateTimeImmutable( $date, new DateTimeZone('UTC') );

    error_log('WEEKENDS RAW: ' . $settings['weekends']);
    error_log('DATE: ' . $date);
    error_log('WEEKDAY l: ' . strtolower( $dt->format('l') ));

    // ISO-8601: 1 (Mon) ... 7 (Sun)
    $weekday_num = (int) $dt->format('N');

    $weekday_map = [
        'monday'    => 1,
        'tuesday'   => 2,
        'wednesday' => 3,
        'thursday'  => 4,
        'friday'    => 5,
        'saturday'  => 6,
        'sunday'    => 7,
    ];


    $weekends_raw = array_map(
        'strtolower',
        array_map('trim', explode(',', $settings['weekends']))
    );

    $weekend_nums = [];

    foreach ($weekends_raw as $day) {
        if (isset($weekday_map[$day])) {
            $weekend_nums[] = $weekday_map[$day];
        }
    }

    $is_weekend = in_array($weekday_num, $weekend_nums, true);

    if ( ! $is_weekend ) {
        for (
            $i = (int) $settings['work_start_slot'];
            $i < (int) $settings['work_end_slot'];
            $i++
        ) {
            if ( $i >= 0 && $i <= 23 ) {
                $slots[$i] = 'free';
            }
        }
    }
}

// --------------------------------------------------------
// 3. Breaks (недоступные интервалы)

// --------------------------------------------------------
    $breaks = $wpdb->get_results(
        $wpdb->prepare(
            "
            SELECT slot_start, slot_end
            FROM {$wpdb->prefix}1br_breaks
            WHERE object_id = %d
              AND %s BETWEEN date_start AND date_end
            ",
            $object_id,
            $date
        ),
        ARRAY_A
    );

    foreach ( $breaks as $row ) {
        for ( $i = (int) $row['slot_start']; $i < (int) $row['slot_end']; $i++ ) {
            if ( $i >= 0 && $i <= 23 ) {
                $slots[$i] = 'unavailable';
            }
        }
    }

    // --------------------------------------------------------
    // 4. Overrides (ручные свободные интервалы)
    // --------------------------------------------------------
    $overrides = $wpdb->get_results(
        $wpdb->prepare(
            "
            SELECT slot_start, slot_end
            FROM {$wpdb->prefix}1br_overrides
            WHERE object_id = %d
              AND %s BETWEEN date_start AND date_end
            ",
            $object_id,
            $date
        ),
        ARRAY_A
    );

    foreach ( $overrides as $row ) {
        for ( $i = (int) $row['slot_start']; $i < (int) $row['slot_end']; $i++ ) {
            if ( $i >= 0 && $i <= 23 ) {
                $slots[$i] = 'free';
            }
        }
    }

    // --------------------------------------------------------
    // 5. Bookings (высший приоритет)
    // --------------------------------------------------------
    $bookings = $wpdb->get_results(
        $wpdb->prepare(
            "
            SELECT slot_start, slot_end
            FROM {$wpdb->prefix}1br_bookings
            WHERE object_id = %d
              AND event_date = %s
              AND status IN ('pending','confirmed')
            ",
            $object_id,
            $date
        ),
        ARRAY_A
    );

    foreach ( $bookings as $row ) {
        for ( $i = (int) $row['slot_start']; $i < (int) $row['slot_end']; $i++ ) {
            if ( $i >= 0 && $i <= 23 ) {
                $slots[$i] = 'busy';
            }
        }
    }

    // --------------------------------------------------------
    // 6. Итог
    // --------------------------------------------------------
    $free_slots = [];
    foreach ( $slots as $hour => $state ) {
        if ( $state === 'free' ) {
            $free_slots[] = $hour;
        }
    }

    return [
        'date'           => $date,
        'slots'          => $slots,
        'free_slots'     => $free_slots,
        'has_free_slots' => ! empty( $free_slots ),
    ];
}

/**
 * ------------------------------------------------------------
 * Допустимые стартовые слоты под длительность
 * ------------------------------------------------------------
 *
 * @param int    $object_id
 * @param string $date Y-m-d
 * @param int    $duration_slots
 *
 * @return int[]
 */
function br_calc_get_available_start_slots(
    int $object_id,
    string $date,
    int $duration_slots
): array {

    if ( $duration_slots < 1 || $duration_slots > 24 ) {
        return [];
    }

    // НОРМАЛИЗАЦИЯ ДАТЫ (строго к Y-m-d)
    $ts = strtotime($date);
    if ($ts !== false) {
        $dt = new DateTimeImmutable('@' . $ts);
$dt = $dt->setTimezone( new DateTimeZone('UTC') );
$date = $dt->format('Y-m-d');

    }

    $state = br_calc_get_day_state( $object_id, $date );
    $slots = $state['slots'];
	

    $starts = [];

    for ( $start = 0; $start <= 24 - $duration_slots; $start++ ) {

        $ok = true;

        for ( $i = $start; $i < $start + $duration_slots; $i++ ) {
            if ( ! isset( $slots[$i] ) || $slots[$i] !== 'free' ) {
                $ok = false;
                break;
            }
        }

        if ( $ok ) {
            $starts[] = $start;
        }
    }
/* время определяется из зоны, указанной для каждого объекта в глобальных настройках БД
не серверное. не wordpress - только БД*/

$now = br_get_object_now( $object_id );

if ( $now && ! empty($starts) ) {

    $today = $now->format('Y-m-d');

    if ( $date === $today ) {

        // минимально допустимый старт — следующий час (локальное время объекта)
        $minSlot = (int) $now->format('G') + 2;

        $starts = array_values(
            array_filter(
                $starts,
                function ($slot) use ($minSlot) {
                    return (int) $slot >= $minSlot;
                }
            )
        );
    }
}
    return $starts;
}
/**
 * ------------------------------------------------------------
 * Получить текущее локальное время объекта
 *
 * @param int $object_id
 *
 * @return DateTimeImmutable|null
 * ------------------------------------------------------------
 */
function br_get_object_now( int $object_id ): ?DateTimeImmutable {

    global $wpdb;

    $timezone = $wpdb->get_var(
        $wpdb->prepare(
            "
            SELECT timezone
            FROM {$wpdb->prefix}1br_global_settings
            WHERE object_id = %d
            LIMIT 1
            ",
            $object_id
        )
    );

    if ( ! $timezone ) {
        return null;
    }

    try {
        return new DateTimeImmutable('now', new DateTimeZone($timezone));
    } catch ( Exception $e ) {
        return null;
    }
}
/**

 * ------------------------------------------------------------
 * Проверка: есть ли в дате хотя бы один допустимый интервал
 * ------------------------------------------------------------
 *
 * @param int    $object_id
 * @param string $date Y-m-d
 * @param int    $duration_slots
 *
 * @return bool
 */
function br_calc_has_available_range(
    int $object_id,
    string $date,
    int $duration_slots
): bool {

    return ! empty(
        br_calc_get_available_start_slots(
            $object_id,
            $date,
            $duration_slots
        )
    );
}
/**
 * ------------------------------------------------------------
 * Получение карты доступных дней в горизонте
 * ------------------------------------------------------------
 *
 * @param int $object_id
 * @param int $duration_slots
 * @param int $months_ahead
 *
 * @return array<string,bool>  Y-m-d => available
 */
function br_calc_get_available_days_horizon(
    int $object_id,
    int $duration_slots,
    int $months_ahead = 3
): array {

    if ($object_id <= 0 || $duration_slots <= 0) {
        return [];
    }

    $result = [];

    $start = new DateTime('today');
    $end   = (clone $start)->modify('+' . $months_ahead . ' months');

    while ($start <= $end) {

        $date = $start->format('Y-m-d');

        $result[$date] = br_calc_has_available_range(
            $object_id,
            $date,
            $duration_slots
        );

        $start->modify('+1 day');
    }

    return $result;
}
