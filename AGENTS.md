# WSP WordPress MCP — Agent & Contributor Guide (AGENTS.md)

> **This file is the universal source of truth for every agent and human working on this plugin**
> (Claude, Cursor, Codex, Gemini/Antigravity, OpenClaw, and the human team). Keep it accurate:
> when you change architecture, hooks, tools, or admin UX, **update this file in the same change.**
> The current-state docs are at the top; the historical research/decision/milestone log is in the
> **Appendix** at the bottom.

## What this plugin is

**Plugin Name:** WebSensePro MCP Abilities  
**Version:** 2.0.0  
**Slug/prefix:** `wsp`  
**WP option key:** `wsp_mcp_abilities`  
**Constant prefix:** `WSP_MCP_`

This is a WordPress plugin that exposes WordPress content to AI agents (Claude, Cursor, Codex, etc.) via the **Model Context Protocol (MCP)**. The site admin controls which operations ("tools"/"abilities") are active via a toggle UI in **WP Admin > MCP > Settings**.

**As of v2.0 the plugin ships its OWN native MCP server** (REST endpoint `/wp-json/wsp-mcp/v1/mcp`) — no companion plugin or WordPress MCP Adapter is required. It still *also* registers via the WordPress Abilities API **when that API is present** (dual-mode), so connections made through the MCP Adapter before v2.0 keep working. See **"## v2.0 Architecture (CURRENT — read this first)"** below before changing transport/tool code.

---

## v2.0 Architecture (CURRENT — read this first)

> Other agents (Claude/Gemini/Cursor) and forks: this section is the single source of truth for
> how MCP works in v2.0. You should not need to read the whole codebase to add a tool.

**Transport:** a hand-rolled native MCP server. One REST route, `/wp-json/wsp-mcp/v1/mcp`
(`register_rest_route('wsp-mcp/v1','/mcp', …)`, `permission_callback => '__return_true'`, auth
enforced inside the handler). Speaks Streamable HTTP + JSON-RPC 2.0: `initialize` (echoes the
client's `protocolVersion` if recognized; supported = `2024-11-05`/`2025-03-26`/`2025-06-18`/`2025-11-25`),
`notifications/initialized`, `tools/list`, `tools/call`, `ping`, empty `resources/list` & `prompts/list`.

**Server-layer files (`includes/server/`):**
- `class-mcp-server.php` — `WSP_MCP_Server`: REST route, JSON-RPC dispatch, in-memory tool
  registry (`register_tool()`), Origin validation, rate limiting (120/60s), `no-store` headers.
  Booted on `plugins_loaded` via `WSP_MCP_Server::init()`.
- `class-session-store.php` — `WSP_MCP_Session_Store`: DB table `{prefix}wsp_mcp_sessions`,
  `Mcp-Session-Id` issued on `initialize`, fingerprint-bound, sliding 24h expiry, daily cron
  `wsp_mcp_session_cleanup`.
- `class-auth.php` — `WSP_MCP_Auth`: accepts **(1)** Application Password (HTTP Basic, validated
  by WP core), **(2)** plugin API key via `Authorization: Bearer <key>`, **(3)** same key via
  `X-WSP-MCP-API-Key`. API key stored in option `wsp_mcp_api_key` (admin-only → mapped to lowest-ID
  admin so capability checks resolve). `require_cap()` gates each tool.

**Tools (`includes/tools/native-tools.php`):** `wsp_mcp_register_native_tools()` registers every
tool with `WSP_MCP_Server::register_tool($name, $spec)`. It **reuses the existing
`wsp_execute_*()` callbacks verbatim** (in `includes/abilities/*.php`) — only the transport
changed. Tool spec keys: `description`, `inputSchema` (JSON Schema), `callback` (the
`wsp_execute_*` fn), `capability` (`''` = authenticated only), `enable_key` (the `wsp/...`
registry key driving the admin toggle). Add-ons can hook `do_action('wsp_mcp_register_tools', …)`.

**Naming:** MCP tool names use underscores (`wsp_get_posts`); the matching admin-toggle
`enable_key` uses the slash form (`wsp/get-posts`). The native server only advertises tools whose
`enable_key` is enabled via `wsp_mcp_is_enabled()`, so **MCP > Settings controls both transports**.
Yoast tools register only if `wsp_yoast_is_active()`; Elementor tools only if `wsp_elementor_is_active()`.

**Admin:** `includes/admin/connection-page.php` adds **MCP > Connection** (endpoint URL, API key +
regenerate, and per-client tabbed config snippets for Claude Desktop / Cursor / Codex / Antigravity /
OpenClaw — all native, no MCP Adapter). `dependency.php` provides
`wsp_mcp_abilities_api_available()` (gates dual-mode) and `wsp_mcp_transport_available()` (always
true in v2.0).

### How to add a NEW MCP tool (v2.0)

1. Write/keep the logic as a `wsp_execute_<name>( $input )` function returning an array or
   `WP_Error` (in an `includes/abilities/*.php` file — new file is fine).
2. Add a `WSP_MCP_Server::register_tool('wsp_<name>', [...])` entry in
   `includes/tools/native-tools.php` (description, inputSchema, callback, capability, enable_key).
3. Add the ability's metadata to `wsp_mcp_ability_registry()` in `registry.php` (so the
   admin toggle exists; set `default`).
