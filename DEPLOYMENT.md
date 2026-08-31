### Every new advisor: Live site (advisor cPanel)

Repeat these steps for **each advisor** who requested a template.

#### Prerequisites (already in your flow)

- Advisor logged in and **requested a template** from their dashboard
- Power Admin sees the request in the **Template Requests** list

#### Steps Power Admin performs

| Step | Action |
|------|--------|
| **1** | **Build once** (if not already built) — same build for all advisors |
| **2** | **Upload the template** to the advisor's cPanel (same files as showcase: `dist/` + `api.php` + `includes/` + `data/`) |
| **3** | **Create a new MySQL database** on the advisor's cPanel (one DB per advisor) |
| **4** | **Open the deploy form** in the Power Admin dashboard for that template request |
| **5** | **Fill in the form:** |

**Deploy form fields:**

| Field | Example | Notes |
|-------|---------|-------|
| cPanel domain | `https://advisor-site.com/template4` | Full URL where `api.php` is reachable |
| DB host | `localhost` | Usually `localhost` on cPanel |
| DB name | `advisor6_template4_db` | The database you just created |
| DB user | `advisor6_db_user` | cPanel MySQL user |
| DB password | `••••••••` | cPanel MySQL password |
| API key | `sec_epatronus_live_key_...` | Secret key — must match what advisor site expects; generate one per site |

| Step | Action |
|------|--------|
| **6** | **Click Deploy** |

#### What happens automatically after Deploy

Laravel connects to the advisor's `api.php` and:

- Writes `cpanel-config.php` on the advisor server
- Writes `data/cpanel-db.php` (backup copy of credentials)
- Saves URLs into `site_settings` (`uploads_origin`, `site_url`, `advisor_id`, etc.)
- Pushes initial section content into the advisor's database

Power Admin does **not** need to:

- Manually create or edit `cpanel-config.php` on advisor cPanel (unless deploy sync fails)
- Manually insert URLs into the advisor database
- Maintain separate template code per advisor

#### After deploy (advisor + approver flow)

1. **Advisor** edits content in their dashboard
2. Changes go to **Approver** for approval
3. On approval, Laravel **publishes** content to the advisor's live site via `api.php`
4. Power Admin only intervenes again if something breaks or a new advisor needs deploying

#### If deploy sync fails

Check Laravel logs (`storage/logs/laravel.log`). Common causes:

- Wrong cPanel domain ( `api.php` not reachable )
- Wrong DB credentials
- API key mismatch
- File permissions on advisor cPanel (PHP must be able to write `cpanel-config.php`)

**Manual fallback:** create `public/cpanel-config.php` on the advisor cPanel by hand — see [Advisor config example](#advisor-config) below.

#### Before first advisor deploy: Laravel `.env` (one-time, hub server)

Ensure these are set on the **live** Laravel backend:

```env
HUB_API_URL=https://devznr.epatronus.net/compliance/api/api
HUB_UPLOADS_ORIGIN=https://devznr.epatronus.net/compliance/api/api
```

Then run `php artisan config:clear`. These URLs are pushed to every advisor site on deploy.

---

### Power Admin quick reference

```
SHOWCASE (once):
  Build → Upload → cpanel-config.php (manual) → Done

EACH ADVISOR:
  Upload same build → Create DB → Fill deploy form → Deploy → Done
```

---

## Architecture

```
┌─────────────────────────────────────────────────────────────┐
│  HUB SERVER (dashboard + Laravel backend + showcase)        │
│                                                             │
│  config.js          DEPLOYMENT_MODE: 'showcase'             │
│  cpanel-config.php  → Laravel hub MySQL                     │
│  api.php            → reads templates.dummy_content /         │
│                       sections (advisor_id IS NULL)         │
└─────────────────────────────────────────────────────────────┘

┌─────────────────────────────────────────────────────────────┐
│  ADVISOR cPanel (one copy per advisor, same code)           │
│                                                             │
│  config.js          DEPLOYMENT_MODE: 'advisor', ADVISOR_ID  │
│  cpanel-config.php  → advisor's local MySQL (auto-written)  │
│  api.php            → reads published sections only         │
└─────────────────────────────────────────────────────────────┘
```

---

## Config examples

### Showcase config {#showcase-config}

**`config.js`** (frontend — safe in browser):

```js
DEPLOYMENT_MODE: 'showcase',
API_URL: './api.php',
ADVISOR_ID: null,
UPLOADS_ORIGIN: 'https://devznr.epatronus.net/compliance/api/api',
```

**`public/cpanel-config.php`** (server-side — never expose):

```php
'DEPLOYMENT_MODE' => 'showcase',
'DB_HOST' => 'localhost',
'DB_NAME' => 'your_laravel_hub_database',
'DB_USER' => '...',
'DB_PASS' => '...',
'SECRET_API_KEY' => '...',
'UPLOADS_ORIGIN' => 'https://devznr.epatronus.net/compliance/api/api',
'SITE_URL' => 'https://devznr.epatronus.net/compliance/template4-showcase',
'LARAVEL_API_URL' => 'https://devznr.epatronus.net/compliance/api/api',
```

Content source: Laravel `templates.dummy_content` → `sections` (advisor_id NULL) → JSON fallback.

### Advisor config {#advisor-config}

Only needed if automatic deploy sync fails.

**`config.js`** (set `ADVISOR_ID` to match the advisor before building, or edit after upload):

```js
DEPLOYMENT_MODE: 'advisor',
API_URL: './api.php',
ADVISOR_ID: 6,
UPLOADS_ORIGIN: 'https://devznr.epatronus.net/compliance/api/api',
```

**`public/cpanel-config.php`**:

```php
'DEPLOYMENT_MODE' => 'advisor',
'ADVISOR_ID' => 6,
'DB_HOST' => 'localhost',
'DB_NAME' => 'advisor_template4_db',
'DB_USER' => '...',
'DB_PASS' => '...',
'SECRET_API_KEY' => '...',
'UPLOADS_ORIGIN' => 'https://devznr.epatronus.net/compliance/api/api',
'SITE_URL' => 'https://advisor-site.example.com',
'LARAVEL_API_URL' => 'https://devznr.epatronus.net/compliance/api/api',
```

---

## URL management via `site_settings`

These keys are stored in the database and returned on every GET from `api.php`:

| Key | Purpose |
|-----|---------|
| `deployment_mode` | `showcase` or `advisor` |
| `uploads_origin` | Base URL for advisor-uploaded images |
| `site_url` | Public URL of this deployment |
| `laravel_api_url` | Hub Laravel API |
| `advisor_id` | Advisor ID for this site |
| `primary_color` | Theme color |
| `secondary_color` | Accent color |
| `logo_url` | Site logo path |

The React app reads `uploads_origin` from the API response at runtime (overrides `config.js` fallback).

For **advisor sites**, these are written automatically on deploy. Power Admin does not edit them in phpMyAdmin unless fixing a broken URL later.

---

## Security note

**Never put database passwords in `config.js`.**  
DB credentials belong only in `cpanel-config.php` on the server (or are written there automatically by the deploy sync).

---

## Retiring the duplicate `template4` folder

After verifying this unified build works on both showcase and one advisor cPanel, you can stop maintaining the separate `template4` desktop copy and deploy this project everywhere.
