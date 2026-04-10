<?php
/**
 * ------------------------------------------------------------
 * Файл: wp-content/plugins/booking-room/admin-block-schedule.php
 * Назначение:
 *  - блок управления расписанием для выбранного объекта
 * ------------------------------------------------------------
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * ------------------------------------------------------------
 * Контекст блока
 * ------------------------------------------------------------
 * ВАЖНО:
 *  - объект уже определён в admin-settings.php
 *  - доступные переменные:
 *      $current_object_id
 *      $current_object
 *      $wpdb
 */

// Текущая дата (сегодня)
$today = gmdate('Y-m-d');

// Выбранная дата (с безопасной обработкой GET)
$selected_date = $today;

if (
    isset($_GET['date']) &&
    isset($_GET['_wpnonce']) &&
    wp_verify_nonce(
        sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ),
        'br_view_schedule'
    )
) {

    $raw_date = sanitize_text_field(
        wp_unslash( $_GET['date'] )
    );

    $timestamp = strtotime($raw_date);

    if ($timestamp !== false) {
        $selected_date = gmdate('Y-m-d', $timestamp);
    }
}

?>
<?php
global $wpdb;
// ===============================
// Загрузка глобальных настроек
// ===============================

$global_settings = $wpdb->get_row(
    $wpdb->prepare(
        "
        SELECT *
       FROM {$wpdb->prefix}1br_global_settings
        WHERE object_id = %d
        LIMIT 1
        ",
        $current_object_id
    ),
    ARRAY_A
);

// ===============================
// Загрузка букингов
// ===============================
$schedule_bookings = $wpdb->get_results(

    $wpdb->prepare(
        "
        SELECT *
        FROM {$wpdb->prefix}1br_bookings
        WHERE object_id = %d
          AND event_date = %s
          AND status IN ('pending','confirmed')
        ",
        $current_object_id,
        $selected_date
    ),
    ARRAY_A
	
);

// ===============================
// Загрузка overrides
// ===============================
$overrides = $wpdb->get_results(
   $wpdb->prepare(
      "
   SELECT *
   FROM {$wpdb->prefix}1br_overrides
   WHERE object_id = %d
   AND date_start <= %s
   AND date_end >= %s
   ",
   $current_object_id,
   $selected_date,
   $selected_date
    ),
    ARRAY_A
		);

// ===============================
// Загрузка breaks (недоступные слоты)
// ===============================
$breaks = $wpdb->get_results(
    $wpdb->prepare(
        "
        SELECT *
        FROM {$wpdb->prefix}1br_breaks
        WHERE object_id = %d
          AND date_start <= %s
          AND date_end >= %s
        ",
        $current_object_id,
        $selected_date,
        $selected_date
    ),
    ARRAY_A
);

?>
<div class="br-admin-block">

    <!-- ===============================
         Заголовок блока
         =============================== -->
    <div class="br-admin-block-header">
        <h2>Schedule</h2>
    </div>

    <!-- ===============================
         Панель выбора даты
         =============================== -->
    <div class="br-admin-block-body">

        <div style="display:flex; align-items:center; gap:12px; margin-bottom:16px;">

            <!-- Календарь -->
            <input
    type="date"
    name="schedule_date"
    id="br-schedule-date"
    value="<?php echo esc_attr( $selected_date ); ?>"
>




           <!-- Кнопка возврата к сегодняшней дате -->
<button
    type="button"
    class="button"
    onclick="
        const input = this.previousElementSibling;
        input.value = '<?php echo esc_js( $today ); ?>';
        input.dispatchEvent(new Event('change'));
    "
>
    Today
</button>
<div id="br-holiday-info" class="br-holiday-info" style="display:none;">
    🎉 <span class="br-holiday-text">Holiday</span>
	 <label class="br-holiday-allow-label">
        <input type="checkbox" id="br-holiday-allow-booking" />
        <span>Make available for booking</span>
    </label>
</div>
</div>
<div class="br-schedule-wrapper br-mode-view">

    <!-- Ось времени -->
    <div class="br-time-axis">
        <?php for ($h = 1; $h <= 23; $h++): ?>
           <span style="left: calc(100% / 24 * <?php echo (int) $h; ?>);">
               <?php echo esc_html( (int) $h ); ?>

            </span>
        <?php endfor; ?>
    </div>

    <!-- Шкала -->