4. (Dual-mode, optional) also add the `wp_register_ability()` block in the abilities file so
   pre-2.0 MCP-Adapter users get it too.
5. Enable it in MCP > Settings, then **reconnect the client** (see gotcha below).

### Gotcha: client tool-list caching

MCP clients fetch `tools/list` once at connect and cache it. After enabling/adding tools, the user
must **reconnect** (fully restart Claude Desktop, not just open a new chat) before new tools appear.

---

## Directory structure

```
wsp-wordpress-mcp/
└── wsp-wordpress-mcp/          ← plugin root (the installable folder)
    ├── wsp-wordpress-mcp.php   ← main file: constants, requires, hooks, activation/migration
    ├── readme.txt              ← WP.org readme (v2.0)
    ├── uninstall.php           ← deletes wsp_mcp_* options + drops sessions table
    └── includes/
        ├── dependency.php      ← transport-capability helpers (abilities-API detection)
        ├── registry.php        ← central ability registry + settings helpers
        ├── server/             ← v2.0 native MCP server
        │   ├── class-mcp-server.php     ← transport + JSON-RPC dispatch + tool registry
        │   ├── class-session-store.php  ← DB-backed sessions
        │   └── class-auth.php           ← API key + App Password + bearer + caps
        ├── tools/
        │   └── native-tools.php ← registers every wsp_execute_* as a native MCP tool
        ├── admin/
        │   ├── settings-page.php    ← toggle UI (MCP > Settings) — collapsible accordion groups
        │   ├── config-page.php      ← legacy MCP-Adapter config snippets (MCP > Config Files)
        │   └── connection-page.php  ← native endpoint + API key + per-client tabs (MCP > Connection)
        └── abilities/           ← wsp_execute_* logic (reused by BOTH transports)
            ├── posts.php  pages.php  taxonomy.php  comments.php  media.php
            ├── users.php  search.php  site.php  yoast.php  elementor.php
```

**Rule:** The main file is a minimal loader (+ activation/migration glue) only. All feature logic lives in `includes/`. Never put feature code in `wsp-wordpress-mcp.php`.

---

## Constants

| Constant | Value |
|---|---|
| `WSP_MCP_VERSION` | `'2.0.0'` |
| `WSP_MCP_OPTION` | `'wsp_mcp_abilities'` (per-ability on/off toggles) |
| `WSP_MCP_DIR` | `plugin_dir_path(__FILE__)` |

**Other persistent state:** option `wsp_mcp_api_key` (native API key), option `wsp_mcp_db_version`
(migration gate), DB table `{prefix}wsp_mcp_sessions`, cron event `wsp_mcp_session_cleanup`.

---

## Core hooks (registered in main file)

| Hook | Callback | Purpose |
|---|---|---|
| `admin_menu` | `wsp_mcp_add_menu` | Top-level "MCP" menu + Settings/Config submenus (Connection submenu added in `connection-page.php`) |
| `admin_init` | `wsp_mcp_register_settings` | Registers `wsp_mcp_abilities` option with Settings API |
| `plugins_loaded` | `WSP_MCP_Server::init` | **v2.0** — builds tool registry + registers the native REST endpoint |
| `plugins_loaded` | `wsp_mcp_maybe_upgrade_db` | **v2.0** — heals table on upgrade (db_version gate) |
| `wsp_mcp_session_cleanup` | `WSP_MCP_Session_Store::cleanup_expired` | **v2.0** — daily expired-session purge |
| `wp_abilities_api_categories_init` | `wsp_register_ability_category` | Dual-mode: registers `wsp` category (only fires if Abilities API present) |
| `wp_abilities_api_init` | `wsp_mcp_register_all_abilities` | Dual-mode: guarded by `wsp_mcp_abilities_api_available()` before `wp_register_ability()` |

Activation (`wsp_mcp_activate`): create sessions table, ensure API key, schedule cron.
Deactivation (`wsp_mcp_deactivate`): clear cron.

---

## Registry (`includes/registry.php`)

### Key functions

**`wsp_mcp_ability_registry()`** — returns the master array of all abilities. Each entry:
```php
'wsp/ability-key' => [
    'label'       => 'Human label',
    'description' => 'What it does',
    'group'       => 'Posts',         // display group in admin UI
    'access'      => 'read'|'write',  // used for UI badge + write confirmation prompt
    'default'     => true|false,      // whether enabled out of the box
]
```
Elementor abilities are only appended if `\Elementor\Plugin` class exists.

**`wsp_mcp_get_settings()`** — merges saved option with registry defaults. Returns `['wsp/key' => bool]`.

**`wsp_mcp_is_enabled($key)`** — returns `true` if a given ability is toggled on.

**`wsp_mcp_sanitize_settings($input)`** — sanitize callback for Settings API. Casts each known key to bool.

