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
* **Sites** — Register remote sites, connection tester, tagging and grouping, per-site checklist run history
* **Checklists** — Drag-and-drop builder, step configuration, API payload mapper, import/export, plus 86 built-in templates and agency vault
* **Automation & Audit** — Run control, live step tracker, immutable audit logs, drift verifier
* **Integrations** — MainWP, ManageWP, WP Umbrella, WP Engine, WPvibe adapters
* **Settings** — Encrypted credential vault, Slack/Discord/Teams webhooks, role guardrails

== Installation ==

1. Upload the plugin to `/wp-content/plugins/LaunchDek`
2. Activate through the Plugins menu
3. Go to LaunchDek → Sites to register your first remote site with an Application Password
4. Optional: set up the client checklist panel (see FAQ below)
5. Create or clone a checklist, then launch it from the Dashboard or Automation page

== Frequently Asked Questions ==

= Does LaunchDek require a plugin on remote sites? =

No for core automation. LaunchDek connects to client sites using Application Passwords and the WordPress REST API — checklists, API steps, connection tests, and drift checks work without installing anything on the client.

= How do I show the checklist panel on a client site's wp-admin? =

That is optional and requires a **one-time setup per client site**:

1. Connect the site on LaunchDek → Sites with an Application Password.
2. Download `launchdek-client.php` from the panel setup section in the site editor.
3. Upload it to `wp-content/mu-plugins/` on the client site (create the folder if needed).
4. Click **Retry panel install** on the hub — LaunchDek deploys the full panel bundle and verifies the connection.

On the same server (for example MAMP), the hub may install the panel automatically without the bootstrap upload. After the first setup, the hub handles panel updates and checklist pushes via REST.

The client panel is a bundled must-use plugin included with LaunchDek — not a separate plugin download from wordpress.org.

= What outbound connections does LaunchDek make? =

* User-registered remote WordPress sites (Application Password REST calls)
* Optional client panel deploy and checklist sync (user-initiated when connecting or pushing a run)
* User-configured webhook URLs (Slack, Discord, Teams)
* User-initiated integration adapter pushes

= Are credentials encrypted? =

Yes, when enabled in Settings, application passwords are encrypted using your WordPress salt keys.

== Changelog ==

= 1.0.26 =
* Templates: built-in template catalog is cached and preloaded on dashboard, checklists, and settings screens — category tabs and the template picker render on first paint without waiting for a REST fetch

= 1.0.25 =
* Admin: archive run, delete run, delete site, and delete checklist confirmations now use a WordPress-style modal instead of browser confirm dialogs

= 1.0.24 =
* Sites: checklist history **View run** is now a split button with a dropdown for Edit, Duplicate, Export, Archive, and Delete (archive hides the run from history; delete removes it permanently)
* Sites: checklist history loads faster with a lean API query (single JOIN + batched step progress), smaller default page size (25), in-flight request deduplication, and **Load more** pagination for long histories

= 1.0.23 =
* Sites: checklist history now includes a Steps column with step fraction, status badge, and progress bar (replaces the separate Status column)

= 1.0.22 =
* Dashboard: API Connection Status summary no longer shows an Unknown card — only All Sites, Healthy, and Issues (per-site untested state remains on the Sites page)

= 1.0.21 =
* Settings: customize the client checklist panel heading (default "Agency Checklist"); syncs on the next checklist push or client panel refresh
* Client checklist panel: step completion now updates progress in every minimized layout (sidebar tab, floating pill, toast, top bar, bottom dock, fullscreen launcher, and admin bar badge)

= 1.0.20 =
* Settings: first-run onboarding wizard no longer auto-opens when saving settings (for example after changing the client panel layout); use **Show onboarding wizard** to preview it from Settings

= 1.0.19 =
* Client checklist panel: WordPress settings deep links are inferred from checklist steps when possible (manual paths and API settings routes) and shown as a compact icon aligned with step actions
* Fix deep links not appearing — admin paths like options-general.php were stripped by URL sanitization when saving checklists; built-in templates are repaired automatically on next hub load
* Deep links are now inferred from step titles and instructions (e.g. "Settings → Permalinks to Post name" opens Permalinks) for pasted SOP and manual steps without an explicit path

= 1.0.18 =
* Settings: Exclude Options list — block sensitive WordPress settings fields (site URL, admin email, environment type, etc.) from being pushed to remote sites via API checklist steps

= 1.0.17 =
* Client checklist panel: minimize all notes with one click; each step's saved notes can also be collapsed individually

= 1.0.16 =
* Client checklist panel: Mark complete and Mark not complete feel instant — optimistic UI updates plus faster hub callbacks (no redundant panel re-sync or bundle reinstall on every step toggle)

= 1.0.15 =
* Sites page: expand each site row to view checklist run history (completed, running, and failed runs) with links to open the run on Automation

= 1.0.15 =
* Client checklist panel: removed Left sidebar and Split panel display layout options; sites using those layouts fall back to the right sidebar floater

