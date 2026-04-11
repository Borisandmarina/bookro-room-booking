<?php
/**
 * ------------------------------------------------------------
 * Файл: wp-content/plugins/booking-room/admin-tab-bookings.php
 *
 * Назначение:
 *  - Страница «Bookings» в админке плагина Booking Room
 *  - Переключение контекста между объектами
 *  - Таблица бронирований
 *  - Bulk actions: Change status / Delete (with custom modal)
 * ------------------------------------------------------------
 */

defined('ABSPATH') || exit;


require_once BR_PLUGIN_PATH . 'admin-bookings-query.php';


global $wpdb;
$current_user_id = get_current_user_id();

/* ============================================================
 * Save selected object
 * ============================================================ */
$get_object_id = 0;

if ( isset( $_GET['object_id'] ) ) {
    $get_object_id = absint( wp_unslash( $_GET['object_id'] ) );
}

if ( $get_object_id > 0 ) {
    update_user_meta(
        $current_user_id,
        'br_current_object_id',
        $get_object_id
    );
}

/* ============================================================
 * Objects
 * ============================================================ */
$objects = $wpdb->get_results(
    "SELECT id, name, status FROM {$wpdb->prefix}1br_objects ORDER BY id ASC",
    ARRAY_A
);

if ( empty($objects) ) {
    echo '<div class="wrap"><h1>Bookings</h1><p>No objects found.</p></div>';
	
    return;
}

$saved_object_id   = (int) get_user_meta($current_user_id, 'br_current_object_id', true);
$current_object_id = $get_object_id > 0
    ? $get_object_id
    : ( $saved_object_id ?: (int) $objects[0]['id'] );
/* ============================================================
 * Object timezone (из БД)
 * ============================================================ */
$object_timezone = $wpdb->get_var(
    $wpdb->prepare(
        "SELECT timezone 
         FROM {$wpdb->prefix}1br_global_settings 
         WHERE object_id = %d 
         LIMIT 1",
        $current_object_id
    )
);

if (empty($object_timezone)) {
    $object_timezone = 'UTC';
}
	

/* ============================================================
 * Data
 * ============================================================ */
$bookings = br_get_bookings([
    'object_id'     => $current_object_id,
    'status_filter' => 'all',
]);

$event_types = $wpdb->get_results(
    $wpdb->prepare(
        "SELECT id, label FROM {$wpdb->prefix}1br_event_types WHERE object_id = %d",
        $current_object_id
    ),
    OBJECT_K
);

$participants = $wpdb->get_results(
    $wpdb->prepare(
        "SELECT id, label FROM {$wpdb->prefix}1br_visitors_count WHERE object_id = %d",
        $current_object_id
    ),
    OBJECT_K
);

$services = $wpdb->get_results(
    $wpdb->prepare(
        "SELECT id, label FROM {$wpdb->prefix}1br_services WHERE object_id = %d",
        $current_object_id
    ),
    OBJECT_K
);

function br_booking_status_color(string $status): string {
    return match ($status) {
        'pending'   => '#f0ad4e',
        'confirmed' => '#d9534f',
        'cancelled' => '#999999',
        'archive'   => '#555555',
        default     => '#777777',
    };
}

/**
 * ------------------------------------------------------------
 * Nonce для bulk + filter действий
 * ------------------------------------------------------------
 */
$br_bookings_nonce = wp_create_nonce('br_bookings_bulk_action');
?>

<script>
/**
 * Глобально доступный nonce для Bookings:
 * - bulk status
 * - bulk delete
 * - filters
 */
window.BR_BOOKINGS_NONCE = '<?php echo esc_js($br_bookings_nonce); ?>';
</script>


<div class="wrap">
<?php
/**
 * ------------------------------------------------------------
 * Object selector (Bookings page)
 * Назначение:
 *  - переключение текущего объекта
 *  - обновляет страницу с object_id
 * ------------------------------------------------------------
 */
?>
<div class="br-bookings-object-selector" style="
    margin: 0 0 16px;
    padding: 12px;
    border: 1px solid #ccd0d4;
    background: #fff;
    display: flex;
    align-items: center;
    gap: 12px;
