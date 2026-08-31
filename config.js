/**
 * Frontend bootstrap config — safe to expose in the browser.
 * Database credentials belong in public/cpanel-config.php (server-side only).
 *
 * DEPLOYMENT_MODE:
 *   'showcase' — hub demo site (content from Laravel hub DB via api.php)
 *   'advisor'  — live advisor cPanel site (published content from local DB)
 */
const CONFIG = {
  DEPLOYMENT_MODE: 'showcase',
  API_URL: './api.php',
  ADVISOR_ID: null,
  // Fallback until api.php returns uploads_origin from site_settings
  UPLOADS_ORIGIN: 'https://devznr.epatronus.net/compliance/api/api',
}

export default CONFIG