<div class="br-schedule">

    <!-- Сетка -->
    <div class="br-grid-layer">
        <div></div><div></div><div></div><div></div><div></div><div></div>
        <div></div><div></div><div></div><div></div><div></div><div></div>
        <div></div><div></div><div></div><div></div><div></div><div></div>
        <div></div><div></div><div></div><div></div><div></div><div></div>
    </div>

    <!-- Свободные слоты (визуал, без логики) -->
    <div class="br-slots-layer">
        <?php for ($i = 0; $i < 24; $i++): ?>
            <div data-slot="<?php echo esc_attr( (int) $i ); ?>"></div>
        <?php endfor; ?>
    </div>

    <!-- Бронирования -->
    <div class="br-bookings-layer"></div>

    <!-- Interaction layer (НОВЫЙ, пока без логики) -->
    <div class="br-interaction-layer">
        <?php for ($i = 0; $i < 24; $i++): ?>
           <div data-slot="<?php echo esc_attr( (int) $i ); ?>"></div>
        <?php endfor; ?>
    </div>

</div>

<!-- Кнопки управления -->


    <div style="margin-top:10px; display:flex; gap:10px;">
        <button class="button button-primary" id="btn-add">
            Add free slots
        </button>
 <button class="button" id="btn-breaks">
        Add unavailable slots
    </button>
        <button class="button" id="btn-remove">
            Remove free slots
        </button>
		
    </div>
	<div id="br-booking-info"></div>
	
<style>
/* ===============================
   Holiday allow checkbox
   =============================== */
.br-holiday-allow-label {
    align-items: center;
    gap: 12px;
    cursor: pointer;
}


.br-holiday-info {
    /* === НАСТРАИВАЕМЫЕ ПАРАМЕТРЫ === */
    font-size: 16px;          /* размер шрифта */
    min-height: 30px;         /* высота плашки */
    padding: 0 10px;          /* горизонтальные отступы */

    /* === ВЫРАВНИВАНИЕ И ВИД === */
    margin-left: 10px;
    border-radius: 4px;
    background: #ffe9a8;
    color: #7a5200;
    font-weight: 600;
    white-space: nowrap;

    display: inline-flex;
    align-items: center;      /* вертикальное центрирование */
    gap: 6px;
	border-color: #d63638;
    border-style: solid;
    border-width: 1px;
}


h2 {
    color: #2271b1;
    font-size: 2em;
    margin: 1em 0;
}
/* ===============================
   Блоки на белой плашке
   =============================== */
.br-admin-block {
    background: #f5f5f5;
    border: 1px solid #ccd0d4;
    padding: 16px;
    margin-bottom: 20px;
}

/* ===============================
   Общий контейнер шкалы
   =============================== */
.br-schedule-wrapper {
    width: 100%;
}

/* ===============================
   Метки времени
   =============================== */
.br-time-axis {
    position: relative;
    height: 16px;
    margin-bottom: 4px;
}

.br-time-axis span {
    position: absolute;
    top: 0;
    transform: translateX(-50%);
    font-size: 11px;
    color: #555;
    white-space: nowrap;
}

/* ===============================
   Контейнер шкалы
   =============================== */
.br-schedule {
    position: relative;
    width: 100%;
    height: 40px;
    background: #fff;
    box-sizing: border-box;
}

/* ===============================
   Сетка
   =============================== */
.br-grid-layer {
    position: absolute;
    inset: 0;
    display: grid;
    grid-template-columns: repeat(24, 1fr);

    z-index: 9;              /* ВЫШЕ bookings (5), НИЖЕ interaction (10) */
    pointer-events: none;    /* КРИТИЧЕСКИ ВАЖНО */
}


.br-grid-layer div {
    border-right: 1px solid #8c8f94;
}

.br-grid-layer div:last-child {
    border-right: none;
}

/* ===============================
   Слой слотов (база)
   =============================== */
.br-slots-layer {
    position: absolute;
    inset: 0;
    display: grid;
    grid-template-columns: repeat(24, 1fr);
    z-index: 2;
    pointer-events: none;
}

/* каждая ячейка — участник stacking context */
.br-slots-layer > div {
    position: relative;
    height: 100%;
    background: transparent;
}

/* ===============================
   Глобальное рабочее время (ФОН)
   =============================== */
.br-slots-layer > div.worktime:not(.br-slot) {
    background: rgba(108, 207, 142, 0.25);
    pointer-events: none;
}

.br-slots-layer > div.br-slot {
    z-index: 1;
}


/* ===============================
   Слоты из БД
   =============================== */
.br-slot.free {
    position: relative;
    background: #6ccf8e;
    z-index: 2;
}

