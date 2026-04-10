<?php
/**
 * ------------------------------------------------------------
 * Файл: wp-content/plugins/booking-room/form.php
 *
 * Назначение:
 *  - Frontend-форма бронирования зала
 *  - Одна страница, три шага
 *  - Жёсткая последовательность ввода
 *
 * МОДЕЛЬ ВРЕМЕНИ:
 *  - 1 слот = 1 час
 *  - 24 слота в сутках (0–23)
 *
 * РЕАЛИЗОВАНО:
 *  - Шаг 1 (UI):
 *      1) выбор объекта (зала)
 *      2) длительность аренды (в слотах)
 *      3) дата
 *      4) время начала (номер слота)
 * ------------------------------------------------------------
 */

defined('ABSPATH') || exit;

if ( ! function_exists('rb_render_booking_form') ) {

    function rb_render_booking_form() {

        

        global $wpdb;

        /* ============================================================
           ОБЪЕКТЫ (ТОЛЬКО ACTIVE)
           ============================================================ */
        $objects = $wpdb->get_results(
            "
            SELECT id, name
            FROM {$wpdb->prefix}1br_objects
            WHERE status = 'active'
            ORDER BY id ASC
            ",
            ARRAY_A
        );

        /* ============================================================
           ДЛИТЕЛЬНОСТИ: 1–24 слота
           ============================================================ */
        $durations = [];
        for ($i = 1; $i <= 24; $i++) {
            $durations[] = $i;
        }

        /* ============================================================
           ВРЕМЕННЫЕ СЛОТЫ: 0–23
           ============================================================ */
        $time_slots = [];
        for ($slot = 0; $slot < 24; $slot++) {
            $time_slots[] = [
                'slot'  => $slot,
                'label' => sprintf('%02d:00', $slot),
            ];
        }
		/* ============================================================
		   STEP 2 — DATA FROM DB
		   ============================================================ */

$event_types = $wpdb->get_results(
    "SELECT id, label, object_id
     FROM {$wpdb->prefix}1br_event_types
     WHERE is_visible = 1
     ORDER BY sort_order ASC, id ASC",
    ARRAY_A
);


/* ============================================================
           RENTAL RATE (currency only)
           ============================================================ */
        $rental_rate = $wpdb->get_row(
            "
            SELECT currency
            FROM {$wpdb->prefix}1br_rental_rate
            LIMIT 1
            ",
            ARRAY_A
        );

        $currency = isset($rental_rate['currency'])
            ? $rental_rate['currency']
            : '';
        ob_start();
		
        ?>

      <style>
.rb-shortcode-root {
    width: 100%;
}

.rb-shortcode-root .rb-wrapper {
    max-width: 350px;
    margin-left: auto;
    margin-right: auto;
}


/* ===============================
   Base (scoped)
   =============================== */
.rb-wrapper label {
    margin-bottom: 0rem;
    font-size: 18px;
    color: #03549b;
}


/* ===============================
   Breadcrumbs
   =============================== */
.rb-wrapper .rb-breadcrumbs {
    display: flex;
    justify-content: space-between;
    margin-bottom: 1px;
    font-weight: 600;
    font-size: 18px;
}

.rb-wrapper .rb-breadcrumb {
    color: #999;
}

.rb-wrapper .rb-breadcrumb.active {
    color: #1e88e5;
}

/* ===============================
   Card
   =============================== */
.rb-wrapper .rb-card {
	max-width: 350px;
    margin: 10px 10px 10px 10px;
    padding: 5px 25px 25px 25px;
    border-radius: 12px;
    background: #fff;
    box-shadow: 0 8px 30px rgba(0,0,0,0.08);
}

/* ===============================
   Steps (CRITICAL)
   =============================== */
.rb-wrapper .rb-step-content {
    display: none;
}

.rb-wrapper .rb-step-content.active {
    display: block;
}

/* ===============================
   Fields
   =============================== */
.rb-wrapper .rb-field {
    margin-bottom: 15px;
}

.rb-wrapper .rb-label {
    display: flex;
    align-items: center;
    gap: 10px;
    font-weight: 400;
    margin-bottom: 1px;
}

.rb-wrapper .rb-label span {
    width: 28px;
    height: 28px;
    border-radius: 50%;
    background: #1e88e5;
    color: #fff;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    font-size: 18px;
}

.rb-wrapper .rb-field select,
.rb-wrapper .rb-field input {
    width: 100%;
    padding: 10px;
    border-radius: 6px;
    border: 1px solid #ccc;
    font-size: 16px;
}

/* ===============================
   Step 2 summary
   =============================== */
.rb-wrapper .rb-step-summary {
    margin-bottom: 16px;
    line-height: 1.6;
    font-size: 16px;
}

/* ===============================
   Actions (buttons)
   =============================== */
.rb-wrapper .rb-actions {
    display: flex;
    gap: 10px;
    margin: 20px auto 0;
    max-width: 420px;
}

.rb-wrapper .rb-button {
    flex: 1;
    padding: 5px;
    background: #28a745;
    color: #fff;
    border: none;
    border-radius: 6px;
    font-size: 18px;
    font-weight: 600;
    cursor: pointer;
}

.rb-wrapper .rb-button.secondary {
    background: #6c757d;
}

.rb-wrapper .rb-button:hover {
    background: #218838;
}

.rb-wrapper .rb-button.secondary:hover {
    background: #5a6268;
}

.rb-wrapper .rb-button:disabled {
    opacity: 0.6;
    cursor: not-allowed;
}

/* ===============================
   Submit success
   =============================== */
.rb-wrapper #rb-submit-success {
    margin: 16px auto 0;
    max-width: 420px;
    padding: 14px 16px;
    border: 2px solid #2e7d32;
    background-color: #f1f8f4;
    color: #1b5e20;
    font-weight: 600;
    border-radius: 6px;
}

/* ===============================
   Step 1 — Visual states
   =============================== */
.rb-wrapper .rb-field.rb-locked {
    opacity: 0.45;
}

.rb-wrapper .rb-field.rb-active {
    opacity: 1;
}

.rb-wrapper .rb-field.rb-locked .rb-label span {
    background: #ccc;
    color: #666;
}

.rb-wrapper .rb-field.rb-active .rb-label span {
    background: #1e88e5;
    color: #fff;
}

/* ===============================
   Rental cost currency alignment
   =============================== */
.rb-wrapper .rb-field input[name="rental_cost"] + span {
    display: inline-flex;
    align-items: center;
    font-size: 16px;
    line-height: 1;
    transform: translateY(-4px);
}

</style>

        <div class="rb-wrapper">

    

    <div class="rb-card">

        <form id="rb-booking-form">
		<?php wp_nonce_field('br_submit_booking', '_wpnonce'); ?>


           <!-- STEP 1 -->
<div class="rb-step-content active" data-step="1">
<!-- Breadcrumbs -->
    <div class="rb-breadcrumbs">
        <div class="rb-breadcrumb active" data-step="1">Step 1</div>
        <div class="rb-breadcrumb" data-step="2">Step 2</div>
        <div class="rb-breadcrumb" data-step="3">Step 3</div>
    </div>
	<hr>
                <!-- 1. Object -->
                <div class="rb-field rb-active">
                    <div class="rb-label">
                        <span>1</span>
                        <label>ROOM</label>
                    </div>
                    <select name="object_id" id="br-object-id" required>
                       <option value="">Select room</option>
                        <?php foreach ($objects as $obj): ?>
                            <option value="<?php echo (int) $obj['id']; ?>">
                                <?php echo esc_html($obj['name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <!-- 2. Duration -->
                <div class="rb-field rb-locked">
                    <div class="rb-label">
                        <span>2</span>
                        <label>Duration (hours)</label>
                    </div>
                    <select name="duration_slots" id="br-duration-slots" required>
                        <?php foreach ($durations as $d): ?>
                            <option value="<?php echo esc_attr($d); ?>">
                                <?php echo esc_html($d); ?>h
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <!-- 3. Date -->
				<div class="rb-field rb-locked">
					<div class="rb-label">
						<span>3</span>
						<label>Select booking date</label>
					</div>
					<input
						type="text"
						id="br-booking-date"
						name="event_date"
					>
				</div>

                <!-- 4. Start slot -->
                <div class="rb-field rb-locked">
                    <div class="rb-label">
                        <span>4</span>
                        <label>Start time</label>
                    </div>
                    <select name="slot_start" required disabled></select>
                </div>

            </div>

<!-- STEP 2 -->

<div class="rb-step-content" data-step="2">
<!-- Breadcrumbs -->
    <div class="rb-breadcrumbs">
        <div class="rb-breadcrumb active" data-step="1">Step 1</div>
        <div class="rb-breadcrumb" data-step="2">Step 2</div>
        <div class="rb-breadcrumb" data-step="3">Step 3</div>
    </div>
	<hr>
    <!-- Summary from Step 1 -->
    <div class="rb-step-summary" id="rb-step-summary"></div>

    <hr>

<div class="rb-field">
    <div class="rb-label"><label>Event type *</label></div>
    <select name="event_type_id" id="rb-event-type">
        <option value="">Select event type</option>
        <?php foreach ($event_types as $row): ?>
            <option
                value="<?php echo (int)$row['id']; ?>"
                data-object-id="<?php echo (int)$row['object_id']; ?>"
            >
                <?php echo esc_html($row['label']); ?>
            </option>
        <?php endforeach; ?>
        <option value="other">Other</option>
    </select>
</div>

<div class="rb-field" id="rb-event-type-other" style="display:none;">
    <div class="rb-label"><label>Other event type *</label></div>
    <input type="text" name="event_type_other">
</div>

    <div class="rb-field">
        <div class="rb-label"><label>Additional information</label></div>
        <textarea name="additional_info" rows="4"></textarea>
    </div>

    <div class="rb-field">
        <div class="rb-label"><label>Rental cost</label></div>
        <div style="display:flex; gap:8px;">
            <input type="text" name="rental_cost" readonly placeholder="—" style="flex:1;">
            <span><?php echo esc_html($currency ?: ''); ?></span>
        </div>
    </div>

</div>

<!-- STEP 3 -->
<div class="rb-step-content" data-step="3">
<!-- Breadcrumbs -->
    <div class="rb-breadcrumbs">
        <div class="rb-breadcrumb active" data-step="1">Step 1</div>
        <div class="rb-breadcrumb" data-step="2">Step 2</div>
        <div class="rb-breadcrumb" data-step="3">Step 3</div>
    </div>
	<hr>
    <div class="rb-field">
        <div class="rb-label"><label>Email *</label></div>
        <input type="email" name="client_email" required>
    </div>
	
	<div class="rb-field">
        <div class="rb-label"><label>Company</label></div>
        <input type="text" name="client_company">
    </div>

    <div class="rb-field">
        <div class="rb-label"><label>First name</label></div>
        <input type="text" name="client_name">
    </div>

    <div class="rb-field">
        <div class="rb-label"><label>Last name</label></div>
        <input type="text" name="client_surname">
    </div>

    <div class="rb-field">
        <div class="rb-label"><label>Phone</label></div>
        <input type="text" name="client_phone">
    </div>
<div class="rb-field">
    <label style="display:flex; gap:8px; align-items:flex-start;">
        <input type="checkbox" name="privacy_consent" required>
        <span style="font-size:14px; line-height:1.4;">
            I agree that my personal data will be stored and used to process my booking request.
        </span>
    </label>
</div>
<div class="rb-field" style="font-size:13px; color:#666;">
    Your email is used only to process your booking request and is stored in the website database.
</div>

    <!-- REAL SUBMIT BUTTON -->
    <div class="rb-field">
        <button type="submit" class="rb-button" id="rb-submit" style="display:none;">
            Submit booking
        </button>
    </div>

</div>

</form>

<!-- NAVIGATION BUTTONS (ОБЯЗАТЕЛЬНО ВНЕ form-step-content) -->
<div class="rb-actions">
    <button type="button" class="rb-button secondary" id="rb-prev" disabled>Back</button>
    <button type="button" class="rb-button" id="rb-next">Next</button>
</div>

<div id="rb-submit-success" style="display:none;"></div>
</div>
<script>
document.addEventListener('DOMContentLoaded', function () {

    var step1 = document.querySelector('.rb-step-content[data-step="1"]');
    if (!step1) return;

    var fields = step1.querySelectorAll('.rb-field');
    if (fields.length < 4) return;

    var fieldObject   = fields[0];
    var fieldDuration = fields[1];
    var fieldDate     = fields[2];
    var fieldTime     = fields[3];

    var objectSelect   = step1.querySelector('[name="object_id"]');
    var durationSelect = step1.querySelector('[name="duration_slots"]');
    var dateInput      = step1.querySelector('[name="event_date"]');
    var startSelect    = step1.querySelector('[name="slot_start"]');

    function activate(field) {
        field.classList.remove('rb-locked');
        field.classList.add('rb-active');

        field.querySelectorAll('input, select, textarea').forEach(function (el) {
            el.disabled = false;
            el.readOnly = false;
        });
    }

    function lock(field) {
        field.classList.remove('rb-active');
        field.classList.add('rb-locked');

        field.querySelectorAll('input, select, textarea').forEach(function (el) {
            el.disabled = true;
        });
    }

    function fillStartSlots(select) {
        if (!select) return;

        select.innerHTML = '';

        for (var i = 0; i < 24; i++) {
            var opt = document.createElement('option');
            opt.value = i;
            opt.textContent = (i < 10 ? '0' : '') + i + ':00';
            select.appendChild(opt);
        }

        select.selectedIndex = 0;
    }

    // initial state
    activate(fieldObject);
    lock(fieldDuration);
    lock(fieldDate);
    lock(fieldTime);

    // 1 → 2
    objectSelect.addEventListener('change', function () {
        if (this.value) {
            activate(fieldDuration);
        } else {
            lock(fieldDuration);
            lock(fieldDate);
            lock(fieldTime);
        }
    });

    // 2 → 3
    durationSelect.addEventListener('change', function () {
        if (this.value) {
            activate(fieldDate);
        } else {
            lock(fieldDate);
            lock(fieldTime);
        }
    });

    // 3 → 4
    dateInput.addEventListener('change', function () {
        if (this.value) {
            activate(fieldTime);
            fillStartSlots(startSelect);
        } else {
            lock(fieldTime);
        }
    });

});
</script>

<script>
    window.brBookingFormConfig = {
        ajaxUrl: "<?php echo esc_url( admin_url('admin-ajax.php') ); ?>"

    };
</script>
<script>
(function () {

    var currentStep = 1;
    var maxStep = 3;

    var steps  = document.querySelectorAll('.rb-step-content');
    var crumbs = document.querySelectorAll('.rb-breadcrumb');

    var btnNext = document.getElementById('rb-next');
    var btnPrev = document.getElementById('rb-prev');

    var form = document.getElementById('rb-booking-form');

    /* ============================================================
       Helpers (ES5-safe)
       ============================================================ */

    function slotToTime(slot) {
        var h = String(slot || 0);
        if (h.length < 2) h = '0' + h;
        return h + ':00';
    }

    function formatDateEN(dateStr) {
        if (!dateStr) return '';

        // ожидаем DD.MM.YYYY
        var parts = dateStr.split('.');
        if (parts.length !== 3) return dateStr;

        var d = parts[0];
        var m = parts[1];
        var y = parts[2];

        var date = new Date(y + '-' + m + '-' + d);

        return date.toLocaleDateString('en-GB', {
            weekday: 'long',
            day: '2-digit',
            month: '2-digit',
            year: 'numeric'
        });
    }

    /* ============================================================
   STEP 1 validation (READ ONLY)
   ============================================================ */

function isStep1Valid() {
    if (!form) return false;

    var required = [
        '[name="object_id"]',
        '[name="duration_slots"]',
        '[name="event_date"]',
        '[name="slot_start"]'
    ];

    for (var i = 0; i < required.length; i++) {
        var el = form.querySelector(required[i]);
        if (!el || !el.value) return false;
    }

    return true;
}

/* ============================================================
   STEP 2 validation (READ ONLY)
   ============================================================ */

function isStep2Valid() {
    if (!form) return false;

    var eventType  = form.querySelector('[name="event_type_id"]');
    var eventOther = form.querySelector('[name="event_type_other"]');

    if (!eventType || !eventType.value) {
        return false;
    }

    if (eventType.value === 'other') {
        if (!eventOther || !eventOther.value.trim()) {
            return false;
        }
    }

    return true;
}

/* ============================================================
   NEXT button state controller
   ============================================================ */

function updateNextButtonState() {

    if (currentStep === 1) {
        btnNext.disabled = !isStep1Valid();
        return;
    }

    if (currentStep === 2) {
        btnNext.disabled = !isStep2Valid();
        return;
    }

    btnNext.disabled = false;
}

/* ============================================================
   RESET step 1 (FULL RESET TO INITIAL STATE)
   ============================================================ */


function resetStep1() {
    if (!form) return;

    var objectSelect   = form.querySelector('[name="object_id"]');
    var durationSelect = form.querySelector('[name="duration_slots"]');
    var dateInput      = form.querySelector('[name="event_date"]');
    var startSelect    = form.querySelector('[name="slot_start"]');

    /* Object */
    if (objectSelect) {
        objectSelect.selectedIndex = 0;
    }

    /* Duration */
    if (durationSelect) {
        durationSelect.value = '';
        durationSelect.disabled = true;
    }

    /* Date — ПОЛНОСТЬЮ ЗАКРЫТО */
    if (dateInput) {
        dateInput.value = '';
        dateInput.readOnly = true;
        dateInput.disabled = true;
    }

    /* Start slot */
    if (startSelect) {
        startSelect.innerHTML = '';
        startSelect.disabled = true;
    }

    updateNextButtonState();
}



    /* ============================================================
       STEP 2 summary (READ ONLY SNAPSHOT)
       ============================================================ */

    function renderStep2Summary() {

        var summary = document.getElementById('rb-step-summary');
        if (!summary || !form) return;

        var objectSelect = form.querySelector('[name="object_id"]');
        var durationEl   = form.querySelector('[name="duration_slots"]');
        var dateEl       = form.querySelector('[name="event_date"]');
        var slotEl       = form.querySelector('[name="slot_start"]');

        var objectName = '';
        if (objectSelect && objectSelect.selectedOptions && objectSelect.selectedOptions[0]) {
            objectName = objectSelect.selectedOptions[0].text;
        }

        var duration  = durationEl ? parseInt(durationEl.value || 0, 10) : 0;
        var date      = dateEl ? dateEl.value : '';
        var slotStart = slotEl ? parseInt(slotEl.value || 0, 10) : 0;

        var startTime = slotToTime(slotStart);
        var endTime   = slotToTime(slotStart + duration);

        summary.innerHTML =
            '<div><strong>Object:</strong> ' + objectName + '</div>' +
            '<div>' + formatDateEN(date) + '</div>' +
            '<div>' + startTime + ' - ' + endTime + '</div>';
            /*'<div>Duration: ' + slotToTime(duration) + ' hours</div>';*/
    }
	
/* ============================================================
   Render
   ============================================================ */

function render() {

    for (var i = 0; i < steps.length; i++) {
        steps[i].classList.toggle(
            'active',
            parseInt(steps[i].dataset.step, 10) === currentStep
        );
    }

    for (var j = 0; j < crumbs.length; j++) {
        crumbs[j].classList.toggle(
            'active',
            parseInt(crumbs[j].dataset.step, 10) === currentStep
        );
    }

    btnPrev.disabled = currentStep === 1;
    btnNext.textContent = currentStep === maxStep ? 'Submit' : 'Next';

    updateNextButtonState();

    if (currentStep === 2) {
        renderStep2Summary();

        // первичный расчет цены при входе на Step 2
        if (typeof scheduleCalc === 'function') {
            scheduleCalc();
        }
    }
}



    /* ============================================================
       Navigation
       ============================================================ */

    btnNext.addEventListener('click', function () {

        if (btnNext.disabled) return;

        if (currentStep < maxStep) {
            currentStep++;
            render();
            return;
        }

        /* ============================================================
           FINAL SUBMIT (AJAX)
           ============================================================ */

        if (!form) return;
		var consent = form.querySelector('[name="privacy_consent"]');
if (!consent || !consent.checked) {
    alert('You must agree to data processing.');
    return;
}
        const data = new FormData(form);
        data.append('action', 'br_submit_booking');

        btnNext.disabled = true;

        fetch(window.brBookingFormConfig.ajaxUrl, {
            method: 'POST',
            body: data
        })
        .then(r => r.json())
        .then(resp => {

            btnNext.disabled = false;

            if (!resp || !resp.success) {
    alert('Booking error. Please try again.');
    return;
}

// показать инфоблок об успехе
var successBox = document.getElementById('rb-submit-success');
if (successBox) {
    successBox.textContent =
        'Booking successfully submitted. We will contact you shortly.';
    successBox.style.display = 'block';
}

// можно заблокировать кнопки
btnNext.disabled = true;
btnPrev.disabled = true;

// НЕ делаем reset формы
// НЕ возвращаемся на шаг 1

        })
        .catch(() => {
            btnNext.disabled = false;
            alert('Network error');
        });
    });

    btnPrev.addEventListener('click', function () {

        if (currentStep === 2) {
            resetStep1();
        }

        if (currentStep > 1) {
            currentStep--;
            render();
        }
    });

    /* ============================================================
       LISTEN STEP 1 READY (VARIANT 2 CORE)
       ============================================================ */


    document.addEventListener('rb-step1-ready', function () {
        if (currentStep === 1) {
            updateNextButtonState();
        }
    });

    if (form) {
        form.addEventListener('change', updateNextButtonState);
        form.addEventListener('input', updateNextButtonState);
    }

    document.addEventListener('DOMContentLoaded', render);

})();
</script>
<script>
document.addEventListener('DOMContentLoaded', function () {

    var select = document.getElementById('rb-event-type');
    var otherField = document.getElementById('rb-event-type-other');
    var otherInput = otherField
        ? otherField.querySelector('input')
        : null;

    if (!select || !otherField) return;

    function toggleOtherField() {
        if (select.value === 'other') {
            otherField.style.display = 'block';
        } else {
            otherField.style.display = 'none';
            if (otherInput) {
                otherInput.value = '';
            }
        }
    }

    // initial state
    toggleOtherField();

    // on change
    select.addEventListener('change', toggleOtherField);
});
</script>
<script>

document.addEventListener('DOMContentLoaded', function () {

    const form = document.getElementById('rb-booking-form');
    if (!form) return;

    const output = form.querySelector('[name="rental_cost"]');
    if (!output) return;

    let timer = null;

    function recalcPrice() {

    // гарантируем, что ключевые поля НЕ disabled
    form.querySelectorAll(
        '[name="object_id"], [name="duration_slots"], [name="event_date"], [name="slot_start"]'
    ).forEach(function (el) {
        el.disabled = false;
        el.readOnly = false;
    });

    const data = new FormData(form);
    data.append('action', 'br_calculate_price');
	data.append('_wpnonce', br_ajax.price_nonce);


    fetch(window.brBookingFormConfig.ajaxUrl, {
        method: 'POST',
        body: data
    })
    .then(r => r.json())
    .then(resp => {
        if (!resp || !resp.success) return;
        output.value = resp.data.total;

var currencySpan = output.nextElementSibling;
if (currencySpan && resp.data.currency) {
    currencySpan.textContent = resp.data.currency;
}

    })
    .catch(() => {});
}

function scheduleCalc() {
    clearTimeout(timer);
    timer = setTimeout(recalcPrice, 300);
}

form.addEventListener('change', scheduleCalc);
form.addEventListener('input', scheduleCalc);
});

</script>
<script>
document.addEventListener('DOMContentLoaded', function () {

    /*
     * Step 2 data (Visitors / Event types / Services)
     * are rendered server-side via PHP.
     *
     * No dynamic loading is required here.
     * This block intentionally does nothing.
     */

});
</script>
<script>
document.addEventListener('DOMContentLoaded', function () {

    const objectSelect = document.getElementById('br-object-id');
    if (!objectSelect) return;

    function filterStep2ByObject(objectId) {

        // event types
        document.querySelectorAll(
            '[name="event_type_id"] option[data-object-id]'
        ).forEach(opt => {
            opt.style.display =
                opt.dataset.objectId === objectId ? '' : 'none';
        });
    }

    objectSelect.addEventListener('change', function () {
        if (this.value) {
            filterStep2ByObject(this.value);
        }
    });

});
</script>
<script>
document.addEventListener('DOMContentLoaded', function () {

    const form = document.getElementById('rb-booking-form');
    if (!form) return;

    form.addEventListener('submit', function (e) {
        e.preventDefault();

        const data = new FormData(form);
        data.append('action', 'br_submit_booking');

        fetch(window.brBookingFormConfig.ajaxUrl, {
            method: 'POST',
            body: data
        })
        .then(r => r.json())
        .then(resp => {

            if (!resp || !resp.success) {
                alert('Booking error');
                return;
            }

            console.log('BOOKING SAVED, ID:', resp.data.booking_id);

            alert('Booking successfully created');
            form.reset();
        })
        .catch(() => {
            alert('Network error');
        });
    });

});
</script>


<?php
return '<div class="rb-shortcode-root">' . ob_get_clean() . '</div>';

}
}
add_shortcode('booking_room_form', 'rb_render_booking_form');