">
    <strong>Object:</strong>

    <form method="get" style="margin:0;">
        <input type="hidden" name="page" value="booking-room-bookings">

        <select name="object_id" onchange="this.form.submit()">
            <?php foreach ($objects as $obj): ?>
                <option
                    value="<?php echo (int) $obj['id']; ?>"
                    <?php selected($obj['id'], $current_object_id); ?>
                >
                    <?php echo esc_html($obj['name']); ?>
                    [<?php echo esc_html( strtoupper( $obj['status'] ) ); ?>]

                </option>
            <?php endforeach; ?>
        </select>
    </form>
</div>

<?php
require BR_PLUGIN_PATH . 'admin-block-schedule.php';
?>
<div class="br-admin-block">
<div class="br-admin-block-header">
        <h2>Bookings</h2>
    </div>

<div class="br-bookings-export" style="
    margin:8px 0 16px;
    display:flex;
    gap:8px;
    align-items:center;
">
    <strong>Export:</strong>

    <button class="button" id="br-export-html">HTML</button>
    <button class="button" id="br-export-excel">Excel</button>
</div>


<script>
(function(){

    function collectExportData() {

        const objectSelect = document.querySelector('select[name="object_id"]');

        return {
            _wpnonce: window.BR_BOOKINGS_NONCE || '',

            object_id: objectSelect ? objectSelect.value : '',

            status: document.getElementById('br-filter-status')?.value || 'all',
            date_from: document.getElementById('br-filter-date-from')?.value || '',
            date_to: document.getElementById('br-filter-date-to')?.value || '',
            client: document.getElementById('br-filter-client')?.value || '',
            company: document.getElementById('br-filter-company')?.value || ''
        };
    }

    function submitExport(url) {

        const data = collectExportData();

        const form = document.createElement('form');
        form.method = 'POST';
        form.action = url;
        form.target = '_blank';

        Object.keys(data).forEach(key => {
            const input = document.createElement('input');
            input.type  = 'hidden';
            input.name  = key;
            input.value = data[key];
            form.appendChild(input);
        });

        document.body.appendChild(form);
        form.submit();
        document.body.removeChild(form);
    }

    document.getElementById('br-export-html')?.addEventListener('click', function () {
       submitExport('<?php echo esc_url( admin_url('admin-post.php?action=br_export_bookings_html') ); ?>');
    });

    document.getElementById('br-export-excel')?.addEventListener('click', function () {
        submitExport('<?php echo esc_url( admin_url('admin-post.php?action=br_export_bookings_excel') ); ?>');
    });

})();
</script>

<!-- ============================================================
     BOOKINGS FILTER BAR (HTML ONLY — STEP 4.2.1)
     ============================================================ -->
<div class="br-bookings-filters" style="
    margin:12px 0 16px;
    padding:12px;
    border:1px solid #ccd0d4;
    background:#fff;
    display:flex;
    flex-wrap:wrap;
    gap:12px;
    align-items:flex-end;
">

    <!-- Status filter -->
    <div style="display:flex; flex-direction:column; gap:4px;">
        <label for="br-filter-status"><strong>Status</strong></label>
        <select id="br-filter-status">
            <option value="all">All</option>
            <option value="pending">Pending</option>
            <option value="confirmed">Confirmed</option>
            <option value="pending_confirmed">Pending + Confirmed</option>
            <option value="cancelled">Cancelled</option>
            <option value="archive">Archive</option>
        </select>
    </div>

    <!-- Date from -->
    <div style="display:flex; flex-direction:column; gap:4px;">
        <label for="br-filter-date-from"><strong>Date from</strong></label>
        <input
            type="text"
            id="br-filter-date-from"
            placeholder="DD.MM.YYYY"
            style="width:120px;"
        >
    </div>

    <!-- Date to -->
    <div style="display:flex; flex-direction:column; gap:4px;">
        <label for="br-filter-date-to"><strong>Date to</strong></label>
        <input
            type="text"
            id="br-filter-date-to"
            placeholder="DD.MM.YYYY"
            style="width:120px;"
        >
    </div>

    <!-- Client -->
    <div style="display:flex; flex-direction:column; gap:4px;">
        <label for="br-filter-client"><strong>Client</strong></label>
        <input
            type="text"
            id="br-filter-client"
            placeholder="Name or surname"
            style="width:180px;"
        >
    </div>

    <!-- Company -->
    <div style="display:flex; flex-direction:column; gap:4px;">
        <label for="br-filter-company"><strong>Company</strong></label>
        <input
            type="text"
            id="br-filter-company"
            placeholder="Company name"
            style="width:180px;"
        >
    </div>

    <!-- Actions -->
    <div style="display:flex; gap:8px; margin-left:auto;">
        <button class="button" id="br-filter-apply">
            Filter
        </button>
        <button class="button" id="br-filter-reset">
            Reset
        </button>
    </div>

