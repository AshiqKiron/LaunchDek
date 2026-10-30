=== ChecklistDek ===
Contributors: ashiquzzaman
Tags: agency, checklist, site management, onboarding, workflow
Requires at least: 6.2
Tested up to: 7.1
Requires PHP: 7.4
Stable tag: 1.0.8
License: GPLv3 or later
License URI: https://www.gnu.org/licenses/gpl-3.0.html

Agency hub for remote WordPress sites — build checklists, push runs to a bundled client panel in wp-admin, and track launches with audit logs.


== Description ==

**ChecklistDek** is a self-hosted WordPress **agency hub** for teams who manage multiple client sites. Install it on your command-center WordPress site, connect remote properties with Application Passwords, deploy the bundled **must-use client checklist panel** on each client site, and deliver launches, migrations, security, and maintenance through checklists clients see in their own wp-admin.

Turn SOPs into structured **checklists**—build from scratch, clone built-in templates, or import JSON. Push a **run** to a connected site; progress, manual steps, optional notes, and wp-admin deep links sync through the client panel. The hub handles connection health, **manual and core REST API** steps (with exclude-options for sensitive settings), batch execution, drift checks, and filterable **activity logs** on infrastructure you control.

#### Key features

* **Hub + client panel** — Orchestrate from your hub; clients complete steps in wp-admin (mu-plugin required on every managed site; see FAQ).
* **Templates and builder** — Built-in stacks by category, three-column builder with live panel preview, import/export JSON.
* **Sites and operations** — Site registry with tags and run history, dashboard quick launch, Batch Run and drift monitor, optional MainWP / WP Umbrella sync.
* **Team controls** — Role capabilities, optional encryption for stored Application Passwords on the hub, onboarding wizard.

#### Community vs Pro

**Community (this build):** Unlimited hub sites; starter template library; custom checklists, integrations, Activity Logs, manual drift verify; client panel via manual mu-plugin upload and three panel layouts.