.br-slot.breaks {
    position: relative;
    background: #e0e0e0;
    z-index: 2;
}


.br-slots-layer > div.selecting {
    background: #fff7d9;
}

/* ===============================
   Слой бронирований (ВСЕГДА СВЕРХУ)
   =============================== */
.br-bookings-layer {
    position: absolute;
    inset: 0;
    z-index: 20;
    pointer-events: auto;
}

.br-booking {
    position: absolute;
    top: 0;
    height: 100%;
    cursor: pointer;
    z-index: 1;
}

.br-booking.pending {
    background: #ffe3b3;
}

.br-booking.confirmed {
    background: #f5b7b1;
}
/* ===============================
   Interaction layer (НОВЫЙ)
   =============================== */
.br-interaction-layer {
    position: absolute;
    inset: 0;
    display: grid;
    grid-template-columns: repeat(24, 1fr);
    z-index: 10;            /* выше bookings */
    pointer-events: none;  /* КРИТИЧЕСКИ важно */
}

.br-interaction-layer > div {
    background: transparent;
}


/* ===============================
   Режимы работы
   =============================== */
.br-mode-view .br-bookings-layer {
    pointer-events: auto;
}

.br-mode-add .br-slots-layer,
.br-mode-remove .br-slots-layer,
.br-mode-breaks .br-slots-layer {
    pointer-events: auto;
}

.br-mode-add .br-bookings-layer,
.br-mode-remove .br-bookings-layer,
.br-mode-breaks .br-bookings-layer {
    pointer-events: none;
}
/* ===============================
   Interaction layer — режимы
   =============================== */

/* просмотр — interaction НЕ мешает */
.br-mode-view .br-interaction-layer {
    pointer-events: none;
}

/* редактирование — interaction активен */
.br-mode-add .br-interaction-layer,
.br-mode-breaks .br-interaction-layer,
.br-mode-remove .br-interaction-layer {
    pointer-events: auto;
}


/* ===============================
   Инфоблок бронирований
   =============================== */
.br-booking-info {
    margin-top: 12px;
    border: 1px solid #ccd0d4;
    background: #fff;
}

.br-booking-info-header {
    padding: 6px 10px;
    font-weight: 600;
}

.br-booking-info-header.pending {
    background: #ffe3b3;
}

.br-booking-info-header.confirmed {
    background: #f5b7b1;
}

.br-booking-info-body {
    padding: 10px;
    font-size: 13px;
}

.br-booking-info-sep {
    border-top: 1px solid #ddd;
    margin: 10px 0;
}

.br-booking-info-actions {
    padding: 8px 10px;
    display: flex;
    gap: 8px;
}
.cursor-forbidden {
    cursor: not-allowed !important;
}
</style>

<script>
/* ===============================
   Booking info block (EN)
   =============================== */

function renderBookingInfo(booking) {

    const container = document.getElementById('br-booking-info');
    if (!container) return;

    let actionsHtml = '';

    if (booking.status === 'pending') {
        actionsHtml = `
            <button class="button button-confirm"
                onclick="updateBookingStatus(${booking.id}, 'confirmed')">
                Confirmed
            </button>
            <button class="button button-cancel"
                onclick="updateBookingStatus(${booking.id}, 'cancelled')">
                Cancelled
            </button>
        `;
    }

    if (booking.status === 'confirmed') {
        actionsHtml = `
            <button class="button button-cancel"
                onclick="updateBookingStatus(${booking.id}, 'cancelled')">
                Cancelled
            </button>
        `;
    }

    container.innerHTML = `
        <div class="br-booking-info">

            <div class="br-booking-info-header ${booking.status}"
                 style="display:flex; align-items:center; gap:8px;">
                <span>${booking.status.toUpperCase()}</span>
                <span style="font-weight:400; font-size:12px; opacity:0.8;">
                    ID=${booking.id}
                </span>
            </div>

            <div class="br-booking-info-body">

                <!-- EVENT DETAILS -->
                <strong>Event type:</strong> ${booking.event_type_label || '—'}<br>
                <!--<strong>Participants:</strong> ${booking.participants_label || '—'}<br>
                <strong>Services:</strong> ${booking.services_labels || '—'}<br>-->
                <strong>Price:</strong> ${booking.price_gross} UAH<br>

                ${booking.order_comment
                    ? `<strong>Comment:</strong> ${booking.order_comment}<br>`
                    : ''
                }

                <div class="br-booking-info-sep"></div>

                <!-- SCHEDULE -->
                <strong>Date:</strong> ${booking.event_date}<br>
                <strong>Time:</strong> ${booking.slot_start}:00 – ${booking.slot_end}:00<br>
                <strong>Duration:</strong> ${booking.duration_slots} h

                <div class="br-booking-info-sep"></div>

                <!-- CLIENT -->
                <strong>Sender:</strong><br>
                ${booking.client_name} ${booking.client_surname}<br>
                ${booking.client_company || ''}<br>
                ${booking.client_phone}<br>
                ${booking.client_email}
            </div>

            <div class="br-booking-info-actions">
                ${actionsHtml}
            </div>

        </div>
    `;
}