</div>
<script>
(function(){

    const btnFilter = document.getElementById('br-filter-apply');
    const btnReset  = document.getElementById('br-filter-reset');

    if (!btnFilter || !btnReset) {
        return;
    }

    function collectFilters() {
        return {
            action: 'br_bookings_filter',
            _wpnonce: window.BR_BOOKINGS_NONCE || '',

            object_id: document.querySelector('select[name="object_id"]')?.value || '',

            status: document.getElementById('br-filter-status')?.value || 'all',

            date_from: document.getElementById('br-filter-date-from')?.value || '',
            date_to:   document.getElementById('br-filter-date-to')?.value || '',

            client:  document.getElementById('br-filter-client')?.value || '',
            company: document.getElementById('br-filter-company')?.value || ''
        };
    }

    btnFilter.addEventListener('click', function () {

        const data = collectFilters();

        console.log('[Bookings Filter] APPLY', data);

        fetch(ajaxurl, {
            method: 'POST',
            headers: {'Content-Type': 'application/x-www-form-urlencoded'},
            body: new URLSearchParams(data)
        })
        .then(r => r.json())
        .then(res => {
            if (!res.success) {
                alert('Filter error');
                return;
            }

            const tbody = document.querySelector('#br-bookings-table tbody');
            if (tbody) {
                tbody.innerHTML = res.data.html;
            }
        })
        .catch(err => {
            console.error('[Bookings Filter] AJAX error', err);
        });
    });

    btnReset.addEventListener('click', function () {
        window.location.reload();
    });

})();
</script>

<script>
jQuery(document).ready(function ($) {

    if (typeof $.fn.datepicker === 'undefined') {
        console.warn('jQuery UI Datepicker not loaded');
        return;
    }

    const datepickerOptions = {
        dateFormat: 'dd.mm.yy',
        firstDay: 1,

        // Button panel
        showButtonPanel: true,
        currentText: 'Today',
        closeText: 'Done',

        // Explicit EN texts (safety)
        dayNamesMin: ['Su', 'Mo', 'Tu', 'We', 'Th', 'Fr', 'Sa'],
        monthNames: [
            'January', 'February', 'March', 'April',
            'May', 'June', 'July', 'August',
            'September', 'October', 'November', 'December'
        ],
        monthNamesShort: [
            'Jan', 'Feb', 'Mar', 'Apr',
            'May', 'Jun', 'Jul', 'Aug',
            'Sep', 'Oct', 'Nov', 'Dec'
        ]
    };

    $('#br-filter-date-from').datepicker(datepickerOptions);
    $('#br-filter-date-to').datepicker(datepickerOptions);

});
</script>



<!-- ================= BULK TOOLBAR ================= -->
<div class="br-bookings-toolbar" style="margin:12px 0; display:flex; gap:8px; align-items:center;">

    <select id="br-bulk-status">
        <option value="">Change status…</option>
        <option value="pending">Pending</option>
        <option value="confirmed">Confirmed</option>
        <option value="cancelled">Cancelled</option>
        <option value="archive">Archive</option>
    </select>

    <button class="button" id="br-bulk-apply">Apply</button>
    <button class="button button-danger" id="br-bulk-delete">Delete</button>

</div>

<!-- ================= TABLE ================= -->
<table class="widefat fixed striped" id="br-bookings-table">

<thead>
<tr>
    <th><input type="checkbox" id="br-bookings-check-all"></th>
    <th class="sortable" data-type="number">ID <span class="sort">▼</span></th>
    <th class="sortable" data-type="status">Status <span class="sort">▼</span></th>
    <th class="sortable" data-type="date">Date <span class="sort">▼</span></th>
    <th>Time</th>
    <th class="sortable" data-type="number">Duration <span class="sort">▼</span></th>
    <th class="sortable" data-type="string">Event type <span class="sort">▼</span></th>
    <th class="sortable" data-type="string">Participants <span class="sort">▼</span></th>
    <th>Services</th>
    <th class="sortable" data-type="number">Price <span class="sort">▼</span></th>
    <th class="sortable" data-type="string">Client <span class="sort">▼</span></th>
    <th class="sortable" data-type="string">Company <span class="sort">▼</span></th>
    <th>Phone</th>
    <th>Email</th>
</tr>
</thead>

