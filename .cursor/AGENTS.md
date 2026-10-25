# ChecklistDek — agent context

WordPress agency hub plugin (PHP 7.4+, WP 6.2+). Persistent AI guidance lives in [`rules/`](rules/).

| Rule file | Scope |
|-----------|--------|
| [`checklistdek-project.mdc`](rules/checklistdek-project.mdc) | Architecture, REST, licensing, integrations, naming (always apply) |
| [`checklistdek-standards.mdc`](rules/checklistdek-standards.mdc) | Coding, i18n, UX, performance, release (always apply) |
| [`checklistdek-compliance.mdc`](rules/checklistdek-compliance.mdc) | Security and pre-ship checklist (always apply) |
| [`wordpress-php.mdc`](rules/wordpress-php.mdc) | PHP, repositories, settings shape, REST handlers (`**/*.php`) |
| [`admin-assets.mdc`](rules/admin-assets.mdc) | Admin JS/CSS and client panel assets (`admin/`, `mu-plugin/`) |

**Build editions:** `CHECKLISTDEK_BUILD` `full` (repo default) vs `community` (WordPress.org zip metadata). Community ships `includes/community/` stubs for mu-plugin installer, MainWP agent, webhook/cron, and **three** panel layouts; omits full Pro PHP, Freemius, `includes/class-checklistdek-licensing.php`, `includes/client-panel-layouts.php` (nine layouts), hub auto-install, and most template JSON (four categories at zip build time). `bin/build-community-zip.php` runs `filter_community_pot()` so the shipped `.pot` drops Pro-only / stale billing strings. Runtime gates live in `includes/checklistdek-build.php`: `checklistdek_load_licensing()` loads Pro `CHECKLISTDEK_Licensing` when bundled; otherwise helpers use `is_readable()` on Pro class files (Community → false). Freemius plan gates use `CHECKLISTDEK_Licensing::can_use_premium_code()` when the licensing class is loaded. Gate Pro UI/REST/settings with `checklistdek_can_use_*()` / `checklistdek_should_show_upgrade_submenu()` — not legacy build-edition booleans. No in-plugin Billing page or `/billing/*` REST; **Upgrade** submenu redirects to `CHECKLISTDEK_UPGRADE_URL` when `checklistdek_should_show_upgrade_submenu()`. `checklistdekAdmin` has no billing keys. Admin-ajax handlers are the same on Community and Pro.

**Session / nonce:** Hub `checklistdek-admin` and remote client panel scripts depend on `heartbeat` and refresh the `wp_rest` nonce from bare jQuery `heartbeat-tick` (`rest_nonce` — not namespaced handlers). `postAjax()` sends `X-WP-Nonce`; `CHECKLISTDEK_Admin_Ajax::get_request_nonce()` accepts POST or header.

**Bootstrap order:** `checklistdek-build.php` → `checklistdek_load_freemius()` (no-op when Freemius file omitted) → core includes; site/checklist repositories → `checklistdek_load_licensing()` (Pro licensing PHP when bundled) → run repository **before** `class-checklistdek-installer.php` (migrations use `::table()` helpers); panel layouts / mu-plugin paths via `checklistdek_get_client_panel_layouts_file()` / `checklistdek_get_mu_plugin_installer_file()`. Uninstall: Freemius `after_uninstall` when `cld_fs()` exists, else `register_uninstall_hook`.

**LaunchDek upgrades:** `CHECKLISTDEK_Installer::migrate_launchdek_branding()` runs on every `maybe_install()` — renames legacy `launchdek_*` tables, options, and capabilities to `checklistdek_*` (DB version `1.7.0`). Do not use `launchdek_*` in new code.

**Admin JS globals:** On every plugin screen: `initFieldTooltips()` (fixed `#checklistdek-field-tooltip` portal for `.checklistdek-has-tooltip`), plus `initTemplatePicker`, `initOnboarding`, `initConfirmModal` before page inits. Dashboard, Checklists, and Settings preload `checklistdekAdmin.templates` (built-in catalog + `get_categories_for_catalog()` from shipped JSON) to skip initial `GET /templates`.

**i18n:** User-facing copy uses text domain `checklistdek`; in PHP prefer the literal `'checklistdek'` in `__()` / `esc_html__()` (Plugin Check / WPCS), not the `CHECKLISTDEK_TEXT_DOMAIN` constant.

**SQL:** Plugin-owned table names in `$wpdb->prepare()` use the `%i` placeholder (WP 6.2+) via repository `::table()` / `::steps_table()` helpers (and `CHECKLISTDEK_Audit_Log`).

**Ship gate:** satisfy [`checklistdek-compliance.mdc`](rules/checklistdek-compliance.mdc) before presenting final code.