**`wsp_register_ability_category()`** — registers the `wsp` MCP category.

---

## Ability modules

Each file in `includes/abilities/` follows the same pattern:
1. A `wsp_register_*_abilities()` function — checks `wsp_mcp_is_enabled()` per ability before calling `wp_register_ability()`.
2. One `wsp_execute_*()` callback per ability — the actual logic.

All abilities share this base config:
```php
$base = [
    'category'      => 'wsp',
    'output_schema' => ['type' => 'object'],
    'meta'          => ['mcp' => ['public' => true]],
];
```

### Ability reference table

#### Posts (`posts.php`)

| Ability key | Label | Access | Default | Permission | Inputs |
|---|---|---|---|---|---|
| `wsp/get-posts` | Get Blog Posts | read | ON | `__return_true` | `per_page` (int), `status` (publish\|draft\|all) |
| `wsp/create-post` | Create Post | write | OFF | `publish_posts` | `title`*, `content`*, `status`, `categories[]`, `tags[]`, `excerpt`, `slug` |
| `wsp/update-post` | Update Post | write | OFF | `edit_posts` | `id`*, `title`, `content`, `status`, `categories[]`, `tags[]` |
| `wsp/delete-post` | Delete Post | write | OFF | `delete_posts` | `id`* |

- Delete moves to trash, not permanent deletion.
- `status=all` expands to `['publish','draft','pending','future']`.

#### Pages (`pages.php`)

| Ability key | Label | Access | Default | Permission | Inputs |
|---|---|---|---|---|---|
| `wsp/get-pages` | Get Pages | read | ON | `__return_true` | none |
| `wsp/create-page` | Create Page | write | OFF | `publish_pages` | `title`*, `content`*, `status`, `parent` (int), `slug` |
| `wsp/update-page` | Update Page | write | OFF | `edit_pages` | `id`*, `title`, `content`, `status` |
| `wsp/delete-page` | Delete Page | write | OFF | `delete_pages` | `id`* |

#### Taxonomy (`taxonomy.php`)

| Ability key | Label | Access | Default | Permission | Inputs |
|---|---|---|---|---|---|
| `wsp/get-categories` | Get Categories | read | ON | `__return_true` | none |
| `wsp/create-category` | Create Category | write | OFF | `manage_categories` | `name`*, `description`, `parent` (int) |
| `wsp/get-tags` | Get Tags | read | ON | `__return_true` | none |
| `wsp/create-tag` | Create Tag | write | OFF | `manage_categories` | `name`*, `description` |

- `get_categories` uses `hide_empty => false` so all categories appear.

#### Comments (`comments.php`)

| Ability key | Label | Access | Default | Permission | Inputs |
|---|---|---|---|---|---|
| `wsp/get-comments` | Get Comments | read | OFF | `moderate_comments` | `status` (hold\|approve\|all), `per_page` |
| `wsp/approve-comment` | Approve Comment | write | OFF | `moderate_comments` | `id`* |
| `wsp/delete-comment` | Delete Comment | write | OFF | `moderate_comments` | `id`* |

#### Media (`media.php`)

| Ability key | Label | Access | Default | Permission | Inputs |
|---|---|---|---|---|---|
| `wsp/get-media` | Get Media | read | OFF | `upload_files` | `per_page`, `type` (MIME e.g. `image`) |

- Read-only. No upload ability exists.

#### Users (`users.php`)

| Ability key | Label | Access | Default | Permission | Inputs |
|---|---|---|---|---|---|
| `wsp/get-users` | Get Users | read | OFF | `list_users` | none |

- Returns: `id`, `display_name`, `email`, `roles[]`, `registered`.

#### Search (`search.php`)

| Ability key | Label | Access | Default | Permission | Inputs |
|---|---|---|---|---|---|
| `wsp/search` | Search Content | read | ON | `__return_true` | `query`* |

- Uses `WP_Query` with `s` param. Searches posts and pages. Returns top 10, publish only.

#### Site (`site.php`)

| Ability key | Label | Access | Default | Permission | Inputs |
|---|---|---|---|---|---|
| `wsp/get-site-info` | Get Site Info | read | ON | `__return_true` | none |
| `wsp/get-plugins` | Read Plugins | read | OFF | `activate_plugins` | none |

- `get-site-info` returns: `name`, `url`, `tagline`, `admin_email`, `wp_version`, `language`.
- `get-plugins` loads `wp-admin/includes/plugin.php` if needed, then intersects all plugins with active list.

#### Elementor (`elementor.php`)

Only registered if `class_exists('\Elementor\Plugin')`. All abilities require `edit_posts` capability.

