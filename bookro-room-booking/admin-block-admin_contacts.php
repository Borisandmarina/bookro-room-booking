<?php
/**
 * ------------------------------------------------------------
 * Файл: wp-content/plugins/booking-room/admin-block-admin_contacts.php
 * Назначение:
 *  - управление контактами администраторов
 *  - таблица: wp_1br_admin_contacts
 * ------------------------------------------------------------
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * ДОСТУПНЫ:
 *  - $current_object_id
 *  - $wpdb
 */
?>

<div class="br-admin-block">

    <div class="br-admin-block-header">
        <h2>Administrator contacts</h2>
    </div>

    <div class="br-admin-block-body">

        <!-- Add form -->
        <div style="display:flex; gap:8px; flex-wrap:wrap; margin-bottom:10px;">
            <input type="email" id="br-contact-email" placeholder="Email *" required>
            <input type="text" id="br-contact-first-name" placeholder="First name">
            <input type="text" id="br-contact-last-name" placeholder="Last name">
            <input type="text" id="br-contact-position" placeholder="Position">
            <input type="text" id="br-contact-phone" placeholder="Phone">
            <button type="button" class="button button-primary" id="br-add-contact">
                Add
            </button>
        </div>

        <!-- Table -->
        <table class="widefat striped">
            <thead>
                <tr>
                    <th>Email</th>
                    <th>First name</th>
                    <th>Last name</th>
                    <th>Position</th>
                    <th>Phone</th>
                    <th style="width:120px;"></th>
                </tr>
            </thead>
            <tbody id="br-admin-contacts-table"></tbody>
        </table>

    </div>

</div>

<style>
.br-admin-contacts-email {
    white-space: nowrap;
}
.br-admin-contacts-email[contenteditable="true"] {
    outline: none;
}
.br-admin-contacts-email.invalid {
    background:#ffeaea;
}
</style>

<script>
/* ============================================================
   Helpers
   ============================================================ */

function brCleanEmailInput(value) {
    return value.replace(/\s+/g, '').trim();
}

/* ============================================================
   Load contacts
   ============================================================ */

function brFetchAdminContacts() {

    fetch(ajaxurl, {
        method: 'POST',
        headers: {'Content-Type':'application/x-www-form-urlencoded'},
        body: new URLSearchParams({
            action: 'br_get_admin_contacts',
            object_id: <?php echo (int) $current_object_id; ?>
        })
    })
    .then(r => r.json())
    .then(resp => {
        if (!resp || !resp.success) return;

        const tbody = document.getElementById('br-admin-contacts-table');
        tbody.innerHTML = '';

        resp.data.forEach(row => {

            const eye = row.is_active == 1 ? '👁' : '🚫';

            const tr = document.createElement('tr');
            tr.innerHTML = `
                <td contenteditable="true"
                    class="br-admin-contacts-email"
                    data-field="email"
                    data-id="${row.id}">
                    ${row.email}
                </td>
                <td contenteditable="true" data-field="first_name" data-id="${row.id}">${row.first_name ?? ''}</td>
                <td contenteditable="true" data-field="last_name" data-id="${row.id}">${row.last_name ?? ''}</td>
                <td contenteditable="true" data-field="position" data-id="${row.id}">${row.position ?? ''}</td>
                <td contenteditable="true" data-field="phone" data-id="${row.id}">${row.phone ?? ''}</td>
                <td>
                    <button class="button button-small" onclick="brToggleAdminContact(${row.id})">${eye}</button>
                    <button class="button button-small button-link-delete" onclick="brDeleteAdminContact(${row.id})">Delete</button>
                </td>
            `;
            tbody.appendChild(tr);
        });

        brBindInlineEdit();
    });
}

/* ============================================================
   Inline edit (with email mask)
   ============================================================ */

function brBindInlineEdit() {

    document.querySelectorAll('#br-admin-contacts-table td[contenteditable]')
        .forEach(td => {

            let original = td.innerText.trim();

            td.addEventListener('keydown', e => {
                if (e.key === 'Enter') {
                    e.preventDefault();
                    td.blur();
                }
            });

            td.addEventListener('blur', () => {

                let value = td.innerText.trim();
                const id    = td.dataset.id;
                const field = td.dataset.field;

                if (field === 'email') {
                    value = brCleanEmailInput(value);
                    td.innerText = value;
                }

                if (value === original) return;

                fetch(ajaxurl, {
                    method: 'POST',
                    headers: {'Content-Type':'application/x-www-form-urlencoded'},
                    body: new URLSearchParams({
                        action: 'br_update_admin_contact',
                        object_id: <?php echo (int) $current_object_id; ?>,
                        id: id,
                        field: field,
                        value: value
                    })
                })
                .then(r => r.json())
                .then(resp => {
                    if (!resp || !resp.success) {
                        td.classList.add('invalid');
                        td.innerText = original;
                        setTimeout(() => td.classList.remove('invalid'), 800);
                        return;
                    }
                    original = value;
                });
            });
        });
}

/* ============================================================
   Add contact
   ============================================================ */

document.getElementById('br-add-contact').addEventListener('click', () => {

    const email = brCleanEmailInput(document.getElementById('br-contact-email').value);
    if (!email) return alert('Email is required');

    fetch(ajaxurl, {
        method: 'POST',
        headers: {'Content-Type':'application/x-www-form-urlencoded'},
        body: new URLSearchParams({
            action: 'br_add_admin_contact',
            object_id: <?php echo (int) $current_object_id; ?>,
            email: email,
            first_name: document.getElementById('br-contact-first-name').value,
            last_name: document.getElementById('br-contact-last-name').value,
            position: document.getElementById('br-contact-position').value,
            phone: document.getElementById('br-contact-phone').value
        })
    })
    .then(r => r.json())
    .then(resp => {
        if (!resp || !resp.success) {
            alert('Invalid email');
            return;
        }

        document.querySelectorAll(
            '#br-contact-email,#br-contact-first-name,#br-contact-last-name,#br-contact-position,#br-contact-phone'
        ).forEach(i => i.value = '');

        brFetchAdminContacts();
    });
});

/* ============================================================
   Toggle / Delete
   ============================================================ */

function brToggleAdminContact(id) {
    fetch(ajaxurl, {
        method:'POST',
        headers:{'Content-Type':'application/x-www-form-urlencoded'},
        body:new URLSearchParams({
            action:'br_toggle_admin_contact',
            object_id:<?php echo (int) $current_object_id; ?>,
            id:id
        })
    }).then(() => brFetchAdminContacts());
}

function brDeleteAdminContact(id) {
    if (!confirm('Delete contact?')) return;
    fetch(ajaxurl, {
        method:'POST',
        headers:{'Content-Type':'application/x-www-form-urlencoded'},
        body:new URLSearchParams({
            action:'br_delete_admin_contact',
            object_id:<?php echo (int) $current_object_id; ?>,
            id:id
        })
    }).then(() => brFetchAdminContacts());
}

/* ============================================================
   Initial load
   ============================================================ */

document.addEventListener('DOMContentLoaded', brFetchAdminContacts);
</script>
