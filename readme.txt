=== LaunchDek ===
Contributors: ashiquzzaman
Tags: agency, checklist, site management, onboarding, workflow
Requires at least: 6.0
Tested up to: 7.1
Requires PHP: 7.4
Stable tag: 1.0.0
License: GPLv3 or later
License URI: https://www.gnu.org/licenses/gpl-3.0.html

Agency hub for remote WordPress sites — build checklists, push runs to a bundled client panel in wp-admin, and track launches with audit logs.


== Description ==

**LaunchDek** is a self-hosted WordPress **agency hub** for teams who manage multiple client sites. Install it once on your command-center WordPress install, connect remote properties with Application Passwords, deploy LaunchDek’s bundled **must-use client checklist panel** on each site, and deliver launches, migrations, security work, and maintenance through checklists your clients actually see inside their own wp-admin—not buried in email threads, Notion pages, or a separate SaaS dashboard.

Stop copying the same SOP into chat every time a site goes live. LaunchDek turns procedures into structured **checklists** you build once, clone from **86 built-in templates**, save to a private vault, or import from JSON. Push a **run** to a connected site and the checklist appears in the client panel with progress, manual completion, optional notes and screenshots, and deep links into the right wp-admin screens. Your hub orchestrates API automation, connection health, batch execution, drift checks, and an immutable-style **activity log**—all on infrastructure you control.

**Built for Repeatable Delivery, Designed for WordPress:** Checklists combine **manual** checkpoints with **core REST API** steps through a guided payload mapper. Sensitive remote settings can stay protected via hub **exclude-options**. Outbound traffic is limited to sites you register, webhooks and email alerts you configure, and integration sync you start from the admin—no undisclosed telemetry. Whether you run five sites or fifty, you get one place to test connections, tag sites by client or tier, quick-launch from the dashboard, and answer “who did what, on which site, and when?” from Activity Logs.

#### Why Choose a Self-Hosted Agency Checklist Hub?

* **Own Your Workflow Data:** Checklists, runs, step notes, and audit entries live in your hub database. Export JSON, reuse templates, and keep delivery consistent without locking SOPs inside a third-party project tool.
* **WordPress-Native Remote Auth:** Application Passwords and core REST endpoints connect the hub to each client site, deploy the must-use panel, sync run snapshots, and execute API steps—no proprietary connector keys required for LaunchDek’s hub-to-client flow.
* **Manual and API Steps in One Run:** Blend human tasks (with inferred or explicit wp-admin deep links) with automated REST mutations while blocking sensitive `/wp/v2/settings` fields from remote pushes when you configure exclude-options.
* **Must-Use Client Panel (Required):** Every managed client site runs LaunchDek’s bundled mu-plugin. Checklists are **shown** in wp-admin through that panel—manual completion, notes, progress, and deep links—kept in sync when you push or refresh runs from the hub.
* **Audit-Ready Activity History:** Filterable activity logs with readable summaries, labeled detail fields, and optional raw JSON help you review launches, API steps, client completions, and drift events after the fact.
* **Role Guardrails for Teams:** Map LaunchDek capabilities to agency roles so developers, account leads, and auditors see only what they should—from dashboard visibility to checklist editing and run execution.

#### How It Works

1. **Install LaunchDek on Your Hub Site:** Activate on the WordPress install you use as your agency command center (local, staging, or production).
2. **Connect Each Client Site:** Add URL, username, and Application Password; run a connection test.
3. **Deploy the Must-Use Client Panel:** Complete the one-time mu-plugin setup on every remote site (see FAQ). LaunchDek does not deliver checklists to client wp-admin without this panel installed.
4. **Build or Import Checklists:** Start from built-in templates (security, launch, SEO, WooCommerce, maintenance, troubleshooting, and more), paste SOP text in onboarding, or use the three-column builder with live client-panel preview and API payload mapping.
5. **Push Runs and Execute:** Launch from the Dashboard or Batch Run—checklists appear in the client panel; track the same run on the hub with the step tracker, batch queue, and drift monitor.
6. **Review and Notify:** Watch the dashboard feed, open Activity Logs for filtered audit tables, and optionally send email or Slack, Discord, and Teams webhooks on run and step events you choose (Pro and Agency).

#### Designed for Every Agency Workflow