**Pro and Agency:** Full template library, Private Agency Vault (team checklist templates—not Application Password encryption), Auto-Capture, email and Slack/Discord/Teams webhooks, scheduled drift, hub auto-deploy of the client panel, and nine panel layouts. [Compare plans](https://asphaltthemes.com/checklistdek).

#### Privacy

Community does not phone home to ChecklistDek analytics. Outbound traffic is limited to sites you register and integration sync you start. Credentials are encrypted on the hub when enabled and are not exposed in REST or audit views.

Developed by [Asphalt Themes](https://asphaltthemes.com/). Use the onboarding wizard after activation to connect a site, install the client panel, and push your first run.


== Screenshots ==

1. Dashboard command center with stats, connection ticker, quick launch bar, and live activity feed
2. Onboarding wizard — paste an SOP or start from a template, then connect your first client site
3. Checklists → Templates — built-in standard stacks by category with cross-category search
4. My Checklists three-column builder — drag-and-drop canvas, step configuration, and live client-panel preview
5. Sites registry with connection health, tags, expandable checklist run history, and client panel setup
6. Batch Run — Run Checklist wizard, batch queue, and drift monitor tabs
7. Activity Logs — filtered audit table with readable summaries and expandable detail fields
8. Integrations — MainWP and WP Umbrella connectors with sync preview (manual client panel setup on Community)
9. Settings — panel layout picker, exclude-options, Application Password encryption, and role permissions
10. Remote client wp-admin — checklist panel with progress, deep links, notes, and manual step completion


== Installation ==

#### Quick WordPress Installation (hub site)

1. In your WordPress admin dashboard, navigate to **Plugins → Add New**.
2. Search for **ChecklistDek**.
3. Click **Install Now**, then click **Activate**.
4. Complete the onboarding wizard or open **ChecklistDek** in the admin sidebar to connect your first client site and deploy the client checklist panel.

#### Manual Installation (hub site)

1. Download the plugin archive and upload the `checklistdek` folder to the `/wp-content/plugins/` directory on your **hub** server.
2. Activate the plugin through the **Plugins** screen in WordPress.
3. Go to **ChecklistDek → Sites**, register each client site with an Application Password, and complete client panel setup on every remote site before pushing checklists.

#### Client site setup (every managed site)

ChecklistDek checklists appear in client wp-admin only through the bundled must-use panel:

1. Connect the site on **ChecklistDek → Sites** with an Application Password.
2. Download `checklistdek-client.php` from the panel setup section in the site editor and upload the full client panel bundle to `wp-content/mu-plugins/` on the client site.
3. Click **Retry panel install** on the hub to verify the panel is detected.
4. Push a checklist run from the hub; the client panel displays the active run snapshot.


== Frequently Asked Questions ==

= Who is ChecklistDek built for? =

ChecklistDek is built for WordPress agencies, freelancers, and in-house web teams who manage multiple client sites and want repeatable checklists, client-visible progress in wp-admin, and audit-friendly history—without moving SOPs into a separate SaaS product.

= Does ChecklistDek require a plugin on remote client sites? =

**Yes.** Every managed remote site must have ChecklistDek’s bundled **must-use client checklist panel** in `wp-content/mu-plugins/` (one-time setup per site). That panel is how checklists appear, how manual steps are completed, and how notes and progress sync with your hub. There is no supported “hub only, no client UI” workflow.

= How do I install the client checklist panel on a remote site? =

1. Connect the site on **ChecklistDek → Sites** with an Application Password.
2. Download `checklistdek-client.php` from the panel setup block in the site editor (or onboarding / connection tester).
3. Upload it to `wp-content/mu-plugins/` on the client site (create the folder if needed).
4. Click **Retry panel install** on the hub to verify the connection.

The WordPress.org Community build does not write panel PHP into `mu-plugins` or uploads automatically—you install the bundled files yourself. The client panel ships with ChecklistDek; it is not a separate WordPress.org plugin listing.

= Can I skip the must-use plugin and still show checklists to clients? =

No. Email, exports, or hub-side tracking alone are not substitutes for the in-dashboard checklist experience ChecklistDek delivers through the client panel.

= Does ChecklistDek work without MainWP or WP Umbrella? =

Yes. Integrations are optional. You can register sites manually with Application Passwords, deploy the client panel, and run checklists without any connector plugin.

= What is the difference between Community, Pro, and Agency? =

**Community** (this WordPress.org plugin) is complete for its scope: unlimited hub sites, custom checklists, batch runs, integrations, Activity Logs, manual drift verification, **17** built-in templates in four categories, and **three** client panel layout choices in Settings. Client sites use the bundled mu-plugin you upload manually (this build does not auto-write panel files to remote servers).

**Pro** and **Agency** are separate downloads from [Asphalt Themes](https://asphaltthemes.com/checklistdek) with a larger template library, Private Agency Vault, Auto-Capture, email and webhook notifications, scheduled drift cron, hub auto-deploy of the client panel, nine panel layout choices, and licensed site tiers on paid plans. Application Password encryption in Settings is included in Community and Pro. They replace this plugin on your hub; your existing sites and checklists stay in the database.

= Are credentials stored securely? =

When enabled under **Settings → Application password encryption**, site Application Passwords are encrypted at rest using your WordPress salt keys. Decrypted passwords are never exposed in REST responses or audit entries shown to non-privileged users.

= Is “Application password encryption” the same as Private Agency Vault? =

No. **Application password encryption** (Settings, included in Community and Pro) protects Application Passwords stored on your hub for remote site connections. **Private Agency Vault** is a Pro feature for saving your own checklists as reusable team templates on the Checklists → Templates tab—it does not store site passwords.

= What outbound connections does ChecklistDek make? =

* User-registered remote WordPress sites (Application Password REST calls)
* Checklist run snapshots synced to registered sites after you install the client panel and push a run (Community uses manual panel upload; Pro/Agency may also auto-deploy panel files from the hub)
* User-initiated integration sync (MainWP child sites or WP Umbrella Public API when configured)
* Pro and Agency only: user-configured webhook URLs (Slack, Discord, Teams) and optional email alerts via WordPress `wp_mail` when those events are enabled in the separate paid package

= Does ChecklistDek require WooCommerce on client sites? =

No. WooCommerce-specific steps appear in some built-in templates, but the hub and client panel work with any WordPress 6.0+ site that supports Application Passwords and REST.

= Can I use ChecklistDek on a local hub (MAMP, Local, etc.)? =

Yes. The hub can manage local and remote URLs. The remote client may require the `?rest_route=` fallback when pretty permalinks are broken; ChecklistDek handles that automatically for REST calls. Admin-ajax handlers are used for some heavy lists on memory-constrained local installs.

= What happens when I uninstall ChecklistDek from the hub? =

Uninstall removes ChecklistDek tables, options, and custom capabilities from the hub site. Client sites retain the mu-plugin files until you remove them manually from `wp-content/mu-plugins/`. Completed hub run history is deleted with the plugin—export checklists first if you need archives.

= How do I upgrade to Pro or Agency? =

Purchase from [Asphalt Themes](https://asphaltthemes.com/checklistdek), install the Pro or Agency plugin package on your hub (replacing this WordPress.org build), and complete license activation in that package. Your existing sites and checklists remain in the database.


== External services ==

Optional connectors run only when an administrator with **Manage Settings** starts an action under **ChecklistDek → Integrations**—not on activation or routine admin loads. ChecklistDek does not send checklists, runs, audit logs, or Application Passwords to these vendors.

= MainWP Dashboard =

[MainWP](https://mainwp.com/) (Dashboard on hub, Child on client sites) is optional and requires MainWP Dashboard on the hub.

**When:** **Sync Sites**, **Preview Sync**, or **Push Client Panel** in Integrations.

**Data:** Sync reads local MainWP tables on the hub (child ID, name, URL, optional versions) into ChecklistDek sites. Pro/Agency panel push may use MainWP Connect over HTTPS (child may fetch a bootstrap ZIP from your hub). Community does not use that install path—upload the client panel manually.

**Why:** Import your MainWP site list and optional field mapping; Pro/Agency may deploy the client panel without hub Application Passwords.

**Legal:** [Terms of Service](https://mainwp.com/terms-of-service/) · [Privacy Policy](https://mainwp.com/privacy-policy/).

= WP Umbrella Public API =

[WP Umbrella](https://wp-umbrella.com/) is optional. The connector calls `https://public-api.wp-umbrella.com` (for example `GET /projects` with your Public API token).

**When:** After you save a token or click **Sync Sites** / **Preview Sync**. Panel push uses Application Passwords to your sites only—not WP Umbrella.

**Data:** Project name, URL, ID, and optional version hints are stored locally; the token is encrypted on the hub and never shown in REST or UI. Token save may run a one-project validation request.

**Why:** Import WP Umbrella inventory and map fields before you connect sites in ChecklistDek.

**Legal:** [Terms](https://wp-umbrella.com/terms-conditions/) · [Privacy Policy](https://wp-umbrella.com/privacy-policy/).


== Changelog ==

= 1.0.8 =
* Edition split: WordPress.org zip ships only working Community features (Pro PHP omitted at build time); Pro zip bundles premium modules and Freemius license gates.
* WordPress.org zip omits all Private Agency Vault PHP, UI, and REST code; shared installer/build helpers no longer reference vault schema or license gates.
* Checklists Templates tab: `checklistdek_checklists_after_builtin_templates` hook lets Pro inject Private Agency Vault UI without vault markup in the free package.
* Private Agency Vault, Auto-Capture, notifications, scheduled drift, and hub panel auto-deploy are unavailable on Community (no in-plugin Pro upsell or disabled controls).
* Pro hubs drop the legacy `is_vault` checklist column when Agency Vault is unavailable (cleanup omitted from WordPress.org zip).
* WordPress.org build: no **Upgrade** sidebar item (legacy billing/upgrade admin URLs go to the dashboard).

= 1.0.7 =
* Settings: rename “Credential Vault Security” to “Application password encryption” and clarify it is separate from Private Agency Vault (team checklist templates).
* Database 1.8.0: `is_vault` checklist column for Private Agency Vault.
* Community: Pro-only UI omitted from Settings and Checklists where features are not bundled; org zip excludes Pro licensing PHP.

= 1.0.6 =
* Sites: per-site Push Checklist, cached connection health, and unreachable-site warnings.
* Dashboard: unify live log feed copy in audit `format_row()`; legacy cached feed rows backfill `show_site_label` only.
* Community: removed in-plugin Billing page; **Upgrade** submenu opens the public ChecklistDek pricing page on asphaltthemes.com.
