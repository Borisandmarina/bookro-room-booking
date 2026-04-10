<?php
/**
 * ------------------------------------------------------------
 * Файл: wp-content/plugins/booking-room/admin-bookings-query.php
 *
 * Назначение:
 *  - Read-only слой выборки бронирований для страницы Bookings
 *  - Формирование SQL с фильтрами
 *  - Фиксированная сортировка (event_date DESC, created_at DESC)
 *  - Никакого HTML, AJAX и изменений данных
 * ------------------------------------------------------------
 */

defined( 'ABSPATH' ) || exit;

/**
 * Получить список бронирований по текущему объекту с фильтрами
 *
 * @param array $args
 * @return array[]
 */
function br_get_bookings( array $args ): array {

    global $wpdb;

    if ( empty( $args['object_id'] ) || (int) $args['object_id'] <= 0 ) {
        return [];
    }

    $table  = $wpdb->prefix . '1br_bookings';
    $where  = [];
    $params = [];

    // OBJECT
    $where[]  = 'object_id = %d';
    $params[] = (int) $args['object_id'];

    // STATUS
    if ( ! empty( $args['status_filter'] ) && $args['status_filter'] !== 'all' ) {

        if ( $args['status_filter'] === 'pending_confirmed' ) {
            $where[] = "status IN ('pending','confirmed')";
        } else {
            $where[]  = 'status = %s';
            $params[] = $args['status_filter'];
        }
    }

    // DATE RANGE
    if ( ! empty( $args['date_from'] ) ) {
        $where[]  = 'event_date >= %s';
        $params[] = $args['date_from'];
    }

    if ( ! empty( $args['date_to'] ) ) {
        $where[]  = 'event_date <= %s';
        $params[] = $args['date_to'];
    }

    // CLIENT SEARCH
    if ( ! empty( $args['client_search'] ) ) {

        $like = '%' . $wpdb->esc_like( $args['client_search'] ) . '%';

        $where[] = '(' .
            'client_name LIKE %s OR ' .
            'client_surname LIKE %s OR ' .
            'client_email LIKE %s OR ' .
            'client_phone LIKE %s' .
        ')';

        $params[] = $like;
        $params[] = $like;
        $params[] = $like;
        $params[] = $like;
    }

    // COMPANY SEARCH
    if ( ! empty( $args['company_value'] ) ) {

        $like = '%' . $wpdb->esc_like( $args['company_value'] ) . '%';

        $where[]  = 'client_company LIKE %s';
        $params[] = $like;
    }

        $where_sql = '';
    if ( ! empty( $where ) ) {
        $where_sql = ' WHERE ' . implode( ' AND ', $where );
    }

    if ( empty( $params ) ) {

        return $wpdb->get_results(
            "
            SELECT
                id,
                object_id,
                created_at,
                client_name,
                client_surname,
                client_company,
                client_phone,
                client_email,
                event_date,
                duration_slots,
                slot_start,
                slot_end,
                interval1_slot_start,
                interval1_slot_end,
                interval1_slots,
                interval2_slot_start,
                interval2_slot_end,
                interval2_slots,
                participants_option_id,
                event_type_id,
                event_type_custom,
                equipment_ids,
                order_comment,
                price_net,
                status
            FROM {$table}
            {$where_sql}
            ORDER BY event_date DESC, created_at DESC
            ",
            ARRAY_A
        );

    }

    return $wpdb->get_results(
        $wpdb->prepare(
            "
            SELECT
                id,
                object_id,
                created_at,
                client_name,
                client_surname,
                client_company,
                client_phone,
                client_email,
                event_date,
                duration_slots,
                slot_start,
                slot_end,
                interval1_slot_start,
                interval1_slot_end,
                interval1_slots,
                interval2_slot_start,
                interval2_slot_end,
                interval2_slots,
                participants_option_id,
                event_type_id,
                event_type_custom,
                equipment_ids,
                order_comment,
                price_net,
                status
            FROM {$table}
            {$where_sql}
            ORDER BY event_date DESC, created_at DESC
            ",
            ...$params
        ),
        ARRAY_A
    );
}

