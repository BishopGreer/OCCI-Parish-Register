# OCCI Parish Register — Claude Code Context

## Project Overview
WordPress plugin for individual parishes of Old Catholic Churches International (OCCI).
Provides a complete canonical sacramental records management system for use at the parish level. All six principal sacramental registers are stored in MariaDB/MySQL within WordPress.

This is a SEPARATE and INDEPENDENT project from OCCI Sacramental Records (the national database plugin). Do not mix changes between the two.

**Current version:** 1.0.10
**GitHub repository:** https://github.com/BishopGreer/OCCI-Parish-Register
**Working directory:** ~/Projects/occi-parish-register

## Versioning Rules
- Every change request bumps the version incrementally: 1.0.1, 1.0.2, etc.
- Version is updated in BOTH the plugin header (`* Version: X.X.X`) AND the `OCCI_PR_VERSION` constant in `occi-parish-register.php`.
- ZIP filename always includes the version: `occi-parish-register-X.X.X.zip`
- A GitHub release tag (`vX.X.X`) triggers the Actions workflow to build and publish the release ZIP automatically.
- Only use a major version bump (e.g., 1.1.0, 2.0.0) if explicitly requested.

## File Structure
```
occi-parish-register/
├── occi-parish-register.php          # Main plugin file, constants, activation hooks
├── readme.txt                         # WordPress readme with changelog
├── CLAUDE.md                          # This file
├── .gitignore
├── .github/workflows/release.yml      # Auto-builds ZIP on version tag push
├── admin/
│   ├── css/occi-admin.css
│   └── js/occi-admin.js
├── assets/images/
│   └── certificate-template.png      # Default OCCI blank certificate (792x1056px)
└── includes/
    ├── class-occipr-admin.php         # Admin menu, footer bar, settings pages
    ├── class-occipr-baptism.php       # Baptism register CRUD
    ├── class-occipr-certificates.php  # Certificate printing + Person Report
    ├── class-occipr-communion.php     # First Communion register CRUD
    ├── class-occipr-confirmation.php  # Confirmation register CRUD (flat per-person model)
    ├── class-occipr-database.php      # Schema creation, all table definitions
    ├── class-occipr-death.php         # Death/burial register CRUD
    ├── class-occipr-import-export.php # JSON export and import with deduplication
    ├── class-occipr-marriage.php      # Marriage register CRUD
    ├── class-occipr-ordination.php    # Ordination register CRUD
    ├── class-occipr-parishes.php      # Parish registry with per-parish cert templates
    ├── class-occipr-directory.php     # Parish Directory: households + members, privacy controls, print views
    ├── class-occipr-report.php        # Person Sacramental Report (cross-register search)
    ├── class-occipr-updater.php       # Custom updater (GitHub releases, no config needed)
    └── functions.php                  # Shared helper functions
```

## Key Identifiers (use these — never the national plugin's identifiers)
- Plugin slug: `occi-parish-register`
- PHP constant prefix: `OCCI_PR_`
- Class prefix: `OCCIPR_`
- File prefix: `class-occipr-`
- DB table prefix: `{$wpdb->prefix}occipr_`
- Capabilities: `occipr_manage_records`, `occipr_view_records`
- Option keys: `occi_pr_*`
- Transient keys: `occi_pr_*`

## Database Tables
- `{$wpdb->prefix}occipr_parishes`
- `{$wpdb->prefix}occipr_households`  — parish directory family units
- `{$wpdb->prefix}occipr_members`     — individuals within a household
- `{$wpdb->prefix}occipr_baptisms`
- `{$wpdb->prefix}occipr_confirmations`
- `{$wpdb->prefix}occipr_marriages`
- `{$wpdb->prefix}occipr_deaths`
- `{$wpdb->prefix}occipr_communions`
- `{$wpdb->prefix}occipr_ordinations`

## The Six Registers (same fields as national plugin — see national CLAUDE.md for full schema)

### Baptism — Confirmation — Marriage — Death — First Communion — Ordination
All field definitions are identical to the national OCCI Sacramental Records plugin.
Refer to ~/Projects/occi-sacramental-records/CLAUDE.md for the complete field-by-field schema.

## Capabilities & Access Control
- `occipr_manage_records` — full CRUD
- `occipr_view_records` — read-only
- Both granted to: Contributor, Author, Editor, Administrator
- Subscribers get nothing
- Applied on activation AND on every version bump

## Automatic Updates
Hardcoded to GitHub repo `BishopGreer/OCCI-Parish-Register`. No wp-config.php configuration needed.

## Admin Footer
Every plugin admin page shows a fixed bottom bar:
- Left: "Old Catholic Churches International — Parish Register"
- Right: "vX.X.X — Pax et Bonum" (in gold)

## Organizational Preferences
- No em dashes in prose or UI
- Close plugin-related responses with "Pax et Bonum"
- Scripture references use CPDV (Catholic Public Domain Version)
- Never suggest or reference David Haas

## Releasing a New Version
1. Make changes, bump version in plugin header and OCCI_PR_VERSION constant
2. Update readme.txt changelog
3. Commit, tag, and push:
   ```bash
   git add .
   git commit -m "Version X.X.X: description of changes"
   git push origin main
   git tag vX.X.X
   git push origin vX.X.X
   ```
4. GitHub Actions builds `occi-parish-register-X.X.X.zip` and publishes the release.
