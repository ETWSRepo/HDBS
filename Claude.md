This project uses standard prompts stored in "Z:\\Backup\\Websites\\Web Utilities\\StandardPrompts.md"

# Project: Handmade Designs By Suzi

## Stack
- PHP/HTML/JavaScript SPA (storefront + admin back office)
- Hostinger shared hosting, LiteSpeed server
- MySQL with PDO (DbgPDO wrapper), settings stored as key-value in `settings` table
- FTP deploy via `deploy.ps1` using curl.exe + Invoke-RestMethod

## Key Files
- `index.html` — storefront SPA (4 footers with `.site-version-line`)
- `api/admin.php` — central admin API (login, settings, version, logs)
- `api/config.php` — DB connection, cors(), ok(), fail(), body() helpers
- `api/github_log.php` — GitHub commit proxy (private repo, requires `github_token` setting)
- `api/deploy_log.php` — deploy history log (POST appends, GET returns reversed)
- `api/prompt_log.php` — prompt history CRUD
- `js/admin-misc.js` — all admin screen JS (nav, settings, logs, version, prompts)
- `js/admin-nav.js` — nav titles map and routing
- `js/table.js` / `css/table.css` — TableKit (NEVER modify — copy from component source if updated)
- `js/toolbar.js` / `css/toolbar.css` — PageToolbar (NEVER modify — copy from component source if updated)
- `regression_test.php` — token-gated test runner
- `deploy.ps1` — local deploy script (not deployed to server)

## Credentials & Security
- FTP credentials in `.ftp-credentials` (never commit)
- FTP: host=ftp.handmadedesignsbysuzi.com, user=u541882440.handmadedesignsbysuzi
- regression_test.php protected by `?token=` matching `rt_token` in settings DB
- Current token: `9f21953ce5be66f40203791c4cf8055e`
- GitHub API: private repo C177LVR/HandmadeDesignsBySuzi, token stored in `github_token` setting

## API Conventions
- `apiFetch(endpoint)` in JS prepends `https://handmadedesignsbysuzi.com/api/` — pass `'admin.php'` not `'api/admin.php'`
- All API responses use `ok([...])` or `fail('message')`

## Site Version
- Stored as `major_version` and `minor_version` in settings table
- Displayed in all 4 footers via `.site-version-line` div (opacity 0.5)
- `minor_version` auto-increments when `regression_test.php` is deployed
- Settings screen has Version card to manually set major/minor

## Admin Nav
- Nested JSON stored in `nav_order` setting
- Folders: 🛍️ Shop, 🔧 Developer (collapse state in localStorage `hdbs_nav_folders`)
- Drag-and-drop reordering across folders and root

## Regression Tests
- Run: `curl "https://handmadedesignsbysuzi.com/regression_test.php?token=9f21953ce5be66f40203791c4cf8055e"`
- Currently: 461/461 passing
- Always run and confirm passing before reporting a task done

## Deploy
- Single file: `.\deploy.ps1 path/to/file.php`
- Full deploy: `.\deploy.ps1`
- Deploying `regression_test.php` auto-increments minor version and logs deploy
- Use `Invoke-RestMethod` for JSON POSTs in deploy.ps1 (curl.exe has PS 5.1 quoting issues)