</script>
<script>
window.BR_DATA = {
    objectId: <?php echo (int) $current_object_id; ?>,
    date: "<?php echo esc_js($selected_date); ?>",
    settings: <?php echo wp_json_encode($global_settings); ?>,
    bookings: <?php echo wp_json_encode($schedule_bookings); ?>,
    overrides: <?php echo wp_json_encode($overrides); ?>,
    breaks: <?php echo wp_json_encode($breaks); ?>
};
</script>

<script>
/* ============================================================
   RENDER FROM STATE (FREE / BREAK / WORKTIME)
   ============================================================ */

function renderFromState() {

    if (!window.BR_DATA) return;

    const cells = document.querySelectorAll(
        '.br-slots-layer [data-slot]'
    );

    // 1️⃣ полная очистка
    cells.forEach(cell => {
        cell.classList.remove('br-slot', 'free', 'breaks', 'worktime');
        delete cell.dataset.type;
    });

    // 2️⃣ ⛔ ПРАЗДНИК + ЗАПРЕТ БРОНИРОВАНИЯ
    // НИЧЕГО не рисуем (шкала должна выглядеть закрытой)
    if (window.BR_IS_HOLIDAY === true && window.BR_HOLIDAY_ALLOW === false) {
        return;
    }

    // 3️⃣ рабочее время (фон)
    if (BR_DATA.settings) {

        const workStart = parseInt(BR_DATA.settings.work_start_slot, 10);
        const workEnd   = parseInt(BR_DATA.settings.work_end_slot, 10);

        const weekendsRaw = BR_DATA.settings.weekends;
        const weekends = (
            typeof weekendsRaw === 'string' && weekendsRaw.length > 0
        )
            ? weekendsRaw.split(',').map(d => d.trim())
            : [];

        const selectedDate = new Date(BR_DATA.date + 'T00:00:00');
        const dayName = selectedDate.toLocaleDateString('en-US', {
            weekday: 'long'
        });

        const isWeekend = weekends.includes(dayName);

        if (!isWeekend) {
            for (let i = workStart; i < workEnd; i++) {
                const cell = document.querySelector(
                    '.br-slots-layer [data-slot="' + i + '"]'
                );
                if (cell) {
                    cell.classList.add('worktime');
                }
            }
        }
    }

    // 4️⃣ free (overrides)
    if (Array.isArray(BR_DATA.overrides)) {
        BR_DATA.overrides.forEach(r => {
            const start = parseInt(r.slot_start, 10);
            const end   = parseInt(r.slot_end, 10);

            for (let i = start; i < end; i++) {
                const cell = document.querySelector(
                    '.br-slots-layer [data-slot="' + i + '"]'
                );
                if (!cell) continue;

                cell.classList.remove('worktime');
                cell.classList.add('br-slot', 'free');
                cell.dataset.type = 'free';
            }
        });
    }

    // 5️⃣ breaks
    if (Array.isArray(BR_DATA.breaks)) {
        BR_DATA.breaks.forEach(r => {
            const start = parseInt(r.slot_start, 10);
            const end   = parseInt(r.slot_end, 10);

            for (let i = start; i < end; i++) {
                const cell = document.querySelector(
                    '.br-slots-layer [data-slot="' + i + '"]'
                );
                if (!cell) continue;

                cell.classList.remove('free', 'worktime');
                cell.classList.add('br-slot', 'breaks');
                cell.dataset.type = 'break';
            }
        });
    }
}
</script>



<script>
/* ===============================
   RENDER BOOKINGS (DB-only)
   =============================== */

