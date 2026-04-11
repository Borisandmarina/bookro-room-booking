<?php
/**
 * ------------------------------------------------------------
 * Файл: wp-content/plugins/booking-room/admin-settings.php
 * Назначение:
 *  - главная страница настроек плагина Booking Room
 *  - переключение контекста между объектами
 * ------------------------------------------------------------
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

global $wpdb;
$current_user_id = get_current_user_id();

/**
 * ------------------------------------------------------------
 * Получение списка объектов
 * ------------------------------------------------------------
 */
$table_objects = $wpdb->prefix . '1br_objects';

$objects = $wpdb->get_results(
    "SELECT id, name, status FROM {$table_objects} ORDER BY id ASC",
    ARRAY_A
);

if ( empty( $objects ) ) {
    echo '<div class="wrap"><h1>Booking Room</h1><p>No objects found.</p></div>';
    return;
}

/**
 * ------------------------------------------------------------
 * Определение текущего объекта
 * ------------------------------------------------------------
 * Рабочая версия (БЕЗ защиты, БЕЗ редиректов)
 */
$saved_object_id = (int) get_user_meta( $current_user_id, 'br_current_object_id', true );

$current_object_id = isset( $_GET['object_id'] ) && (int) $_GET['object_id'] > 0
    ? (int) $_GET['object_id']
    : ( $saved_object_id > 0 ? $saved_object_id : (int) $objects[0]['id'] );

update_user_meta(
    $current_user_id,
    'br_current_object_id',
    $current_object_id
);





/**
 * ------------------------------------------------------------
 * Получение данных текущего объекта
 * ------------------------------------------------------------
 */
$current_object = $wpdb->get_row(
    $wpdb->prepare(
        "
        SELECT id, name, status, timezone, address, email, phone
        FROM {$table_objects}
        WHERE id = %d
        LIMIT 1
        ",
        $current_object_id
    ),
    ARRAY_A
);


?>

<div class="wrap">
    <h1 style="display:flex; align-items:center; gap:16px;">
        <span>Settings</span>

        <!-- Выбор объекта -->
        <form method="get" action="" style="margin:0;">
    <input type="hidden" name="page" value="booking-room">
    <input type="hidden" name="tab" value="<?php echo isset($_GET['tab']) ? esc_attr($_GET['tab']) : 'settings'; ?>">
    <select name="object_id" onchange="this.form.submit()">

                <?php foreach ( $objects as $obj ): ?>
                    <option value="<?php echo (int) $obj['id']; ?>"
                        <?php selected( $obj['id'], $current_object_id ); ?>>
                        <?php
                            echo esc_html( $obj['name'] );
							echo ' [' . esc_html( strtoupper( (string) $obj['status'] ) ) . ']';

                        ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </form>
		<div id="br-object-rename" style="display:flex; gap:6px; align-items:center;">

    <button
        type="button"
        class="button"
        id="br-rename-btn"
        title="Edit object details"
    >✎ Edit object details</button>

    <input
        type="text"
        id="br-object-name-input"
        value="<?php echo esc_attr( $current_object['name'] ); ?>"
        style="display:none; min-width:220px;"
    >

    <button
        type="button"
        class="button button-primary"
        id="br-rename-save"
        style="display:none;"
    >Save</button>

    <button
        type="button"
        class="button"
        id="br-rename-cancel"
        style="display:none;"
    >Cancel</button>

</div>



		<!-- Управление объектами (заглушки, привязаны к текущему объекту) -->
<div style="display:flex; gap:8px; margin-left:16px;">

</div>
</div>


    </h1>

<?php
    /* ============================================================
     * Schedule & tools
     * ============================================================ */
    require BR_PLUGIN_PATH . 'admin-block-schedule.php';

?>

<?php
    /* ============================================================
     * Other blocks
     * ============================================================ */
    require BR_PLUGIN_PATH . 'admin-block-event_types.php';
    require BR_PLUGIN_PATH . 'admin-block-admin_contacts.php';
    require BR_PLUGIN_PATH . 'admin-block-global.php';
?>


    <!-- ============================================================
         Booking form shortcode
         ============================================================ -->
    <div class="br-admin-block">

        <div class="br-admin-block-header">
            <h2>Booking Form Shortcode</h2>
        </div>

        <div class="br-admin-block-body">

            <p><strong>Shortcode:</strong></p>

            <div style="
                background:#fff;
                border:1px solid #ccd0d4;
                padding:10px 12px;
                font-family: monospace;
                font-size:14px;
                margin-bottom:12px;
                display:inline-block;
            ">
                [booking_room_form]
            </div>

            <p>
                Insert this shortcode into any page or post to display the booking form.
                <br>
                The form works independently of the active theme and automatically uses
                the schedule, availability rules, and pricing settings configured in this plugin.
            </p>

        </div>

    </div>
<!-- Object edit popup -->
<div id="br-object-popup">
    <div class="br-popup-overlay"></div>

    <div class="br-popup-window">

        <h2 class="br-popup-title">Edit object details</h2>

        <div class="br-popup-field">
            <label for="br-popup-name">Name object</label>
            <input type="text" id="br-popup-name">
        </div>

        <div class="br-popup-field">
            <label for="br-popup-address">Address object</label>
            <textarea id="br-popup-address" rows="4"></textarea>
        </div>

        <div class="br-popup-field">
            <label for="br-popup-email">Email object</label>
            <input type="email" id="br-popup-email">
        </div>

        <div class="br-popup-field">
            <label for="br-popup-phone">Phone object</label>
            <input type="text" id="br-popup-phone">
        </div>

        <div class="br-popup-actions">
            <button type="button" class="button" id="br-popup-cancel">Cancel</button>
            <button type="button" class="button button-primary" id="br-popup-save">Save</button>
        </div>

    </div>
