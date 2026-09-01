<?php
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-API-Key, X-Requested-With');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

header('Content-Type: application/json');

// --------------------------------------------------------------------------
// UNIFIED TEMPLATE API — one codebase for showcase AND advisor deployments.
// Configure via public/cpanel-config.php (copy from cpanel-config.php.example).
// --------------------------------------------------------------------------
$DEPLOYMENT_MODE = 'showcase';
$ADVISOR_ID      = null;
$UPLOADS_ORIGIN  = '';
$SITE_URL        = '';
$LARAVEL_API_URL = '';
$SECRET_API_KEY  = 'YOUR_SECRET_KEY';

$DB_HOST = 'localhost';
$DB_NAME = 'YOUR_DB_NAME';
$DB_USER = 'YOUR_DB_USER';
$DB_PASS = '';

$manualConfigFile = __DIR__ . '/cpanel-config.php';
if (file_exists($manualConfigFile)) {
    $manual = include $manualConfigFile;
    if (is_array($manual)) {
        $DEPLOYMENT_MODE = $manual['DEPLOYMENT_MODE'] ?? $DEPLOYMENT_MODE;
        $ADVISOR_ID      = $manual['ADVISOR_ID'] ?? $ADVISOR_ID;
        $UPLOADS_ORIGIN  = $manual['UPLOADS_ORIGIN'] ?? $UPLOADS_ORIGIN;
        $SITE_URL        = $manual['SITE_URL'] ?? $SITE_URL;
        $LARAVEL_API_URL = $manual['LARAVEL_API_URL'] ?? $LARAVEL_API_URL;
        $DB_HOST         = $manual['DB_HOST'] ?? $DB_HOST;
        $DB_NAME         = $manual['DB_NAME'] ?? $DB_NAME;
        $DB_USER         = $manual['DB_USER'] ?? $DB_USER;
        $DB_PASS         = $manual['DB_PASS'] ?? $DB_PASS;
        $SECRET_API_KEY  = $manual['SECRET_API_KEY'] ?? $SECRET_API_KEY;
    }
}

$dataDir  = __DIR__ . '/data';
$dataFile = $dataDir . '/content.json';
$showcaseDataFile = $dataDir . '/showcase-content.json';
$seedFile = $dataDir . '/default-sections.json';
$dbConfigFile = $dataDir . '/cpanel-db.php';

require_once __DIR__ . '/includes/showcase-fetch.php';

function jsonBody($raw) {
    if (!$raw) return [];
    if (is_array($raw)) return $raw;
    $decoded = json_decode($raw, true);
    return is_array($decoded) ? $decoded : [];
}

function sectionKey($name) {
    return strtolower(preg_replace('/[^a-z0-9]/i', '', (string)$name));
}

function canonicalSectionName($name) {
    $map = [
        'header' => 'Header',
        'hero' => 'Hero Slider',
        'heroslider' => 'Hero Slider',
        'herosection' => 'Hero Slider',
        'features' => 'What we do',
        'featurescarousel' => 'What we do',
        'whatwedo' => 'What we do',
        'about' => 'About Section',
        'aboutsection' => 'About Section',
        'aboutus' => 'About Section',
        'history' => 'Company History',
        'companyhistory' => 'Company History',
        'services' => 'Featured Services',
        'featuredservices' => 'Featured Services',
        'annual' => 'Annual Progression',
        'annualprogression' => 'Annual Progression',
        'progression' => 'Annual Progression',
        'portfolio' => 'Portfolio Section',
        'portfoliosection' => 'Portfolio Section',
        'branch' => 'Branches and Appointment',
        'branches' => 'Branches and Appointment',
        'appointment' => 'Branches and Appointment',
        'branchesandappointment' => 'Branches and Appointment',
        'stat' => 'Counter Stats',
        'stats' => 'Counter Stats',
        'counterstats' => 'Counter Stats',
        'testimonial' => 'Testimonials Carousel',
        'testimonials' => 'Testimonials Carousel',
        'testimonialscarousel' => 'Testimonials Carousel',
        'news' => 'Latest News',
        'latestnews' => 'Latest News',
        'logo' => 'Client Logos',
        'logos' => 'Client Logos',
        'clientlogos' => 'Client Logos',
        'cta' => 'CTA Banner',
        'banner' => 'CTA Banner',
        'ctabanner' => 'CTA Banner',
        'footer' => 'Footer',
    ];
    $key = sectionKey($name);
    return $map[$key] ?? trim((string)$name);
}

function tableExists(PDO $pdo, $table) {
    $stmt = $pdo->prepare('SHOW TABLES LIKE ?');
    $stmt->execute([$table]);
    return (bool)$stmt->fetch();
}

function columnMap(PDO $pdo, $table) {
    $cols = [];
    try {
        foreach ($pdo->query("SHOW COLUMNS FROM `{$table}`") as $row) {
            $cols[strtolower($row['Field'])] = $row['Field'];
        }
    } catch (Exception $e) {
        return [];
    }
    return $cols;
}