function renderBookings() {

    if (!window.BR_DATA) return;

    const wrapper = document.querySelector('.br-schedule-wrapper');
    if (!wrapper) return;

    const bookingsLayer = wrapper.querySelector('.br-bookings-layer');
    if (!bookingsLayer) return;

    bookingsLayer.innerHTML = '';

    if (!Array.isArray(BR_DATA.bookings)) return;

    BR_DATA.bookings.forEach(booking => {

        const bookingEl = document.createElement('div');

        bookingEl.classList.add('br-booking');
        bookingEl.classList.add(
            booking.status === 'confirmed' ? 'confirmed' : 'pending'
        );

        const start = parseInt(booking.slot_start, 10);
        const end   = parseInt(booking.slot_end, 10);
        const width = end - start;

        bookingEl.style.left  = `calc(100% / 24 * ${start})`;
        bookingEl.style.width = `calc(100% / 24 * ${width})`;

        bookingEl.addEventListener('click', e => {
            e.stopPropagation();
            renderBookingInfo(booking);
        });

        bookingsLayer.appendChild(bookingEl);
    });
}
</script>

<script>
/* ===============================
   Holiday info + checkbox (DB)
   =============================== */
function checkHolidayAndUpdateInfo(callback) {

    if (!window.BR_DATA) return;

    const holidayInfo   = document.getElementById('br-holiday-info');
    const holidayText   = holidayInfo?.querySelector('.br-holiday-text');
    const allowCheckbox = document.getElementById('br-holiday-allow-booking');

    if (!holidayInfo || !holidayText || !allowCheckbox) return;

    // ⛔ ЖЁСТКИЙ СБРОС
    holidayInfo.style.display = 'none';
    holidayText.textContent = 'Holiday';
    allowCheckbox.checked = false;

    window.BR_IS_HOLIDAY = false;
    window.BR_HOLIDAY_ALLOW = false;

    fetch(ajaxurl, {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: new URLSearchParams({
            action: 'br_check_holiday',
            object_id: BR_DATA.objectId,
            date: BR_DATA.date
        })
    })
    .then(r => r.json())
    .then(resp => {

        if (!resp || !resp.success || !resp.data) {
            renderFromState();
            return;
        }

        const data = resp.data;

        if (!data.is_holiday) {
            renderFromState();
            return;
        }

        // ✅ ЕДИНСТВЕННЫЙ ИСТОЧНИК ИСТИНЫ — БД
        window.BR_IS_HOLIDAY = true;
        window.BR_HOLIDAY_ALLOW = Number(data.allow_booking) === 1;

        holidayText.textContent =
            'Holiday' + (data.name ? ': ' + data.name : '');

        holidayInfo.style.display = 'inline-flex';
        allowCheckbox.checked = window.BR_HOLIDAY_ALLOW;

        renderFromState();

        if (typeof callback === 'function') {
            callback(data);
        }
    });
}
</script>

<script>
const holidayAllowCheckbox =
    document.getElementById('br-holiday-allow-booking');

if (holidayAllowCheckbox) {

    holidayAllowCheckbox.addEventListener('change', function () {

        const allow = this.checked ? 1 : 0;

        fetch(ajaxurl, {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: new URLSearchParams({
                action: 'br_set_holiday_allow_booking',
                object_id: BR_DATA.objectId,
                date: BR_DATA.date,
                allow_booking: allow
            })
        })
        .then(r => r.json())
        .then(resp => {

            if (!resp || !resp.success) {
                if (typeof checkHolidayAndUpdateInfo === 'function') {
                    checkHolidayAndUpdateInfo();
                }
                return;
            }

            if (typeof checkHolidayAndUpdateInfo === 'function') {
                checkHolidayAndUpdateInfo();
            }

            fetchScheduleSnapshot(() => {
                renderFromState();
                renderBookings();
            });

        });

    });

}
</script>


<script>
/* ============================================================
   DOM REFERENCES (GLOBAL)
   ============================================================ */

const wrapper = document.querySelector('.br-schedule-wrapper');

const slotsLayer = wrapper
    ? wrapper.querySelector('.br-slots-layer')
    : null;

const interactionLayer = wrapper
    ? wrapper.querySelector('.br-interaction-layer')
    : null;

const slotCells = interactionLayer
    ? interactionLayer.querySelectorAll('[data-slot]')
    : [];
</script>
<script>
/* ===============================
   INITIAL RENDER (DB-DRIVEN ONLY)
   =============================== */
document.addEventListener('DOMContentLoaded', () => {

    if (!window.BR_DATA) return;

    // 1️⃣ сначала читаем праздник (НЕ влияет на рендер букингов)
    if (typeof checkHolidayAndUpdateInfo === 'function') {
        checkHolidayAndUpdateInfo();
    }

    // 2️⃣ ВСЕГДА получаем слепок из БД
    fetchScheduleSnapshot(() => {

        // 3️⃣ ВСЕГДА рисуем состояние и букинги
        if (typeof renderFromState === 'function') {
            renderFromState();
        }

        if (typeof renderBookings === 'function') {
            renderBookings();
        }

    });

});
</script>