| Ability key | Label | Access | Default | Inputs |
|---|---|---|---|---|
| `wsp/elementor-list-pages` | List Elementor Pages | read | OFF | `post_type`, `status`, `per_page` |
| `wsp/elementor-get-page` | Get Page Structure | read | OFF | `post_id`* |
| `wsp/elementor-get-element` | Get Element Settings | read | OFF | `post_id`*, `element_id`* |
| `wsp/elementor-find-element` | Find Element | read | OFF | `post_id`*, `widget_type`, `search` |
| `wsp/elementor-list-templates` | List Templates | read | OFF | `type`, `per_page` |
| `wsp/elementor-update-element` | Update Element | write | OFF | `post_id`*, `element_id`*, `settings`* (object) |
| `wsp/elementor-add-widget` | Add Widget | write | OFF | `post_id`*, `widget_type`*, `container_id`, `settings`, `position` |
| `wsp/elementor-add-container` | Add Container | write | OFF | `post_id`*, `type` (container\|section), `parent_id`, `settings`, `position` |
| `wsp/elementor-remove-element` | Remove Element | write | OFF | `post_id`*, `element_id`* |

**Elementor data model:**
- Elementor page data is stored in `_elementor_data` post meta as a JSON-encoded recursive element tree.
- Each element: `{ id (8-char hex), elType, widgetType?, settings{}, elements[], isInner? }`
- Root elements are sections (legacy) or containers (modern). Widgets cannot be placed directly inside a section — they need a column inside the section.
- `wsp_elementor_get_data($post_id)` — reads and JSON-decodes `_elementor_data`. Returns `WP_Error` if not an Elementor page.
- `wsp_elementor_save_data($post_id, $data)` — JSON-encodes, saves with `wp_slash`, clears Elementor file cache.
- `wsp_elementor_generate_id()` — 8-char hex via `md5(uniqid(...))`.
- Helper functions for tree traversal: `wsp_elementor_find_by_id`, `wsp_elementor_remove_by_id`, `wsp_elementor_update_by_id`, `wsp_elementor_insert_into`, `wsp_elementor_first_insertable`, `wsp_elementor_simplify_tree`, `wsp_elementor_search_tree`.

---

## Admin UI

### Settings page (`MCP > Settings`) — `settings-page.php`

- Registered as top-level menu at position 3, icon `dashicons-admin-generic`.
- Groups abilities by `group` field from registry, displays toggle switches.
- Stats bar: total abilities, enabled count, active write count.
- **Collapsible accordion groups:** each `group` renders as a `.wsp-group` whose `.wsp-gh` header
  toggles a `.wsp-gbody` (the rows). **All groups start collapsed.** The header shows a count badge
  (`enabled / total`, green when any are on) that updates live via JS as toggles change. Open/closed
  state persists per-browser in `localStorage` key `wsp_acc_open` (array of group names). The chevron
  (`.wsp-chev`) rotates on the `.wsp-open` class.
- JS: write abilities show a `confirm()` dialog before enabling. "Toggle All" per group (its click is
  excluded from the accordion toggle via `e.target.closest('.wsp-toggle-all')`).
- Saves to `wsp_mcp_abilities` option via Settings API (`wsp_mcp_settings_group`).

### Connection page (`MCP > Connection`) — `connection-page.php`

- **Primary, native-transport page (v2.0).** Top card shows the native endpoint
  (`rest_url('wsp-mcp/v1/mcp')`) + API key with a Regenerate button (nonce-protected admin-post action
  `wsp_mcp_regenerate_key` → `WSP_MCP_Auth::regenerate_api_key()`).