function ensureSectionMetaColumns(PDO $pdo) {
    $cols = columnMap($pdo, 'sections');
    if (!$cols) return;

    $nameCol = $cols['section_name'] ?? $cols['name'] ?? null;
    if (!isset($cols['display_name'])) {
        $after = $nameCol ? " AFTER `{$nameCol}`" : '';
        $pdo->exec("ALTER TABLE `sections` ADD COLUMN `display_name` VARCHAR(255) NULL{$after}");
    }
    if (!isset($cols['is_visible'])) {
        $pdo->exec('ALTER TABLE `sections` ADD COLUMN `is_visible` TINYINT(1) NOT NULL DEFAULT 1');
    }
}

function rowIsVisible(array $row) {
    if (!array_key_exists('is_visible', $row)) return true;
    $value = $row['is_visible'];
    if ($value === null || $value === '') return true;
    return !in_array($value, [false, 0, '0', 'false'], true);
}

function sectionKeyFromName($name) {
    return strtolower(preg_replace('/[^a-z0-9]/i', '', (string)$name));
}

function canonicalSectionKey($name) {
    $key = sectionKeyFromName(canonicalSectionName($name));
    $map = [
        'herosection' => 'heroslider',
        'hero' => 'heroslider',
        'features' => 'whatwedo',
        'featurescarousel' => 'whatwedo',
        'about' => 'aboutsection',
        'aboutus' => 'aboutsection',
        'history' => 'companyhistory',
        'services' => 'featuredservices',
        'annual' => 'annualprogression',
        'progression' => 'annualprogression',
        'portfolio' => 'portfoliosection',
        'branches' => 'branchesandappointment',
        'branch' => 'branchesandappointment',
        'appointment' => 'branchesandappointment',
        'stats' => 'counterstats',
        'stat' => 'counterstats',
        'testimonials' => 'testimonialscarousel',
        'testimonial' => 'testimonialscarousel',
        'news' => 'latestnews',
        'logos' => 'clientlogos',
        'logo' => 'clientlogos',
        'cta' => 'ctabanner',
        'banner' => 'ctabanner',
    ];
    return $map[$key] ?? $key;
}

function dedupeSectionRows(array $rows, $advisorId = null) {
    $grouped = [];
    foreach ($rows as $row) {
        $name = canonicalSectionName($row['name'] ?? '');
        if ($name === '') continue;
        $grouped[$name][] = $row;
    }

    $deduped = [];
    foreach ($grouped as $name => $group) {
        if (count($group) === 1) {
            $deduped[] = $group[0];
            continue;
        }

        $chosen = null;
        if ($advisorId !== null && $advisorId !== '') {
            foreach ($group as $row) {
                $rowAdvisor = $row['advisor_id'] ?? null;
                if ($rowAdvisor !== null && (string)$rowAdvisor === (string)$advisorId) {
                    $chosen = $row;
                    break;
                }
            }
        }

        if (!$chosen) {
            foreach ($group as $row) {
                if (!$chosen) {
                    $chosen = $row;
                    continue;
                }
                $chosenRank = sectionContentRank(decodeSectionContent($chosen['content'] ?? null));
                $rowRank = sectionContentRank(decodeSectionContent($row['content'] ?? null));
                if ($rowRank > $chosenRank) {
                    $chosen = $row;
                }
            }
        }

        $deduped[] = $chosen;
    }

    return $deduped;
}

function loadSeedSections() {
    global $seedFile;
    if (!file_exists($seedFile)) return [];
    $raw = json_decode(file_get_contents($seedFile), true);
    return is_array($raw) ? $raw : [];
}

function loadSavedDbConfig() {
    global $dbConfigFile;
    if (!file_exists($dbConfigFile)) return [];
    $cfg = include $dbConfigFile;
    return is_array($cfg) ? $cfg : [];
}

function persistDbConfig($cfg) {
    global $dataDir, $dbConfigFile;
    if (!file_exists($dataDir)) mkdir($dataDir, 0755, true);
    $export = var_export([
        'DB_HOST' => $cfg['DB_HOST'] ?? 'localhost',
        'DB_NAME' => $cfg['DB_NAME'] ?? '',
        'DB_USER' => $cfg['DB_USER'] ?? '',
        'DB_PASS' => $cfg['DB_PASS'] ?? '',
        'SECRET_API_KEY' => $cfg['SECRET_API_KEY'] ?? '',
        'DEPLOYMENT_MODE' => $cfg['DEPLOYMENT_MODE'] ?? 'advisor',
        'ADVISOR_ID' => $cfg['ADVISOR_ID'] ?? null,
        'UPLOADS_ORIGIN' => $cfg['UPLOADS_ORIGIN'] ?? '',
        'SITE_URL' => $cfg['SITE_URL'] ?? '',
        'LARAVEL_API_URL' => $cfg['LARAVEL_API_URL'] ?? '',
    ], true);
    file_put_contents($dbConfigFile, "<?php\nreturn {$export};\n");
}