<script>
/* ============================================================
   CURSOR / UX CONTROL (FIXED)
   ============================================================ */

if (interactionLayer && slotsLayer) {

    interactionLayer.addEventListener('mousemove', (e) => {

        if (mode === 'view') {
            interactionLayer.classList.remove('cursor-forbidden');
            return;
        }

        const slotEl = e.target.closest('[data-slot]');
        if (!slotEl) return;

        const slot = parseInt(slotEl.dataset.slot, 10);

        /* ⛔ букинги — ВСЕГДА запрет */
        if (blockedSlots.has(slot)) {
            interactionLayer.classList.add('cursor-forbidden');
            return;
        }

        const visualCell = slotsLayer.querySelector(
            '[data-slot="' + slot + '"]'
        );

        if (!visualCell) {
            interactionLayer.classList.remove('cursor-forbidden');
            return;
        }

        /*
         * add  → зелёный → зелёный  ✅
         * breaks → серый → серый   ✅
         */
        interactionLayer.classList.remove('cursor-forbidden');
    });

}
</script>

<?php
$br_admin_nonce = wp_create_nonce('br_admin_nonce');
?>
<script>
window.BR_ADMIN_NONCE = "<?php echo esc_js($br_admin_nonce); ?>";
</script>
<script>
/* ===============================
   Update booking status (AJAX)
   =============================== */
window.updateBookingStatus = function (bookingId, newStatus) {

    if (!window.BR_ADMIN_NONCE) {
        alert('Security token missing. Reload page.');
        return;
    }

    fetch(ajaxurl, {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: new URLSearchParams({
            action: 'br_update_booking_status',
            booking_id: bookingId,
            status: newStatus,
            _wpnonce: window.BR_ADMIN_NONCE
        }).toString()
    })
    .then(r => {
        if (r.status === 403) {
            throw new Error('403 Forbidden');
        }
        return r.json();
    })
    .then(resp => {

        if (!resp || !resp.success) {
            alert('Error: ' + (resp && resp.data ? resp.data : 'Unknown'));
            return;
        }

        if (typeof fetchScheduleSnapshot === 'function') {

            fetchScheduleSnapshot(() => {

                if (typeof renderFromState === 'function') {
                    renderFromState();
                }

                if (typeof renderBookings === 'function') {
                    renderBookings();
                }

                if (typeof checkHolidayAndUpdateInfo === 'function') {
                    checkHolidayAndUpdateInfo();
                }

                const info = document.getElementById('br-booking-info');
                if (info) {
                    info.innerHTML = '';
                }

            });
        }

    })
    .catch(err => {
        console.error('[Update Booking Status ERROR]', err);
        alert('Request failed (possible nonce error).');
    });
};
</script>




<script>
/* ===============================
   Mode / buttons init
   =============================== */

const btnAdd    = document.getElementById('btn-add');
const btnRemove = document.getElementById('btn-remove');
const btnBreaks = document.getElementById('btn-breaks');

let mode = 'view';
let isDragging = false;
let startSlot = null;

/* ===============================
   Переключение режимов
   =============================== */

if (btnAdd) {
    btnAdd.addEventListener('click', () => {
        mode = 'add';

        if (!wrapper) return;

        wrapper.classList.remove(
            'br-mode-view',
            'br-mode-remove',
            'br-mode-breaks'
        );
        wrapper.classList.add('br-mode-add');
    });
}

if (btnRemove) {
    btnRemove.addEventListener('click', () => {
        mode = 'remove';

        if (!wrapper) return;

        wrapper.classList.remove(
            'br-mode-view',
            'br-mode-add',
            'br-mode-breaks'
        );
        wrapper.classList.add('br-mode-remove');
    });
}

if (btnBreaks) {
    btnBreaks.addEventListener('click', () => {
        mode = 'breaks';

        if (!wrapper) return;

        wrapper.classList.remove(
            'br-mode-view',
            'br-mode-add',
            'br-mode-remove'
        );
        wrapper.classList.add('br-mode-breaks');
    });
}
</script>

<script>
/* ===============================
   BLOCKED SLOTS (BOOKINGS)
   =============================== */

const blockedSlots = new Set();
let blockedSlotsInitialized = false;

