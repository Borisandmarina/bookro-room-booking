=== BookRo Room Booking ===
Contributors: borisdevin
Tags: booking, room booking, calendar, reservation, meeting room
Requires at least: 6.9
Tested up to: 6.9
Requires PHP: 7.4
Stable tag: 4.2.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Conference room booking plugin based on hourly time slots with visual daily schedule management.

== Description ==

BookRo Room Booking is a conference room booking plugin built around hourly time slots and a visual daily schedule timeline.

It allows administrators to manage bookings, working hours, breaks and pricing directly from the WordPress dashboard, while users can book rooms through a simple frontend form.

= Key Features =

* Visual daily timeline (24 hourly slots)
* Booking management table
* Booking status control (pending / confirmed)
* HTML export
* Excel export
* Single object (room) support
* Working hours configuration
* Breaks and overrides
* Base rental rate and currency
* Event types (including custom type)
* Frontend booking form via shortcode
* Email export
* Automatic customer data prefill by email

= Frontend Booking Form =

Use the shortcode:

`[booking_room_form]`

Place it on any page to display the booking form.

= Pricing Model =

The plugin includes base rental rate support.

== Privacy & Data Handling ==

BookRo Room Booking stores booking and contact information in custom database tables.

Personal contact data is stored in:

- 1br_admin_contacts
- 1br_admin_contact_objects
- 1br_user_contacts

When the plugin is deleted from WordPress (Delete action, not just Deactivate), all personal contact data stored in these tables is permanently removed from the database.

== External Services ==

= Email notifications =

This plugin sends booking notifications using the standard WordPress email system (`wp_mail`).

Email delivery depends on the hosting provider or configured SMTP service.

No data is transmitted to third-party services by default.

== Frequently Asked Questions ==

= Does the plugin support multiple rooms? =

This version supports one object (room).

= How are time slots calculated? =

The plugin uses 24 hourly slots per day by default.

= Does uninstall remove data? =

Yes. When the plugin is deleted, personal contact data tables are automatically removed.

== Screenshots ==

1. Admin daily timeline
2. Booking management table
3. Frontend booking form
4. Working hours settings
5. Breaks and overrides management
6. Base rental rate settings
7. Event types management
8. Export interface
9. Users management

== Changelog ==

= 1.0.0 =
* Initial release
* Hourly booking system
* Visual schedule timeline
* Email and Excel export
* GDPR-compliant uninstall removal of personal data