/**
 * Write public/cpanel-config.php when Laravel dashboard deploys an advisor site.
 */
function persistCpanelConfigFile(array $cfg) {
    global $manualConfigFile;
    if (isPlaceholderDb($cfg['DB_NAME'] ?? '', $cfg['DB_USER'] ?? '')) {
        return false;
    }
    $export = var_export([
        'DEPLOYMENT_MODE' => $cfg['DEPLOYMENT_MODE'] ?? 'advisor',
        'DB_HOST' => $cfg['DB_HOST'] ?? 'localhost',
        'DB_NAME' => $cfg['DB_NAME'] ?? '',
        'DB_USER' => $cfg['DB_USER'] ?? '',
        'DB_PASS' => $cfg['DB_PASS'] ?? '',
        'SECRET_API_KEY' => $cfg['SECRET_API_KEY'] ?? '',
        'ADVISOR_ID' => $cfg['ADVISOR_ID'] ?? null,
        'UPLOADS_ORIGIN' => $cfg['UPLOADS_ORIGIN'] ?? '',
        'SITE_URL' => $cfg['SITE_URL'] ?? '',
        'LARAVEL_API_URL' => $cfg['LARAVEL_API_URL'] ?? '',
    ], true);
    return (bool)file_put_contents($manualConfigFile, "<?php\n/**\n * Auto-written by Laravel deploy sync — do not commit.\n */\nreturn {$export};\n");
}

function isPlaceholderDb($name, $user) {
    return empty($name) || empty($user)
        || $name === 'YOUR_CPANEL_DB_NAME' || $user === 'YOUR_CPANEL_DB_USER'
        || $name === 'YOUR_DB_NAME' || $user === 'YOUR_DB_USER';
}

function resolveDbConfig($input = null) {
    global $DB_HOST, $DB_NAME, $DB_USER, $DB_PASS, $SECRET_API_KEY;
    global $DEPLOYMENT_MODE, $ADVISOR_ID, $UPLOADS_ORIGIN, $SITE_URL, $LARAVEL_API_URL;

    $saved = loadSavedDbConfig();
    $cfg = [
        'DB_HOST' => $saved['DB_HOST'] ?? $DB_HOST,
        'DB_NAME' => $saved['DB_NAME'] ?? $DB_NAME,
        'DB_USER' => $saved['DB_USER'] ?? $DB_USER,
        'DB_PASS' => $saved['DB_PASS'] ?? $DB_PASS,
        'SECRET_API_KEY' => $saved['SECRET_API_KEY'] ?? $SECRET_API_KEY,
        'DEPLOYMENT_MODE' => $saved['DEPLOYMENT_MODE'] ?? $DEPLOYMENT_MODE,
        'ADVISOR_ID' => array_key_exists('ADVISOR_ID', $saved) ? $saved['ADVISOR_ID'] : $ADVISOR_ID,
        'UPLOADS_ORIGIN' => $saved['UPLOADS_ORIGIN'] ?? $UPLOADS_ORIGIN,
        'SITE_URL' => $saved['SITE_URL'] ?? $SITE_URL,
        'LARAVEL_API_URL' => $saved['LARAVEL_API_URL'] ?? $LARAVEL_API_URL,
    ];

    if (is_array($input)) {
        $postName = $input['db_name'] ?? $input['cpanel_db_name'] ?? null;
        $postUser = $input['db_user'] ?? $input['cpanel_db_user'] ?? null;
        if ($postName && $postUser && !isPlaceholderDb($postName, $postUser)) {
            $cfg['DB_HOST'] = $input['db_host'] ?? $input['cpanel_db_host'] ?? 'localhost';
            $cfg['DB_NAME'] = $postName;
            $cfg['DB_USER'] = $postUser;
            $cfg['DB_PASS'] = $input['db_pass'] ?? $input['cpanel_db_pass'] ?? '';
            if (!empty($input['api_key'])) {
                $cfg['SECRET_API_KEY'] = $input['api_key'];
            }
        }
        foreach (['deployment_mode', 'uploads_origin', 'site_url', 'laravel_api_url', 'advisor_id'] as $key) {
            if (isset($input[$key]) && $input[$key] !== '') {
                $cfg[strtoupper($key)] = $input[$key];
            }
        }
    }

    return $cfg;
}

function resolveDeploymentMode(array $cfg, PDO $pdo = null) {
    // cpanel-config.php / deploy POST payload is the source of truth.
    // Do NOT let a leftover site_settings.deployment_mode flip advisor → showcase
    // (that prevented sections table creation on advisor cPanels).
    $mode = strtolower(trim((string)($cfg['DEPLOYMENT_MODE'] ?? 'advisor')));
    if ($mode === '') {
        $mode = 'advisor';
    }
    return in_array($mode, ['showcase', 'advisor'], true) ? $mode : 'advisor';
}

