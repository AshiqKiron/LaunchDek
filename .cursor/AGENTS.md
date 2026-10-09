# LaunchDek — agent context

WordPress agency hub plugin (PHP 7.4+, WP 6.2+). Persistent AI guidance lives in [`rules/`](rules/).

| Rule file | Scope |
|-----------|--------|
| [`launchdek-project.mdc`](rules/launchdek-project.mdc) | Architecture, REST, licensing, integrations, naming (always apply) |
| [`launchdek-standards.mdc`](rules/launchdek-standards.mdc) | Coding, i18n, UX, performance, release (always apply) |
| [`launchdek-compliance.mdc`](rules/launchdek-compliance.mdc) | Security and pre-ship checklist (always apply) |
| [`wordpress-php.mdc`](rules/wordpress-php.mdc) | PHP, repositories, settings shape, REST handlers (`**/*.php`) |
| [`admin-assets.mdc`](rules/admin-assets.mdc) | Admin JS/CSS and client panel assets (`admin/`, `mu-plugin/`) |

**Build editions:** `LAUNCHDEK_BUILD` `full` (repo default) vs `community` (WordPress.org zip). Community omits Pro PHP, Freemius, Billing admin submenu + partial, `/billing/*` REST, and most template categories — gate with `launchdek_includes_pro_package()` / `LAUNCHDEK_Licensing`. `launchdekAdmin.billingUrl` is localized only on full builds.

**Bootstrap order:** `launchdek-build.php` → optional `launchdek-freemius.php` → core includes; site/checklist/run repositories load **before** `class-launchdek-installer.php` (migrations use `::table()` helpers).

**Admin JS globals:** On every plugin screen: `initFieldTooltips()` (fixed `#launchdek-field-tooltip` portal for `.launchdek-has-tooltip`), plus `initTemplatePicker`, `initOnboarding`, `initConfirmModal` before page inits. Dashboard, Checklists, and Settings preload `launchdekAdmin.templates` (built-in catalog + plan-scoped categories) to skip initial `GET /templates`.

**i18n:** User-facing copy uses text domain `launchdek`; in PHP prefer the literal `'launchdek'` in `__()` / `esc_html__()` (Plugin Check / WPCS), not the `LAUNCHDEK_TEXT_DOMAIN` constant.

**SQL:** Plugin-owned table names in `$wpdb->prepare()` use the `%i` placeholder (WP 6.2+) via repository `::table()` / `::steps_table()` helpers (and `LAUNCHDEK_Audit_Log`).

**Ship gate:** satisfy [`launchdek-compliance.mdc`](rules/launchdek-compliance.mdc) before presenting final code.