<tbody>
<?php if (empty($bookings)): ?>
<tr><td colspan="14">No bookings found.</td></tr>
<?php else: foreach ($bookings as $row): ?>

<?php
$event_type_label = !empty($row['event_type_custom'])
    ? $row['event_type_custom']
    : ($event_types[$row['event_type_id']]->label ?? '');

$participants_label = $participants[$row['participants_option_id']]->label ?? '';

$service_labels = [];
if (!empty($row['equipment_ids'])) {
    foreach (array_map('intval', explode(',', $row['equipment_ids'])) as $sid) {
        if (isset($services[$sid])) {
            $service_labels[] = $services[$sid]->label;
        }
    }
}
?>

<tr>
    <td><input type="checkbox" class="br-booking-checkbox" value="<?php echo (int)$row['id']; ?>"></td>

    <td data-sort="<?php echo (int)$row['id']; ?>">
        <?php echo (int)$row['id']; ?>
    </td>

    <td data-sort="<?php echo esc_attr($row['status']); ?>">
        <span style="padding:3px 8px;border-radius:10px;color:#fff;background:<?php echo esc_attr( br_booking_status_color($row['status']) ); ?>">

            <?php echo esc_html(ucfirst($row['status'])); ?>
        </span>
    </td>

   <?php
$tz = new DateTimeZone( $object_timezone ); // timezone из БД
$dt = new DateTime( $row['event_date'], $tz );
$formatted_date = $dt->format( 'd.m.Y' );
?>

<td data-sort="<?php echo esc_attr( $formatted_date ); ?>">
    <?php echo esc_html( $formatted_date ); ?>

    </td>

    <td>
        <?php printf('%02d:00 – %02d:00', (int)$row['slot_start'], (int)$row['slot_end']); ?>
    </td>

    <td data-sort="<?php echo (int)$row['duration_slots']; ?>">
        <?php echo (int)$row['duration_slots']; ?>h
    </td>

    <td data-sort="<?php echo esc_attr($event_type_label); ?>">
        <?php echo esc_html($event_type_label); ?>
    </td>

    <td data-sort="<?php echo esc_attr($participants_label); ?>">
        <?php echo esc_html($participants_label); ?>
    </td>

    <td>
        <?php echo esc_html(implode(', ', $service_labels)); ?>
    </td>

    <td data-sort="<?php echo esc_attr($row['price_net']); ?>">
        <?php echo esc_html(number_format((float)$row['price_net'], 2)); ?>
    </td>

    <td data-sort="<?php echo esc_attr($row['client_name'].' '.$row['client_surname']); ?>">
        <?php echo esc_html($row['client_name'].' '.$row['client_surname']); ?>
    </td>

    <td data-sort="<?php echo esc_attr((string)$row['client_company']); ?>">
        <?php echo esc_html($row['client_company']); ?>
    </td>

    <td><?php echo esc_html($row['client_phone']); ?></td>
    <td><?php echo esc_html($row['client_email']); ?></td>
</tr>

<?php endforeach; endif; ?>
</tbody>
</table>
</div>

<!-- ================= DELETE MODAL ================= -->
<div id="br-delete-modal" style="
    display:none;
    position:fixed;
    inset:0;
    background:rgba(0,0,0,0.5);
    z-index:100000;
    align-items:center;
    justify-content:center;
">
    <div style="
        background:#fff;
        padding:20px 24px;
        border-radius:6px;
        width:360px;
        box-shadow:0 10px 30px rgba(0,0,0,0.3);
    ">
        <h2 style="margin-top:0;">Delete bookings</h2>

        <p>
            Are you sure you want to delete the selected bookings?
            <br>
            <strong>This action cannot be undone.</strong>
        </p>

        <div style="display:flex; justify-content:flex-end; gap:8px; margin-top:20px;">
            <button class="button" id="br-delete-cancel">Cancel</button>
            <button class="button button-danger" id="br-delete-confirm">Delete</button>
        </div>
    </div>
</div>
</div>
<style>
#br-bookings-table th.sortable { cursor:pointer; user-select:none; }
#br-bookings-table th .sort { font-size:11px; opacity:.4; margin-left:4px; }
#br-bookings-table th.active .sort { opacity:1; }
</style>