function syncDeploymentSettings(PDO $pdo, array $cfg) {
    $pairs = [
        'deployment_mode' => $cfg['DEPLOYMENT_MODE'] ?? 'advisor',
        'uploads_origin'  => $cfg['UPLOADS_ORIGIN'] ?? '',
        'site_url'        => $cfg['SITE_URL'] ?? '',
        'laravel_api_url' => $cfg['LARAVEL_API_URL'] ?? '',
        'advisor_id'      => $cfg['ADVISOR_ID'] ?? '',
    ];
    $stmt = $pdo->prepare('REPLACE INTO site_settings (setting_key, setting_value) VALUES (?, ?)');
    foreach ($pairs as $key => $value) {
        if ($value !== null && $value !== '') {
            $stmt->execute([$key, (string)$value]);
        }
    }
}

function saveSiteSetting(PDO $pdo, $key, $value) {
    if ($value === null || $value === '') return;
    $stmt = $pdo->prepare('REPLACE INTO site_settings (setting_key, setting_value) VALUES (?, ?)');
    $stmt->execute([$key, (string)$value]);
}

$PDO_CONNECT_ERROR = null;

/**
 * Ensure advisor cPanel has local site_settings + sections tables.
 * Safe to call repeatedly (IF NOT EXISTS / empty-seed only).
 */