= 1.0.14 =
* Client checklist panel: completed checklists show a summary with Started/Completed timestamps and a Dismiss button to close the panel
* Client checklist panel: completed checklists stay visible with all steps and completion details instead of disappearing from wp-admin
* Client checklist panel: step notes now save only when you type text and click Add note; empty or duplicate sync entries are filtered out
* Client checklist panel: saved notes display the author name and timestamp
* Client checklist panel: fix step notes being wiped when the hub re-syncs a run snapshot; notes now save locally first and merge with hub updates
* Client checklist panel: fix Mark complete for all logged-in wp-admin users; ensure legacy runs get callback tokens
* Client checklist panel: all steps collapsed by default (title + status only); chevron expands to show actions and notes
* Client checklist panel: collapsed tab label renamed to Expand
* Client checklist panel: completed steps show who completed them (name and email) and when
* Client panel bundle now syncs automatically when pushing a checklist to a connected site
* Automation page: Refresh Client Panel button re-pushes the active run snapshot without re-running steps

= 1.0.13 =
* Added 48 built-in troubleshooting checklists — common WordPress errors, plugin-specific guides, security incidents, performance, migration, and deployment issues
* New Troubleshooting template category on Checklists → Templates

= 1.0.12 =
* Client checklist panel: expandable steps with chevron toggle, "Go to settings" deep link, and per-step note field control from the hub builder
* Checklist builder: chevron on canvas steps opens step settings; manual steps can enable or disable the client note textarea

= 1.0.11 =
* Dashboard stat cards, API connection summary, and live log feed now load instantly from cache and refresh only when underlying data changes

= 1.0.11 =
* Client checklist panel: 11 display layouts selectable from Settings with a live wireframe preview
* Layouts include right/left sidebar, split panel, live top bar, bottom dock, floating pill, toast, admin bar flyout, fullscreen overlay, focus mode, and inline metabox
* Layout choice syncs to client sites on the next checklist push or client panel refresh

= 1.0.11 =
* Sites page: combined connection dot and health badge into a single Status column
* Sites page: Edit split-button with dropdown for Push Checklist and Test actions

= 1.0.10 =
* Dashboard live log feed limited to the latest 15 entries (with site name labels) and a link to view all activity logs
* Sites page: Activity Logs button links to a dedicated activity logs page with filters and action details
* Sites page: cached sites list with server-side preload on first paint; cache refreshes when sites are added, updated, or deleted

= 1.0.9 =
* Added 11 built-in checklist templates for popular plugins: Yoast SEO, Rank Math, Wordfence, Elementor, Easy Digital Downloads, Contact Form 7, LiteSpeed Cache, Site Kit by Google, All-in-One WP Migration, WPForms, and UpdraftPlus
* Added 10 general-purpose checklists: Fresh WordPress Setup, SSL & HTTPS, Reliable Email Delivery, Content Publish Review, User Access Audit, Plugin Health Audit, 404 & Redirect Audit, First Week After Launch, Database Cleanup, and Blog Launch
* Auto-capture: record configuration changes on a client site and import them as checklist steps in the hub builder
* Client panel watches REST mutations, core settings changes, and plugin toggles while recording is active

= 1.0.8 =
* Sites page: one-time client panel bootstrap download (`launchdek-client.php`), setup instructions, and Retry panel install for cross-host sites
* Readme and Sites UI copy now accurately describe optional panel setup vs core Application Password automation

= 1.0.7 =
* Client checklist panel bundle included with LaunchDek; hub deploys panel files after bootstrap or on same-server installs
* Hub push flow syncs checklist runs to the client admin sticky panel; clients can complete manual steps from the panel
* Sites page: optional "Show checklist on client admin panel" when pushing; Client panel badge when the panel is detected
* Checklists UI: horizontal canvas scrolling, equal-height template cards, softer page tabs, sidebar width, and correct singular/plural step counts

= 1.0.6 =
* Sites page: per-site Push Checklist button to start a run from the master hub, cached connection health with background refresh for stale sites, and a warning icon when a remote site is unreachable

= 1.0.5 =
* Combined Checklists and Templates into a single Checklists admin menu with My Checklists and Templates tabs
* Legacy Templates menu URL redirects to Checklists → Templates tab

= 1.0.4 =
* Template picker modal with capsule category filters — "Start from a template" and New Checklist open an in-page chooser instead of redirecting to the Templates page
* Template picker shows a steps preview panel before import; onboarding loads template steps into the paste field and live preview
* Select a built-in checklist template and import it directly into the builder or onboarding paste/preview

= 1.0.4 =
* Client checklist panel: focused runner UX with progress bar, "Step X of Y", collapsed completed steps, and localStorage focus preference
* Client panel step notes with optional screenshot attachments (media library); notes sync to hub and appear in Automation run tracker
* Hub webhooks for client_step_completed and client_note_added; dashboard feed highlights client completions and notes with site name

= 1.0.5 =
* Dashboard Quick Launch Bar: site and checklist dropdowns preload on first paint (no REST wait)

= 1.0.4 =
* Dashboard API Connection Status: summary stat cards (All Sites, Healthy, Issues, Unknown) with Manage Sites link to the full sites list

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
* Automation & Audit page: batch queue engine, runner sidebar, audit filters (status/date/user), drift monitor status

= 1.0.0 =
* Full platform release: sites, checklists, automation, templates, integrations, settings

= 0.1.0 =
* Initial scaffold
