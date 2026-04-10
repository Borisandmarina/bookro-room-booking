<?php
/**
 * ------------------------------------------------------------
 * Файл: wp-content/plugins/booking-room/admin-block-visitors_count.php
 * Назначение:
 *  - Visitors count coefficient (Lite mode)
 *  - отображение настроек без возможности редактирования
 *  - таблица и вся логика отключены
 * ------------------------------------------------------------
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}
?>

<div class="br-admin-block-header">
    <h3>
        Visitors count coefficient. Multiplies the base rental rate
        depending on the number of participants
    </h3>
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