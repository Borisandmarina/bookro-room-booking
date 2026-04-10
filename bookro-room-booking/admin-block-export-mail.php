<?php
/**
 * ------------------------------------------------------------
 * Файл: wp-content/plugins/booking-room/admin-block-export-mail.php
 *
 * Назначение:
 *  - UI блока "Export via Email"
 *  - Управление администраторами по объектам
 *  - Версия: HTML-каркас (без логики и данных)
 * ------------------------------------------------------------
 */

defined('ABSPATH') || exit;
$client_emails_disabled = (int) get_option('br_disable_client_emails', 0);
?>

<div class="br-export-block">

    <h3>Export via Email</h3>

    <p class="description" style="max-width:760px;">
        Manage administrators and their email notifications per object.
        Administrators can be assigned to individual objects or to all objects.
    </p>

    <hr style="margin:20px 0;">
	<!-- =================================================
     Чекбокс отключения отправки почты клиентам
     ================================================= -->
	 <div style="margin-top:16px; margin-bottom:16px;">

    <label style="display:flex; align-items:center; gap:8px; font-weight:600;">
        <?php
$client_emails_disabled = (int) get_option('br_disable_client_emails', 0);
?>

<input
    type="checkbox"
    id="br-disable-client-emails"
    <?php checked( $client_emails_disabled === 0 ); ?>
>


        Email notifications to clients are enabled.
    </label>

    <p class="description" style="margin-left:26px; max-width:720px;">
        When disabled, emails will not be delivered to clients!
    </p>

</div>

<hr style="margin:20px 0;">

   <!-- =================================================
     ADD ADMIN TO OBJECT (PARTIAL ACCESS)
     ================================================= -->
<h4>Add administrator to object</h4>

<div style="display:flex; gap:10px; align-items:center; margin-bottom:16px;">

    <!-- OBJECT SELECT -->
    <select id="br-select-object">
        <option value="">Select object…</option>

        <?php foreach ($objects as $object): ?>
            <option value="<?php echo (int) $object['id']; ?>">
                <?php echo esc_html($object['name']); ?>
            </option>
        <?php endforeach; ?>
    </select>

    <!-- ADMIN SELECT (FILLED VIA AJAX AFTER OBJECT SELECTED) -->
    <select id="br-select-admin-partial" disabled>
        <option value="">Select administrator…</option>
    </select>

    <!-- ADD BUTTON -->
    <button
        type="button"
        class="button button-primary"
        id="br-add-admin-partial"
        disabled
    >
        Add
    </button>

</div>


    <!-- =================================================
         PARTIAL ACCESS TABLE
         ================================================= -->
    <h4>Administrators with partial access</h4>

    <table class="widefat striped" id="br-partial-access-table">

        <thead>
            <tr>
                <th>Object</th>
                <th>Last name</th>
                <th>First name</th>
                <th>Position</th>
                <th>Phone</th>
                <th>Email</th>
                <th style="width:90px; text-align:center;">Actions</th>
            </tr>
        </thead>

        <tbody>
            <tr>
                <td colspan="7" style="text-align:center; color:#777;">
                    No administrators assigned to individual objects
                </td>
            </tr>
        </tbody>

    </table>

    <hr style="margin:28px 0;">

<style>
/* sortable headers */
#br-partial-access-table thead th {
    cursor: default;
}

#br-partial-access-table thead th.sortable {
    cursor: pointer;
    user-select: none;
}

#br-partial-access-table thead th.sortable::after {
    content: '';
    display: inline-block;
    margin-left: 6px;
    font-size: 11px;
    opacity: 0.4;
}

#br-partial-access-table thead th.sort-asc::after {
    content: '▲';
    opacity: 0.8;
}

#br-partial-access-table thead th.sort-desc::after {
    content: '▼';
    opacity: 0.8;
}
</style>
<script>
    var ajaxurl = "<?php echo esc_url( admin_url('admin-ajax.php') ); ?>";

</script>

