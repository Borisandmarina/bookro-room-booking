<?php
/**
 * Файл: rules.php
 * Назначение:
 *  - правила пересечений диапазонов slot_start / slot_end
 *  - ТОЛЬКО серверная логика
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Возвращает допустимую таблицу
 */
function br_get_safe_table( $wpdb, $table ) {

    $table = str_replace( $wpdb->prefix, '', $table );

    switch ( $table ) {
        case '1br_breaks':
            return $wpdb->prefix . '1br_breaks';

        case '1br_overrides':
            return $wpdb->prefix . '1br_overrides';

        default:
            return false;
    }
}

/* ============================================================ */
function br_rule_new_inside_old($wpdb, $other_table, $object_id, $date, $slot_start, $slot_end) {

    $table = br_get_safe_table($wpdb, $other_table);
    if (!$table) return;

    $old = $wpdb->get_results(
    $wpdb->prepare(
        "SELECT id, slot_start, slot_end
         FROM {$table}
         WHERE object_id = %d
         AND date_start = %s
         AND date_end = %s",
        $object_id,
        $date,
        $date
    ),
    ARRAY_A
);

    if (empty($old)) return;

    foreach ($old as $r) {

        if ($slot_start > (int)$r['slot_start'] &&
            $slot_end < (int)$r['slot_end']) {

            $wpdb->delete($table, ['id'=>$r['id']], ['%d']);

            $wpdb->insert($table, [
                'object_id'=>$object_id,
                'date_start'=>$date,
                'date_end'=>$date,
                'slot_start'=>$r['slot_start'],
                'slot_end'=>$slot_start
            ], ['%d','%s','%s','%d','%d']);

            $wpdb->insert($table, [
                'object_id'=>$object_id,
                'date_start'=>$date,
                'date_end'=>$date,
                'slot_start'=>$slot_end,
                'slot_end'=>$r['slot_end']
            ], ['%d','%s','%s','%d','%d']);
        }
    }
}

/* ============================================================ */
function br_rule_partial_overlap($wpdb, $other_table, $object_id, $date, $slot_start, $slot_end) {

    $table = br_get_safe_table($wpdb, $other_table);
    if (!$table) return;

    $old = $wpdb->get_results(
    $wpdb->prepare(
        "SELECT id, slot_start, slot_end
         FROM {$table}
         WHERE object_id = %d
         AND date_start = %s
         AND date_end = %s",
        $object_id,
        $date,
        $date
    ),
    ARRAY_A
);

    if (empty($old)) return;

    foreach ($old as $r) {

        $old_start = (int)$r['slot_start'];
        $old_end   = (int)$r['slot_end'];

        if ($slot_end <= $old_start || $slot_start >= $old_end)
            continue;

        if ($slot_start <= $old_start && $slot_end >= $old_end) {
            $wpdb->delete($table, ['id'=>$r['id']], ['%d']);
            continue;
        }

        if ($slot_start <= $old_start && $slot_end < $old_end) {

            $wpdb->delete($table, ['id'=>$r['id']], ['%d']);

            $wpdb->insert($table, [
                'object_id'=>$object_id,
                'date_start'=>$date,
                'date_end'=>$date,
                'slot_start'=>$slot_end,
                'slot_end'=>$old_end
            ], ['%d','%s','%s','%d','%d']);
        }

        if ($slot_start > $old_start && $slot_start < $old_end && $slot_end >= $old_end) {

            $wpdb->delete($table, ['id'=>$r['id']], ['%d']);

            $wpdb->insert($table, [
                'object_id'=>$object_id,
                'date_start'=>$date,
                'date_end'=>$date,
                'slot_start'=>$old_start,
                'slot_end'=>$slot_start
            ], ['%d','%s','%s','%d','%d']);
        }
    }
}

/* ============================================================ */
function br_rule_full_cover($wpdb, $other_table, $object_id, $date, $slot_start, $slot_end) {

    $table = br_get_safe_table($wpdb, $other_table);
    if (!$table) return;

    $old = $wpdb->get_results(
    $wpdb->prepare(
        "SELECT id, slot_start, slot_end
         FROM {$table}
         WHERE object_id = %d
         AND date_start = %s
         AND date_end = %s",
        $object_id,
        $date,
        $date
    ),
    ARRAY_A
);

    if (empty($old)) return;

    foreach ($old as $r) {

        if ($slot_start <= (int)$r['slot_start'] &&
            $slot_end >= (int)$r['slot_end']) {

            $wpdb->delete($table, ['id'=>$r['id']], ['%d']);
        }
    }
}

/* ============================================================ */
function br_rule_same_type(
    $wpdb,
    $table_name,
    $object_id,
    $date,
    &$slot_start,
    &$slot_end
) {

    $table = br_get_safe_table($wpdb, $table_name);
    if (!$table) return false;

    $old = $wpdb->get_results(
    $wpdb->prepare(
        "SELECT id, slot_start, slot_end
         FROM {$table}
         WHERE object_id = %d
         AND date_start = %s
         AND date_end = %s",
        $object_id,
        $date,
        $date
    ),
    ARRAY_A
);

    if (empty($rows)) return true;

    foreach ($rows as $r) {

        $old_start = (int)$r['slot_start'];
        $old_end   = (int)$r['slot_end'];

        if ($slot_end <= $old_start || $slot_start >= $old_end)
            continue;

        if ($slot_start === $old_start && $slot_end === $old_end)
            return false;

        if ($slot_start >= $old_start && $slot_end <= $old_end)
            return false;

        $slot_start = min($slot_start, $old_start);
        $slot_end   = max($slot_end, $old_end);

        $wpdb->delete($table, ['id'=>$r['id']], ['%d']);
    }

    return true;
}

/* ============================================================ */
function br_rule_normalize_same_type($wpdb, $table_name, $object_id, $date) {

    $table = br_get_safe_table($wpdb, $table_name);
    if (!$table) return;

    $rows = $wpdb->get_results(
        $wpdb->prepare(
            "SELECT id, slot_start, slot_end
             FROM {$table}
             WHERE object_id = %d
             AND date_start = %s
             AND date_end = %s
             ORDER BY slot_start ASC",
            $object_id,
            $date,
            $date
        ),
        ARRAY_A
    );

    if (!is_array($rows) || count($rows) < 2) return;

    $current = array_shift($rows);

    foreach ($rows as $r) {

        if ($r['slot_start'] <= $current['slot_end']) {

            $current['slot_end'] =
                max($current['slot_end'], $r['slot_end']);

            $wpdb->delete($table, ['id'=>$r['id']], ['%d']);

            $wpdb->update(
                $table,
                ['slot_end'=>$current['slot_end']],
                ['id'=>$current['id']],
                ['%d'],
                ['%d']
            );

        } else {
            $current = $r;
        }
    }
}