function initBlockedSlotsOnce() {
    if (blockedSlotsInitialized) return;

    if (
        !window.BR_DATA ||
        !Array.isArray(BR_DATA.bookings)
    ) {
        return;
    }

    blockedSlotsInitialized = true;

    BR_DATA.bookings.forEach(booking => {
        const start = parseInt(booking.slot_start, 10);
        const end   = parseInt(booking.slot_end, 10);

        for (let i = start; i < end; i++) {
            blockedSlots.add(i);
        }
    });

}
</script>

<script>
/* ===============================
   SELECTION (VISUAL ONLY)
   =============================== */

function clearSelection() {
    if (!slotsLayer) return;

    slotsLayer
        .querySelectorAll('.selecting')
        .forEach(c => c.classList.remove('selecting'));
}

function markRange(from, to) {
    initBlockedSlotsOnce();
    if (!slotsLayer) return;

    const min = Math.min(from, to);
    const max = Math.max(from, to);

    for (let i = min; i <= max; i++) {

        // запрет free поверх бронирований
        if (mode === 'add' && blockedSlots.has(i)) {
            continue;
        }

        const cell = slotsLayer.querySelector('[data-slot="' + i + '"]');
        if (!cell) continue;

        cell.classList.add('selecting');
    }
}
</script>

<script>
/* ===============================
   APPLY RANGE (SYMMETRIC MODEL)
   =============================== */

function applyRange(from, to) {
    initBlockedSlotsOnce();

    /* ⛔ запрет: праздник и бронирование запрещено */
    if (window.BR_IS_HOLIDAY === true && window.BR_HOLIDAY_ALLOW === false) {
        return;
    }

    if (mode !== 'add' && mode !== 'breaks') return;

    const min = Math.min(from, to);
    const max = Math.max(from, to);

    const start = min;
    const end   = max + 1;

    /* запрет пересечения с бронированиями */
    for (let i = start; i < end; i++) {
        if (blockedSlots.has(i)) {
            return;
        }
    }

    fetch(ajaxurl, {
        method: 'POST',
        headers: {'Content-Type':'application/x-www-form-urlencoded'},
        body: new URLSearchParams({
            action: 'br_apply_range',
            object_id: BR_DATA.objectId,
            date: BR_DATA.date,
            type: mode === 'add' ? 'free' : 'break',
            slot_start: start,
            slot_end: end
        })
    });

}
</script>

<script>
function fetchScheduleSnapshot(callback) {

    fetch(ajaxurl, {
        method: 'POST',
        headers: {'Content-Type':'application/x-www-form-urlencoded'},
        body: new URLSearchParams({
            action: 'br_get_schedule_snapshot',
            object_id: BR_DATA.objectId,
            date: BR_DATA.date
        })
    })
    .then(r => r.json())
    .then(resp => {
        if (!resp || !resp.success) {
            console.error('[SCHEDULE SNAPSHOT ERROR]', resp);
            return;
        }

        BR_DATA.settings  = resp.data.settings;
        BR_DATA.overrides = resp.data.overrides;
        BR_DATA.breaks    = resp.data.breaks;
        BR_DATA.bookings  = resp.data.bookings;

        blockedSlots.clear();
        blockedSlotsInitialized = false;

        // ⛔ НИКАКОГО render ТУТ

        if (typeof callback === 'function') {
            callback();
        }
    })
    .catch(err => {
        console.error('[SCHEDULE SNAPSHOT FETCH FAILED]', err);
    });
}
</script>

<script>
/* ===============================
   REMOVE: удаление диапазонов (DB-driven)
   =============================== */

function applyRemoveMode() {

    if (mode !== 'remove') {
        return;
    }

    if (!slotsLayer) return;

    const selectedSlots = Array.from(
        slotsLayer.querySelectorAll('.selecting')
    ).map(cell => parseInt(cell.dataset.slot, 10));

    if (selectedSlots.length === 0) {
        return;
    }

    const selStart = Math.min(...selectedSlots);
    const selEnd   = Math.max(...selectedSlots) + 1;

    // === ЕДИНСТВЕННОЕ ДЕЙСТВИЕ ===
    // JS НЕ считает диапазоны в БД
    // JS только сообщает намерение

    fetch(ajaxurl, {
        method: 'POST',
        headers: {'Content-Type':'application/x-www-form-urlencoded'},
        body: new URLSearchParams({
            action: 'br_remove_range',
            object_id: BR_DATA.objectId,
            date: BR_DATA.date,
            slot_start: selStart,
            slot_end: selEnd
        })
    });
}
</script>
<script>
/* ============================================================
   DATE CHANGE (SINGLE SOURCE OF TRUTH)
   ============================================================ */

