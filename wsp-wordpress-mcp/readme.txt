=== WebSensePro MCP Abilities ===
Contributors: websensepro
Tags: mcp, ai, claude, model context protocol, elementor
Requires at least: 6.2
Tested up to: 6.9
Requires PHP: 7.4
Stable tag: 2.0.0
License: GPL-2.0-or-later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Expose your WordPress site to AI agents (Claude, Cursor, and other MCP clients) through a built-in MCP server — no companion plugin required.

== Description ==

WebSensePro MCP Abilities turns your WordPress site into a Model Context Protocol (MCP) server. AI clients can read and edit posts, pages, categories, tags, media, comments, users, and (when installed) Yoast SEO meta and Elementor page content — all under granular, per-ability admin control.

As of v2.0 the plugin ships its **own native MCP server**. You no longer need the WordPress MCP Adapter or any companion plugin: activate, copy your connection details from **MCP > Connection**, and connect.

= Key features =

* Built-in MCP server over a single REST endpoint (Streamable HTTP, JSON-RPC 2.0) — no external dependency.
* Per-ability on/off toggles in **MCP > Settings**; write abilities are off by default.
* Two authentication methods: WordPress Application Passwords (HTTP Basic) or a plugin-generated API key (`Authorization: Bearer` or `X-WSP-MCP-API-Key`).
* Capability checks on every tool — an AI client can only do what its authenticated user can do.
* Optional Yoast SEO and Elementor tools, shown only when those plugins are active.

= What AI clients can do =

* Read / create / update / delete posts and pages
* Manage categories and tags
* Read and moderate comments
* Read media, users, site info, and active plugins
* Search content
* Read and update Yoast SEO meta (requires Yoast SEO)
* Read and edit Elementor page structure (requires Elementor)

== Installation ==

1. Upload the plugin to `/wp-content/plugins/` and activate it.
2. Go to **MCP > Settings** and enable the abilities you want to expose.
3. Go to **MCP > Connection** to copy your endpoint URL and API key (or use a WordPress Application Password).
4. Add the connection to your MCP client (Claude Desktop config, or any HTTP MCP client / IDE).

== Frequently Asked Questions ==

= Do I need the WordPress MCP Adapter plugin? =

No. As of v2.0 this plugin includes its own MCP server. If the Abilities API / MCP Adapter happens to be present, existing connections through it continue to work, but it is not required.

= How does authentication work? =

Use a WordPress Application Password (sent via HTTP Basic auth) or the plugin-generated API key shown on the Connection page. Either is validated on every request, and tool actions are limited by the authenticated user's capabilities.

= Which AI clients are supported? =

Any client that supports the Streamable HTTP MCP transport — Claude Desktop, MCP Inspector, IDEs, and scripts.

== Changelog ==

= 2.0.0 =
* New: built-in native MCP server — no companion plugin or WordPress MCP Adapter required.
* New: MCP > Connection page with endpoint URL, API key, and per-client config tabs for Claude Desktop, Cursor, Codex, Antigravity, and OpenClaw (native, no adapter).
* New: Application Password + API key authentication; per-tool capability enforcement.
* New: DB-backed session store with daily cleanup.
* Improved: MCP > Settings groups are now collapsible accordions with live enabled/total counts.
* Dual-mode: existing Abilities-API connections keep working when that transport is present.

= 1.3.0 =
* Added Yoast SEO abilities (read/update SEO title, meta description, focus keyphrase).

= 1.2.1 =
* Added OpenClaw tab to the Config Files page.

= 1.2.0 =
* Elementor abilities, modular architecture, auto config generator.
