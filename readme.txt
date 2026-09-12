=== LaunchDek ===
Contributors: ashiquzzaman
Tags: agency, workflow, remote, automation, wordpress
Requires at least: 6.0
Tested up to: 6.7
Requires PHP: 7.4
Stable tag: 1.0.0
License: GPLv3 or later
License URI: https://www.gnu.org/licenses/gpl-3.0.html

Remote WordPress site orchestration hub for agencies — connect sites, run checklist workflows, and audit everything.

== Description ==

LaunchDek is a master-site plugin for WordPress agencies to manage remote client sites via Application Passwords and REST API workflows.

**Features:**

* **Dashboard** — Live stats, API connection ticker, activity log feed, quick launch bar
* **Sites** — Register remote sites, connection tester, tagging and grouping
* **Workflows** — Drag-and-drop builder, step configuration, API payload mapper, import/export
* **Automation & Audit** — Run control, live step tracker, immutable audit logs, drift verifier
* **Templates** — Built-in stacks (security, WooCommerce, SEO, migration) and agency vault
* **Integrations** — MainWP, ManageWP, WP Umbrella, WP Engine, WPvibe adapters
* **Settings** — Encrypted credential vault, Slack/Discord/Teams webhooks, role guardrails

== Installation ==

1. Upload the plugin to `/wp-content/plugins/LaunchDek`
2. Activate through the Plugins menu
3. Go to LaunchDek → Sites to register your first remote site
4. Create or clone a workflow, then launch it from the Dashboard or Automation page

== Frequently Asked Questions ==

= Does LaunchDek require a plugin on remote sites? =

No. LaunchDek connects to remote sites using WordPress Application Passwords and the standard REST API.

= Are credentials encrypted? =

Yes, when enabled in Settings, application passwords are encrypted using your WordPress salt keys.

== Changelog ==

= 1.0.1 =
* Settings page aligned to wireframe: credential vault, webhook channel routing, agency role guardrails
* Integrations page: Platform Connectors Hub with Connected/Setup actions and Telemetry Sync Mapping Rules
* Dashboard layout aligned to wireframe: live status cards, horizontal API connection ticker, readable log feed, quick launch bar
* Workflows page: visual drag-and-drop canvas, sidebar step configuration with role target mapping, import/export center at bottom
* Sites page toolbar: Add Site, Connection Tester, tag/group filters; simplified table with OK/Fail health badges
* Templates page: horizontal built-in stack selector, detail panel with clone action, agency vault copy aligned to wireframe

= 1.0.0 =
* Full platform release: sites, workflows, automation, templates, integrations, settings

= 1.0.1 =
* Automation & Audit page: batch queue engine, runner sidebar, audit filters (status/date/user), drift monitor status

= 0.1.0 =
* Initial scaffold
