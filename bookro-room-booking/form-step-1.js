/**
 * ------------------------------------------------------------
 * File: wp-content/plugins/booking-room/assets/js/form-step-1.js
 *
 * Purpose:
 *  - Step 1 booking form logic
 *  - Load & revalidate availability via AJAX
 * ------------------------------------------------------------
 */

document.addEventListener('DOMContentLoaded', () => {

    const form = document.getElementById('rb-booking-form');
    if (!form) return;

    const objectSelect   = form.querySelector('[name="object_id"]');
    const durationSelect = form.querySelector('[name="duration_slots"]');
    const dateInput      = form.querySelector('[name="event_date"]');
    const startSelect    = form.querySelector('[name="slot_start"]');

    if (!objectSelect || !durationSelect || !dateInput || !startSelect) {
        return;
    }

    /* ========================================================
       INITIAL STATE
       ======================================================== */

    durationSelect.value = '';
    durationSelect.disabled = true;

    dateInput.value = '';
    dateInput.readOnly = true;
    dateInput.disabled = true;

    startSelect.innerHTML = '';
    startSelect.disabled = true;

    /* ========================================================
       HELPERS
       ======================================================== */

    function resetStartSlots() {
        startSelect.innerHTML = '';
        startSelect.disabled = true;
    }

    function applyAvailableSlots(availableSlots) {

        startSelect.innerHTML = '';
        startSelect.disabled = true;

        if (!Array.isArray(availableSlots) || availableSlots.length === 0) {
            return;
        }

        availableSlots.forEach(slot => {
            const opt = document.createElement('option');
            opt.value = slot;
            opt.textContent = String(slot).padStart(2, '0') + ':00';
            startSelect.appendChild(opt);
        });

        startSelect.disabled = false;

        if (
            startSelect.value &&
            !availableSlots.includes(parseInt(startSelect.value, 10))
        ) {
            startSelect.value = '';
        }

        document.dispatchEvent(new Event('rb-step1-ready'));
    }

    /* ========================================================
       CORE: LOAD AVAILABILITY
       ======================================================== */

    function loadAvailability() {

        const objectId = objectSelect.value;
        const date     = dateInput.value;
        const duration = parseInt(durationSelect.value, 10);

        if (!objectId || !date || !duration) {
            resetStartSlots();
            return;
        }

        resetStartSlots();

        const params = new URLSearchParams();
        params.append('action', 'br_get_available_start_slots');
        params.append('object_id', objectId);
        params.append('date', date);
        params.append('duration_slots', duration);

        fetch(window.brBookingFormConfig.ajaxUrl, {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: params.toString()
        })
        .then(r => r.json())
        .then(resp => {
            if (resp && resp.success) {
                applyAvailableSlots(resp.data.available_slots || []);
            } else {
                resetStartSlots();
            }
        })
        .catch(() => resetStartSlots());
    }

    /* ========================================================
       STEP FLOW
       ======================================================== */

    // STEP 1 → 2
    objectSelect.addEventListener('change', () => {
        durationSelect.value = '';
        durationSelect.disabled = !objectSelect.value;

        dateInput.value = '';
        dateInput.disabled = true;
        dateInput.readOnly = true;

        resetStartSlots();
    });

    // STEP 2 → 3
    durationSelect.addEventListener('change', () => {

        dateInput.value = '';
        dateInput.disabled = false;
        dateInput.readOnly = false;

        resetStartSlots();

        if (!durationSelect.value) return;

        if (dateInput._pikaday) {
            dateInput._pikaday.destroy();
            dateInput._pikaday = null;
        }

        const horizonDays = {};

        fetch(window.brBookingFormConfig.ajaxUrl, {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: new URLSearchParams({
                action: 'br_get_available_days_horizon',
                object_id: objectSelect.value,
                duration_slots: durationSelect.value
            })
        })
        .then(r => r.json())
        .then(resp => {

            if (resp && resp.success && resp.data && resp.data.days) {
                Object.assign(horizonDays, resp.data.days);
            }

            dateInput._pikaday = new Pikaday({
                field: dateInput,
                format: 'YYYY-MM-DD',
                trigger: dateInput,

                disableDayFn: function (day) {
                    const d =
                        day.getFullYear() + '-' +
                        String(day.getMonth() + 1).padStart(2, '0') + '-' +
                        String(day.getDate()).padStart(2, '0');

                    return !(d in horizonDays) || horizonDays[d] !== true;
                },

                onSelect: function () {
                    loadAvailability();
                }
            });

        });

        dateInput.addEventListener('focus', function () {
            if (dateInput._pikaday) {
                dateInput._pikaday.show();
            }
        });

    });

    // STEP 3 → 4
    dateInput.addEventListener('change', loadAvailability);

});