* **For Launch and Go-Live Projects:** Run go-live, migration, SMTP, caching, and SEO setup checklists so DNS, SSL, redirects, and analytics steps stay accounted for.
* **For Security and Compliance Engagements:** Apply hardening, access audit, and GDPR-oriented templates, with exclude-options protection so API checklist steps cannot overwrite sensitive remote settings you block in Settings.
* **For Ongoing Maintenance:** Operational discipline via monthly maintenance, backup verification, plugin health, and safe-update routine checklists—run after updates or on a cadence your team defines.
* **For Troubleshooting and Recovery:** Built-in troubleshooting stacks for common errors (white screen, REST blocked, email delivery, WooCommerce, Elementor, and dozens more) so junior developers follow the same diagnostic path seniors would.
* **For Agencies Standardizing SOPs:** Save private vault checklists, import and export JSON, and use Auto-Capture on a connected client site (Pro and Agency; client panel required) to record admin actions into draft steps during a live session.
* **For Teams Already on MainWP or WP Umbrella:** Sync site inventory into LaunchDek, map telemetry fields to site records, and deploy or refresh the must-use client panel on synced properties before pushing checklists.

#### Comprehensive Checklist Builder

Shape every delivery playbook with a three-column builder (canvas, step configuration, and live client-panel preview):

* **Steps Canvas:** Reorder steps with drag-and-drop; mix manual checkpoints and API automation in a single checklist.
* **Step Configuration:** Set instructions, inferred or explicit wp-admin deep links, target roles for client completion, optional note and screenshot fields, and API routes with a guided payload mapper.
* **Templates and Vault:** Browse built-in standard stacks by category, search across all template text, clone into My Checklists, and maintain a private agency vault for practice-specific stacks (Pro and Agency).
* **Import and Export:** Move checklist JSON between hubs or environments; validate API steps before production runs.
* **Client Panel Branding:** Customize panel layout and heading (for example “Agency Checklist”) from Settings and the builder; snapshots sync on push and refresh actions.

#### Sites, Runs, and Operations

* **Sites Registry:** Connection health, WordPress and PHP versions, tags, custom groups, integration metadata, and expandable per-site checklist run history with progress bars and completion timestamps.
* **Dashboard Command Center:** At-a-glance stats, connection ticker, cached quick-launch bar for site plus checklist pairs, and a live activity feed with links into full Activity Logs.
* **Batch Run:** Run Checklist wizard for single-target execution, Batch Queue for multi-site work, and Drift Monitor to compare remote state against expectations (scheduled drift on Pro and Agency).
* **Integrations Hub:** MainWP and WP Umbrella site sync with preview and client-panel push—always user-initiated from Integrations.
* **Settings You Can Trust:** Optional encrypted credential vault, exclude-options for remote settings mutations, role-permission matrix, and onboarding you can replay from Settings.

#### Why WordPress Agencies Choose LaunchDek

* **Replace Fragile Runbooks:** One checklist runs the same way on site ten as on site one—with step status, notes, and audit trails instead of unchecked Slack messages.
* **Client Visibility Without Giving Up Control:** Customers see checklist progress in wp-admin; run orchestration, API automation, and audit history stay on your master hub.
* **Faster Onboarding for New Team Members:** Templates plus deep links turn “ask Sarah how we launch WooCommerce” into “run the WooCommerce launch checklist.”
* **Honest Remote Architecture:** Panel deploy, run push, connection tests, integration sync, and drift checks happen when you initiate them—the client panel is always part of the checklist experience on remote sites.
* **GPL and Self-Hosted:** Community Edition runs entirely on your WordPress install; you choose hosting, backups, and who gets access via WordPress roles and LaunchDek capabilities.

#### Community vs. LaunchDek Pro and Agency

**Community Edition (WordPress.org) features:**

* Unlimited connected sites on your hub
* **17** built-in checklist templates across **4** categories (security, maintenance, performance, ecommerce)
* Unlimited custom checklists, import/export, and JSON validation
* Sites registry, connection tests, tags, custom site groups, and per-site run history
* Dashboard stats, quick launch, activity feed, and full Activity Logs
* Batch Run (single-site wizard, batch queue, manual drift verify)
* MainWP and WP Umbrella inventory sync, telemetry mapping, and client panel push
* Must-use client checklist panel deploy and run sync
* **3** client panel layouts (live top bar, bottom dock, floating pill)
* Encrypted credential vault (optional), exclude-options, role permission matrix
* Onboarding wizard and built-in template search

