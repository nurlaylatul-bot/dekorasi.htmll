=== AllCoach - Coaching Management & Scheduling ===
Contributors: Themegrill
Tags: coaching, coach booking, client management, appointments, scheduling
Requires at least: 6.5
Tested up to: 6.9
Requires PHP: 8.2
Stable tag: 1.1.1
License: GNU General Public License v3.0
License URI: http://www.gnu.org/licenses/gpl-3.0.html

All-in-one coaching plugin to sell programs with PayPal, manage clients, book sessions, and automate notifications from WordPress.

== Description ==

### Run Your Coaching Business from WordPress

AllCoach is a complete coaching business management plugin for WordPress. Sell coaching programs, take payments with PayPal, deliver interactive client activities, schedule one-on-one sessions, and send automated notifications — all from a single dashboard.

Whether you are a personal trainer, life coach, nutritionist, business coach, or tutor, AllCoach gives you everything you need to run your practice without stitching together separate tools.

### Why Choose AllCoach?

- **Everything in One Place:** Programs, clients, sessions, orders, and notifications are all managed from your WordPress admin. No extra subscriptions or integrations needed.

- **Interactive Client Activities:** Deliver structured worksheets and assignments to clients. Clients submit responses directly from their portal and you review them from the admin dashboard.

- **Client-Friendly Portal:** Clients can access their programs, activities, sessions, meeting links, and profile from a dedicated frontend account area.

- **Google Meet Integration:** Connect your Google account once and meeting links can be generated automatically for sessions — no manual copy-pasting.

- **PayPal & Manual Payments:** Sell paid programs through PayPal — cards included — or confirm offline payments by hand. Free programs are confirmed automatically at checkout.

- **Know Your Numbers:** A built-in analytics dashboard shows how your programs, clients, orders, and bookings are performing over time.

- **Privacy Ready:** Collect privacy policy consent at checkout, and let clients' data flow through WordPress' own personal data export and erase tools.

### Who is AllCoach for?

- **Personal Trainers** — Sell training programs, deliver workout plans as activities, and schedule one-on-one sessions with automated reminders.
- **Life Coaches** — Manage client enrollments, assign reflection exercises, and meet clients via Google Meet.
- **Nutritionists** — Deliver structured nutrition programs with interactive check-ins and track client responses.
- **Business Coaches** — Sell paid programs, assign worksheets, confirm payments manually, and track client progress.
- **Tutors & Educators** — Organize content into programs with sections and activities, manage session bookings, and stay on top of client submissions.

### AllCoach Features

= Coaching Programs =

* Create and publish coaching programs with flexible pricing — paid or free
* Organize programs into sections for a structured delivery path
* Add interactive activities to each section
* Set enrollment limits or leave them unlimited
* Assign programs to categories for easy filtering and discovery
* Share a unique buy URL for direct enrollment

= Client Activities =

* Build activities with rich content and interactive form fields
* Supported field types: short answer, long answer, multiple choice, checkboxes, and date
* Clients complete and submit activities directly from their account portal
* Track completion status for every client per activity
* Review all client responses from the admin dashboard
* Reuse activities across programs with the activity library

= Client Management =

* Maintain a full client directory with enrollment history and session notes
* Track activity completion and session progress per client
* View all orders, sessions, and activity responses from a single client profile
* Guest checkout — new clients enroll with just their name and email, account created automatically

= Session Booking =

* Clients book one-on-one sessions directly from their account portal
* Session statuses: scheduled, completed, cancelled, no-show
* Full session history logged per client
* Timezone-aware booking with client timezone support

= Coach Availability =

* Define your weekly available days and time slots
* Bookings are automatically validated against your availability
* Double-booking prevented — for both you and your clients

= Meeting Types =

* **Google Meet** — connect your Google account and let AllCoach generate the link
* **Custom URL** — bring your own Zoom, Teams, or Whereby link
* **Face to face** — set a physical location for in-person sessions
* Meeting details are included in confirmation and reminder emails

= Google Meet Integration =

* Connect your Google account via OAuth from the Settings page
* Google Calendar events created automatically for every session
* Meeting links generated automatically and stored with each booking
* Meeting URL included in confirmation and reminder emails via the `{meeting_url}` placeholder

= PayPal Payments =

* Accept PayPal and card payments for paid programs
* Order status stays in sync with PayPal through void, decline, and refund webhooks
* Abandoned checkout orders are cleaned up automatically
* Enrollment and session booking are gated until the order is actually paid

