# ChecklistDek — agent context

WordPress agency hub plugin (PHP 7.4+, WP 6.2+). Persistent AI guidance lives in [`rules/`](rules/).

| Rule file | Scope |
|-----------|--------|
| [`checklistdek-project.mdc`](rules/checklistdek-project.mdc) | Architecture, REST, licensing, integrations, naming (always apply) |
| [`checklistdek-standards.mdc`](rules/checklistdek-standards.mdc) | Coding, i18n, UX, performance, release (always apply) |
| [`checklistdek-compliance.mdc`](rules/checklistdek-compliance.mdc) | Security and pre-ship checklist (always apply) |
| [`wordpress-php.mdc`](rules/wordpress-php.mdc) | PHP, repositories, settings shape, REST handlers (`**/*.php`) |
| [`admin-assets.mdc`](rules/admin-assets.mdc) | Admin JS/CSS and client panel assets (`admin/`, `mu-plugin/`) |

**Build editions:** `CHECKLISTDEK_BUILD` `full` (repo default) vs `community` (WordPress.org zip). Community ships `includes/community/` stubs for licensing, mu-plugin installer, MainWP agent, webhook/cron; omits full Pro PHP, Freemius, Billing submenu + partial, `/billing/*` REST, `class-rest-api-install.php`, and most template categories. Gate Pro UI with `checklistdek_includes_pro_package()`; `checklistdek_can_auto_deploy_client_panel()` is false on Community (manual client panel upload). Community unlocks all nine panel layouts via Settings (not plan-limited). `checklistdekAdmin.billing`, billing gate strings, and `billingUrl` are localized only on full builds; admin-ajax handlers are the same on Community and Pro.

**Session / nonce:** Hub `checklistdek-admin` and remote client panel scripts depend on `heartbeat` and refresh the `wp_rest` nonce from bare jQuery `heartbeat-tick` (`rest_nonce` — not namespaced handlers). `postAjax()` sends `X-WP-Nonce`; `CHECKLISTDEK_Admin_Ajax::get_request_nonce()` accepts POST or header.

**Bootstrap order:** `checklistdek-build.php` → optional `checklistdek-freemius.php` → core includes via `checklistdek_get_licensing_file()` / `checklistdek_get_mu_plugin_installer_file()`; site/checklist/run repositories load **before** `class-checklistdek-installer.php` (migrations use `::table()` helpers).

**LaunchDek upgrades:** `CHECKLISTDEK_Installer::migrate_launchdek_branding()` runs on every `maybe_install()` — renames legacy `launchdek_*` tables, options, and capabilities to `checklistdek_*` (DB version `1.7.0`). Do not use `launchdek_*` in new code.

**Admin JS globals:** On every plugin screen: `initFieldTooltips()` (fixed `#checklistdek-field-tooltip` portal for `.checklistdek-has-tooltip`), plus `initTemplatePicker`, `initOnboarding`, `initConfirmModal` before page inits. Dashboard, Checklists, and Settings preload `checklistdekAdmin.templates` (built-in catalog + plan-scoped categories) to skip initial `GET /templates`.

**i18n:** User-facing copy uses text domain `checklistdek`; in PHP prefer the literal `'checklistdek'` in `__()` / `esc_html__()` (Plugin Check / WPCS), not the `CHECKLISTDEK_TEXT_DOMAIN` constant.

**SQL:** Plugin-owned table names in `$wpdb->prepare()` use the `%i` placeholder (WP 6.2+) via repository `::table()` / `::steps_table()` helpers (and `CHECKLISTDEK_Audit_Log`).

**Ship gate:** satisfy [`checklistdek-compliance.mdc`](rules/checklistdek-compliance.mdc) before presenting final code.
