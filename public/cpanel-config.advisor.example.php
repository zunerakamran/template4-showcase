<?php
/**
 * ADVISOR cPanel config — copy to cpanel-config.php on the ADVISOR server only.
 * Do NOT use the showcase/hub database credentials here.
 *
 * Power Admin: create a NEW MySQL DB on THIS advisor cPanel, then fill values below.
 * Or leave placeholders and use the dashboard Deploy form (Laravel will write this file).
 */
return [
    'DEPLOYMENT_MODE' => 'advisor',

    // THIS advisor's local cPanel MySQL (NOT the hub Laravel database)
    'DB_HOST' => 'localhost',
    'DB_NAME' => 'YOUR_ADVISOR_DB_NAME',
    'DB_USER' => 'YOUR_ADVISOR_DB_USER',
    'DB_PASS' => 'YOUR_ADVISOR_DB_PASS',

    // Must match cpanel_api_key entered in Power Admin deploy form
    'SECRET_API_KEY' => 'YOUR_SECRET_KEY',

    // Advisor user id from the hub dashboard
    'ADVISOR_ID' => 6,

    // Hub URLs (same for all advisors — where uploads live)
    'UPLOADS_ORIGIN'  => 'https://devznr.epatronus.net/compliance/api/api',
    'SITE_URL'        => 'https://adviser1.fin-proms.com',
    'LARAVEL_API_URL' => 'https://devznr.epatronus.net/compliance/api/api',
];