</div>
<style>

#br-delete-popup {
    position: fixed;
    inset: 0;
    display: none;
    z-index: 999999;
}

#br-delete-popup .br-popup-overlay {
    position: fixed;
    inset: 0;
    background: rgba(0,0,0,0.45);
}

#br-delete-popup .br-popup-window {
    position: fixed;
    top: 50%;
    left: 50%;
    transform: translate(-50%, -50%);
    background: #fff;

    width: 420px;
    max-width: calc(100% - 32px);

    padding: 20px 20px 16px;
    border-radius: 6px;

    box-shadow: 0 10px 30px rgba(0,0,0,0.25);
    box-sizing: border-box;
}

#br-delete-popup .br-popup-title {
    margin: 0 0 12px;
    font-size: 18px;
}

#br-delete-popup p {
    margin: 0 0 16px;
    line-height: 1.4;
}

#br-delete-popup .br-popup-actions {
    display: flex;
    justify-content: flex-end;
    gap: 8px;
}


.br-admin-block {
    border: 2px solid #2271b1;
}

/* ===== Object edit popup ===== */

#br-object-popup {
    position: fixed;
    inset: 0;
    display: none;
    z-index: 999999;
}

#br-object-popup .br-popup-overlay {
    position: fixed;
    inset: 0;
    background: rgba(0,0,0,0.35);
}

#br-object-popup .br-popup-window {
    position: fixed;
    top: 50%;
    left: 50%;
    transform: translate(-50%, -50%);
    background: #fff;
    width: 420px;
    max-width: calc(100% - 32px);
    padding: 20px 20px 16px;
    border-radius: 6px;
    box-shadow: 0 10px 30px rgba(0,0,0,0.25);
    box-sizing: border-box;
}

/* Title */
.br-popup-title {
    margin: 0 0 16px;
    font-size: 18px;
}

/* Fields */
.br-popup-field {
    display: flex;
    flex-direction: column;
    gap: 4px;
    margin-bottom: 12px;
}

.br-popup-field label {
    font-weight: 600;
}

.br-popup-field input,
.br-popup-field textarea {
    width: 100%;
    box-sizing: border-box;
}

/* Actions */
.br-popup-actions {
    display: flex;
    justify-content: flex-end;
    gap: 8px;
    margin-top: 16px;
}

</style>

<script>
(function () {

    const renameBtn = document.getElementById('br-rename-btn');
    const popup     = document.getElementById('br-object-popup');

    const nameInput    = document.getElementById('br-popup-name');
    const addressInput = document.getElementById('br-popup-address');
    const emailInput   = document.getElementById('br-popup-email');
    const phoneInput   = document.getElementById('br-popup-phone');

    const btnSave   = document.getElementById('br-popup-save');
    const btnCancel = document.getElementById('br-popup-cancel');

    const objectId = <?php echo (int) $current_object['id']; ?>;

    /* === ДАННЫЕ ОБЪЕКТА ИЗ PHP (БЕЗ AJAX) === */
    const objectData = {
        name:    <?php echo json_encode($current_object['name']); ?>,
        address: <?php echo json_encode($current_object['address']); ?>,
        email:   <?php echo json_encode($current_object['email']); ?>,
        phone:   <?php echo json_encode($current_object['phone']); ?>
    };

    // ✎ Открыть popup (мгновенно)
    renameBtn.addEventListener('click', function () {

        nameInput.value    = objectData.name    || '';
        addressInput.value = objectData.address || '';
        emailInput.value   = objectData.email   || '';
        phoneInput.value   = objectData.phone   || '';

        popup.style.display = 'block';
    });

    // Cancel
    btnCancel.addEventListener('click', function () {
        popup.style.display = 'none';
    });

    // Save (AJAX)
    btnSave.addEventListener('click', function () {

        fetch(ajaxurl, {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: new URLSearchParams({
                action: 'br_update_object',
                object_id: objectId,
                name: nameInput.value,
                address: addressInput.value,
                email: emailInput.value,
                phone: phoneInput.value
            })
        })
        .then(r => r.json())
        .then(resp => {
            if (!resp || !resp.success) {
                alert('Save failed');
                return;
            }

            // обновляем локальные данные
            objectData.name    = nameInput.value;
            objectData.address = addressInput.value;
            objectData.email   = emailInput.value;
            objectData.phone   = phoneInput.value;

            // обновляем select
            const option = document.querySelector(
                'select[name="object_id"] option[value="' + objectId + '"]'
            );

            if (option) {
                option.textContent =
                    nameInput.value +
                    ' [<?php echo esc_html( strtoupper( $current_object['status'] ) ); ?>]';

            }

            popup.style.display = 'none';
        });
    });

})();
</script>

<script>
document.addEventListener('DOMContentLoaded', function () {

    const params = new URLSearchParams(window.location.search);

    if (params.get('br_open_object_popup') !== '1') {
        return;
    }

    const popup    = document.getElementById('br-object-popup');
    const renameBtn = document.getElementById('br-rename-btn');

    if (!popup || !renameBtn) {
        return;
    }

    // имитируем обычный клик по кнопке редактирования
    renameBtn.click();

    // чистим URL, чтобы popup не открывался повторно
    params.delete('br_open_object_popup');

    const newUrl =
        window.location.pathname +
        (params.toString() ? '?' + params.toString() : '');

    window.history.replaceState({}, '', newUrl);

});
</script>











