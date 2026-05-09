=== OCCI Parish Register ===
Contributors: Old Catholic Churches International
Tags: sacramental records, church, old catholic, database, baptism, marriage, ordination
Requires at least: 6.0
Tested up to: 6.8
Requires PHP: 8.0
Stable tag: 2.0.4
License: GPL-2.0-or-later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Parish-level sacramental record database for Old Catholic Churches International (OCCI).

== Description ==

OCCI Parish Register provides a complete, secure, and canonically structured sacramental records management system for Old Catholic Churches International and its constituent parishes. All six principal sacramental registers are stored in MariaDB/MySQL within WordPress.

= Registers and Modules Included =

* Baptism Register
* Confirmation Register (per-person, flat model)
* Marriage Register
* Death Register
* First Holy Communion Register
* Ordination Register
* Parish Registry (shared lookup with per-parish certificate templates)
* Parish Directory (households and individual members with privacy controls)
* Mass Attendance (headcount log per service with reports)
* Donations (donor and fund management with ledger and reports)
* OCIA -- Order of Christian Initiation of Adults (candidate tracking through all journey stages)
* PSR / Religious Education (student enrollment through program completion)
* Parish Reports (printable reports for Attendance, Donations, OCIA, and PSR)
* Online Registration (public-facing shortcode forms with staff approval queue)

= Key Features =

* Full CRUD for all six sacramental registers, searchable by name and date range
* Surname index search per register; chronological default ordering
* Parish lookup with city and state; alternate location field for off-site sacraments
* Notations column on every register; confidential flag on baptism records
* Certificate printing using a full-page background image template (OCCI default included; per-parish overrides supported)
* Person Sacramental Report: search all registers simultaneously for a single individual
* Import / Export: JSON-based exchange format for inter-parish data sharing with intelligent duplicate detection by name and date of birth
* Parish Directory with household and individual member records, per-field privacy toggles, and printed directory views
* Mass Attendance module: log headcount per service with year-to-date summaries and monthly reports
* Donations module: configurable funds, anonymous donation support, multiple payment methods, monthly ledger reports
* OCIA module: track candidates from Inquiry through Completion with full journey dates, sponsors, and links to sacramental records created at reception
* PSR module: track students by grade, academic year, catechist, and sacramental status (Baptized, First Communion, Confirmed)
* Online Registration: public shortcode forms for Parish Member, PSR, and OCIA; submissions held in a staff review queue before any record is created; optional hCaptcha or Google reCAPTCHA v2 bot protection
* Automatic update checker hardcoded to the official GitHub repository -- no configuration required, updates appear in WordPress Dashboard > Updates automatically
* Two access roles: occipr_manage_records (full CRUD) and occipr_view_records (read-only); both granted to Contributor role and above on activation
* Per-parish certificate template images via WordPress Media Library
* Fixed admin footer bar displaying organization name and version on all plugin pages
* All queries use $wpdb->prepare() for SQL injection prevention; all forms protected with WordPress nonces
* Date formatting prints month name per canonical handbook guidelines (e.g., "May 5, 2026")
* Print-optimized CSS for certificates, registers, and reports; signature lines included

= Canonical Compliance =

Designed in alignment with canon law (cc. 535, 874-878, 892-896, 1121-1123, 1182) and informed by the Diocese of Little Rock Handbook for Sacramental Records as a reference standard, adapted for OCCI's Old Catholic tradition independent of Rome.

= Automatic Updates =

No configuration is required. The plugin checks for new releases directly from its GitHub repository (BishopGreer/OCCI-Parish-Register) and surfaces them in WordPress Dashboard > Updates like any other plugin. Updates are checked every 12 hours. A Force Update Check button is available on the Certificate Settings page.

== Installation ==

1. Upload the plugin folder to /wp-content/plugins/ or install via the ZIP upload in Plugins > Add New.
2. Activate the plugin through the Plugins menu in WordPress.
3. Navigate to Sacramental Records in the admin menu.
4. Add your parishes first under the Parishes submenu.
5. Begin entering records in each register.

Automatic updates require no additional setup. The plugin will notify you of new releases in WordPress Dashboard > Updates.

== Database Tables ==

The following tables are created on activation using dbDelta() and are compatible with MariaDB and MySQL:

* {prefix}occipr_parishes
* {prefix}occipr_baptisms
* {prefix}occipr_confirmations
* {prefix}occipr_marriages
* {prefix}occipr_deaths
* {prefix}occipr_communions
* {prefix}occipr_ordinations
* {prefix}occipr_households
* {prefix}occipr_members
* {prefix}occipr_attendance
* {prefix}occipr_donation_funds
* {prefix}occipr_donations
* {prefix}occipr_ocia
* {prefix}occipr_psr
* {prefix}occipr_submissions

Tables are updated automatically when a new plugin version is installed; no manual migration is required.

== Frequently Asked Questions ==

= Will deactivating the plugin delete my records? =

No. Deactivation does not drop any tables or remove any data. Records persist until you manually remove the plugin's database tables.

= Can I grant a parish secretary access without full admin access? =

Yes. Both occipr_manage_records and occipr_view_records are granted to the Contributor role and above. Use a role management plugin to assign these capabilities to custom roles as needed.

= Does this replace the physical register? =

No. Per canon law and best practices, physical registers remain the authoritative record. This system provides a searchable, backed-up digital complement. Physical registers must never be destroyed.

= How do I set up automatic updates? =

Nothing to set up. The plugin checks GitHub for new releases automatically and surfaces them in WordPress Dashboard > Updates. If an update is not appearing, go to Sacramental Records > Certificate Settings and click Force Update Check Now to clear the cached check.

= Can each parish use its own certificate background image? =

Yes. Edit any parish record and use the Media Library button to upload a parish-specific certificate image. The plugin cascades: parish image → global OCCI setting → bundled default.

= Why is my Parish Directory empty even after adding households? =

Each household must have a parish selected before it will appear in the directory. When adding or editing a household, use the Parish dropdown on the right side of the form to assign it to your parish. If no parish appears in the dropdown, add one first under Sacramental Records > Parishes.

= How does the import handle duplicate records? =

Records are matched by name plus sacrament date. Baptisms additionally use date of birth when present, so two people with the same name but different birth dates are never treated as the same individual. Existing records are skipped; new records for known individuals are added normally. Parishes are matched by name, city, and state and created automatically if not found.

== Changelog ==

= 2.0.4 =
* Added plugin icon and banner image support for the WordPress plugin list and update detail popup
* Drop icon-128x128.png, icon-256x256.png, banner-772x250.jpg, and/or banner-1544x500.jpg into assets/images/ and they appear automatically -- no code changes needed

= 2.0.3 =
* Corrected readme.txt throughout: removed all references to wp-config.php constants for auto-updates (no configuration is or was ever required for this plugin)
* Fixed database table list: corrected table prefix from occi_ to occipr_ and added all 8 tables introduced since 1.0.0 (households, members, attendance, donation_funds, donations, ocia, psr, submissions)
* Expanded Registers and Modules section to list all current modules: Parish Directory, Mass Attendance, Donations, OCIA, PSR, Parish Reports, Online Registration
* Expanded Key Features section to reflect all features added since the initial release
* Updated Automatic Updates section and FAQ answer to accurately describe the zero-configuration GitHub-based updater
* Updated Upgrade Notice to current version

= 2.0.2 =
* Added CAPTCHA bot protection for all public registration forms
* Supports hCaptcha and Google reCAPTCHA v2; configured in Registration Settings with a single provider/site-key/secret-key set of fields
* CAPTCHA widget renders automatically above the submit button on all three forms (Member, PSR, OCIA) when a provider is configured
* Server-side token verification via hcaptcha.com/siteverify or google.com/recaptcha/api/siteverify before any submission is stored
* Specific "Please complete the verification check" error message shown when captcha fails
* No CAPTCHA loaded when provider is set to None -- zero performance impact for sites that do not need it

= 2.0.1 =
* Fixed update notification not appearing on the WordPress Updates page: the updater now hooks to both the write filter (pre_set_site_transient_update_plugins) and the read filter (site_transient_update_plugins), so the update badge shows on every page load rather than only immediately after a cache refresh
* Fixed WordPress.org potentially overwriting our update entry: the updater now clears any no_update entry for our slug before injecting the response
* Minor hardening: capability check added to cache-clear handler; wp_safe_redirect replaces wp_redirect

= 2.0.0 =
* Fixed automatic update detection: plugin now correctly appears in WordPress Dashboard > Updates when a new version is available on GitHub
* Fixed manual ZIP upload: the release ZIP now contains the correct occi-parish-register/ parent folder so uploading via Plugins > Add New > Upload updates the existing plugin instead of installing a duplicate
* Fixed release workflow to include the public/ directory (registration form CSS was missing from distributed ZIPs since 1.0.11)
* Fixed Force Update Check button on Certificate Settings: the cache-clearing handler is now properly hooked to admin_init so the button actually works
* Improved fix_directory_name filter: more robustly handles both automatic updates and manual uploads; verifies the main plugin file exists before renaming the extracted folder