function ensureAdvisorSchema(PDO $pdo) {
    global $PDO_CONNECT_ERROR;

    try {
        $pdo->exec("CREATE TABLE IF NOT EXISTS `site_settings` (
            `setting_key` VARCHAR(100) PRIMARY KEY,
            `setting_value` TEXT NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");
    } catch (Exception $e) {
        $PDO_CONNECT_ERROR = 'site_settings create failed: ' . $e->getMessage();
    }

    try {
        if (!tableExists($pdo, 'sections')) {
            $pdo->exec("CREATE TABLE `sections` (
                `id` INT AUTO_INCREMENT PRIMARY KEY,
                `section_name` VARCHAR(255) NOT NULL UNIQUE,
                `display_name` VARCHAR(255) NULL,
                `is_visible` TINYINT(1) NOT NULL DEFAULT 1,
                `content` LONGTEXT NOT NULL,
                `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");
        }

        ensureSectionMetaColumns($pdo);

        $cols = columnMap($pdo, 'sections');
        $nameCol = $cols['section_name'] ?? $cols['name'] ?? null;
        if ($nameCol) {
            $count = (int)$pdo->query('SELECT COUNT(*) FROM `sections`')->fetchColumn();
            // Skip local JSON seed when Laravel is pushing showcase/hub sections in this request.
            $incomingSections = $GLOBALS['__cpanel_incoming_sections'] ?? [];
            $hasIncoming = is_array($incomingSections) && count($incomingSections) > 0;
            if ($count === 0 && !$hasIncoming) {
                $seed = loadSeedSections();
                if ($seed) {
                    $stmt = $pdo->prepare("INSERT INTO `sections` (`{$nameCol}`, `content`) VALUES (?, ?)");
                    foreach ($seed as $sName => $sContent) {
                        $stmt->execute([
                            canonicalSectionName($sName),
                            json_encode($sContent, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                        ]);
                    }
                }
            }
        }
    } catch (Exception $e) {
        $PDO_CONNECT_ERROR = 'sections create/seed failed: ' . $e->getMessage();
    }
}

function getPdoConnection($host, $name, $user, $pass, $deploymentMode = 'advisor') {
    global $PDO_CONNECT_ERROR;
    $PDO_CONNECT_ERROR = null;
    if (isPlaceholderDb($name, $user)) {
        $PDO_CONNECT_ERROR = 'MySQL credentials are placeholders. Create public/cpanel-config.php with this site\'s database credentials.';
        return null;
    }
    try {
        $pdo = new PDO("mysql:host={$host};dbname={$name};charset=utf8mb4", $user, $pass, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
    } catch (Exception $e) {
        $PDO_CONNECT_ERROR = $e->getMessage();
        return null;
    }

    // Always ensure site_settings exists (both modes use it)
    try {
        $pdo->exec("CREATE TABLE IF NOT EXISTS `site_settings` (
            `setting_key` VARCHAR(100) PRIMARY KEY,
            `setting_value` TEXT NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");
    } catch (Exception $e) {
        // optional on hub/showcase if table already managed by Laravel
    }

    if (strtolower((string)$deploymentMode) === 'advisor') {
        ensureAdvisorSchema($pdo);
    }

    return $pdo;
}

function fetchSectionRows(PDO $pdo, $advisorId = null) {
    $cols = columnMap($pdo, 'sections');
    if (!$cols) return [];

    $nameCol = $cols['section_name'] ?? $cols['name'] ?? null;
    $contentCol = $cols['content'] ?? null;
    if (!$nameCol || !$contentCol) return [];

    $hasAdvisor = isset($cols['advisor_id']);
    $hasDisplayName = isset($cols['display_name']);
    $hasVisible = isset($cols['is_visible']);
    $sql = "SELECT `{$nameCol}` AS name, `{$contentCol}` AS content";
    if ($hasDisplayName) $sql .= ', `display_name`';
    if ($hasVisible) $sql .= ', `is_visible`';
    if ($hasAdvisor) $sql .= ', `advisor_id`';
    $sql .= ' FROM `sections`';

    $params = [];
    if ($hasAdvisor && $advisorId !== null && $advisorId !== '') {
        $sql .= ' WHERE (`advisor_id` = ? OR `advisor_id` IS NULL)';
        $params[] = $advisorId;
    }

    $sql .= $hasAdvisor ? ' ORDER BY (`advisor_id` IS NULL) ASC, `id` ASC' : ' ORDER BY `id` ASC';

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll() ?: [];
}

function decodeSectionContent($cnt) {
    if (is_string($cnt)) {
        $decoded = json_decode($cnt, true);
        return is_array($decoded) ? $decoded : $cnt;
    }
    return $cnt;
}

function sectionContentRank($cnt) {
    if (!is_array($cnt)) return 0;
    $rank = count($cnt);
    if (!empty($cnt['boxes']) && is_array($cnt['boxes'])) $rank += 50 + count($cnt['boxes']);
    if (!empty($cnt['slides']) && is_array($cnt['slides'])) $rank += 50 + count($cnt['slides']);
    return $rank;
}

function rowsToSections(array $rows) {
    $sections = [];
    $list = [];

    foreach ($rows as $row) {
        $name = canonicalSectionName($row['name'] ?? '');
        if ($name === '') continue;

        $visible = rowIsVisible($row);
        $cnt = decodeSectionContent($row['content'] ?? null);
        $label = !empty($row['display_name']) ? $row['display_name'] : $name;
        $key = canonicalSectionKey($name);

        $list[] = [
            'name'         => $name,
            'section_key'  => $key,
            'display_name' => $label,
            'is_visible'   => $visible,
            'content'      => $visible ? $cnt : null,
        ];

        if (!$visible) continue;

        if (!isset($sections[$name]) || sectionContentRank($cnt) > sectionContentRank($sections[$name])) {
            $sections[$name] = $cnt;
        }
    }

    return [$sections, $list];
}

function upsertPublishedSection(PDO $pdo, $name, $content, $advisorId = null, $displayName = null, $isVisible = true) {
    $cols = columnMap($pdo, 'sections');
    $nameCol = $cols['section_name'] ?? $cols['name'] ?? null;
    $contentCol = $cols['content'] ?? null;
    if (!$nameCol || !$contentCol) {
        throw new Exception('sections table is missing name/content columns');
    }

    $name = canonicalSectionName($name);
    $json = is_string($content) ? $content : json_encode($content, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    $hasAdvisor = isset($cols['advisor_id']);
    $hasPage = isset($cols['page_id']);
    $hasId = isset($cols['id']);
    $hasDisplayName = isset($cols['display_name']);
    $hasVisible = isset($cols['is_visible']);
    $visibleValue = $isVisible ? 1 : 0;

    $updated = 0;
    if ($hasId) {
        $select = "SELECT `id`, `{$nameCol}` AS name";
        if ($hasAdvisor) $select .= ', `advisor_id`';
        $select .= ' FROM `sections`';
        $existing = $pdo->query($select)->fetchAll() ?: [];

        $setParts = ["`{$contentCol}` = ?", "`{$nameCol}` = ?"];
        $setValues = [$json, $name];
        if ($hasDisplayName) { $setParts[] = '`display_name` = ?'; $setValues[] = $displayName ?: null; }
        if ($hasVisible) { $setParts[] = '`is_visible` = ?'; $setValues[] = $visibleValue; }
        $upd = $pdo->prepare('UPDATE `sections` SET ' . implode(', ', $setParts) . ' WHERE `id` = ?');

        foreach ($existing as $row) {
            if (canonicalSectionName($row['name'] ?? '') !== $name) continue;
            if ($hasAdvisor && $advisorId !== null && $advisorId !== '') {
                $rowAdvisor = $row['advisor_id'] ?? null;
                if ($rowAdvisor !== null && (string)$rowAdvisor !== (string)$advisorId) continue;
            }
            $upd->execute(array_merge($setValues, [$row['id']]));
            $updated++;
        }
        if ($updated > 0) return;
    } else {
        $find = $pdo->prepare("SELECT `{$nameCol}` FROM `sections` WHERE `{$nameCol}` = ? LIMIT 1");
        $find->execute([$name]);
        if ($find->fetchColumn()) {
            $setParts = ["`{$contentCol}` = ?"];
            $setValues = [$json];
            if ($hasDisplayName) { $setParts[] = '`display_name` = ?'; $setValues[] = $displayName ?: null; }
            if ($hasVisible) { $setParts[] = '`is_visible` = ?'; $setValues[] = $visibleValue; }
            $upd = $pdo->prepare('UPDATE `sections` SET ' . implode(', ', $setParts) . " WHERE `{$nameCol}` = ?");
            $setValues[] = $name;
            $upd->execute($setValues);
            return;
        }
    }

    $fields = [$nameCol, $contentCol];
    $values = [$name, $json];
    if ($hasDisplayName) { $fields[] = 'display_name'; $values[] = $displayName ?: null; }
    if ($hasVisible) { $fields[] = 'is_visible'; $values[] = $visibleValue; }
    if ($hasAdvisor && $advisorId !== null && $advisorId !== '') {
        $fields[] = 'advisor_id';
        $values[] = $advisorId;
    }
    if ($hasPage) {
        $pageId = 1;
        try {
            $pageId = $pdo->query("SELECT `id` FROM `pages` WHERE `slug` = 'home' LIMIT 1")->fetchColumn() ?: $pageId;
        } catch (Exception $e) {}
        $fields[] = 'page_id';
        $values[] = $pageId;
    }

    $placeholders = implode(', ', array_fill(0, count($fields), '?'));
    $fieldSql = '`' . implode('`, `', $fields) . '`';
    $ins = $pdo->prepare("INSERT INTO `sections` ({$fieldSql}) VALUES ({$placeholders})");
    $ins->execute($values);
}

function updateSectionMetaOnly(PDO $pdo, $name, $displayName = null, $isVisible = true, $advisorId = null) {
    $cols = columnMap($pdo, 'sections');
    $nameCol = $cols['section_name'] ?? $cols['name'] ?? null;
    if (!$nameCol) throw new Exception('sections table is missing name column');

    $name = canonicalSectionName($name);
    $hasAdvisor = isset($cols['advisor_id']);
    $hasDisplayName = isset($cols['display_name']);
    $hasVisible = isset($cols['is_visible']);
    $hasId = isset($cols['id']);

    $setParts = [];
    $setValues = [];
    if ($hasDisplayName) { $setParts[] = '`display_name` = ?'; $setValues[] = $displayName ?: null; }
    if ($hasVisible) { $setParts[] = '`is_visible` = ?'; $setValues[] = $isVisible ? 1 : 0; }
    if (!$setParts) return;

    if ($hasId) {
        $select = "SELECT `id`, `{$nameCol}` AS name";
        if ($hasAdvisor) $select .= ', `advisor_id`';
        $select .= ' FROM `sections`';
        $existing = $pdo->query($select)->fetchAll() ?: [];
        $upd = $pdo->prepare('UPDATE `sections` SET ' . implode(', ', $setParts) . ' WHERE `id` = ?');
        foreach ($existing as $row) {
            if (canonicalSectionName($row['name'] ?? '') !== $name) continue;
            if ($hasAdvisor && $advisorId !== null && $advisorId !== '') {
                $rowAdvisor = $row['advisor_id'] ?? null;
                if ($rowAdvisor !== null && (string)$rowAdvisor !== (string)$advisorId) continue;
            }
            $upd->execute(array_merge($setValues, [$row['id']]));
        }
        return;
    }

    $upd = $pdo->prepare('UPDATE `sections` SET ' . implode(', ', $setParts) . " WHERE `{$nameCol}` = ?");
    $setValues[] = $name;
    $upd->execute($setValues);
}

function loadSiteSettings(PDO $pdo, array &$result) {
    try {
        $settingsStmt = $pdo->query('SELECT setting_key, setting_value FROM site_settings');
        while ($row = $settingsStmt->fetch()) {
            $result[$row['setting_key']] = $row['setting_value'];
        }
    } catch (Exception $e) {}
}

function loadJsonFallback(array &$result, $file) {
    if (!file_exists($file)) return;
    $fileData = json_decode(file_get_contents($file), true) ?: [];
    if (!empty($fileData['primary_color'])) $result['primary_color'] = $fileData['primary_color'];
    if (!empty($fileData['secondary_color'])) $result['secondary_color'] = $fileData['secondary_color'];
    if (!empty($fileData['logo_url'])) $result['logo_url'] = $fileData['logo_url'];

    if (!isset($fileData['sections']) || !is_array($fileData['sections'])) return;

    $meta = isset($fileData['sections_meta']) && is_array($fileData['sections_meta']) ? $fileData['sections_meta'] : [];
    $list = [];
    foreach ($fileData['sections'] as $secName => $secContent) {
        $name = canonicalSectionName($secName);
        $sectionMeta = $meta[$name] ?? [];
        $isVisible = !array_key_exists('is_visible', $sectionMeta) || !in_array($sectionMeta['is_visible'], [false, 0, '0', 'false'], true);
        $cnt = is_string($secContent) ? (json_decode($secContent, true) ?: $secContent) : $secContent;
        if ($isVisible) $result['sections'][$name] = $cnt;
        $list[] = [
            'name'         => $name,
            'section_key'  => canonicalSectionKey($name),
            'display_name' => $sectionMeta['display_name'] ?? $name,
            'is_visible'   => $isVisible,
            'content'      => $isVisible ? $cnt : null,
        ];
    }
    $result['sections_list'] = $list;
    $result['content_source'] = 'json_file';
}

$rawInput = file_get_contents('php://input');
$input = $_SERVER['REQUEST_METHOD'] === 'POST' ? (json_decode($rawInput, true) ?: []) : [];
$GLOBALS['__cpanel_incoming_sections'] = (isset($input['sections']) && is_array($input['sections'])) ? $input['sections'] : [];
$dbCfg = resolveDbConfig($_SERVER['REQUEST_METHOD'] === 'POST' ? $input : null);
if (!empty($dbCfg['SECRET_API_KEY'])) {
    $SECRET_API_KEY = $dbCfg['SECRET_API_KEY'];
}

$deploymentMode = resolveDeploymentMode($dbCfg);
$pdo = getPdoConnection($dbCfg['DB_HOST'], $dbCfg['DB_NAME'], $dbCfg['DB_USER'], $dbCfg['DB_PASS'], $deploymentMode);

if ($pdo) {
    // On deploy / when payload forces advisor mode, always ensure local tables exist
    $forceAdvisor = strtolower((string)($dbCfg['DEPLOYMENT_MODE'] ?? '')) === 'advisor'
        || !empty($input['write_config'])
        || (isset($input['deployment_mode']) && strtolower((string)$input['deployment_mode']) === 'advisor');
    if ($forceAdvisor || $deploymentMode === 'advisor') {
        $deploymentMode = 'advisor';
        ensureAdvisorSchema($pdo);
    }
    syncDeploymentSettings($pdo, array_merge($dbCfg, ['DEPLOYMENT_MODE' => $deploymentMode]));
    $deploymentMode = resolveDeploymentMode(array_merge($dbCfg, ['DEPLOYMENT_MODE' => $deploymentMode]), $pdo);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!$input) {
        http_response_code(400);
        echo json_encode(['status' => 'error', 'message' => 'Invalid JSON payload']);
        exit;
    }

    if (!empty($SECRET_API_KEY) && $SECRET_API_KEY !== 'YOUR_SECRET_KEY') {
        $providedKey = $input['api_key'] ?? $_SERVER['HTTP_X_API_KEY'] ?? null;
        if (!$providedKey && isset($_SERVER['HTTP_AUTHORIZATION'])) {
            if (preg_match('/Bearer\s+(.*)$/i', $_SERVER['HTTP_AUTHORIZATION'], $matches)) {
                $providedKey = $matches[1];
            }
        }
        if ($providedKey !== $SECRET_API_KEY) {
            http_response_code(401);
            echo json_encode(['status' => 'error', 'message' => 'Unauthorized: Invalid API Key.']);
            exit;
        }
    }

    $updatedSectionsCount = 0;
    $advisorId = $input['advisor_id'] ?? ($_GET['advisor_id'] ?? $dbCfg['ADVISOR_ID'] ?? null);
    $dbError = null;

    if ($pdo) {
        try {
            foreach (['primary_color', 'secondary_color', 'logo_url', 'uploads_origin', 'site_url', 'laravel_api_url', 'deployment_mode', 'advisor_id'] as $settingKey) {
                if (!empty($input[$settingKey])) {
                    saveSiteSetting($pdo, $settingKey, $input[$settingKey]);
                }
            }

            if (isset($input['sections']) && is_array($input['sections'])) {
                foreach ($input['sections'] as $newSec) {
                    $name = $newSec['name'] ?? $newSec['section_name'] ?? null;
                    $content = $newSec['content'] ?? null;
                    $displayName = $newSec['display_name'] ?? null;
                    $isVisible = array_key_exists('is_visible', $newSec) ? (bool)$newSec['is_visible'] : true;
                    if (!$name) continue;
                    if ($content !== null && $content !== '') {
                        upsertPublishedSection($pdo, $name, $content, $advisorId, $displayName, $isVisible);
                        $updatedSectionsCount++;
                    } elseif (array_key_exists('is_visible', $newSec) || $displayName !== null) {
                        updateSectionMetaOnly($pdo, $name, $displayName, $isVisible, $advisorId);
                        $updatedSectionsCount++;
                    }
                }
            }
        } catch (Exception $e) {
            $dbError = $e->getMessage();
        }
    } else {
        $dbError = 'MySQL is not connected. Fill DB credentials in cpanel-config.php.';
    }

    if ($pdo && !$dbError && !isPlaceholderDb($dbCfg['DB_NAME'], $dbCfg['DB_USER'])) {
        persistDbConfig($dbCfg);
    }

    $configWritten = false;
    $shouldWriteConfig = !empty($input['write_config'])
        || (!empty($input['db_name']) && !empty($input['db_user']));
    if ($shouldWriteConfig && !$dbError && !isPlaceholderDb($dbCfg['DB_NAME'], $dbCfg['DB_USER'])) {
        $configWritten = persistCpanelConfigFile($dbCfg);
    }

    if (!file_exists($dataDir)) mkdir($dataDir, 0755, true);
    $existing = file_exists($dataFile) ? (json_decode(file_get_contents($dataFile), true) ?: []) : [];
    if (!empty($input['primary_color'])) $existing['primary_color'] = $input['primary_color'];
    if (!empty($input['secondary_color'])) $existing['secondary_color'] = $input['secondary_color'];
    if (!empty($input['logo_url'])) $existing['logo_url'] = $input['logo_url'];
    if (!isset($existing['sections']) || !is_array($existing['sections'])) $existing['sections'] = [];

    if (isset($input['sections']) && is_array($input['sections'])) {
        if (!isset($existing['sections_meta']) || !is_array($existing['sections_meta'])) {
            $existing['sections_meta'] = [];
        }
        foreach ($input['sections'] as $newSec) {
            $name = $newSec['name'] ?? $newSec['section_name'] ?? null;
            $content = $newSec['content'] ?? null;
            $displayName = $newSec['display_name'] ?? null;
            $isVisible = array_key_exists('is_visible', $newSec) ? (bool)$newSec['is_visible'] : true;
            if ($name && $content !== null && $content !== '') {
                $parsed = is_string($content) ? (json_decode($content, true) ?: $content) : $content;
                $canonical = canonicalSectionName($name);
                $existing['sections'][$canonical] = $parsed;
                $existing['sections_meta'][$canonical] = [
                    'display_name' => $displayName ?: $canonical,
                    'is_visible'   => $isVisible,
                ];
                if (!$pdo) $updatedSectionsCount++;
            }
        }
    }

    file_put_contents($dataFile, json_encode($existing, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

    http_response_code($dbError ? 500 : 200);
    echo json_encode([
        'status' => $dbError ? 'error' : 'success',
        'message' => $dbError
            ? ('Failed saving content: ' . $dbError)
            : 'Content saved successfully',
        'deployment_mode' => $deploymentMode,
        'db_active' => (bool)$pdo,
        'config_written' => $configWritten,
        'updated_count' => $updatedSectionsCount,
        'data' => $existing,
    ]);
    exit;
}

$result = [
    'primary_color'   => '#0B1B3D',
    'secondary_color' => '#C8102E',
    'logo_url'        => '/assets/intime/logo-dark.png',
    'sections'        => [],
    'sections_list'   => [],
    'content_source'  => 'empty',
    'deployment_mode' => $deploymentMode,
    'db_connected'    => (bool)$pdo,
    'db_name'         => (!empty($dbCfg['DB_NAME']) && !isPlaceholderDb($dbCfg['DB_NAME'], $dbCfg['DB_USER'] ?? '')) ? $dbCfg['DB_NAME'] : null,
    'db_error'        => $PDO_CONNECT_ERROR,
];

if ($pdo) {
    loadSiteSettings($pdo, $result);
    // Keep config-driven mode; only fill mode from DB if config did not set one
    if (empty($dbCfg['DEPLOYMENT_MODE']) && !empty($result['deployment_mode'])) {
        $deploymentMode = strtolower((string)$result['deployment_mode']);
    } else {
        $result['deployment_mode'] = $deploymentMode;
    }
}

if ($deploymentMode === 'showcase' && $pdo) {
    $slug = preg_replace('/[^a-z0-9_-]/i', '', $_GET['slug'] ?? 'template4') ?: 'template4';
    $showcase = fetchShowcaseContent($pdo, $slug, file_exists($showcaseDataFile) ? $showcaseDataFile : $dataFile);
    if ($showcase) {
        if (!empty($showcase['sections'])) $result['sections'] = $showcase['sections'];
        if (!empty($showcase['sections_list'])) $result['sections_list'] = $showcase['sections_list'];
        if (!empty($showcase['content_source'])) $result['content_source'] = $showcase['content_source'];
        foreach (['primary_color', 'secondary_color', 'logo_url'] as $colorKey) {
            if (!empty($showcase[$colorKey])) $result[$colorKey] = $showcase[$colorKey];
        }
    }
} elseif ($pdo) {
    $advisorId = $_GET['advisor_id'] ?? $result['advisor_id'] ?? $dbCfg['ADVISOR_ID'] ?? null;
    try {
        $dbRows = fetchSectionRows($pdo, $advisorId);
        $rows = dedupeSectionRows($dbRows, $advisorId);
        [$result['sections'], $result['sections_list']] = rowsToSections($rows);
        if (count($dbRows) > 0) {
            $result['content_source'] = 'cpanel_sections';
        }
    } catch (Exception $e) {
        $result['db_error'] = $e->getMessage();
    }
}

if (count($result['sections_list']) === 0 && empty($result['sections'])) {
    loadJsonFallback($result, $dataFile);
}

if (empty($result['uploads_origin']) && !empty($dbCfg['UPLOADS_ORIGIN'])) {
    $result['uploads_origin'] = $dbCfg['UPLOADS_ORIGIN'];
}
if (empty($result['site_url']) && !empty($dbCfg['SITE_URL'])) {
    $result['site_url'] = $dbCfg['SITE_URL'];
}

echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
exit;