- Five tabbed, copy-to-clipboard snippets, all pointing at the native endpoint with the **API key
  hardcoded into the auth header** (no `${VAR}` env interpolation — avoids the mcp-remote "missing env
  var" failure). Server name auto-derives as `wsp-<host>`:
  - **Claude Desktop** — `mcpServers` + `npx -y mcp-remote <url> --header "Authorization: Bearer <key>"` (stdio bridge, needs Node.js; Claude Desktop config files don't support remote HTTP directly).
  - **Cursor** — native remote HTTP: `mcpServers.<name>.{ url, headers: { Authorization: "Bearer <key>" } }` (`~/.cursor/mcp.json`).
  - **Codex** — native streamable HTTP TOML: `[mcp_servers.<name>]` `url` + `http_headers = { "Authorization" = "Bearer <key>" }` (`~/.codex/config.toml`).
  - **Antigravity** — native remote HTTP, but the URL key is **`serverUrl`** (not `url`): `mcpServers.<name>.{ serverUrl, headers }` (`~/.gemini/config/mcp_config.json`).
  - **OpenClaw** — nested **`mcp.servers`** schema (not top-level `mcpServers`) + `mcp-remote` bridge, key inlined in the header (`~/.openclaw/openclaw.json`).
- Tab/copy UI markup + JS mirror the Config Files page.

### Config page (`MCP > Config Files`) — `config-page.php`

- **Legacy / dual-mode page** (shows an inline warning notice linking to MCP > Connection as the
  recommended native path).
- Generates ready-to-paste MCP config snippets for Claude Desktop, Cursor, Codex (TOML), and Antigravity.
- Auto-fills `WP_API_URL` from `rest_url('mcp/mcp-adapter-default-server')` and `WP_API_USERNAME` from current logged-in user.
- User replaces `replace-with-your-application-password` with a WP Application Password.
- Uses `@automattic/mcp-wordpress-remote@latest` npm package as the MCP transport (the MCP-Adapter route).

---

## Security patterns used

- All text input: `sanitize_text_field(wp_unslash($input['x']))`.
- HTML content: `wp_kses_post(wp_unslash($input['content']))`.
- IDs: `intval($input['id'])`.
- Slugs: `sanitize_title($input['slug'])`.
- MIME types: `sanitize_mime_type($input['type'])`.
- Permission callbacks: `__return_true` for public reads; `current_user_can('cap')` closures for writes and sensitive reads.
- MCP requests are authenticated inside the native server handler (App Password / Bearer key); per-tool capability checks via `require_cap()`.
- Admin-post actions (e.g. API-key regenerate) are nonce-protected with `wp_nonce_field()` / `check_admin_referer()` and gated by `current_user_can('manage_options')`.
- Output in admin pages is escaped (`esc_html`/`esc_attr`/`esc_url`/`esc_textarea`/`esc_js`).

---

## Defaults at a glance

**ON by default:** `get-posts`, `get-pages`, `get-categories`, `get-tags`, `search`, `get-site-info`

**OFF by default:** everything else (all write abilities, comments, media, users, plugins, all Elementor abilities)

---

## Naming conventions

- PHP functions: `wsp_` prefix, snake_case.
- Ability keys: `wsp/kebab-case`.
- MCP tool names: `wsp_snake_case` (underscores).
- Option: `wsp_mcp_abilities` (single serialized array).
- No classes for feature logic — procedural PHP throughout; the only classes are the server layer (`WSP_MCP_Server`, `WSP_MCP_Session_Store`, `WSP_MCP_Auth`).
- Each ability file is self-contained: registration + execute callbacks together.

---

## Contributing (team)

Read **"## v2.0 Architecture (CURRENT — read this first)"** before writing any transport/tool code.
This file (`AGENTS.md`) is the shared contract — **update it in the same PR whenever you change
architecture, hooks, tools, constants, or admin UX.**

**Adding a feature / tool:** follow **"### How to add a NEW MCP tool (v2.0)"** above (logic →
`native-tools.php` registration → `registry.php` metadata → optional dual-mode `wp_register_ability()`).
Keep business logic in `includes/abilities/*.php`; keep transport wiring in `includes/server/` and
`includes/tools/`. **Never** put feature code in `wsp-wordpress-mcp.php` (loader + activation glue only).

**Conventions:**
- Match the existing procedural style and `wsp_`/`wsp/`/`wsp_…` naming above.
- Sanitize every input, escape every output, enforce a capability on every write/sensitive read
  (see "## Security patterns used"). WP.org review will reject otherwise.
- New persistent state (options/tables/cron): add cleanup to `uninstall.php`, and gate any schema
  change behind the `wsp_mcp_db_version` migration in `wsp_mcp_maybe_upgrade_db`.
- Bump `WSP_MCP_VERSION` (main file header + `define`) **and** `Stable tag:` in `readme.txt` together;
  add a `== Changelog ==` entry.

**Branch / PR workflow:**
- Branch off `main`; never commit straight to `main`.
- One logical change per PR; update `AGENTS.md` + `readme.txt` changelog in the same PR.
- End commit messages with the project's Co-Authored-By trailer when an agent made the change.

**Testing note:** there is **no PHP runtime on the primary dev machine** — PHP cannot be linted or
run locally. Validate changes by installing the plugin in a real WordPress site and exercising the
endpoint with MCP Inspector / a connected client. Before release, run **Plugin Check** in WP admin.

**Dual-mode guarantee:** the Abilities-API path stays behind a `function_exists('wp_register_ability')`
guard so the plugin never fatals standalone and pre-2.0 MCP-Adapter connections keep working. Don't
remove that guard without a deliberate deprecation.

---

## Adding a new ability — LEGACY Abilities-API path (dual-mode only)

> ⚠️ For v2.0, the primary way to add a tool is **"How to add a NEW MCP tool (v2.0)"** in the
> "v2.0 Architecture" section near the top. The steps below only register a tool on the *Abilities
> API* transport (for pre-2.0 MCP-Adapter users). To expose a tool on the native server you MUST
> also register it in `includes/tools/native-tools.php`. Do both for full coverage.

1. Add the ability's metadata to `wsp_mcp_ability_registry()` in `registry.php`.
2. Create or add to an appropriate file in `includes/abilities/`.
3. Inside `wsp_register_*_abilities()`, add an `if (wsp_mcp_is_enabled('wsp/new-key'))` block calling `wp_register_ability()`.
4. Add a `wsp_execute_new_key($input)` function.
5. Call `wsp_register_*_abilities()` from `wsp_mcp_register_all_abilities()` in the main file if it's a new file.

---
---

# Appendix: History & Rationale (historical — for context only)

> The sections below are the **historical record** of how v2.0 was researched, decided, and built
> (captured 2026-06-19). They are kept so future agents/contributors don't re-research the same
> ground. They describe decisions and a milestone plan that are now **implemented** — for current
> behavior, trust the sections above, not this appendix.

## MCP Transport Architecture — Research & Strategic Decision (2026-06-19)

> This section captures a full investigation into how this plugin connects to AI clients,
> the dependency it currently relies on, WordPress.org approval implications, and the
> decided direction. **Saved here so future sessions do not need to re-research.**

### The current dependency chain (what made the pre-2.0 plugin work)

The pre-2.0 plugin **did not implement any MCP transport itself**. It only registered "abilities"
via `wp_register_ability()`. For those abilities to be reachable by an AI client, the site
needed a **three-package stack**, none of which is on the WordPress.org plugin directory:

| Package | Role | Status |
|---|---|---|
| `wordpress/abilities-api` | Provides `wp_register_ability()`, `wp_register_ability_category()`, `wp_abilities_api_init` — **the functions this plugin calls directly** | pre-1.0, GPL-2.0, designed to merge into WP core |
| `wordpress/mcp-adapter` | Bridges the Abilities API to the MCP protocol (the transport/endpoint) | v0.5.0, `minimum-stability: dev`, PHP 7.4+, GPL-2.0-or-later, `Requires at least: 6.9` |
| `wordpress/php-mcp-schema` | mcp-adapter's transitive dependency | v0.1.0 |

Key facts established by inspecting the repos directly:
- `mcp-adapter`'s `composer.json` requires only `php-mcp-schema` — it does **not** declare a
  Composer dependency on `abilities-api`. The Abilities API is expected to be present separately
  (it is a core-track feature plugin, likely already partly in WP 6.9 core — which is why the
  current 2-plugin setup works at all with just mcp-adapter + WP 6.9).
- **Neither `abilities-api` nor `mcp-adapter` is on the WordPress.org plugin directory** (verified
  via `api.wordpress.org/plugins/info`). This means the official `Requires Plugins:` header
  (WP 6.5+, the sanctioned way to declare a plugin dependency) **cannot** be used — it only
  resolves plugins hosted on the WP.org directory by slug.
- All three are GPL-2.0-or-later, so bundling them is **license-safe**.
- The client connection today also requires the user to run the
  `@automattic/mcp-wordpress-remote` npm bridge (needs Node.js) — a far bigger adoption barrier
  for non-technical users than "install a second plugin."

### The three paths considered

- **Path A — Keep the dependency, harden it.** Add a `function_exists('wp_register_ability')` /
  `class_exists('WP\\MCP\\Core\\McpAdapter')` guard so the plugin degrades gracefully (admin
  notice) instead of fataling when the adapter is absent. Low effort, stays on the official
  Abilities-API track. **But:** still needs the companion plugin + Node.js bridge, and is the
  **riskiest for WP.org approval** because the plugin is non-functional standalone and can't
  declare its GitHub-only dependency via the official header.
- **Path B — Bundle all three packages via Composer.** Single-plugin install, but you vendor
  three pre-1.0 dev-stability core-bound packages (collision risk when the Abilities API lands in
  core; ongoing re-bundling), **and you still need the npm bridge.** Worst effort-to-payoff. **Ruled out.**
- **Path C — Go native: implement MCP yourself, no dependency.** Own the REST transport,
  JSON-RPC dispatch, session store, and an OAuth 2.0 server. Self-contained single plugin,
  **direct Claude/ChatGPT connection via "Add Custom Connector" (no Node.js)**, cleanest WP.org
  submission. **Cost:** you own the protocol/client-quirk maintenance treadmill.

### Decisive evidence — two WP.org-approved precedents both chose native (Path C)

Two independently-built, WordPress.org-approved MCP plugins were studied as references.
**Both rejected `mcp-adapter`/Abilities-API and built native MCP servers.**

#### Reference Plugin A (PHP 7.4+, WP 5.8+)
- Self-contained: hand-rolled `spl_autoload_register`, own namespace, no Composer.
- Transport: `register_rest_route('<ns>/v1', '/mcp', …)` with `permission_callback => '__return_true'`,
  auth enforced **inside** the handler (`Server::validate_auth`). Streamable HTTP (2025-11-25 spec),
  JSON-RPC dispatch (`initialize`, `tools/list`, `tools/call`, `ping`).
- Native **OAuth 2.0 server** via rewrite rules at domain root (`.well-known/oauth-protected-resource`,
  `.well-known/oauth-authorization-server`, `/authorize`, `/token`, `/register` + PKCE).
- Tools: one big inline array in `get_tools()` + `execute_tool()` switch; integrations (Woo, Elementor, ACF…) add tools conditionally.
- Dual auth: API key (custom header or `Authorization: Bearer`) OR OAuth bearer token; DB-backed sessions; rate limiting; Origin validation (DNS-rebinding guard).
- Hard-won production lessons in its comments: Cache-Control `no-store` on every response (edge-cache
  poisoning on Cloudflare/LiteSpeed), per-User-Agent dispatch on `GET /mcp` (4 iterations to satisfy
  Claude.ai/ChatGPT/mcp-remote), DB-backed sessions (object-cache eviction), per-tool capability
  checks (CVSS 8.1 fix).

#### Reference Plugin B (PHP 8.0+, WP 6.2+) — the cleanest template
- Self-contained: `require_once` of class files + **one file per tool** under
  `includes/tools/wp/` and `includes/tools/bridge/`. No Composer/vendor.
- Transport: own REST endpoint `/wp-json/<ns>/v1/message` (Streamable HTTP + JSON-RPC).
  One HTTP request per call — explicitly "works on shared hosting" (no long-lived SSE).
- Built-in **OAuth 2.0 server** (`/oauth/authorize`, `/token`, `/register`, PKCE) → connects
  directly via Claude's **"Add Custom Connector"** by pasting the site URL. **No Node.js bridge** for
  that path (a `mcp-remote` stdio snippet is still offered for Claude-Desktop config-file users).
- **Three auth paths:** Application Password, plugin-generated Bearer key, and OAuth token.
- Tool registration is trivially modular — the exact pattern to copy:
  ```php
  <Server>::register_tool(
      'get_site_info',
      [ 'description' => '…', 'inputSchema' => [ 'type'=>'object', 'properties'=>new stdClass() ] ],
      function ( array $args ): array|WP_Error {
          return <Server>::ok([ /* … */ ]);
      }
  );
  ```
  To add a tool: write a file, `require_once` it, add the name to a `TOOL_NAMES` constant + a
  one-line `TOOL_MIGRATION_BACKFILL` entry (so upgraded installs enable it).
- **Freemium model proven on WP.org:** ~45 tools work standalone; an optional API key unlocks
  ~12 more "bridge" tools. The external API is disclosed in an **"External Services"** readme section
  and the plugin is **fully functional without it** — the reason WP.org approved it.
- Other WP.org-compliance patterns worth copying: SSRF guards on URL media ingestion (reject
  private/loopback/cloud-metadata IPs, re-validate redirects), MIME verified against real bytes,
  filename denylist (`.php/.phtml/.htaccess`), credential-key denylist on post-meta writes,
  `require_cap()` capability enforcement, dbDelta-idempotent migrations gated by a `db_version`
  option, change-receipt + rollback audit trail.

### DECISION (2026-06-19): Go native (Path C), model on Reference Plugin B

**Rationale:**
1. **WP.org approval** — the two approved precedents are both native and self-functional. A plugin
   that "requires a GitHub-only companion plugin" is the riskiest path (non-functional standalone,
   no valid `Requires Plugins` header). Native is the proven-approvable architecture.
2. **Feature velocity** — the one-file-per-tool registry is the same modular structure already
   preferred here, and the per-feature cost is tiny.
3. **End-user UX** — native + OAuth means users paste a URL into Claude/ChatGPT and install
   **nothing else** (no second plugin, no Node.js). This is the biggest adoption win, especially
   for a non-developer audience.
4. The current dependency stack is pre-1.0, dev-stability, and core-bound — building on a moving
   target headed for core is the wrong long-term bet.

**The rewrite is of the transport layer, not the business logic.** The existing ability *logic*
(the `wsp_execute_*` functions for posts/pages/taxonomy/media/Elementor/Yoast, etc.) is reusable —
the change is swapping "register with mcp-adapter via `wp_register_ability()`" for "register with
our own MCP server + tool registry."

**Planned v2.0 milestones (native architecture):**
1. REST MCP endpoint + JSON-RPC dispatcher (`initialize`, `tools/list`, `tools/call`, `ping`).
2. Tool registry (`register_tool()` pattern) — port existing `wsp_execute_*` logic into tool files.
3. DB-backed session store + Origin validation + rate limiting.
4. OAuth 2.0 authorization server (authorize/token/register + PKCE) + Application Password auth.
5. WP.org hardening: External-Services disclosure (only if an external API is added), capability
   enforcement, SSRF guards on any upload tool, idempotent migrations, `readme.txt`.

**Interim:** keep v1.x on Path A (add the `function_exists` guard so it never fatals standalone)
so existing installs and the published YouTube tutorials remain valid until v2.0 ships.

**MCP spec version note:** the protocol moves ~quarterly (`2024-11-05 → 2025-03-26 → 2025-06-18 →
2025-11-25`). A native server must echo the client's requested `protocolVersion` when recognized
and fall back to a known-good default otherwise. This maintenance is the main cost of going native,
but both precedents show it is sustainable.

---

## v2.0 Native Build — Milestone Plan (2026-06-19)

Eight milestones, dependency-ordered, each independently testable (MCP Inspector against the
local endpoint). The existing `wsp_execute_*` ability logic (~1,222 lines) is reused; the rewrite
is of the transport layer only.

### Target structure (v2.0)

```
wsp-wordpress-mcp/
├── wsp-wordpress-mcp.php          ← loader; dual-mode boot
├── readme.txt                     ← WP.org
├── uninstall.php
└── includes/
    ├── dependency.php             ← M0 transport detection + admin notice (shipped in v1.x)
    ├── registry.php               ← KEEP (gated behind function_exists) — dual-mode
    ├── server/
    │   ├── class-mcp-server.php    ← REST endpoint + JSON-RPC dispatch + tool registry
    │   ├── class-session-store.php ← DB-backed sessions
    │   ├── class-auth.php          ← API key + App Password + bearer + caps
    │   └── class-oauth.php         ← OAuth 2.0 AS + PKCE + token store
    ├── tools/                      ← one file per tool group (ports wsp_execute_*)
    └── admin/                      ← config/connection page + setup wizard
```

### Milestones

| # | Milestone | New lines | Depends on | Exit criteria |
|---|---|---|---|---|
| **M0** | Interim v1.x safety guard | ~30 | — | `function_exists('wp_register_ability')` guard so standalone install never fatals; admin notice when transport absent. Ships in v1.x now. **(DONE — `includes/dependency.php`.)** |
| **M1** | MCP transport core | ~500–700 | M0 | MCP Inspector can `initialize` (echo `protocolVersion`) → `tools/list` → `tools/call` on a test tool. JSON-RPC dispatch (`initialize`, `tools/list`, `tools/call`, `ping`, empty `resources/list`/`prompts/list`), `no-store` headers, CORS/OPTIONS, Origin validation, rate limiting. |
| **M2** | Tool registry + port existing logic | ~400–600 | M1 | All current abilities callable as native tools. `register_tool()` registry; one file per group reusing existing `wsp_execute_*` bodies; per-tool toggles reuse `wsp_mcp_abilities`; `TOOL_NAMES` + backfill migration. |
| **M3** | Session store (DB-backed) | ~150–200 | M1 | `initialize` issues `Mcp-Session-Id`; later calls validated; credential fingerprint binding; daily cron cleanup; expiry works. |
| **M4** | Auth completion | ~150–250 | M1 | Three non-OAuth paths: plugin API key, `Authorization: Bearer`, Application Password (Basic). Per-tool capability enforcement; admin-user mapping. |
| **M5** | OAuth 2.0 server *(deferrable to v2.1)* | ~500–800 | M1, M4 | Claude "Add Custom Connector" via site URL completes OAuth + connects. `.well-known/*`, `/authorize`, `/token`, `/register`, PKCE, token-store table, consent screen, external-redirect handling. |
| **M6** | Admin / connection UX | ~150–250 | M2, M4 | User copies a working config from wp-admin: native endpoint URL, API key, "Add Custom Connector" steps, `mcp-remote` stdio snippet, per-site connection name. |
| **M7** | WP.org hardening + release | ~150–250 | all | Passes Plugin Check; `readme.txt`; idempotent migrations gated by `db_version`; SSRF guards on any upload tool; sanitize/escape/cap pass; **dual-mode verified (no breakage)**; uninstall cleanup. External-Services disclosure only if an external API is added. |

### Status (2026-06-19)

**v2.0 MVP built — M0–M4, M6, M7 complete; M5 (OAuth) deferred to v2.1.** Pending PHP lint
+ live testing in a WordPress environment (no PHP runtime on the dev machine).

New files: `includes/server/class-mcp-server.php` (transport + JSON-RPC dispatch + tool
registry), `includes/server/class-session-store.php` (DB sessions), `includes/server/class-auth.php`
(API key + Application Password + bearer + caps), `includes/tools/native-tools.php` (wraps every
`wsp_execute_*` as a native tool), `includes/admin/connection-page.php` (MCP > Connection),
`readme.txt`. Endpoint: `/wp-json/wsp-mcp/v1/mcp`. Version bumped to 2.0.0; `Requires PHP 7.4`,
`Requires at least 6.2`. `dependency.php` repurposed: `wsp_mcp_abilities_api_available()` gates
dual-mode; native transport always available. Dual-mode preserved (Abilities path behind
`function_exists`).

### Shipping options

- **MVP (no OAuth):** M0–M4 + M6 + M7, skip M5 → **~1,300–1,800 lines.** Connects via Claude Desktop config + API key / App Password.
- **Full (URL-paste UX):** all milestones → **~2,000–2,700 lines.** Adds direct "Add Custom Connector".
- **Recommendation:** ship MVP as v2.0; add M5 as v2.1 (OAuth is the biggest/riskiest chunk and not required for a working release).

### Critical path

```
M0 ─► M1 ─┬─► M2 ──────────┐
          ├─► M3           ├─► M6 ─► M7 ─► (v2.0 MVP)
          └─► M4 ─► M5 ────┘         └─► (v2.1 adds M5)
```
M2/M3/M4 parallelizable once M1 lands. M5 gates only the full release.

### No-breakage guarantee (dual-mode)

v2.0 keeps `registry.php`'s `wp_register_ability()` calls **behind a `function_exists` guard** AND
registers the native endpoint. Existing mcp-adapter users keep their current connection untouched;
new users get the native one-plugin path. No bundled adapter → no class collision → no fatal. The
guard is M0, already shipping in v1.x. Deprecate the Abilities path in a later version.