= 1.0.12 =
* Increased spacing on public registration forms: more padding inside each section, larger gap between fields and grid columns, more breathing room inside repeating member rows

= 1.0.11 =
* Added Online Registration module with public-facing shortcode forms for Parish Member, PSR, and OCIA registration
* Three shortcodes: [occipr_member_registration], [occipr_psr_registration], [occipr_ocia_registration]
* Admin Registration Settings page: enable/disable each form independently, assign a default parish per form, customize success messages, set staff notification email
* All submissions held in a pending queue (occipr_submissions table) as JSON for staff review before any record is created
* Admin Online Registrations queue: list view with form type, submission date, and status; detail view showing all submitted fields
* Approve action: auto-inserts the submission into the correct register table (household + members for parish registration, PSR, or OCIA); uses a database transaction for two-table member inserts to prevent orphan records
* Reject action: staff can add notes; record marked rejected without creating any register entry
* Dashboard stat card shows count of pending registrations; yellow notice banner appears when submissions are awaiting review
* Parish Member form supports multiple family members with dynamically added rows (vanilla JS, no jQuery dependency)
* Public CSS scoped to .occipr-registration-form to avoid theme conflicts; responsive layout stacks on mobile
* New database table: occipr_submissions

= 1.0.10 =
* Added PSR (Parish School of Religion / Religious Education) module
* Track students from enrollment through program completion with grade level, academic year, class group, and catechist assignment
* Parent and guardian fields: two guardian entries (name, phone, email) and a home address field
* Three sacramental status toggles -- Baptized, First Communion, Confirmed -- each with date, church, and optional link to an existing register record
* Detail view shows full sacramental status with links to linked register records
* Overview bar on list page: active, completed, withdrawn, and total counts
* Filter list by parish, status, academic year, and grade level; defaults to Active students
* Dashboard stat card shows currently active PSR students
* PSR report tab in Parish Reports: summary bar (total, active, baptized, communion, confirmed); enrollment by grade with sacramental status counts; student count by catechist with percentage; full printable student roster with checkmarks for each sacrament received; filterable by parish, status, academic year, and grade
* New database table: occipr_psr

= 1.0.9 =
* Added OCIA (Order of Christian Initiation of Adults) module
* Track candidates through all stages: Inquirer, Catechumen, Elect, Completed, Withdrawn
* Full candidate record: legal and preferred name, contact info, previous faith tradition, OCIA journey dates (inquiry, enrollment/Rite of Acceptance, Rite of Election, completion), status, catechist, presider, two sponsor fields
* Previous baptism section: checkbox toggle reveals prior baptism date and church
* Link completed candidates directly to their Baptism, Confirmation, and First Communion records in the register
* Detail view shows full journey history with links to linked sacramental records
* Overview bar on list page: active count, inquirers, catechumens, elect, completed
* Filter by parish, status, and cohort year; sorted by status priority (Elect first, Withdrawn last)
* OCIA report tab in Parish Reports: active roster, enrollment-by-cohort-year table with completion rates, completed candidates list; filterable by parish, status, and year; printable
* Dashboard stat card shows currently active OCIA candidates (Inquirers + Catechumens + Elect)
* New database table: occipr_ocia

= 1.0.8 =
* Added Cash App as a donation payment method

= 1.0.7 =
* Added Parish Reports module with Attendance and Donations report tabs
* Attendance Report: filter by parish, year, service type, and date range; summary bar (services, total, average, high, low); monthly breakdown table; service type breakdown; full service log; printable
* Donations Report: filter by parish, fund, payment source, year, and date range; summary bar; breakdown by fund with percentages; breakdown by payment source with percentages; monthly totals with running total; full donation ledger; printable
* Updated donation payment methods: Cash, Check, Venmo, PayPal, Liberapay, Tithe.ly, Other
* All donation records and reports now display the specific payment source

= 1.0.6 =
* Added Donations module: record and track parish donations with full donor and fund management
* Configurable donation funds (General Collection, Building Fund, etc.) with active/inactive toggle and sort order
* Per-donation fields: date, fund, amount, payment method (Cash, Check, Online, Tithe.ly, Other), check number, parish, notes
* Anonymous donation support: donor fields hidden and cleared when anonymous is checked
* Optional donor name, envelope number, and household directory link per donation
* Source and external ID fields on every record for future Tithe.ly and CSV import deduplication
* List page with search and filters by parish, fund, payment method, and date range
* Summary bar: year-to-date count and total, plus filtered count and total
* Dashboard stat card shows year-to-date donation total and count
* New database tables: occipr_donation_funds, occipr_donations

