<?php

/**
 * ------------------------------------------------------------
 * Файл: wp-content/plugins/booking-room/admin-global-settings.php
 * Назначение:
 *  - AJAX-обработчики глобальных настроек
 *  - чтение и сохранение wp_1br_global_settings
 * ------------------------------------------------------------
 */

if ( ! defined('ABSPATH') ) {
    exit;
}

/* ============================================================
   Получение глобальных настроек
   ============================================================ */
add_action('wp_ajax_br_get_global_settings', 'br_get_global_settings');

function br_get_global_settings() {
    br_admin_ajax_guard();
    global $wpdb;

    if ( ! isset( $_POST['object_id'] ) ) {
        wp_send_json_error( 'Missing object_id' );
    }

    $object_id = absint( wp_unslash( $_POST['object_id'] ) );

    // гарантируем существование строки глобальных настроек
    $exists = $wpdb->get_var(
        $wpdb->prepare(
            "SELECT id FROM {$wpdb->prefix}1br_global_settings WHERE object_id = %d",
            $object_id
        )
    );

    if ( ! $exists ) {
        $wpdb->insert(
            $wpdb->prefix . '1br_global_settings',
            [
                'object_id'        => $object_id,
                'work_start_slot'  => 8,
                'day_split_slot'   => 17,
                'work_end_slot'    => 22,
                'timezone'         => 'UTC',
                'weekends'         => '0'
            ],
            ['%d','%d','%d','%d','%s','%s']
        );
    }

    $row = $wpdb->get_row(
        $wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}1br_global_settings WHERE object_id = %d LIMIT 1",
            $object_id
        ),
        ARRAY_A
    );

    if ( ! $row ) {
        wp_send_json_error('Settings not found');
    }

    $row['weekends'] = $row['weekends']
        ? explode(',', $row['weekends'])
        : [];

    wp_send_json_success($row);
}

/* ============================================================
   Сохранение одного параметра
   ============================================================ */
add_action('wp_ajax_br_save_global_setting', 'br_save_global_setting');

function br_save_global_setting() {
    br_admin_ajax_guard();
    global $wpdb;

    if ( ! isset( $_POST['object_id'], $_POST['field'], $_POST['value'] ) ) {
        wp_send_json_error( 'Missing params' );
    }

    $object_id = absint( wp_unslash( $_POST['object_id'] ) );
    $field     = sanitize_text_field( wp_unslash( $_POST['field'] ) );
    $value     = sanitize_text_field( wp_unslash( $_POST['value'] ) );


    /* ============================================================
       Гарантируем существование строки настроек
       ============================================================ */
    $exists = $wpdb->get_var(
        $wpdb->prepare(
            "SELECT id FROM {$wpdb->prefix}1br_global_settings WHERE object_id = %d",
            $object_id
        )
    );

    if ( ! $exists ) {
                $wpdb->insert(
            $wpdb->prefix . '1br_global_settings',
            [
                'object_id'        => $object_id,
                'work_start_slot'  => 8,
                'work_end_slot'    => 22,
                'timezone'         => 'UTC',
                'weekends'         => '0'
            ],
            ['%d','%d','%d','%s','%s']
        );
    }

    /* ============================================================
       WEEKENDS (add / remove)
       ============================================================ */
    if ($field === 'weekends_add' || $field === 'weekends_remove') {

        $allowed_days = [
            'Monday','Tuesday','Wednesday',
            'Thursday','Friday','Saturday','Sunday'
        ];

        $current = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT weekends FROM {$wpdb->prefix}1br_global_settings WHERE object_id = %d",
                $object_id
            )
        );

        if ($current === '0' || $current === null || $current === '') {
            $days = [];
        } else {
            $days = array_map('trim', explode(',', $current));
        }

        // ADD
        if ($field === 'weekends_add') {

            if ($value === '0') {
                $wpdb->update(
                    $wpdb->prefix . '1br_global_settings',
                    ['weekends' => '0'],
                    ['object_id' => $object_id],
                    ['%s'],
                    ['%d']
                );
                wp_send_json_success();
            }

            if (in_array($value, $allowed_days, true) && !in_array($value, $days, true)) {
                $days[] = $value;
            }
        }

        // REMOVE
        if ($field === 'weekends_remove') {
            $days = array_values(array_diff($days, [$value]));
        }

        $new_value = empty($days) ? '0' : implode(',', $days);

        $wpdb->update(
            $wpdb->prefix . '1br_global_settings',
            ['weekends' => $new_value],
            ['object_id' => $object_id],
            ['%s'],
            ['%d']
        );

        wp_send_json_success();
    }

    /* ============================================================
       ОДИНОЧНЫЕ ПОЛЯ (Lite allowed)
       ============================================================ */
    $allowed_fields = [
        'work_start_slot',
        'work_end_slot',
        'timezone'
    ];

    if ( ! in_array($field, $allowed_fields, true) ) {
        wp_send_json_error('Invalid field');
    }

    if (in_array($field, ['work_start_slot','work_end_slot'], true)) {
        $value = (int) $value;
    }

    if ($field === 'work_start_slot' && ($value < 1 || $value > 22)) {
        wp_send_json_error('Invalid work_start_slot');
    }

    if ($field === 'work_end_slot' && ($value < 2 || $value > 23)) {
        wp_send_json_error('Invalid work_end_slot');
    }

    $result = $wpdb->update(
        $wpdb->prefix . '1br_global_settings',
        [$field => $value],
        ['object_id' => $object_id],
        ['%s'],
        ['%d']
    );

    if ($result === false) {
        wp_send_json_error($wpdb->last_error);
    }

    wp_send_json_success();
}
