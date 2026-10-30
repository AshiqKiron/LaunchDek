# ChecklistDek — agent context

WordPress agency hub plugin (PHP 7.4+, WP 6.2+). Persistent AI guidance lives in [`rules/`](rules/).

| Rule file | Scope |
|-----------|--------|
| [`checklistdek-project.mdc`](rules/checklistdek-project.mdc) | Architecture, REST, licensing, integrations, naming (always apply) |
| [`checklistdek-standards.mdc`](rules/checklistdek-standards.mdc) | Coding, i18n, UX, performance, release (always apply) |
| [`checklistdek-compliance.mdc`](rules/checklistdek-compliance.mdc) | Security and pre-ship checklist (always apply) |
| [`wordpress-php.mdc`](rules/wordpress-php.mdc) | PHP, repositories, settings shape, REST handlers (`**/*.php`) |
| [`admin-assets.mdc`](rules/admin-assets.mdc) | Admin JS/CSS and client panel assets (`admin/`, `mu-plugin/`) |

**Build editions:** WordPress.org zip from `php bin/build-community-zip.php` → `dist/checklistdek-free.zip` omits Pro PHP, Freemius, licensing (`includes/class-checklistdek-licensing.php`), and the premium module loader (`includes/checklistdek-premium-modules.php` — Agency Vault UI/REST/JS); does **not** patch `CHECKLISTDEK_BUILD` in bootstrap (constant defaults to `full` in `checklistdek-build.php` when unset). Org package ships `includes/community/` stubs (probe-only mu-plugin installer, MainWP agent, webhook/cron no-ops, **three** panel layouts) and four built-in template categories. Pro/premium zip from `bin/build-pro-zip.php` ships the full tree (excludes `includes/community/`). Gate Pro UI/REST with `checklistdek_can_use_*()` / path helpers — not `CHECKLISTDEK_BUILD === 'community'`. When `CHECKLISTDEK_Licensing` is absent, helpers fall back to `is_readable()` on full Pro class files (org package → false). Full builds load Pro licensing + Freemius for plan gates. No in-plugin Billing page or `/billing/*` REST; **Upgrade** submenu redirects to `CHECKLISTDEK_UPGRADE_URL` when `checklistdek_should_show_upgrade_submenu()`. `checklistdekAdmin` has no billing keys. Admin-ajax handlers are the same on Community and Pro. Validate org zip with `php bin/verify-community-zip.php`.

**Session / nonce:** Hub `checklistdek-admin` and remote client panel scripts depend on `heartbeat` and refresh the `wp_rest` nonce from bare jQuery `heartbeat-tick` (`rest_nonce` — not namespaced handlers). `postAjax()` sends `X-WP-Nonce`; `CHECKLISTDEK_Admin_Ajax::get_request_nonce()` accepts POST or header.

**Bootstrap order:** `checklistdek-build.php` → `checklistdek_load_freemius()` (no-op when Freemius file omitted) → core includes; site/checklist repositories → `checklistdek_load_licensing()` (Pro licensing PHP when bundled) → run repository **before** `class-checklistdek-installer.php` (migrations use `::table()` helpers); panel layouts / mu-plugin paths via `checklistdek_get_client_panel_layouts_file()` / `checklistdek_get_mu_plugin_installer_file()`; `checklistdek_load_premium_modules()` after REST API require (Agency Vault when loader bundled + licensed). Uninstall: Freemius `after_uninstall` when `cld_fs()` exists, else `register_uninstall_hook`.

**LaunchDek upgrades:** `CHECKLISTDEK_Installer::migrate_launchdek_branding()` runs on every `maybe_install()` — renames legacy `launchdek_*` tables, options, and capabilities to `checklistdek_*` (DB version `1.7.0`). Do not use `launchdek_*` in new code.

**Admin JS globals:** On every plugin screen: `initFieldTooltips()` (fixed `#checklistdek-field-tooltip` portal for `.checklistdek-has-tooltip`), plus `initTemplatePicker`, `initOnboarding`, `initConfirmModal` before page inits. Dashboard, Checklists, and Settings preload `checklistdekAdmin.templates` (built-in catalog + `get_categories_for_catalog()` from shipped JSON) to skip initial `GET /templates`.

**i18n:** User-facing copy uses text domain `checklistdek`; in PHP prefer the literal `'checklistdek'` in `__()` / `esc_html__()` (Plugin Check / WPCS), not the `CHECKLISTDEK_TEXT_DOMAIN` constant.

**SQL:** Plugin-owned table names in `$wpdb->prepare()` use the `%i` placeholder (WP 6.2+) via repository `::table()` / `::steps_table()` helpers (and `CHECKLISTDEK_Audit_Log`).

**Ship gate:** satisfy [`checklistdek-compliance.mdc`](rules/checklistdek-compliance.mdc) before presenting final code.