= 1.0.5 =
* Added Mass Attendance module: log headcount (and optional communion count) per service
* Service types: Sunday Mass, Holy Day of Obligation, Special Mass, Other
* Optional service label for special occasions (e.g. Easter Vigil, Feast of St. Francis)
* Optional service time field -- useful for parishes with multiple Masses on the same day
* Filter by parish, service type, and date range
* Summary bar shows year-to-date service count, total attended, and average headcount
* Dashboard stat card shows services logged this year with average headcount
* New database table: occipr_attendance

= 1.0.4 =
* Added FAQ entry explaining that households must have a parish selected to appear in the Parish Directory

= 1.0.3 =
* Added explicit table-existence check to upgrade routine so missing tables are always created regardless of stored version
* Save handlers for households, members, and parishes now show a clear database error message instead of failing silently
* Fixes parish directory appearing empty even after upgrading to 1.0.2

= 1.0.2 =
* Fixed all page slug conflicts with the national OCCI Sacramental Records plugin
* All admin page slugs renamed from occi- to occipr- prefix (baptisms, confirmations, marriages, deaths, communions, ordinations, directory, import-export, report, cert-settings)
* Eliminates cross-plugin data bleed when both plugins are active on the same WordPress site
* Parish Directory now correctly displays households previously added

= 1.0.1 =
* Added Parish Directory module with household and individual member records
* Household records: family name, address, phone, email, family photo, envelope number, parish link, status, admin notes
* Member records: legal first name, preferred/chosen name, middle name, last name, role in household, date of birth, member since, status
* Inclusive gender field with preset options (Male, Female, Nonbinary, Transgender Female, Transgender Male, Genderqueer, Genderfluid, Two-Spirit, Agender, Prefer Not to Say) plus a self-describe free-text entry
* Pronouns field (free-text) on every member record
* Contact fields per member: personal phone, email, Facebook, Instagram, website/other
* Family photo and individual member photos via WordPress Media Library
* Per-field directory privacy toggles on both households (address, phone, email, photo) and members (phone, email, social media, birthday, photo)
* Public printed directory respects privacy settings; administrative printed directory shows all information regardless of privacy settings
* Household list page with search by family name, city, or member name
* Household detail page showing all members grouped in a card layout
* Dashboard stat card added for household count
* Two new database tables: occipr_households and occipr_members

= 1.0.0 =
* Initial release of OCCI Parish Register
* Baptism Register: date, baptismal name, parents (mother's maiden name required), sponsors with proxy support, minister, parish, alternate location, notations, confidential flag, record book and page number
* Confirmation Register: per-person flat model with date, confirming bishop/delegate, name, saint's name chosen, parish, alternate location, notations
* Marriage Register: both parties with names, maiden names, birth dates, two witnesses, minister, parish, alternate location, notations
* Death Register: date of death, name, burial location, funeral details, graveside flag, cemetery, cremation flag with ashes interment (date/place of cremation not recorded per canon law)
* First Holy Communion Register: communicant name, baptism date and church, presider, parish, notations
* Ordination Register: date, ordinand name, rank (Deacon/Priest/Bishop), presiding bishop, three co-consecrator fields, parish, alternate location, notations
* Parish Registry with name, city, state, and optional per-parish certificate template
* Certificate printing using OCCI blank certificate image as full-page background (792x1056px, letter portrait)
* Certificate template cascades: parish-specific then global OCCI setting then bundled default
* Person Sacramental Report: search all six registers simultaneously by name; print full report as standalone document
* Import/Export system (JSON) with intelligent duplicate detection; parishes created automatically on import
* Dashboard with record counts and quick links
* Sortable, searchable list views for all registers
* Print-ready record view with signature lines for each register
* Automatic update checking via GitHub releases (no configuration required)
* Custom capabilities: occipr_manage_records and occipr_view_records granted to Contributor and above on activation

== Upgrade Notice ==

= 2.0.2 =
Adds hCaptcha and Google reCAPTCHA v2 support for public registration forms. No configuration changes required for existing installs.

== Notes ==

Pax et Bonum.
Old Catholic Churches International
https://myocci.org
