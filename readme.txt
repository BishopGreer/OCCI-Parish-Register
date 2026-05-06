=== OCCI Parish Register ===
Contributors: Old Catholic Churches International
Tags: sacramental records, church, old catholic, database, baptism, marriage, ordination
Requires at least: 6.0
Tested up to: 6.8
Requires PHP: 8.0
Stable tag: 1.0.0
License: GPL-2.0-or-later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Parish-level sacramental record database for Old Catholic Churches International (OCCI).

== Description ==

OCCI Parish Register provides a complete, secure, and canonically structured sacramental records management system for Old Catholic Churches International and its constituent parishes. All six principal sacramental registers are stored in MariaDB/MySQL within WordPress.

= Registers Included =

* Baptism Register
* Confirmation Register (per-person, flat model)
* Marriage Register
* Death Register
* First Holy Communion Register
* Ordination Register
* Parish Registry (shared lookup with per-parish certificate templates)

= Key Features =

* Full CRUD for all six registers, searchable by name and date range
* Surname index search per register; chronological default ordering
* Parish lookup with city and state; alternate location field for off-site sacraments
* Notations column on every register; confidential flag on baptism records
* Certificate printing using a full-page background image template (OCCI default included; per-parish overrides supported)
* Person Sacramental Report: search all registers simultaneously for a single individual
* Import / Export: JSON-based exchange format for inter-parish data sharing with intelligent duplicate detection by name and date of birth
* Automatic update checker supporting self-hosted JSON or GitHub Releases (configured via wp-config.php constants; no WordPress.org required)
* Two access roles: occipr_manage_records (full CRUD) and occipr_view_records (read-only); both granted to Contributor role and above on activation
* Per-parish certificate template images via WordPress Media Library
* Fixed admin footer bar displaying organization name and version on all plugin pages
* All queries use $wpdb->prepare() for SQL injection prevention; all forms protected with WordPress nonces
* Date formatting prints month name per canonical handbook guidelines (e.g., "May 5, 2026")
* Print-optimized CSS for certificates and reports; signature lines included

= Canonical Compliance =

Designed in alignment with canon law (cc. 535, 874-878, 892-896, 1121-1123, 1182) and informed by the Diocese of Little Rock Handbook for Sacramental Records as a reference standard, adapted for OCCI's Old Catholic tradition independent of Rome.

= Automatic Updates =

To enable automatic update checking without WordPress.org, add one of the following to wp-config.php:

**Self-hosted (recommended):**
  define( 'OCCI_UPDATE_URL', 'https://myocci.org/updates/occi-parish-register.json' );

**GitHub Releases:**
  define( 'OCCI_UPDATE_URL',    'https://github.com/YOUR-ORG/OCCI-sacramental-record' );
  define( 'OCCI_UPDATE_SOURCE', 'github' );

Full configuration instructions and the required JSON format are shown in Certificate Settings once the plugin is installed.

== Installation ==

1. Upload the plugin folder to /wp-content/plugins/ or install via the ZIP upload in Plugins > Add New.
2. Activate the plugin through the Plugins menu in WordPress.
3. Navigate to Sacramental Records in the admin menu.
4. Add your parishes first under the Parishes submenu.
5. Begin entering records in each register.

To enable automatic updates, add the appropriate constants to wp-config.php before or after installation (see Description above).

== Database Tables ==

The following tables are created on activation using dbDelta() and are compatible with MariaDB and MySQL:

* {prefix}occi_parishes
* {prefix}occi_baptisms
* {prefix}occi_confirmations
* {prefix}occi_marriages
* {prefix}occi_deaths
* {prefix}occi_communions
* {prefix}occi_ordinations

Tables are updated automatically when a new plugin version is installed; no manual migration is required.

== Frequently Asked Questions ==

= Will deactivating the plugin delete my records? =

No. Deactivation does not drop any tables or remove any data. Records persist until you manually remove the plugin's database tables.

= Can I grant a parish secretary access without full admin access? =

Yes. Both occipr_manage_records and occipr_view_records are granted to the Contributor role and above. Use a role management plugin to assign these capabilities to custom roles as needed.

= Does this replace the physical register? =

No. Per canon law and best practices, physical registers remain the authoritative record. This system provides a searchable, backed-up digital complement. Physical registers must never be destroyed.

= How do I set up automatic updates? =

See the Description section above and the Certificate Settings page within the plugin after installation.

= Can each parish use its own certificate background image? =

Yes. Edit any parish record and use the Media Library button to upload a parish-specific certificate image. The plugin cascades: parish image → global OCCI setting → bundled default.

= How does the import handle duplicate records? =

Records are matched by name plus sacrament date. Baptisms additionally use date of birth when present, so two people with the same name but different birth dates are never treated as the same individual. Existing records are skipped; new records for known individuals are added normally. Parishes are matched by name, city, and state and created automatically if not found.

== Changelog ==

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

= 1.0.0 =
Initial release.

== Notes ==

Pax et Bonum.
Old Catholic Churches International
https://myocci.org