= Order & Payment Management =

* Orders move through pending, confirmed, and cancelled statuses
* Confirm PayPal payments automatically, or confirm offline payments manually with an optional transaction reference
* Reactivate cancelled orders — set back to pending or directly to confirmed
* Bulk status actions for faster order management
* Free programs are confirmed automatically at checkout

= Analytics =

* Dashboard overview of programs, clients, orders, and bookings
* Track revenue and enrollment trends over time
* Choose which metrics you want to see

= Add-ons =

* Dedicated Add-ons page to see and enable available add-ons
* Turn individual features on or off per site
* Discover optional add-ons that extend AllCoach further

= Privacy & GDPR =

* Ask clients to accept your privacy policy at checkout, with consent recorded
* Orders, enrollments, bookings, and consent records are included in WordPress' personal data export
* The same data is removed when you run WordPress' personal data erase request

= Styling =

* Set your primary color and button color from the Styling settings tab
* Client-facing pages pick up your palette automatically

= Client Account Portal =

* Dedicated frontend dashboard for every client — no WordPress admin access required
* **Dashboard** — overview of enrolled programs and upcoming sessions
* **Programs** — browse and access all enrolled programs and their content
* **Activities** — view and complete assigned activities, submit responses
* **Appointments** — view upcoming sessions, book new sessions, join Google Meet
* **Profile** — update personal details and change password
* **Forgot password** — clients can request a reset link and set a new password without leaving the portal

== Installation ==

1. Upload the plugin files to the `/wp-content/plugins/allcoach` directory, or install the plugin through the WordPress plugins screen.
2. Activate the plugin through the Plugins screen in WordPress.
3. Go to AllCoach in your WordPress admin dashboard.
4. Create your coaching programs, sections, and activities.
5. Configure your weekly availability for session bookings.
6. Optionally connect your Google account from the AllCoach Settings page to enable Google Calendar and Google Meet integration.

== Frequently Asked Questions ==

= Do clients need a WordPress account to enroll? =
No. Clients enroll through the guest checkout flow using just their name and email. A client account is created automatically, and they receive a welcome email with a password reset link.

= Can I offer free programs? =
Yes. Set the program price to 0 and it will be marked as free. Free program enrollments are confirmed automatically at checkout.

= What payment methods are supported? =
AllCoach supports PayPal — including card payments through PayPal — and offline/manual payments. PayPal orders are confirmed automatically and stay in sync if a payment is later voided, declined, or refunded. Offline payments are confirmed by hand from the WordPress admin dashboard, with an optional transaction reference.

= What are activities? =
Activities are interactive worksheets, assignments, check-ins, or exercises that you create inside a program. Each activity can contain rich content and form fields such as short answer, long answer, multiple choice, checkboxes, and date fields. Clients complete and submit activities from their portal, and you review responses from the admin dashboard.

= How does session booking work? =
Clients book sessions directly from their account portal. AllCoach validates each booking request against your weekly availability schedule and rejects time slots that conflict with an existing booking for you or the client.

= Does AllCoach prevent double-booking? =
Yes. AllCoach checks existing sessions before confirming a booking and prevents overlapping bookings for both the coach and the client.

= Does AllCoach support client timezones? =
Yes. AllCoach supports timezone-aware booking so clients can book sessions based on their timezone while the coach manages availability from the WordPress dashboard.

= Does AllCoach support Google Meet? =
Yes. Connect your Google account via OAuth from the Settings page. Once connected, AllCoach can create a Google Calendar event and generate a Google Meet link automatically for each session booking. The meeting URL is sent to the client by email and displayed in their appointments page.

= Can I limit how many clients enroll in a program? =
Yes. Set a maximum enrollment count when creating or editing a program. Leave it at 0 for unlimited enrollments.

= Can clients access their programs without WordPress admin access? =
Yes. Clients use a dedicated frontend account portal to view programs, complete activities, manage appointments, and update their profile. They do not need access to the WordPress admin dashboard.

= Do sessions have to use Google Meet? =
No. Each session can use Google Meet, a custom meeting URL of your own (Zoom, Teams, Whereby, or anything else), or a face-to-face location for in-person coaching.

= Is AllCoach GDPR compliant? =
AllCoach gives you the tools to comply. You can require clients to accept your privacy policy at checkout, and the consent is recorded with a timestamp. Client orders, enrollments, session bookings, and consent records are wired into WordPress' built-in personal data export and erase tools, so a data request handled through Tools → Export Personal Data or Erase Personal Data covers AllCoach data too.