**LaunchDek Pro and Agency features:**

* **86** built-in templates across **8** categories—including launch, SEO, compliance, and troubleshooting
* Private **Agency Vault** for reusable internal checklists
* **Auto-Capture** — record admin actions on a client site and import steps into the builder
* **Email and webhook notifications** (Slack, Discord, Teams) for opt-in checklist events
* **Scheduled drift verification** (twicedaily cron across connected sites)
* **9** client panel layouts (sidebar, toast, admin bar flyout, fullscreen, focus mode, inline metabox, and more)
* Licensed site caps: **Pro** — 99 connected sites; **Agency** — 199 connected sites (Community remains unlimited)

Pro and Agency share the same feature set; Agency includes a higher licensed site cap for larger portfolios.

#### Supercharge Your Agency Workflow with LaunchDek Pro

Community LaunchDek covers unlimited hub sites, custom checklists, batch execution, integrations, audit logs, and a strong starter template library. **LaunchDek Pro and Agency** are built for teams that need the full template library, client-facing polish, proactive alerts, and vault workflows without leaving WordPress.

* **Ship launches faster:** Unlock launch, SEO, compliance, and **48+ troubleshooting** templates so every engagement starts from a proven stack—not a blank doc.
* **Standardize agency IP:** Save checklists to the **Private Agency Vault**, clone them across projects, and export JSON between staging and production hubs.
* **Capture SOPs while you work:** **Auto-Capture** on a connected client site records whitelisted admin actions into draft checklist steps during a live session (client panel required).
* **Stay informed without watching the hub:** Opt-in **email** (`wp_mail`) and **Slack, Discord, or Teams** webhooks for run started, completed, failed, drift detected, client step completed, and more.
* **Catch config drift early:** **Scheduled drift verification** runs on a cadence you enable in Settings; manual drift verify remains available on all plans from Batch Run.
* **Match client branding:** Choose from the full client panel layout library so the checklist feels native in each client’s wp-admin.
* **Upgrade without rebuilding:** Purchase Pro or Agency, install the full plugin package on your hub, activate your license under **LaunchDek → Billing**, and keep existing sites, checklists, runs, and audit history in your database.

