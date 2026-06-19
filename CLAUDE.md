# WSP WordPress MCP — Agent Context

## What this plugin is

**Plugin Name:** WebSensePro MCP Abilities  
**Version:** 1.2.0  
**Slug/prefix:** `wsp`  
**WP option key:** `wsp_mcp_abilities`  
**Constant prefix:** `WSP_MCP_`

This is a WordPress plugin that exposes WordPress content to AI agents (Claude, Cursor, Codex, Antigravity) via the **WordPress MCP (Model Context Protocol)** system. It registers named "abilities" — read or write operations — that an MCP client can call. The site admin controls which abilities are active via a toggle UI in **WP Admin > MCP**.

The transport layer is handled entirely by WordPress core's ability/MCP API (`wp_register_ability`, `wp_register_ability_category`, `wp_abilities_api_init` hook). This plugin never implements its own HTTP endpoint — it only registers handlers.

---

## Directory structure

```
wsp-wordpress-mcp/
└── wsp-wordpress-mcp/          ← plugin root (the installable folder)
    ├── wsp-wordpress-mcp.php   ← main file: defines constants, requires all includes, registers hooks
    ├── uninstall.php           ← deletes wsp_mcp_abilities option on uninstall
    └── includes/
        ├── registry.php        ← central ability registry + settings helpers
        ├── admin/
        │   ├── settings-page.php   ← WP Admin toggle UI (MCP > Settings)
        │   └── config-page.php     ← WP Admin connection config snippets (MCP > Config Files)
        └── abilities/
            ├── posts.php
            ├── pages.php
            ├── taxonomy.php
            ├── comments.php
            ├── media.php
            ├── users.php
            ├── search.php
            ├── site.php
            └── elementor.php
```

**Rule:** The main file is a minimal loader only. All logic lives in `includes/`. Never put feature code in `wsp-wordpress-mcp.php`.

---

## Constants

| Constant | Value |
|---|---|
| `WSP_MCP_VERSION` | `'1.2.0'` |
| `WSP_MCP_OPTION` | `'wsp_mcp_abilities'` |
| `WSP_MCP_DIR` | `plugin_dir_path(__FILE__)` |

---

## Core hooks (registered in main file)

| Hook | Callback | Purpose |
|---|---|---|
| `admin_menu` | `wsp_mcp_add_menu` | Registers top-level "MCP" admin menu + two submenus |
| `admin_init` | `wsp_mcp_register_settings` | Registers `wsp_mcp_abilities` option with Settings API |
| `wp_abilities_api_categories_init` | `wsp_register_ability_category` | Registers `wsp` category |
| `wp_abilities_api_init` | `wsp_mcp_register_all_abilities` | Calls every `wsp_register_*_abilities()` function |

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
- JS: write abilities show a `confirm()` dialog before enabling. "Toggle All" per group.
- Saves to `wsp_mcp_abilities` option via Settings API (`wsp_mcp_settings_group`).

### Config page (`MCP > Config Files`) — `config-page.php`

- Generates ready-to-paste MCP config snippets for Claude Desktop, Cursor, Codex (TOML), and Antigravity.
- Auto-fills `WP_API_URL` from `rest_url('mcp/mcp-adapter-default-server')` and `WP_API_USERNAME` from current logged-in user.
- User replaces `replace-with-your-application-password` with a WP Application Password.
- Uses `@automattic/mcp-wordpress-remote@latest` npm package as the MCP transport.

---

## Security patterns used

- All text input: `sanitize_text_field(wp_unslash($input['x']))`.
- HTML content: `wp_kses_post(wp_unslash($input['content']))`.
- IDs: `intval($input['id'])`.
- Slugs: `sanitize_title($input['slug'])`.
- MIME types: `sanitize_mime_type($input['type'])`.
- Permission callbacks: `__return_true` for public reads; `current_user_can('cap')` closures for writes and sensitive reads.
- No nonces needed — MCP requests are authenticated via WordPress Application Password (HTTP Basic Auth handled by the MCP transport).

---

## Defaults at a glance

**ON by default:** `get-posts`, `get-pages`, `get-categories`, `get-tags`, `search`, `get-site-info`

**OFF by default:** everything else (all write abilities, comments, media, users, plugins, all Elementor abilities)

---

## Naming conventions

- PHP functions: `wsp_` prefix, snake_case.
- Ability keys: `wsp/kebab-case`.
- Option: `wsp_mcp_abilities` (single serialized array).
- No classes used — procedural PHP throughout.
- Each ability file is self-contained: registration + execute callbacks together.

---

## Adding a new ability (pattern to follow)

1. Add the ability's metadata to `wsp_mcp_ability_registry()` in `registry.php`.
2. Create or add to an appropriate file in `includes/abilities/`.
3. Inside `wsp_register_*_abilities()`, add an `if (wsp_mcp_is_enabled('wsp/new-key'))` block calling `wp_register_ability()`.
4. Add a `wsp_execute_new_key($input)` function.
5. Call `wsp_register_*_abilities()` from `wsp_mcp_register_all_abilities()` in the main file if it's a new file.