<script>
/* ================= SORTING ================= */
(function(){
const table=document.getElementById('br-bookings-table');
if(!table) return;

const STATUS={pending:1,confirmed:2,cancelled:3,archive:4};

const parseDateEU=v=>{
    const p=v.split('.');
    return new Date(p[2],p[1]-1,p[0]).getTime();
};

const getVal=(tr,i)=>tr.children[i].dataset.sort||tr.children[i].innerText.trim();

table.querySelectorAll('th.sortable').forEach(th=>{
let asc=false;
th.addEventListener('click',()=>{
table.querySelectorAll('th').forEach(h=>h.classList.remove('active'));
th.classList.add('active');
asc=!asc;
th.querySelector('.sort').textContent=asc?'▲':'▼';

const idx=th.cellIndex;
const type=th.dataset.type;
const rows=[...table.tBodies[0].rows];

rows.sort((a,b)=>{
let A=getVal(asc?a:b,idx);
let B=getVal(asc?b:a,idx);
if(type==='number') return A-B;
if(type==='date') return parseDateEU(A)-parseDateEU(B);
if(type==='status') return (STATUS[A]||9)-(STATUS[B]||9);
return A.localeCompare(B,undefined,{numeric:true,sensitivity:'base'});
});

rows.forEach(tr=>table.tBodies[0].appendChild(tr));
});
});
})();

/* ================= BULK ACTIONS ================= */
(function(){

let pendingDeleteIds = [];

function getSelectedIds() {
    return Array.from(
        document.querySelectorAll('.br-booking-checkbox:checked')
    ).map(cb => cb.value);
}

/* Apply status */
document.getElementById('br-bulk-apply')?.addEventListener('click', function () {
    const status = document.getElementById('br-bulk-status').value;
    const ids    = getSelectedIds();

    if (!status || ids.length === 0) {
        alert('Select bookings and status first.');
        return;
    }

    const params = new URLSearchParams();
params.append('action', 'br_bookings_bulk_status');
params.append('_wpnonce', window.BR_BOOKINGS_NONCE);
params.append('new_status', status);

ids.forEach(id => {
    params.append('booking_ids[]', id);
});

fetch(ajaxurl, {
    method: 'POST',
    headers: {'Content-Type': 'application/x-www-form-urlencoded'},
    body: params
})

    .then(r => r.json())
    .then(res => {
        if (res.success) {
            location.reload();
        } else {
            alert('Error while updating status');
        }
    });
});

/* Open delete modal */
const modal  = document.getElementById('br-delete-modal');
const btnYes = document.getElementById('br-delete-confirm');
const btnNo  = document.getElementById('br-delete-cancel');

document.getElementById('br-bulk-delete')?.addEventListener('click', function () {
    const ids = getSelectedIds();
    if (ids.length === 0) {
        alert('Select bookings first.');
        return;
    }
    pendingDeleteIds = ids;
    modal.style.display = 'flex';
});

/* Cancel delete */
btnNo?.addEventListener('click', function () {
    modal.style.display = 'none';
    pendingDeleteIds = [];
});

/* Confirm delete */
btnYes?.addEventListener('click', function () {
    if (pendingDeleteIds.length === 0) {
        modal.style.display = 'none';
        return;
    }

    const params = new URLSearchParams();
    params.append('action', 'br_bookings_bulk_delete');
    params.append('_wpnonce', window.BR_BOOKINGS_NONCE);

    pendingDeleteIds.forEach(id => {
        params.append('booking_ids[]', id);
    });

    fetch(ajaxurl, {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: params
    })
    .then(r => r.json())
    .then(res => {
        if (res.success) {
            location.reload();
        } else {
            alert('Error while deleting bookings');
        }
    });

    modal.style.display = 'none';
});

})();

</script>

<script>
(function(){

    const checkAll = document.getElementById('br-bookings-check-all');

    function getRowCheckboxes() {
        return Array.from(
            document.querySelectorAll('.br-booking-checkbox')
        );
    }

    if (!checkAll) return;

    /* Клик по "выделить всё" */
    checkAll.addEventListener('change', function () {
        getRowCheckboxes().forEach(cb => {
            cb.checked = checkAll.checked;
        });
    });

    /* Обновление состояния "выделить всё" при кликах по строкам */
    document.addEventListener('change', function (e) {
        if (!e.target.classList.contains('br-booking-checkbox')) return;

        const boxes = getRowCheckboxes();
        const checked = boxes.filter(cb => cb.checked).length;

        checkAll.checked = checked === boxes.length;
        checkAll.indeterminate = checked > 0 && checked < boxes.length;
    });

})();
</script>