= Does AllCoach send any data to external services? =
Only when you enable a feature that needs one. Google APIs are contacted if you connect your Google account, and PayPal is contacted if you set up the PayPal gateway. See "Use of 3rd Party Services" below for details.

= What if a client forgets their password? =
Clients can request a reset link from the login screen of the account portal and set a new password themselves — no admin involvement needed.

== Use of 3rd Party Services ==

To provide certain features, AllCoach connects to the following external services when those features are enabled. No data is sent to these services unless the corresponding feature is explicitly configured and activated by the site administrator.

**Google APIs** — Used for Google Calendar and Google Meet integration. When a coach connects their Google account, AllCoach uses the Google OAuth 2.0 service to authenticate, the Google Calendar API to create and manage session events, and the Google Meet API to generate video call links. The coach's access token and refresh token are stored on your WordPress site. Client email addresses are shared with Google Calendar only when adding them as event attendees.

Service endpoints: `accounts.google.com`, `oauth2.googleapis.com`, `www.googleapis.com`

[Google Terms of Service](https://policies.google.com/terms) | [Google Privacy Policy](https://policies.google.com/privacy)

**PayPal** — Used to accept PayPal and card payments. When the PayPal gateway is configured and a client pays for a program, AllCoach sends the order amount, currency, program title, and the client's name and email address to PayPal in order to create and capture the payment. PayPal returns a transaction reference that is stored with the order, and sends webhook notifications back to your site so the order status stays correct when a payment is voided, declined, or refunded. Card details are entered on PayPal's own hosted fields and are never received or stored by AllCoach or your WordPress site.

Service endpoints: `api-m.paypal.com` (live), `api-m.sandbox.paypal.com` (sandbox), `www.paypal.com`

[PayPal Terms of Service](https://www.paypal.com/us/legalhub/useragreement-full) | [PayPal Privacy Policy](https://www.paypal.com/us/legalhub/privacy-full)

Each of these services is only contacted when the corresponding feature is enabled and configured by the site administrator. AllCoach does not transmit personal data to any external service beyond what is described above.

== Screenshots ==

1. AllCoach Programs – Admin dashboard showing the list of coaching programs.
2. Program Activities – Activity management screen for a selected program.
3. Activity Builder – Interface for creating and configuring program activities.
4. Availability Settings – Configure coach availability, schedules, and booking slots.
5. Payment Settings – Payment gateway and pricing configuration options.
6. Client Portal Dashboard – Overview dashboard for clients with upcoming sessions and activity.
7. Client Portal Appointments – Client view of scheduled and past appointments.
8. Booking Calendar – Calendar step of the client appointment booking process.
9. Time Selector – Available time slot selection during booking.
10. Booking Confirmation – Successful appointment booking confirmation screen.

== Changelog ==

= 1.1.1 - 08-05-2026 =
* Feature     - PayPal payments.
* Feature     - Analytics dashboard.
* Feature     - Add-ons page to enable and manage add-ons.
* Feature     - GDPR tools - privacy policy consent at checkout, plus support for WordPress' personal data export and erase requests.
* Feature     - Forgot password in the client portal.
* Feature     - Styling settings tab for primary and button colors.
* Feature     - Custom URL and face-to-face meeting types.
* Enhancement - New client and pending order counts now show as badges on the admin menu.
* Enhancement - Reworked empty states across the admin screens.
* Enhancement - More reliable background processing for scheduled emails and reminders.
* Enhancement - Pending Activities section added to the client dashboard.
* Enhancement - Improved account portal typography and spacing.
* Fix         - Program activities and content are now gated on a confirmed order.
* Fix         - Checkout confirmation now shows the client name and program title, and matches the order status.
* Fix         - Corrected date-only timezone handling for bookings.
* Fix         - Resolved activity editor crashes caused by duplicate editor extensions.
* Fix         - Session dialog is now scrollable on short viewports.
* Fix         - Consistent completed badge color and correct cancelled order label.
* Fix         - Font assets are now included in the release package.

= 1.0.3 - 06-11-2026 =
Fix - Resolved an issue where some component styles were not applied due to incorrectly compiled nested CSS selectors.

= 1.0.2 - 06-11-2026 =
* Enhancement - Security hardening.

= 1.0.1 - 06-10-2026 =
* Fix - Client portal sub-pages (Programs, Appointments, and Profile) now route correctly.

= 1.0.0 - 06-04-2026 =
* Initial release.
