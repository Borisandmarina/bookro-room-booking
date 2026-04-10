<?php
/**
 * ------------------------------------------------------------
 * Файл: wp-content/plugins/booking-room/admin-block-weekend-coeff.php
 * Назначение:
 *  - Base rental rate — weekend (Lite mode)
 *  - отображение настроек без возможности редактирования
 * ------------------------------------------------------------
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

global $wpdb;

$row = $wpdb->get_row(
    $wpdb->prepare(
        "
        SELECT id, label, coefficient
        FROM {$wpdb->prefix}1br_day_weekend_coeff
        WHERE object_id = %d
        LIMIT 1
        ",
        (int) $current_object_id
    ),
    ARRAY_A
);
?>

<div class="br-admin-block-header">
    <h3>Base rental rate — weekend</h3>
</div>
<!-- Pro notice -->
    <div class="br-admin-block-body" style="padding-top:0;">
        <p style="margin:4px 0 12px; font-size:13px; color:#d63638;">
    This feature is available in an
    <a href="https://checkout.freemius.com/plugin/24330/plan/40410/?utm_source=lite&utm_medium=plugin&utm_campaign=upgrade"
       target="_blank"
       rel="noopener noreferrer"
       style="color:#d63638; font-weight:600;">
        extended version.
    </a>
</p>
    </div>