<script>
jQuery(function ($) {

/* =====================================================================
   ОБЩЕЕ СОСТОЯНИЕ СОРТИРОВКИ
   Используется ТОЛЬКО для таблицы Partial Access
   ===================================================================== */
let brPartialSort = {
    column: 'object', // object | last_name
    dir: 'asc'        // asc | desc
};

/* =====================================================================
   AJAX-ПЕРЕЗАГРУЗКА ТАБЛИЦЫ PARTIAL ACCESS
   Единственное место, где обновляется tbody таблицы
   ===================================================================== */
function brReloadPartialAccessTable() {

    const tbody = $('#br-partial-access-table tbody');

    tbody.html(
        '<tr><td colspan="7" style="text-align:center;">Loading…</td></tr>'
    );

    $.post(ajaxurl, {
        action: 'br_get_partial_access_table',
        sort: brPartialSort.column,
        dir:  brPartialSort.dir
    }, function (res) {

        if (!res || !res.success) {
            tbody.html(
                '<tr><td colspan="7" style="text-align:center;color:red;">Error loading data</td></tr>'
            );
            return;
        }

        tbody.html(res.data.html);
    });
}
/* =====================================================================
   AJAX-ПЕРЕЗАГРУЗКА ТАБЛИЦЫ FULL ACCESS
   Единственное место, где обновляется tbody второй таблицы
   ===================================================================== */
function brReloadFullAccessTable() {

    const tbody = $('#br-full-access-table tbody');

    tbody.html(
        '<tr><td colspan="6" style="text-align:center;">Loading…</td></tr>'
    );

    $.post(ajaxurl, {
        action: 'br_get_full_access_table'
    }, function (res) {

        if (!res || !res.success) {
            tbody.html(
                '<tr><td colspan="6" style="text-align:center;color:red;">Error loading data</td></tr>'
            );
            return;
        }

        tbody.html(res.data.html);
    });
}

/* =====================================================================
   НАЧАЛЬНАЯ ЗАГРУЗКА ТАБЛИЦЫ (ПРИ ОТКРЫТИИ СТРАНИЦЫ)
   ===================================================================== */
brReloadPartialAccessTable();
brReloadFullAccessTable();
	/* =====================================================================
   FULL ACCESS: ЗАГРУЗКА АДМИНИСТРАТОРОВ В SELECT
   (Add administrator to all objects)
   ===================================================================== */
function brLoadFullAccessAdminsSelect() {

    const select = $('#br-select-admin-global');

    select
        .prop('disabled', true)
        .html('<option value="">Select administrator…</option>');

    $.post(ajaxurl, {
        action: 'br_get_admins_for_full_access'
    }, function (res) {

        if (!res || !res.success || !res.data.length) {
            return;
        }

        $.each(res.data, function (_, admin) {
            select.append(
                $('<option>', {
                    value: admin.email,
                    text: admin.last_name + ' ' + admin.first_name
                })
            );
        });

        select.prop('disabled', false);
    });
}

// initial load for full access select
brLoadFullAccessAdminsSelect();
	/* =====================================================================
   FULL ACCESS: ADD ADMIN TO ALL OBJECTS
   ===================================================================== */
$('#br-add-admin-global').on('click', function () {

    const select = $('#br-select-admin-global');
    const email  = select.val();
    const button = $(this);

    if (!email) {
        return;
    }

    button.prop('disabled', true);

    $.post(ajaxurl, {
        action: 'br_add_admin_to_all_objects',
        email: email
    }, function (res) {

        if (!res || !res.success) {
            alert(res?.data || 'Error adding administrator');
            button.prop('disabled', false);
            return;
        }

        // обновляем обе таблицы
        brReloadPartialAccessTable();
        brReloadFullAccessTable();

        // обновляем селекты
        brLoadFullAccessAdminsSelect();
        $('#br-select-object').trigger('change');

        button.prop('disabled', false);
    });
});


/* =====================================================================
   СЕЛЕКТ ОБЪЕКТА → ЗАГРУЗКА ДОСТУПНЫХ АДМИНИСТРАТОРОВ
   ===================================================================== */
$('#br-select-object').on('change', function () {

    const objectId    = $(this).val();
    const adminSelect = $('#br-select-admin-partial');
    const addBtn      = $('#br-add-admin-partial');

    adminSelect
        .prop('disabled', true)
        .html('<option value="">Select administrator…</option>');

    addBtn.prop('disabled', true);

    if (!objectId) {
        return;
    }

    $.post(ajaxurl, {
        action: 'br_get_admins_for_object',
        object_id: objectId
    }, function (res) {

        if (!res || !res.success) {
            return;
        }

        if (!res.data || !res.data.length) {
            adminSelect.append(
                '<option value="">No available administrators</option>'
            );
            return;
        }

        $.each(res.data, function (_, admin) {
            adminSelect.append(
                $('<option>', {
                    value: admin.email,
                    text: admin.last_name + ' ' + admin.first_name
                })
            );
        });

        adminSelect.prop('disabled', false);
    });
});

/* =====================================================================
   АКТИВАЦИЯ КНОПКИ ADD ПРИ ВЫБОРЕ АДМИНИСТРАТОРА
   ===================================================================== */
$('#br-select-admin-partial').on('change', function () {
    $('#br-add-admin-partial').prop('disabled', !$(this).val());
});

/* =====================================================================
   ADD → ПРИВЯЗКА АДМИНИСТРАТОРА К ОБЪЕКТУ (PARTIAL ACCESS)
   ===================================================================== */
$('#br-add-admin-partial').on('click', function () {

    const objectId = $('#br-select-object').val();
    const email    = $('#br-select-admin-partial').val();
    const button  = $(this);

    if (!objectId || !email) {
        return;
    }

    button.prop('disabled', true);

    $.post(ajaxurl, {
        action: 'br_add_admin_to_object_partial',
        object_id: objectId,
        email: email
    }, function (res) {

        if (!res || !res.success) {
            alert(res?.data || 'Error adding administrator');
            button.prop('disabled', false);
            return;
        }

        $('#br-select-admin-partial')
            .html('<option value="">Select administrator…</option>')
            .prop('disabled', true);

        $('#br-add-admin-partial').prop('disabled', true);

        $('#br-select-object').trigger('change');

        brReloadPartialAccessTable();
    });
});
/* =====================================================================
   DELETE → УДАЛЕНИЕ ПРИВЯЗКИ (PARTIAL ACCESS)
   ===================================================================== */
$('#br-partial-access-table').on('click', '.br-delete-partial', function () {

    const button   = $(this);
    const email    = button.data('email');
    const objectId = button.data('object-id');

    if (!email || !objectId) {
        return;
    }


    button.prop('disabled', true);

    $.post(ajaxurl, {
        action: 'br_delete_admin_partial',
        email: email,
        object_id: objectId
    }, function (res) {

        if (!res || !res.success) {
            alert(res?.data || 'Error deleting administrator');
            button.prop('disabled', false);
            return;
        }

        // обновляем таблицы
        brReloadPartialAccessTable();
        brReloadFullAccessTable();

        // обновляем селекты
        $('#br-select-object').trigger('change');
        brLoadFullAccessAdminsSelect();
    });
});

/* =====================================================================
   СОРТИРОВКА: ВИЗУАЛЬНАЯ ПОДГОТОВКА ЗАГОЛОВКОВ
   ===================================================================== */
$('#br-partial-access-table thead th').each(function () {
    const text = $(this).text().trim();
    if (text === 'Object' || text === 'Last name') {
        $(this).addClass('sortable');
    }
});
/* =====================================================================
   СОРТИРОВКА: НАЧАЛЬНАЯ ИНДИКАЦИЯ (ПРИ ЗАГРУЗКЕ)
   Показываем стрелки у ВСЕХ сортируемых столбцов
   ===================================================================== */
(function initPartialSortIndicator() {

    const map = {
        object: 'Object',
        last_name: 'Last name'
    };

    // сначала ставим "пустые" стрелки всем сортируемым столбцам
    $('#br-partial-access-table thead th.sortable').each(function () {
        $(this).addClass('sort-asc');
    });

    // затем выделяем активный столбец и направление
    const activeTitle = map[brPartialSort.column];

    $('#br-partial-access-table thead th.sortable').each(function () {
        if ($(this).text().trim() === activeTitle) {
            $(this)
                .removeClass('sort-asc sort-desc')
                .addClass(
                    brPartialSort.dir === 'asc' ? 'sort-asc' : 'sort-desc'
                );
        }
    });

})();


/* =====================================================================
   СОРТИРОВКА: КЛИК ПО ЗАГОЛОВКУ ТАБЛИЦЫ
   ===================================================================== */
$('#br-partial-access-table thead').on('click', 'th.sortable', function () {

    const title = $(this).text().trim();

    const map = {
        'Object': 'object',
        'Last name': 'last_name'
    };

    const column = map[title];

    if (brPartialSort.column === column) {
        brPartialSort.dir = (brPartialSort.dir === 'asc') ? 'desc' : 'asc';
    } else {
        brPartialSort.column = column;
        brPartialSort.dir = 'asc';
    }

    // сбрасываем стрелки ТОЛЬКО у сортируемых столбцов
    $('#br-partial-access-table thead th.sortable')
        .removeClass('sort-asc sort-desc')
        .addClass('sort-asc'); // приглушённая стрелка по умолчанию

    // активному столбцу ставим реальное направление
    $(this)
        .removeClass('sort-asc sort-desc')
        .addClass(
            brPartialSort.dir === 'asc' ? 'sort-asc' : 'sort-desc'
        );

    brReloadPartialAccessTable();
});

/* =====================================================================
   DELETE → УДАЛЕНИЕ АДМИНИСТРАТОРА (FULL ACCESS)
   ===================================================================== */
$('#br-full-access-table').on('click', '.br-delete-full', function () {

    const button = $(this);
    const email  = button.data('email');

    if (!email) {
        return;
    }


    button.prop('disabled', true);

    $.post(ajaxurl, {
        action: 'br_delete_admin_full',
        email: email
    }, function (res) {

        if (!res || !res.success) {
            alert(res?.data || 'Error deleting administrator');
            button.prop('disabled', false);
            return;
        }

        // обновляем таблицы
        brReloadFullAccessTable();
        brReloadPartialAccessTable();

        // обновляем селекты
        brLoadFullAccessAdminsSelect();
        $('#br-select-object').trigger('change');
    });
	});
	});

</script>

<script>
/* =====================================================================
   CLIENT EMAILS: GLOBAL ENABLE / DISABLE
   ===================================================================== */
jQuery(function ($) {

    const clientEmailCheckbox = $('#br-disable-client-emails');

    if (!clientEmailCheckbox.length) {
        return;
    }

    clientEmailCheckbox.on('change', function () {

        const disabled = this.checked ? 0 : 1;

        $.post(ajaxurl, {
            action: 'br_toggle_client_emails',
            disabled: disabled
        }, function (res) {

            if (!res || !res.success) {
                alert('Error saving email settings');
                // откатываем чекбокс в предыдущее состояние
                clientEmailCheckbox.prop('checked', !clientEmailCheckbox.prop('checked'));
            }

        });

    });

});
</script>