const dateInput = document.getElementById('br-schedule-date');

if (dateInput) {

    dateInput.addEventListener('change', () => {

        const newDate = dateInput.value;
        if (!newDate || !window.BR_DATA) return;

        // 1️⃣ обновляем дату
        BR_DATA.date = newDate;

        // 2️⃣ читаем праздник (НЕ блокирует рендер)
        if (typeof checkHolidayAndUpdateInfo === 'function') {
            checkHolidayAndUpdateInfo();
        }

        // 3️⃣ ВСЕГДА получаем слепок и рисуем
        fetchScheduleSnapshot(() => {

            if (typeof renderFromState === 'function') {
                renderFromState();
            }

            if (typeof renderBookings === 'function') {
                renderBookings();
            }

        });

    });

}
</script>



<script>
/* ===============================
   Работа мыши
   =============================== */

if (slotCells && slotCells.length > 0) {

    slotCells.forEach(cell => {

        cell.addEventListener('mousedown', (e) => {
            if (mode === 'view') return;
            if (typeof clearSelection !== 'function') return;

            // ⬇⬇⬇ КЛЮЧЕВОЕ ИСПРАВЛЕНИЕ ⬇⬇⬇
            isDragging = true;
            startSlot = null; // сброс возможного старого значения
            startSlot = parseInt(cell.dataset.slot, 10);
            // ⬆⬆⬆ КЛЮЧЕВОЕ ИСПРАВЛЕНИЕ ⬆⬆⬆

            clearSelection();
            markRange(startSlot, startSlot);

            e.preventDefault();
            e.stopPropagation();
        });

        cell.addEventListener('mouseenter', () => {
            if (!isDragging) return;
            if (startSlot === null) return;
            if (typeof clearSelection !== 'function') return;
            if (typeof markRange !== 'function') return;

            clearSelection();

            const currentSlot = parseInt(cell.dataset.slot, 10);
            markRange(startSlot, currentSlot);
        });

        cell.addEventListener('mouseup', (e) => {
            if (!isDragging) return;
            if (startSlot === null) return;
            if (typeof clearSelection !== 'function') return;

            const endSlot = parseInt(cell.dataset.slot, 10);

            // ⬇⬇⬇ ФИКСИРУЕМ drag ДО сброса флагов ⬇⬇⬇
            isDragging = false;

            if (mode === 'remove') {
                if (typeof applyRemoveMode === 'function') {
                    applyRemoveMode();
                }
            } else {
                if (typeof applyRange === 'function') {
                    applyRange(startSlot, endSlot);
                }
            }

            // ⬇⬇⬇ КЛЮЧЕВОЕ: СБРОС startSlot ПОСЛЕ ОПЕРАЦИИ ⬇⬇⬇
            startSlot = null;
            // ⬆⬆⬆

            setTimeout(() => {
                if (typeof fetchScheduleSnapshot === 'function') {
                    fetchScheduleSnapshot(() => {
                        renderFromState();
                        renderBookings();
                    });
                }
            }, 300);

            clearSelection();
            e.stopPropagation();
        });

    });

    document.addEventListener('mouseup', () => {
        if (!isDragging) return;
        if (typeof clearSelection !== 'function') return;

        isDragging = false;
        startSlot = null; // ⬅⬅⬅ ГЛОБАЛЬНЫЙ СБРОС
        clearSelection();
    });

}
</script>

<script>
/* ===============================
   Глобальный mousedown — возврат в VIEW
   =============================== */

document.addEventListener('mousedown', (e) => {

    if (!wrapper) return;
	if (e.target.closest('#br-booking-info')) {
        return;
    }
    // Кнопки режимов — НЕ сбрасываем
    if (e.target.closest('#btn-add, #btn-remove, #btn-breaks')) {
        return;
    }

    // Клик внутри шкалы — НЕ сбрасываем
    if (e.target.closest('.br-schedule')) {
        return;
    }

    // === ВОЗВРАТ В VIEW ===
    mode = 'view';

    wrapper.classList.remove(
        'br-mode-add',
        'br-mode-remove',
        'br-mode-breaks'
    );
    wrapper.classList.add('br-mode-view');

    // визуальный сброс
    if (typeof clearSelection === 'function') {
        clearSelection();
    }

    const info = document.getElementById('br-booking-info');
    if (info) {
        info.innerHTML = '';
    }

}, true);


</script>

</div>
    </div>
</div>

