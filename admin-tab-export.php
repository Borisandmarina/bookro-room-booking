<?php
/**
 * ------------------------------------------------------------
 * Файл: wp-content/plugins/booking-room/admin-tab-export.php
 *
 * Назначение:
 *  - Вкладка «Export» в админке плагина Booking Room
 *  - Блоки настроек экспорта:
 *      • Google Sheets
 *      • Telegram
 *      • Email
 * ------------------------------------------------------------
 */

defined('ABSPATH') || exit;
global $wpdb;

$current_user_id = get_current_user_id();
wp_add_inline_style(
    'br-admin',
    '
    .br-export-wrapper {
        max-width: 1100px;
    }

    .br-export-block {
        background: #ffffff;
        border: 1px solid #ccd0d4;
        padding: 20px 24px;
        margin-bottom: 20px;
    }

    .br-export-block h3 {
        margin-top: 0;
        margin-bottom: 12px;
        font-size: 18px;
        color: #2271b1;
    }

    .br-export-block p {
        margin: 0;
        color: #555;
        font-size: 14px;
    }

    .form-table td {
        padding: 5px 5px;
    }
    '
);

?>

<?php
/* сохранить выбранный объект */
if ( isset($_GET['object_id']) && (int) $_GET['object_id'] > 0 ) {
    update_user_meta(
        get_current_user_id(),
        'br_current_object_id',
        (int) $_GET['object_id']
    );
}

/* получить список объектов */
$objects = $wpdb->get_results(
    "SELECT id, name, status FROM {$wpdb->prefix}1br_objects ORDER BY id ASC",
    ARRAY_A
);


/* определить текущий объект */
$saved_object_id = (int) get_user_meta(
    get_current_user_id(),
    'br_current_object_id',
    true
);

$current_object_id = isset($_GET['object_id']) && (int) $_GET['object_id'] > 0
    ? (int) $_GET['object_id']
    : ( $saved_object_id > 0 ? $saved_object_id : ( isset($objects[0]['id']) ? (int)$objects[0]['id'] : 0 ) );

?>

<div class="wrap br-export-wrapper">

    <h1 style="display:flex; align-items:center; gap:16px;">
        <span>Export</span>

        <!-- Выбор объекта -->
        <form method="get" action="" style="margin:0;">
            <input type="hidden" name="page" value="booking-room-export">
            <select name="object_id" onchange="this.form.submit()">

                <?php foreach ( $objects as $obj ): ?>
                    <option value="<?php echo (int) $obj['id']; ?>"
                        <?php selected( $obj['id'], $current_object_id ); ?>>
                        <?php
                            echo esc_html( $obj['name'] );
                            echo ' [' . esc_html( strtoupper( $obj['status'] ) ) . ']';
                        ?>
                    </option>
                <?php endforeach; ?>

            </select>
        </form>
    </h1>

<!-- =====================================================
     EMAIL EXPORT — SETTINGS (UI ONLY)
     ===================================================== -->

<?php require BR_PLUGIN_PATH . 'admin-block-export-mail.php'; ?>

</div>