Compare plans and pricing: [LaunchDek on Asphalt Themes](https://asphaltthemes.com/launchdek)

#### More Notable Products From the Author

LaunchDek is developed by [Asphalt Themes](https://asphaltthemes.com/). These free products from the same author may also help your WordPress workflow:

* **[Trustbadg – Trust Badges, Payment Icons & Secure Checkout Seals](https://wordpress.org/plugins/trustbadg-trust-badges/)** — Display trust seals and payment icons in Gutenberg with automatic WooCommerce and EDD placements, inline SVG icons, and no frontend JavaScript bloat.
* **[Rezicraft – Professional Resume & CV Builder](https://wordpress.org/plugins/rezicraft-professional-resume-cv-builder/)** — Create polished online resumes and CVs with Gutenberg-friendly layouts and templates for job seekers and freelancers.
* **[Resumee](https://wordpress.org/themes/resumee/)** — A free WordPress resume and portfolio theme with flexible sections and a clean layout for professional online profiles.

#### Why LaunchDek vs. Third-Party Agency SaaS

Many multi-site management tools centralize dashboards in the cloud, charge per seat or per site, and treat checklists as secondary features. LaunchDek reimagines agency delivery by keeping the **hub** and **workflow data** on WordPress you already host.

* **Complete Data Ownership & Privacy**
  Checklists, credentials (encrypted when enabled), runs, and audit logs stay on your hub. Remote calls go only to sites you register. Integrations sync only when you click Sync. No undisclosed analytics or visitor tracking in the Community build.
* **No Mandatory SaaS Dashboard**
  Build and execute from familiar wp-admin screens on your hub. Clients interact with a lightweight mu-plugin panel on their site—not a separate login to a vendor portal.
* **WordPress-Native Automation**
  API checklist steps use **core REST** endpoints with Application Passwords—not arbitrary remote code execution. Exclude-options and payload validation keep mutating steps predictable.
* **Transparent Client Experience**
  The bundled client panel is explicit, GPL-friendly, and deployed by you (auto-install when possible, or manual bootstrap plus **Retry panel install**). Clients see progress where they work: WordPress admin.
* **Honest Outbound Scope**
  Allowed connections: registered remote sites, user-configured webhooks, optional `wp_mail` notifications, and user-initiated MainWP or WP Umbrella API sync—not background telemetry.

#### Privacy

LaunchDek Community does not send site or user data to LaunchDek-operated analytics servers. Outbound requests are limited to user-registered remote WordPress sites (Application Password REST), client panel deploy and snapshot sync (when you connect, test, or push a run), user-configured webhook URLs, optional email notifications via WordPress `wp_mail`, and user-initiated integration sync (MainWP child sites or WP Umbrella Public API when you configure a token). WP Umbrella tokens are stored encrypted on the hub and are never returned in REST responses. Client run callback tokens travel only between hub and client panel—not in audit entries shown to non-privileged users.

#### Performance

LaunchDek is built for responsive agency wp-admin workflows:

* **Cached dashboard** — stats, connection ticker, live log feed, and quick-launch picker options preload on first paint and refresh when underlying data changes.
* **Lean list APIs** — checklist summaries, site run history, and activity log pagination use lightweight queries; Activity Logs and site history can load via admin-ajax on memory-constrained installs.
* **Targeted remote calls** — connection tests, run execution, drift verification, panel deploy, and integration sync run on user action or configured cron—not on every admin page load (Sites may refresh stale connection health in the background after a threshold).
* **Client panel efficiency** — panel CSS and JS on remote sites load when an active run snapshot exists; step toggles use optimistic UI with hub callbacks that avoid redundant full panel reinstalls on every click.

#### Get Started in Minutes

After activation, the onboarding wizard helps you paste an SOP or import a template, connect your first site, install the client panel on that site, then push your first checklist run. Add more sites under **LaunchDek → Sites** (each needs the mu-plugin), build under **Checklists**, and scale execution with **Batch Run**.

The client checklist panel is **mandatory** on every remote site you manage with LaunchDek. Setup steps appear in the site editor and in the FAQ below; the hub deploys the bundled files into `wp-content/mu-plugins/` when credentials and permissions allow.


== Screenshots ==

1. Dashboard command center with stats, connection ticker, quick launch bar, and live activity feed
2. Onboarding wizard — paste an SOP or start from a template, then connect your first client site
3. Checklists → Templates — built-in standard stacks by category with cross-category search
4. My Checklists three-column builder — drag-and-drop canvas, step configuration, and live client-panel preview
5. Sites registry with connection health, tags, expandable checklist run history, and client panel setup
6. Batch Run — Run Checklist wizard, batch queue, and drift monitor tabs
7. Activity Logs — filtered audit table with readable summaries and expandable detail fields
8. Integrations — MainWP and WP Umbrella connectors with sync preview and client panel push
9. Settings — panel layout picker, exclude-options, credential vault, and role permissions
10. Remote client wp-admin — checklist panel with progress, deep links, notes, and manual step completion


== Installation ==

#### Quick WordPress Installation (hub site)

1. In your WordPress admin dashboard, navigate to **Plugins → Add New**.
2. Search for **LaunchDek**.
3. Click **Install Now**, then click **Activate**.
4. Complete the onboarding wizard or open **LaunchDek** in the admin sidebar to connect your first client site and deploy the client checklist panel.

#### Manual Installation (hub site)

1. Download the plugin archive and upload the `launchdek` folder to the `/wp-content/plugins/` directory on your **hub** server.
2. Activate the plugin through the **Plugins** screen in WordPress.
3. Go to **LaunchDek → Sites**, register each client site with an Application Password, and complete client panel setup on every remote site before pushing checklists.

#### Client site setup (every managed site)

LaunchDek checklists appear in client wp-admin only through the bundled must-use panel:

1. Connect the site on **LaunchDek → Sites** with an Application Password.
2. Download `launchdek-client.php` from the panel setup section in the site editor (or use automatic deploy when the hub can write to the client’s `mu-plugins` folder).
3. Upload the bootstrap file to `wp-content/mu-plugins/` on the client site if needed, then click **Retry panel install** on the hub.
4. Push a checklist run from the hub; the client panel displays the active run snapshot.


== Frequently Asked Questions ==

= Who is LaunchDek built for? =

LaunchDek is built for WordPress agencies, freelancers, and in-house web teams who manage multiple client sites and want repeatable checklists, client-visible progress in wp-admin, and audit-friendly history—without moving SOPs into a separate SaaS product.

= Does LaunchDek require a plugin on remote client sites? =

**Yes.** Every managed remote site must have LaunchDek’s bundled **must-use client checklist panel** in `wp-content/mu-plugins/` (one-time setup per site). That panel is how checklists appear, how manual steps are completed, and how notes and progress sync with your hub. There is no supported “hub only, no client UI” workflow.

= How do I install the client checklist panel on a remote site? =

1. Connect the site on **LaunchDek → Sites** with an Application Password.
2. Download `launchdek-client.php` from the panel setup block in the site editor (or onboarding / connection tester).
3. Upload it to `wp-content/mu-plugins/` on the client site (create the folder if needed).
4. Click **Retry panel install** on the hub so LaunchDek deploys the full panel bundle and verifies the connection.

On the same server (for example local MAMP), the hub may install the panel automatically when credentials allow. The client panel is bundled with LaunchDek—it is not a separate WordPress.org plugin listing.

= Can I skip the must-use plugin and still show checklists to clients? =

No. Email, exports, or hub-side tracking alone are not substitutes for the in-dashboard checklist experience LaunchDek delivers through the client panel.

= Does LaunchDek work without MainWP or WP Umbrella? =

Yes. Integrations are optional. You can register sites manually with Application Passwords, deploy the client panel, and run checklists without any connector plugin.

= What is the difference between Community, Pro, and Agency? =

**Community** (this WordPress.org build) includes unlimited hub sites, core checklist and batch features, integrations, audit logs, **17** templates in four categories, and three client panel layouts. **Pro** and **Agency** unlock the full **86** templates, agency vault, auto-capture, email and webhook notifications, scheduled drift cron, and all panel layouts; **Pro** supports up to **99** licensed sites and **Agency** up to **199**. Compare details under **LaunchDek → Billing** on a full install or on [Asphalt Themes](https://asphaltthemes.com/launchdek).

= Are credentials stored securely? =

When enabled in Settings, site Application Passwords are encrypted at rest using your WordPress salt keys. Decrypted passwords are never exposed in REST responses or audit entries shown to non-privileged users.

= What outbound connections does LaunchDek make? =

* User-registered remote WordPress sites (Application Password REST calls)
* Client panel deploy and checklist sync to registered sites (when you connect, test, or push a run)
* User-configured webhook URLs (Slack, Discord, Teams) on Pro and Agency when events are enabled
* Optional email alerts for checklist events via WordPress `wp_mail` on Pro and Agency
* User-initiated integration sync and panel push (MainWP child sites or WP Umbrella Public API when configured)

= Does LaunchDek require WooCommerce on client sites? =

No. WooCommerce-specific steps appear in some built-in templates, but the hub and client panel work with any WordPress 6.0+ site that supports Application Passwords and REST.

= Can I use LaunchDek on a local hub (MAMP, Local, etc.)? =

Yes. The hub can manage local and remote URLs. The remote client may require the `?rest_route=` fallback when pretty permalinks are broken; LaunchDek handles that automatically for REST calls. Admin-ajax handlers are used for some heavy lists on memory-constrained local installs.

= What happens when I uninstall LaunchDek from the hub? =

Uninstall removes LaunchDek tables, options, and custom capabilities from the hub site. Client sites retain the mu-plugin files until you remove them manually from `wp-content/mu-plugins/`. Completed hub run history is deleted with the plugin—export checklists first if you need archives.

= How do I upgrade to Pro or Agency? =

Purchase from [Asphalt Themes](https://asphaltthemes.com/launchdek), install the full plugin package on your hub (replacing the Community build if needed), activate your license under **LaunchDek → Billing**, and continue using existing sites and checklists stored in your database.


== Changelog ==

= 1.0.48 =
* Billing: Community (free, unlimited sites), Pro ($49/mo, 99 sites), and Agency ($99/mo, 199 sites) tiers with plan gates for templates, vault, auto-capture, notifications, scheduled drift, and panel layouts
* Billing page: feature comparison table with plan columns, tooltips, and purchase links; REST `GET /billing/summary` and `POST /billing/plan` (manage settings) for license activation hook point
* Community plan: Settings and Checklists admin screens show only free-tier controls—Pro-only sections (webhooks, email alerts, scheduled drift, vault, auto-capture) are omitted instead of disabled with upgrade prompts
* Community zip build (`php bin/build-community-zip.php`): sets `LAUNCHDEK_BUILD` to `community`, omits Pro PHP (notifications, auto-capture, scheduled drift, billing UI), and ships only Community template categories

= 1.0.47 =
* Billing: plan comparison table (Free, Personal, Professional, Agency) with feature tooltips and purchase links

= 1.0.46 =
* Notifications: drift verification now sends email and webhook alerts when newly detected configuration drift appears (scheduled or manual verify)
* Notifications: webhook event checkboxes save correctly when all are unchecked; empty selection no longer sends every event to configured webhooks
* Settings: optional notifications for cancelled checklist runs (webhook and email event lists)

= 1.0.45 =
* Admin UI: improved responsive layout on phones and tablets — scrollable data tables, stacked toolbars and forms, mobile-friendly modals, and Sites table hides version columns on narrow screens to reduce horizontal scrolling
* Client checklist panel: uses more of the screen width on very small devices with wrapped step action controls

= 1.0.44 =
* Fix WP Umbrella connector setup: saving the Public API token no longer gets stripped by the settings sanitizer (token persists and the connector shows as connected)
* WP Umbrella: validate Public API tokens on save, preserve encrypted token storage, normalize pasted values (e.g. accidental “Bearer ” prefix), and clarify errors when the wrong key type is used
* Settings: organize hub options into four tabs — Platform & Security, Panel layout & Exclude, Webhooks & Email, and Access & Roles (Save Changes applies all sections)
* Activity Logs: first page loads faster — server-preloaded rows on page load, lightweight admin-ajax feed (avoids full REST bootstrap), lean list queries, and a faster site filter SQL path for `?site_id=` links from Sites
* Activity Logs: **View raw data** loads faster via a lean details fetch, in-memory cache, and prefetch when **View details** is expanded
* Checklists: **Built-In Standard Stacks** includes a search bar to find templates by title, description, slug, or step text (searches all categories; category badges appear on results)
* Sites: checklist history table shows a **Completed** timestamp (in progress runs show —) and no longer includes the **View run** action column
* Activity Logs: **What happened** shows a short summary plus **View details** (labeled fields for run, step, API, drift, and more) and nested **View raw data** (JSON on click); when filtered by site, the Site column is hidden; site filter includes run-linked audit entries
* Sites: improve **Add to group** and custom group UI — stacked new-group field, **Back** instead of a second Cancel, and focus the add-group step without duplicate modal actions
* Sites: show the full site name and URL in the table (no hostname-only or truncated labels)
* Sites: row actions menu **More info** opens a site details modal (ID, URL, username, versions, connection, client panel, credentials, tags, and more) for quicker debugging

= 1.0.43 =
* Sites: add custom tag groups from the site editor (Add group…) and use them in the group filter dropdown; groups are saved in hub settings
* Sites: **Add to group** in the row actions menu assigns a tag and group without opening the full site editor

= 1.0.42 =
* WP Umbrella integration: import connected projects via the Public API, encrypted API token storage in connector setup, client panel push for synced sites with Application Passwords, and sync diagnostics when no sites import

= 1.0.41 =
* Integrations: use admin-ajax for sync, preview, push, and telemetry rule saves so connector actions work on memory-constrained local installs where the REST API cannot bootstrap
* Sites: load expandable checklist run history via admin-ajax so the history panel works on the same memory-constrained installs

= 1.0.40 =
* Integrations: server-render connector list and telemetry rules on first paint (no REST wait); batch synced-site counts in one query; cache MainWP table detection per request
* Integrations: fix Preview Sync "No route was found" on some local/MAMP installs by using the POST sync route with dry_run instead of a separate GET preview route

= 1.0.39 =
* MainWP integration: fix site sync SQL that referenced sync_errors on the wrong table, which could break the REST response and show "Something went wrong"

= 1.0.38 =
* Settings: email notifications for checklist events (completed, started, failed, step failed, drift, client step, client note) with comma-separated recipient addresses

= 1.0.37 =
* Admin: new Billing page in the LaunchDek sidebar (plan overview, hub usage, invoices placeholder)

= 1.0.36 =
* MainWP integration: sync child sites into LaunchDek with telemetry mapping rules (name, URL, WP version, PHP version)
* MainWP integration: push the client checklist panel to synced child sites through MainWP when Application Passwords are not yet configured
* Integrations page: Sync Sites, Preview Sync, and Push Client Panel actions in the connector setup modal

= 1.0.35 =
* Settings: section headings and client panel fields now include left-side info icons with tooltips explaining each area

= 1.0.35 =
* Sites: each site's actions menu includes **Activity Log**, opening the Activity Logs page with that site pre-selected in the filter
* Activity Logs: run-related entries include **View completed steps** — expand to see each finished step with completion time and who completed it (lazy-loaded per run)

= 1.0.34 =
* Activity Logs: faster page load — paginated results (50 per page with Load more), preloaded site filter, lean API responses, batch database lookups, and lazy raw-data fetch on demand
* Automation: removed duplicate full activity log table; Run tab now shows a compact site-scoped recent activity feed with a link to the dedicated Activity Logs page
* Renamed **Automation & Audit** menu page to **Batch Run** (run wizard, batch queue, drift monitor)

= 1.0.33 =
* Activity Log: human-readable summaries replace raw JSON in audit tables; timestamps, filters, and column labels are easier to scan; raw payload data is available on demand via "View raw data"

= 1.0.33 =
* Sites: delete a site from the row actions menu or the edit modal — removes the site, its tags, connection history, and all checklist runs

= 1.0.32 =
* Automation: redesigned with Run Checklist / Batch Queue / Drift Monitor tabs and a 3-step run wizard (choose checklist → choose site → execute steps); step runner is now full-width with progress bar and contextual actions

= 1.0.31 =
* Automation: Target sites selector is now a multi-check dropdown (checkbox list) instead of a native multi-select

= 1.0.30 =
* Checklists: removed the New Checklist tab — use Templates or Start Blank in My Checklists to create checklists
* Checklists: Step Type and API Payload Mapper fields now include info tooltips — API help updates when you switch between Manual and API
* Checklists: builder preview no longer shows the client panel progress bar

= 1.0.29 =
* Checklists: **My Checklists** builder preview now mirrors the remote client checklist panel (sidebar layout, step cards, progress bar, and note controls)
* Checklists: **My Checklists** builder uses a three-column layout — canvas, step configuration, and live preview — with checklist selection via dropdown only (custom checklist cards removed)
* Checklists: edit the client panel heading (default "Agency Checklist") directly in the builder preview sidebar; saves automatically and syncs on the next checklist push or client panel refresh
* Checklists: **Private Agency Vault** section now includes clearer copy and an info tooltip explaining how to save and reuse agency templates
* Checklists: **Your Custom Checklists** now loads faster — lightweight summary API, server-side preload on the Checklists page, and deduplicated client fetches (one request instead of three)
* Templates: template card actions (View steps, Edit, Use Checklist) are hidden by default and appear on card hover

= 1.0.28 =
* Checklists: My Checklists, Templates, New Checklist, and Auto-Capture are now unified tab navigation on a single page (Auto-Capture is inline instead of a modal)

= 1.0.27 =
* Checklists: My Checklists tab now uses a dropdown selector instead of listing every checklist in a sidebar
* Checklists: **Use Checklist** on built-in templates, custom checklists, and vault items now opens the checklist builder so you can review steps, customize, and save
* Checklists: Your Custom Checklists cards show **Edit Checklist** and **Use Checklist** side by side (no View steps toggle)

= 1.0.26 =
* Templates: built-in template catalog is cached and preloaded on dashboard, checklists, and settings screens — category tabs and the template picker render on first paint without waiting for a REST fetch

= 1.0.25 =
* Admin: archive run, delete run, delete site, and delete checklist confirmations now use a WordPress-style modal instead of browser confirm dialogs

= 1.0.25 =
* Automation page: validation and run feedback now uses WordPress inline notices instead of browser alert dialogs

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

= 1.0.19 =
* Onboarding and connection flows: download the client panel bootstrap file (`launchdek-client.php`) with setup steps when connecting a site (onboarding Step 2, Add Site, and Connection Tester)
* Connection test (unsaved credentials) now reports whether the optional client checklist panel is detected on the remote site

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

= 1.0.15 =
* Client checklist panel: LaunchDek branding bar at the top of the panel across all display layouts; builder preview mirrors the branded header

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
