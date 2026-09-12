=== LaunchDek ===
Contributors: ashiquzzaman
Tags: agency, checklist, remote, automation, wordpress
Requires at least: 6.0
Tested up to: 6.7
Requires PHP: 7.4
Stable tag: 1.0.0
License: GPLv3 or later
License URI: https://www.gnu.org/licenses/gpl-3.0.html

Remote WordPress site orchestration hub for agencies — connect sites, run checklists, and audit everything.

== Description ==

LaunchDek is a master-site plugin for WordPress agencies to manage remote client sites via Application Passwords and REST API checklists.

**Features:**

* **Dashboard** — Live stats, API connection ticker, activity log feed, quick launch bar
* **Sites** — Register remote sites, connection tester, tagging and grouping
* **Checklists** — Drag-and-drop builder, step configuration, API payload mapper, import/export
* **Automation & Audit** — Run control, live step tracker, immutable audit logs, drift verifier
* **Templates** — 17 categorized agency checklists (security, launch, performance, SEO, e-commerce, maintenance, compliance) and agency vault
* **Integrations** — MainWP, ManageWP, WP Umbrella, WP Engine, WPvibe adapters
* **Settings** — Encrypted credential vault, Slack/Discord/Teams webhooks, role guardrails

== Installation ==

1. Upload the plugin to `/wp-content/plugins/LaunchDek`
2. Activate through the Plugins menu
3. Go to LaunchDek → Sites to register your first remote site
4. Create or clone a checklist, then launch it from the Dashboard or Automation page

== Frequently Asked Questions ==

= Does LaunchDek require a plugin on remote sites? =

No. LaunchDek connects to remote sites using WordPress Application Passwords and the standard REST API.

= Are credentials encrypted? =

Yes, when enabled in Settings, application passwords are encrypted using your WordPress salt keys.

== Changelog ==

= 1.0.3 =
* Dashboard onboarding modal: paste plain-text SOPs for a live checklist preview, connect a remote site with inline connection test, and Finish & Launch your first run
* Onboarding step 2 aligned to wireframe: App Password fields, Test Connection status, and Skip / Finish & Launch actions
* Activation redirect opens onboarding on the dashboard; dismissal is persisted in settings

= 1.0.2 =
* Renamed Workflows to Checklists across admin UI, REST API (`/checklists`), database tables, capabilities, and audit log
* DB upgrade migrates `launchdek_workflows` → `launchdek_checklists` and `workflow_id` → `checklist_id` on existing installs
* Custom checklist builder: create from scratch, add/configure steps, and save via REST with title validation and inline feedback
* Saved custom checklists appear under Templates → Your Custom Checklists with edit and clone actions

= 1.0.1 =
* Settings page aligned to wireframe: credential vault, webhook channel routing, agency role guardrails
* Integrations page: Platform Connectors Hub with Connected/Setup actions and Telemetry Sync Mapping Rules
* Dashboard layout aligned to wireframe: live status cards, horizontal API connection ticker, readable log feed, quick launch bar
* Checklists page: visual drag-and-drop canvas, sidebar step configuration with role target mapping, import/export center at bottom
* Sites page toolbar: Add Site, Connection Tester, tag/group filters; simplified table with OK/Fail health badges
* Templates page: category tabs with cloneable checklist cards, agency vault copy aligned to wireframe
* Built-in checklist library expanded to 17 agency templates across Security, Launch, Performance, SEO, E-Commerce, Maintenance, and Compliance categories
* Template cards show checklist step previews on hover or via View steps toggle

= 1.0.0 =
* Full platform release: sites, workflows, automation, templates, integrations, settings

= 1.0.1 =
* Automation & Audit page: batch queue engine, runner sidebar, audit filters (status/date/user), drift monitor status

= 0.1.0 =
* Initial scaffold
