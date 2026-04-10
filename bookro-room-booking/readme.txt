=== BookRo Room Booking ===
Contributors: borisdevin
Tags: booking, room booking, conference room, calendar, schedule, reservation, meeting room, hourly booking, event booking
Requires at least: 6.9
Tested up to: 6.9
Requires PHP: 7.4
Stable tag: 4.1.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Conference room booking plugin based on hourly time slots with visual daily schedule management.

== Description ==

Booking Room is a professional conference room booking plugin built around hourly time slots and a visual daily schedule timeline.

It allows administrators to manage bookings, working hours, breaks and pricing directly from the WordPress dashboard, while users can book rooms through a simple frontend form.

= Key Features (Lite) =

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

The Lite version includes base rental rate support.

Advanced pricing coefficients are available in the PRO version.

== Upgrade to PRO ==

The PRO version extends Booking Room with advanced functionality:

* Unlimited objects (multiple rooms)
* Visitors count with pricing coefficient
* Additional services (hourly and one-time)
* Advanced pricing coefficients:
  - Time of day
  - Weekend
  - Holiday
  - Participant-based
  - Service-based
  - Combined coefficients
* Holiday management with allow_booking control
* Google Sheets export
* Google Calendar export
* Telegram export
* Advanced tariff management

Some advanced features are available in the PRO version.

== Privacy & Data Handling ==

Booking Room stores booking and contact information in custom database tables.

Personal contact data is stored in:

- 1br_admin_contacts
- 1br_admin_contact_objects
- 1br_user_contacts

When the plugin is deleted from WordPress (Delete action, not just Deactivate), all personal contact data stored in these tables is permanently removed from the database.

== Frequently Asked Questions ==

= Does the plugin support multiple rooms? =

The Lite version supports one object (room).  
The PRO version supports unlimited rooms.

= How are time slots calculated? =

The plugin uses 24 hourly slots per day by default.

= Does uninstall remove data? =

Yes. When the plugin is deleted, personal contact data tables are automatically removed.

= Does it support Google Calendar? =

Google Calendar export is available in the PRO version.

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
10. PRO features preview

== Changelog ==

= 1.0.0 =
* Initial release
* Hourly booking system
* Visual schedule timeline
* Email and Excel export
* GDPR-compliant uninstall removal of personal data
