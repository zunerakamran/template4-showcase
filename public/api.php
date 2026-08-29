<?php
// Enable CORS
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-API-Key, X-Requested-With');

// Handle preflight OPTIONS request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

header('Content-Type: application/json');

// --------------------------------------------------------------------------
// SHOWCASE CONFIGURATION
// This api.php belongs to the SHOWCASE version of template4.
// It uses its OWN dedicated database — completely separate from advisor DBs.
// 1. Secret API Key: Set to match cpanel_api_key in Laravel Dashboard.
// 2. MySQL Database Settings: Fill with your main cPanel MySQL DB credentials.
// --------------------------------------------------------------------------
$SECRET_API_KEY = "YOUR_SECRET_KEY";

$DB_HOST = "localhost";
$DB_NAME = "devznrepats_compliance_database";
$DB_USER = "devznrepats_compliance_database_user";
$DB_PASS = "cd8jtxl3.JTi";

// File fallback path (showcase-specific)
$dataDir  = __DIR__ . '/data';
$dataFile = $dataDir . '/showcase-content.json';

// Helper: Establish PDO MySQL Connection & Auto-Create Tables with Initial Seeding
function getPdoConnection($host, $name, $user, $pass) {
    if (empty($host) || empty($name) || empty($user) || $host === "YOUR_DB_HOST" || $name === "YOUR_SHOWCASE_DB_NAME") return null;
    try {
        $pdo = new PDO("mysql:host={$host};dbname={$name};charset=utf8mb4", $user, $pass, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
        ]);

        // Use Laravel backend sections table structure
        // The table already exists in the Laravel backend database

        // Auto-create site_settings table
        $pdo->exec("CREATE TABLE IF NOT EXISTS `site_settings` (
            `setting_key` VARCHAR(100) PRIMARY KEY,
            `setting_value` TEXT NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

        // ── LARAVEL BACKEND INTEGRATION ─────────────────────────────────────────
        // Fetch content from Laravel backend's sections table where advisor_id IS NULL
        // This connects to the same database used by the content-flow-backend
        // ─────────────────────────────────────────────────────────────────────

        return $pdo;
    } catch (Exception $e) {
        return null;
    }
}

function decodeDummyContent($raw) {
    if (!$raw) return [];
    if (is_array($raw)) return $raw;
    $decoded = json_decode($raw, true);
    return is_array($decoded) ? $decoded : [];
}

function loadSeedDummyContent($slug) {
    $path = __DIR__ . '/data/' . preg_replace('/[^a-z0-9_-]/i', '', $slug) . '-dummy-content.json';
    if (!file_exists($path)) {
        $path = __DIR__ . '/data/template4-dummy-content.json';
    }
    if (!file_exists($path)) return [];
    return json_decode(file_get_contents($path), true) ?: [];
}

function dummyToSections($content) {
    $sections = [];
    $list = [];
    foreach ($content as $name => $secContent) {
        $cnt = is_string($secContent) ? (json_decode($secContent, true) ?: $secContent) : $secContent;
        $sections[$name] = $cnt;
        $list[] = ['name' => $name, 'content' => $cnt];
    }
    return [$sections, $list];
}

function defaultWhatWeDoBoxes() {
    return [
        [
            'image_url'   => 'intime-12',
            'heading'     => 'Business & Strategy',
            'text'        => "If you're looking for car insurance, we will help you to find the coverage that budget friendly.",
            'button_text' => 'Read more',
            'button_url'  => '#services',
        ],
        [
            'image_url'   => 'intime-06',
            'heading'     => 'Business Planner',
            'text'        => "If you're looking for car insurance, we will help you to find the coverage that budget friendly.",
            'button_text' => 'Read more',
            'button_url'  => '#services',
        ],
        [
            'image_url'   => 'intime-15',
            'heading'     => 'Business Intelligence',
            'text'        => "If you're looking for car insurance, we will help you to find the coverage that budget friendly.",
            'button_text' => 'Read more',
            'button_url'  => '#services',
        ],
    ];
}

function normalizeWhatWeDoContent($content) {
    if (!is_array($content)) $content = [];
    $defaults = defaultWhatWeDoBoxes();
    $legacy = isset($content['eyebrow'], $content['subheading']) && !isset($content['text']) && !isset($content['boxes']);
    $list = [];
    if (isset($content['boxes']) && is_array($content['boxes']) && count($content['boxes'])) {
        $list = $content['boxes'];
    } elseif (isset($content['items']) && is_array($content['items']) && count($content['items'])) {
        $list = $content['items'];
    }
    $boxes = [];
    for ($i = 0; $i < 3; $i++) {
        $item = $list[$i] ?? [];
        $fallback = $defaults[$i];
        $boxes[] = [
            'image_url'   => $item['image_url'] ?? $item['img'] ?? $item['image'] ?? $fallback['image_url'],
            'heading'     => $item['heading'] ?? $item['title'] ?? $fallback['heading'],
            'text'        => $item['text'] ?? $item['desc'] ?? $fallback['text'],
            'button_text' => $item['button_text'] ?? $item['read_more'] ?? $fallback['button_text'],
            'button_url'  => $item['button_url'] ?? $item['url'] ?? $item['link'] ?? $fallback['button_url'],
        ];
    }
    return [
        'subheading' => $legacy ? $content['eyebrow'] : ($content['subheading'] ?? $content['eyebrow'] ?? 'WHAT WE DO'),
        'heading'    => $content['heading'] ?? 'We are the best agency to improve your deals.',
        'text'       => $legacy ? $content['subheading'] : ($content['text'] ?? 'Improve efficiency, provide a better customer experience with modern technology services available around the world. Our skilled staff, combined with decades of experience.'),
        'boxes'      => $boxes,
    ];
}

function ensureWhatWeDoSection(&$content) {
    $source = $content['What we do'] ?? $content['Features Carousel'] ?? null;
    if ($source === null) return false;
    $normalized = normalizeWhatWeDoContent(is_array($source) ? $source : []);
    $changed = !isset($content['What we do'])
        || isset($content['Features Carousel'])
        || json_encode($content['What we do']) !== json_encode($normalized);
    $content['What we do'] = $normalized;
    unset($content['Features Carousel']);
    return $changed;
}

function defaultAboutGauges() {
    return [
        ['value' => '50%', 'label' => 'Business strategy growth'],
        ['value' => '75%', 'label' => 'Finance valuable ideas'],
    ];
}

function pickAboutText($content, $keys, $fallback = '') {
    foreach ($keys as $key) {
        if (!array_key_exists($key, $content)) continue;
        $value = $content[$key];
        if ($value === null || $value === '') continue;
        if (is_array($value) || is_object($value)) continue;
        return $value;
    }
    return $fallback;
}

function normalizeAboutContent($content) {
    if (!is_array($content)) $content = [];
    $defaults = defaultAboutGauges();
    $list = [];
    if (isset($content['gauges']) && is_array($content['gauges']) && count($content['gauges'])) {
        $list = $content['gauges'];
    } elseif (isset($content['stats']) && is_array($content['stats']) && count($content['stats'])) {
        $list = $content['stats'];
    }
    $gauges = [];
    for ($i = 0; $i < 2; $i++) {
        $item = is_array($list[$i] ?? null) ? $list[$i] : [];
        $n = $i + 1;
        $fallback = $defaults[$i];
        $gauges[] = [
            'value' => $content["percent_{$n}"] ?? $content["percentage_{$n}"] ?? $item['value'] ?? $item['percentage'] ?? $item['pct'] ?? $fallback['value'],
            'label' => $content["percent_{$n}_text"] ?? $content["percentage_{$n}_text"] ?? $item['label'] ?? $item['text'] ?? $item['heading'] ?? $fallback['label'],
        ];
    }
    $image = $content['image_preview'] ?? $content['image_url'] ?? $content['image'] ?? $content['img'] ?? 'intime-04.jpg';
    if (is_array($image)) {
        $image = $image['url'] ?? $image['path'] ?? $image['relative_url'] ?? $image['src'] ?? 'intime-04.jpg';
    }
    return [
        'eyebrow'          => $content['eyebrow'] ?? 'ABOUT US',
        'heading'          => $content['heading'] ?? 'Why will you choose our?',
        'subheading'       => $content['subheading'] ?? 'Our agency can only be as strong as our people & because of this, our team have designed game changing products.',
        'text'             => $content['text'] ?? "Intime is a design studio founded in London. Nowadays, we've grown and expanded our services, and have become a multinational firm, offering a variety of services and solutions Worldwide.",
        'image_url'        => $image,
        'experience_years' => pickAboutText($content, ['experience_years', 'red_box_number', 'red_box', 'years'], '10+'),
        'experience_label' => pickAboutText($content, ['experience_label', 'red_box_text', 'red_box_label'], 'Years of Experience'),
        'gauges'           => $gauges,
        'percent_1'        => $gauges[0]['value'],
        'percent_1_text'   => $gauges[0]['label'],
        'percent_2'        => $gauges[1]['value'],
        'percent_2_text'   => $gauges[1]['label'],
    ];
}

function ensureAboutSection(&$content) {
    $source = $content['About Section'] ?? null;
    if ($source === null) return false;
    $normalized = normalizeAboutContent(is_array($source) ? $source : []);
    $changed = json_encode($content['About Section']) !== json_encode($normalized);
    $content['About Section'] = $normalized;
    return $changed;
}

function defaultCompanyHistoryYears() {
    return [
        ['year' => '2010', 'red_text' => '2010 Milestone', 'grey_text' => 'Company Founded', 'heading' => 'Started Business', 'image_url' => 'intime-06.jpg', 'text' => "We partner with you to enable your technology so you focus on your organization's mission, leveraging our top-tier talent."],
        ['year' => '2012', 'red_text' => '2012 Milestone', 'grey_text' => '10+ Key Partners', 'heading' => 'Resilience & Expansion', 'image_url' => 'intime-07.jpg', 'text' => 'A dedicated People Ops leader committed to the growth and continuous development of leaders across operations.'],
        ['year' => '2016', 'red_text' => '2016 Milestone', 'grey_text' => '24/7 Support Launched', 'heading' => 'Crisis & Opportunity', 'image_url' => 'intime-09.jpg', 'text' => 'Our support works around the clock to ensure your business operations are secure, resilient, and monitored safely.'],
        ['year' => '2017', 'red_text' => '2017 Milestone', 'grey_text' => '50+ Nationwide Branches', 'heading' => '50+ Branches Milestone', 'image_url' => 'intime-01.jpg', 'text' => 'We cross industries and provide services to almost every business either as a co-managed or supplemental asset.'],
        ['year' => '2019', 'red_text' => '2019 Milestone', 'grey_text' => 'Global Market Entry', 'heading' => '100+ Global Branches', 'image_url' => 'intime-04.jpg', 'text' => 'Providing consulting expertise on vendor technology, IT budget strategy, and multi-cloud enterprise security.'],
        ['year' => '2021', 'red_text' => '2021 Milestone', 'grey_text' => 'Top Enterprise Award', 'heading' => 'Industry Excellence Award', 'image_url' => 'intime-10.jpg', 'text' => 'Our team is held to the highest level of accountability to ensure exceptional satisfaction and proven results.'],
    ];
}

function pickHistoryImage($item, $fallback) {
    $image = $item['image_preview'] ?? $item['image_url'] ?? $item['image'] ?? $item['img'] ?? $fallback;
    if (is_array($image)) {
        $image = $image['url'] ?? $image['path'] ?? $image['relative_url'] ?? $image['src'] ?? $fallback;
    }
    return $image;
}

function normalizeCompanyHistoryContent($content) {
    if (!is_array($content)) $content = [];
    $defaults = defaultCompanyHistoryYears();
    $legacy = isset($content['eyebrow'], $content['subheading']) && !isset($content['text']) && !isset($content['years']);
    $list = [];
    if (isset($content['years']) && is_array($content['years']) && count($content['years'])) {
        $list = $content['years'];
    } elseif (isset($content['items']) && is_array($content['items']) && count($content['items'])) {
        $list = $content['items'];
    } elseif (isset($content['milestones']) && is_array($content['milestones']) && count($content['milestones'])) {
        $list = $content['milestones'];
    }
    $years = [];
    for ($i = 0; $i < 6; $i++) {
        $item = is_array($list[$i] ?? null) ? $list[$i] : [];
        $n = $i + 1;
        $fallback = $defaults[$i];
        $redText = $content["year_{$n}_red_text"] ?? $item['red_text'] ?? $item['milestone'] ?? $item['badge'] ?? (isset($item['year']) ? $item['year'] . ' Milestone' : $fallback['red_text']);
        $year = $content["year_{$n}"] ?? $item['year'] ?? $fallback['year'];
        $years[] = [
            'year'      => $year,
            'red_text'  => $redText,
            'grey_text' => $content["year_{$n}_grey_text"] ?? $item['grey_text'] ?? $item['highlight'] ?? $item['caption'] ?? $fallback['grey_text'],
            'heading'   => $content["year_{$n}_heading"] ?? $item['heading'] ?? $item['title'] ?? $fallback['heading'],
            'image_url' => pickHistoryImage(array_merge($item, [
                'image_preview' => $content["year_{$n}_image_preview"] ?? ($item['image_preview'] ?? null),
                'image_url'     => $content["year_{$n}_image"] ?? $content["year_{$n}_image_url"] ?? ($item['image_url'] ?? null),
            ]), $fallback['image_url']),
            'text'      => $content["year_{$n}_text"] ?? $item['text'] ?? $item['desc'] ?? $item['description'] ?? $fallback['text'],
        ];
    }
    return [
        'subheading' => $legacy ? ($content['eyebrow'] ?? 'OUR JOURNEY') : ($content['subheading'] ?? $content['eyebrow'] ?? 'OUR JOURNEY'),
        'heading'    => $content['heading'] ?? 'Our Company History',
        'text'       => $legacy ? ($content['subheading'] ?? '') : ($content['text'] ?? 'A decade of growth, innovation, and unwavering commitment to client success.'),
        'years'      => $years,
    ];
}

function ensureCompanyHistorySection(&$content) {
    $source = $content['Company History'] ?? null;
    if ($source === null) return false;
    $normalized = normalizeCompanyHistoryContent(is_array($source) ? $source : []);
    $changed = json_encode($content['Company History']) !== json_encode($normalized);
    $content['Company History'] = $normalized;
    return $changed;
}

function defaultFeaturedServiceBoxes() {
    return [
        ['icon' => 'chart-pie', 'heading' => 'Strategy & Planning', 'text' => 'Collaborate Consulting exists to find the place where being seeming disparate interests meet.', 'button_text' => 'Read more', 'button_url' => '#service-strategy-planning'],
        ['icon' => 'tasks', 'heading' => 'Program Manager', 'text' => 'Collaborate Consulting exists to find the place where being seeming disparate interests meet.', 'button_text' => 'Read more', 'button_url' => '#service-program-manager'],
        ['icon' => 'landmark', 'heading' => 'Tax Management', 'text' => 'Collaborate Consulting exists to find the place where being seeming disparate interests meet.', 'button_text' => 'Read more', 'button_url' => '#service-tax-management'],
        ['icon' => 'coins', 'heading' => 'Investment Policy', 'text' => 'Collaborate Consulting exists to find the place where being seeming disparate interests meet.', 'button_text' => 'Read more', 'button_url' => '#service-investment-policy'],
        ['icon' => 'holding', 'heading' => 'Financial Advices', 'text' => 'Collaborate Consulting exists to find the place where being seeming disparate interests meet.', 'button_text' => 'Read more', 'button_url' => '#service-financial-advices'],
        ['icon' => 'seedling', 'heading' => 'Business Growth Plan', 'text' => 'Collaborate Consulting exists to find the place where being seeming disparate interests meet.', 'button_text' => 'Read more', 'button_url' => '#service-business-growth-plan'],
    ];
}

function normalizeFeaturedServicesContent($content) {
    if (!is_array($content)) $content = [];
    $defaults = defaultFeaturedServiceBoxes();
    $list = [];
    if (isset($content['boxes']) && is_array($content['boxes']) && count($content['boxes'])) {
        $list = $content['boxes'];
    } elseif (isset($content['items']) && is_array($content['items']) && count($content['items'])) {
        $list = $content['items'];
    }
    $boxes = [];
    for ($i = 0; $i < 6; $i++) {
        $item = is_array($list[$i] ?? null) ? $list[$i] : [];
        $fallback = $defaults[$i];
        $boxes[] = [
            'icon'        => $item['icon'] ?? $fallback['icon'],
            'heading'     => $item['heading'] ?? $item['title'] ?? $fallback['heading'],
            'text'        => $item['text'] ?? $item['desc'] ?? $fallback['text'],
            'button_text' => $item['button_text'] ?? $item['read_more'] ?? $fallback['button_text'],
            'button_url'  => $item['button_url'] ?? $item['url'] ?? $item['link'] ?? (isset($item['slug']) ? '#service-' . $item['slug'] : $fallback['button_url']),
        ];
    }
    return [
        'subheading' => $content['subheading'] ?? 'FEATURED SERVICES',
        'heading'    => $content['heading'] ?? 'We help to get Solutions!',
        'text'       => $content['text'] ?? 'Provide users with appropriate view and access permissions to requests, problems, changes, contracts, assets, solutions',
        'boxes'      => $boxes,
    ];
}

function ensureFeaturedServicesSection(&$content) {
    $source = $content['Featured Services'] ?? null;
    if ($source === null) return false;
    $normalized = normalizeFeaturedServicesContent(is_array($source) ? $source : []);
    $changed = json_encode($content['Featured Services']) !== json_encode($normalized);
    $content['Featured Services'] = $normalized;
    return $changed;
}

function parseProgressPct($value, $fallback) {
    if (is_numeric($value)) return max(0, min(100, (int)$value));
    if (is_string($value) && preg_match('/(\d+)/', $value, $m)) {
        return max(0, min(100, (int)$m[1]));
    }
    return $fallback;
}

function defaultAnnualProgressionBars() {
    return [
        ['label' => 'Business growth', 'year' => '2018', 'pct' => 70],
        ['label' => 'Investment growth', 'year' => '2019', 'pct' => 80],
        ['label' => 'Financial growth', 'year' => '2020', 'pct' => 90],
    ];
}

function defaultAnnualProgressionHighlights() {
    return [
        ['icon' => 'shield-alt', 'heading' => 'Risk Free', 'text' => 'We offer risk free business for tension free life.'],
        ['icon' => 'chart-line', 'heading' => 'Business Growth', 'text' => 'We ensure the business growth without conditions.'],
    ];
}

function normalizeAnnualProgressionContent($content) {
    if (!is_array($content)) $content = [];
    $legacy = isset($content['eyebrow'], $content['subheading']) && !isset($content['text']);
    $barDefaults = defaultAnnualProgressionBars();
    $highlightDefaults = defaultAnnualProgressionHighlights();
    $barList = [];
    if (isset($content['bars']) && is_array($content['bars']) && count($content['bars'])) {
        $barList = $content['bars'];
    } elseif (isset($content['progress']) && is_array($content['progress']) && count($content['progress'])) {
        $barList = $content['progress'];
    }
    $highlightList = [];
    if (isset($content['highlights']) && is_array($content['highlights']) && count($content['highlights'])) {
        $highlightList = $content['highlights'];
    } elseif (isset($content['features']) && is_array($content['features']) && count($content['features'])) {
        $highlightList = $content['features'];
    }
    $bars = [];
    for ($i = 0; $i < 3; $i++) {
        $item = is_array($barList[$i] ?? null) ? $barList[$i] : [];
        $fallback = $barDefaults[$i];
        $bars[] = [
            'label' => $item['label'] ?? $item['heading'] ?? $item['title'] ?? $fallback['label'],
            'year'  => $item['year'] ?? $fallback['year'],
            'pct'   => parseProgressPct($item['pct'] ?? $item['percent'] ?? $item['value'] ?? $item['percentage'] ?? null, $fallback['pct']),
        ];
    }
    $highlights = [];
    for ($i = 0; $i < 2; $i++) {
        $item = is_array($highlightList[$i] ?? null) ? $highlightList[$i] : [];
        $fallback = $highlightDefaults[$i];
        $highlights[] = [
            'icon'    => $item['icon'] ?? $fallback['icon'],
            'heading' => $item['heading'] ?? $item['title'] ?? $fallback['heading'],
            'text'    => $item['text'] ?? $item['desc'] ?? $item['description'] ?? $fallback['text'],
        ];
    }
    return [
        'subheading' => $legacy ? ($content['eyebrow'] ?? 'ANNUAL PROGRESSION') : ($content['subheading'] ?? $content['eyebrow'] ?? 'ANNUAL PROGRESSION'),
        'heading'    => $content['heading'] ?? 'Our Business Growth is Really Incredible!',
        'text'       => $legacy ? ($content['subheading'] ?? '') : ($content['text'] ?? 'We love what we do and we do it with passion. We value the experimentation, the reformation of the message, and the smart incentives.'),
        'bars'       => $bars,
        'highlights' => $highlights,
    ];
}

function ensureAnnualProgressionSection(&$content) {
    $source = $content['Annual Progression'] ?? null;
    if ($source === null) return false;
    $normalized = normalizeAnnualProgressionContent(is_array($source) ? $source : []);
    $changed = json_encode($content['Annual Progression']) !== json_encode($normalized);
    $content['Annual Progression'] = $normalized;
    return $changed;
}

function defaultPortfolioItems() {
    return [
        ['heading' => 'Market Expansion', 'category' => 'Business Strategy', 'image_url' => 'intime-12.jpg', 'button_text' => 'Read more', 'button_url' => '#portfolio'],
        ['heading' => 'Business Growth', 'category' => 'Investment', 'image_url' => 'intime-11.jpg', 'button_text' => 'Read more', 'button_url' => '#portfolio'],
        ['heading' => 'Tax Management', 'category' => 'Tax Consulting', 'image_url' => 'intime-08.jpg', 'button_text' => 'Read more', 'button_url' => '#portfolio'],
        ['heading' => 'Investment Policy', 'category' => 'Business Strategy', 'image_url' => 'intime-10.jpg', 'button_text' => 'Read more', 'button_url' => '#portfolio'],
        ['heading' => 'Manage Investment', 'category' => 'Investment', 'image_url' => 'intime-04.jpg', 'button_text' => 'Read more', 'button_url' => '#portfolio'],
        ['heading' => 'Financial Advices', 'category' => 'Tax Consulting', 'image_url' => 'intime-01.jpg', 'button_text' => 'Read more', 'button_url' => '#portfolio'],
    ];
}

function pickPortfolioImage($item, $fallback) {
    $image = $item['image_preview'] ?? $item['image_url'] ?? $item['image'] ?? $item['img'] ?? $fallback;
    if (is_array($image)) {
        $image = $image['url'] ?? $image['path'] ?? $image['relative_url'] ?? $image['src'] ?? $fallback;
    }
    return $image;
}

function normalizePortfolioContent($content) {
    if (!is_array($content)) $content = [];
    $legacy = isset($content['eyebrow'], $content['subheading']) && !isset($content['items']);
    $defaults = defaultPortfolioItems();
    $list = [];
    if (isset($content['items']) && is_array($content['items']) && count($content['items'])) {
        $list = $content['items'];
    } elseif (isset($content['projects']) && is_array($content['projects']) && count($content['projects'])) {
        $list = $content['projects'];
    } elseif (isset($content['boxes']) && is_array($content['boxes']) && count($content['boxes'])) {
        $list = $content['boxes'];
    }
    $items = [];
    for ($i = 0; $i < 6; $i++) {
        $item = is_array($list[$i] ?? null) ? $list[$i] : [];
        $fallback = $defaults[$i];
        $items[] = [
            'heading'     => $item['heading'] ?? $item['title'] ?? $fallback['heading'],
            'category'    => $item['category'] ?? $item['cat'] ?? $item['caption'] ?? $fallback['category'],
            'image_url'   => pickPortfolioImage($item, $fallback['image_url']),
            'button_text' => $item['button_text'] ?? $item['read_more'] ?? $fallback['button_text'],
            'button_url'  => $item['button_url'] ?? $item['url'] ?? $item['link'] ?? $fallback['button_url'],
        ];
    }
    return [
        'subheading' => $legacy ? ($content['eyebrow'] ?? 'COMPLETED PROJECTS') : ($content['subheading'] ?? $content['eyebrow'] ?? 'COMPLETED PROJECTS'),
        'heading'    => $content['heading'] ?? 'You can check our projects as inspirations.',
        'items'      => $items,
    ];
}

function ensurePortfolioSection(&$content) {
    $source = $content['Portfolio Section'] ?? null;
    if ($source === null) return false;
    $normalized = normalizePortfolioContent(is_array($source) ? $source : []);
    $changed = json_encode($content['Portfolio Section']) !== json_encode($normalized);
    $content['Portfolio Section'] = $normalized;
    return $changed;
}

function defaultBranchItems() {
    return [
        ['name' => 'Sydney (Head Office)', 'address' => '1 Epping Road, North Ryde, NSW 2113', 'phone' => '+61 2 9870 7689', 'email' => 'email@example.com'],
        ['name' => 'Brisbane', 'address' => 'Level 28, 400 George Street, Brisbane, QLD 4000', 'phone' => '+61 2 9870 7689', 'email' => 'email@example.com'],
        ['name' => 'Hobart', 'address' => '85 Macquarie Finoa Street, Hobart, TAS 7000', 'phone' => '+61 2 9870 7689', 'email' => 'email@example.com'],
        ['name' => 'Melbourne', 'address' => 'Level 5, 4 Freshwater Place, Southbank, VIC 3006', 'phone' => '+61 2 9870 7689', 'email' => 'email@example.com'],
    ];
}

function pickBranchMapImage($content) {
    $image = $content['image_preview'] ?? $content['map_image'] ?? $content['image_url'] ?? $content['image'] ?? $content['img'] ?? 'maps-point.png';
    if (is_array($image)) {
        $image = $image['url'] ?? $image['path'] ?? $image['relative_url'] ?? $image['src'] ?? 'maps-point.png';
    }
    return $image;
}

function normalizeBranchesContent($content) {
    if (!is_array($content)) $content = [];
    $legacyLabel = isset($content['eyebrow']);
    $defaults = defaultBranchItems();
    $list = [];
    if (isset($content['items']) && is_array($content['items']) && count($content['items'])) {
        $list = $content['items'];
    } elseif (isset($content['branches']) && is_array($content['branches']) && count($content['branches'])) {
        $list = $content['branches'];
    } elseif (isset($content['offices']) && is_array($content['offices']) && count($content['offices'])) {
        $list = $content['offices'];
    }
    $items = [];
    for ($i = 0; $i < 4; $i++) {
        $item = is_array($list[$i] ?? null) ? $list[$i] : [];
        $fallback = $defaults[$i];
        $items[] = [
            'name'    => $item['name'] ?? $item['heading'] ?? $item['title'] ?? $fallback['name'],
            'address' => $item['address'] ?? $fallback['address'],
            'phone'   => $item['phone'] ?? $item['tel'] ?? $fallback['phone'],
            'email'   => $item['email'] ?? $fallback['email'],
        ];
    }
    return [
        'subheading'     => $legacyLabel ? ($content['eyebrow'] ?? 'GET IN TOUCH') : ($content['subheading'] ?? 'GET IN TOUCH'),
        'heading'        => $content['heading'] ?? 'We are Connected All Time to Help Your Business!',
        'text'           => $legacyLabel ? ($content['text'] ?? $content['subheading'] ?? 'We understand the importance of approaching each work integrally and believe in the power of simple and easy communication.') : ($content['text'] ?? 'We understand the importance of approaching each work integrally and believe in the power of simple and easy communication.'),
        'form_heading'   => $content['form_heading'] ?? 'Book an appionment',
        'button_text'    => $content['button_text'] ?? 'SEND YOUR MESSAGE',
        'branches_label' => $content['branches_label'] ?? 'Main Branches:',
        'stat_value'     => $content['stat_value'] ?? '12+',
        'stat_label'     => $content['stat_label'] ?? 'Branches',
        'map_image'      => pickBranchMapImage($content),
        'items'          => $items,
    ];
}

function ensureBranchesSection(&$content) {
    $source = $content['Branches and Appointment'] ?? null;
    if ($source === null) return false;
    $normalized = normalizeBranchesContent(is_array($source) ? $source : []);
    $changed = json_encode($content['Branches and Appointment']) !== json_encode($normalized);
    $content['Branches and Appointment'] = $normalized;
    return $changed;
}

function defaultCounterStats() {
    return [
        ['icon' => 'users', 'value' => '2,800+', 'label' => 'Active Clients', 'sub' => 'Empowering businesses globally with passion and proven expertise.'],
        ['icon' => 'star', 'value' => '1,670+', 'label' => '5-Star Reviews', 'sub' => 'Top customer satisfaction and unmatched quality of service.'],
        ['icon' => 'user-tie', 'value' => '106+', 'label' => 'Team Members', 'sub' => 'Dedicated specialists and leaders driving continuous innovation.'],
        ['icon' => 'award', 'value' => '99.8%', 'label' => 'Success Rate', 'sub' => 'Consistently delivering top-tier performance and business growth.'],
    ];
}

function normalizeCounterStatsContent($content) {
    if (!is_array($content)) $content = [];
    $defaults = defaultCounterStats();
    $list = [];
    if (isset($content['stats']) && is_array($content['stats']) && count($content['stats'])) {
        $list = $content['stats'];
    } elseif (isset($content['items']) && is_array($content['items']) && count($content['items'])) {
        $list = $content['items'];
    }
    $stats = [];
    for ($i = 0; $i < 4; $i++) {
        $item = is_array($list[$i] ?? null) ? $list[$i] : [];
        $fallback = $defaults[$i];
        $n = $i + 1;
        $stats[] = [
            'icon'  => $item['icon'] ?? $content["stat_{$n}_icon"] ?? $fallback['icon'],
            'value' => $item['value'] ?? $item['number'] ?? $content["stat_{$n}_value"] ?? $fallback['value'],
            'label' => $item['label'] ?? $item['title'] ?? $item['heading'] ?? $content["stat_{$n}_label"] ?? $fallback['label'],
            'sub'   => $item['sub'] ?? $item['desc'] ?? $item['description'] ?? $item['text'] ?? $content["stat_{$n}_sub"] ?? $fallback['sub'],
        ];
    }
    return ['stats' => $stats];
}

function ensureCounterStatsSection(&$content) {
    $source = $content['Counter Stats'] ?? null;
    if ($source === null) return false;
    $normalized = normalizeCounterStatsContent(is_array($source) ? $source : []);
    $changed = json_encode($content['Counter Stats']) !== json_encode($normalized);
    $content['Counter Stats'] = $normalized;
    return $changed;
}

function defaultTestimonialItems() {
    return [
        ['quote' => 'Working with several word press themes and templates the last years, I only can say this is the best in every level. I use it for my company and the reviews that I have already are all excellent.', 'name' => 'Alina Lora', 'role' => 'Former Manager, Intime', 'image_url' => 'testimonial-01.jpg'],
        ['quote' => 'This is one of the BEST THEMES I have ever worked with. The extra bells and whistles added to it are amazing. Elementor features add extra flavor. The customer support is very responsive.', 'name' => 'Rohan Jho', 'role' => 'Former Manager, Intime', 'image_url' => 'testimonial-02.jpg'],
        ['quote' => 'Great theme, one of the best I have worked with in a while. Full featured and great support for the minor issues I had which were really my not being skilled/experienced enough.', 'name' => 'Donald Frew', 'role' => 'Former Manager, Intime', 'image_url' => 'testimonial-03.jpg'],
    ];
}

function pickTestimonialSideImage($content) {
    $image = $content['image_preview'] ?? $content['image_url'] ?? $content['image'] ?? $content['img'] ?? $content['side_image'] ?? 'intime-17.jpg';
    if (is_array($image)) {
        $image = $image['url'] ?? $image['path'] ?? $image['relative_url'] ?? $image['src'] ?? 'intime-17.jpg';
    }
    return $image;
}

function normalizeTestimonialsContent($content) {
    if (!is_array($content)) $content = [];
    $defaults = defaultTestimonialItems();
    $list = [];
    if (isset($content['items']) && is_array($content['items']) && count($content['items'])) {
        $list = $content['items'];
    } elseif (isset($content['testimonials']) && is_array($content['testimonials']) && count($content['testimonials'])) {
        $list = $content['testimonials'];
    }
    $legacyThin = isset($content['eyebrow']) && isset($content['heading']) && !count($list);
    $reviewsLabel = $content['reviews_label'] ?? $content['label'] ?? ($legacyThin && strlen($content['subheading'] ?? '') > 40 ? 'Clients Reviews:' : ($content['subheading'] ?? 'Clients Reviews:'));
    $items = [];
    for ($i = 0; $i < 3; $i++) {
        $item = is_array($list[$i] ?? null) ? $list[$i] : [];
        $fallback = $defaults[$i];
        $items[] = [
            'quote'     => $item['quote'] ?? $item['text'] ?? $item['review'] ?? $fallback['quote'],
            'name'      => $item['name'] ?? $item['title'] ?? $item['author'] ?? $fallback['name'],
            'role'      => $item['role'] ?? $item['position'] ?? $item['job'] ?? $item['desc'] ?? $fallback['role'],
            'image_url' => $item['image_url'] ?? $item['image'] ?? $item['img'] ?? $item['avatar'] ?? $fallback['image_url'],
        ];
    }
    return [
        'eyebrow'    => $content['eyebrow'] ?? $content['tagline'] ?? "CLIENT'S TESTIMONIALS",
        'heading'    => $content['heading'] ?? "We are Very Happy to Get Our Client's Reviews.",
        'subheading' => $reviewsLabel,
        'image_url'  => pickTestimonialSideImage($content),
        'items'      => $items,
    ];
}

function ensureTestimonialsSection(&$content) {
    $source = $content['Testimonials Carousel'] ?? null;
    if ($source === null) return false;
    $normalized = normalizeTestimonialsContent(is_array($source) ? $source : []);
    $changed = json_encode($content['Testimonials Carousel']) !== json_encode($normalized);
    $content['Testimonials Carousel'] = $normalized;
    return $changed;
}

function defaultLatestNewsItems() {
    return [
        ['date' => '10', 'month' => 'Nov, 20', 'author' => 'John Doe', 'cat' => 'Consulting', 'title' => 'We would love to share a similar experience', 'excerpt' => 'The theory was first published in 2008 a press released under the name of Cliff Arnall, who at the time was a tutor at the…', 'image_url' => 'intime-03.jpg', 'button_text' => 'Read more', 'button_url' => '#news'],
        ['date' => '06', 'month' => 'Nov, 20', 'author' => 'John Doe', 'cat' => 'HR Consulting', 'title' => 'We glad to discuss your organisation situation.', 'excerpt' => 'The theory was first published in 2008 a press released under the name of Cliff Arnall, who at the time was a tutor at the…', 'image_url' => 'intime-02.jpg', 'button_text' => 'Read more', 'button_url' => '#news'],
        ['date' => '20', 'month' => 'Oct, 20', 'author' => 'John Doe', 'cat' => 'Consulting', 'title' => 'In this context our main approach was to build.', 'excerpt' => 'The theory was first published in 2008 a press released under the name of Cliff Arnall, who at the time was a tutor at the…', 'image_url' => 'intime-05.jpg', 'button_text' => 'Read more', 'button_url' => '#news'],
    ];
}

function normalizeLatestNewsContent($content) {
    if (!is_array($content)) $content = [];
    $defaults = defaultLatestNewsItems();
    $list = [];
    if (isset($content['items']) && is_array($content['items']) && count($content['items'])) {
        $list = $content['items'];
    } elseif (isset($content['posts']) && is_array($content['posts']) && count($content['posts'])) {
        $list = $content['posts'];
    }
    $items = [];
    for ($i = 0; $i < 3; $i++) {
        $item = is_array($list[$i] ?? null) ? $list[$i] : [];
        $fallback = $defaults[$i];
        $items[] = [
            'date'        => $item['date'] ?? $item['day'] ?? $fallback['date'],
            'month'       => $item['month'] ?? $item['month_label'] ?? $fallback['month'],
            'author'      => $item['author'] ?? $item['by'] ?? $fallback['author'],
            'cat'         => $item['cat'] ?? $item['category'] ?? $item['tag'] ?? $fallback['cat'],
            'title'       => $item['title'] ?? $item['heading'] ?? $fallback['title'],
            'excerpt'     => $item['excerpt'] ?? $item['text'] ?? $item['desc'] ?? $item['description'] ?? $fallback['excerpt'],
            'image_url'   => $item['image_url'] ?? $item['image'] ?? $item['img'] ?? $fallback['image_url'],
            'button_text' => $item['button_text'] ?? $item['read_more'] ?? $fallback['button_text'],
            'button_url'  => $item['button_url'] ?? $item['url'] ?? $item['link'] ?? $fallback['button_url'],
        ];
    }
    return [
        'eyebrow' => $content['eyebrow'] ?? $content['tagline'] ?? 'OUR LATEST NEWS',
        'heading' => $content['heading'] ?? 'Learn about our latest news from blog.',
        'items'   => $items,
    ];
}

function ensureLatestNewsSection(&$content) {
    $source = $content['Latest News'] ?? null;
    if ($source === null) return false;
    $normalized = normalizeLatestNewsContent(is_array($source) ? $source : []);
    $changed = json_encode($content['Latest News']) !== json_encode($normalized);
    $content['Latest News'] = $normalized;
    return $changed;
}

function defaultClientLogoItems() {
    return [
        ['name' => 'slack', 'image_url' => ''],
        ['name' => 'Google', 'image_url' => ''],
        ['name' => 'envato', 'image_url' => ''],
        ['name' => 'Sketch', 'image_url' => ''],
        ['name' => 'Figma', 'image_url' => ''],
    ];
}

function normalizeClientLogosContent($content) {
    if (!is_array($content)) $content = [];
    $defaults = defaultClientLogoItems();
    $list = [];
    if (isset($content['items']) && is_array($content['items']) && count($content['items'])) {
        $list = $content['items'];
    } elseif (isset($content['logos']) && is_array($content['logos']) && count($content['logos'])) {
        $list = $content['logos'];
    }
    $items = [];
    for ($i = 0; $i < 5; $i++) {
        $raw = $list[$i] ?? null;
        $item = is_string($raw) ? ['name' => $raw] : (is_array($raw) ? $raw : []);
        $fallback = $defaults[$i];
        $items[] = [
            'name'      => $item['name'] ?? $item['label'] ?? $item['title'] ?? $item['text'] ?? $fallback['name'],
            'image_url' => $item['image_url'] ?? $item['image'] ?? $item['img'] ?? $item['logo'] ?? $fallback['image_url'],
        ];
    }
    return ['items' => $items];
}

function ensureClientLogosSection(&$content) {
    $source = $content['Client Logos'] ?? null;
    if ($source === null) return false;
    $normalized = normalizeClientLogosContent(is_array($source) ? $source : []);
    $changed = json_encode($content['Client Logos']) !== json_encode($normalized);
    $content['Client Logos'] = $normalized;
    return $changed;
}

function normalizeCtaBannerContent($content) {
    if (!is_array($content)) $content = [];
    return [
        'heading'     => $content['heading'] ?? $content['title'] ?? 'Looking for the Best Business Consulting?',
        'subheading'  => $content['subheading'] ?? $content['text'] ?? $content['desc'] ?? $content['description'] ?? 'As a web crawler expert, we will help to organize.',
        'button_text' => $content['button_text'] ?? $content['btn_text'] ?? $content['button'] ?? 'GET A QUOTE',
        'button_url'  => $content['button_url'] ?? $content['btn_url'] ?? $content['url'] ?? $content['link'] ?? '#appointment',
    ];
}

function ensureCtaBannerSection(&$content) {
    $source = $content['CTA Banner'] ?? null;
    if ($source === null) return false;
    $normalized = normalizeCtaBannerContent(is_array($source) ? $source : []);
    $changed = json_encode($content['CTA Banner']) !== json_encode($normalized);
    $content['CTA Banner'] = $normalized;
    return $changed;
}

$pdo = getPdoConnection($DB_HOST, $DB_NAME, $DB_USER, $DB_PASS);

// --------------------------------------------------------------------------
// POST REQUEST: SYNC CONTENT FROM LARAVEL BACKEND
// --------------------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $rawInput = file_get_contents('php://input');
    $input = json_decode($rawInput, true);

    if (!$input) {
        http_response_code(400);
        echo json_encode(['status' => 'error', 'message' => 'Invalid JSON payload']);
        exit;
    }

    // Validate Secret API Key (if configured)
    if (!empty($SECRET_API_KEY) && $SECRET_API_KEY !== "YOUR_SECRET_KEY") {
        $providedKey = $input['api_key'] 
            ?? $_SERVER['HTTP_X_API_KEY'] 
            ?? null;

        if (!$providedKey && isset($_SERVER['HTTP_AUTHORIZATION'])) {
            if (preg_match('/Bearer\s+(.*)$/i', $_SERVER['HTTP_AUTHORIZATION'], $matches)) {
                $providedKey = $matches[1];
            }
        }

        if ($providedKey !== $SECRET_API_KEY) {
            http_response_code(401);
            echo json_encode([
                'status'  => 'error', 
                'message' => 'Unauthorized: Invalid API Key. Provided key does not match cPanel secret key.'
            ]);
            exit;
        }
    }

    $updatedSectionsCount = 0;

    // 1. Sync to MySQL Database Tables if PDO connection active
    if ($pdo) {
        if (!empty($input['primary_color'])) {
            $stmt = $pdo->prepare("REPLACE INTO site_settings (setting_key, setting_value) VALUES ('primary_color', ?)");
            $stmt->execute([$input['primary_color']]);
        }
        if (!empty($input['secondary_color'])) {
            $stmt = $pdo->prepare("REPLACE INTO site_settings (setting_key, setting_value) VALUES ('secondary_color', ?)");
            $stmt->execute([$input['secondary_color']]);
        }
        if (!empty($input['logo_url'])) {
            $stmt = $pdo->prepare("REPLACE INTO site_settings (setting_key, setting_value) VALUES ('logo_url', ?)");
            $stmt->execute([$input['logo_url']]);
        }

        if (isset($input['sections']) && is_array($input['sections'])) {
            $stmt = $pdo->prepare("INSERT INTO sections (section_name, content) VALUES (?, ?) ON DUPLICATE KEY UPDATE content = VALUES(content)");
            foreach ($input['sections'] as $newSec) {
                $name    = $newSec['name'] ?? null;
                $content = $newSec['content'] ?? null;
                if ($name && $content) {
                    $jsonStr = is_string($content) ? $content : json_encode($content, JSON_UNESCAPED_UNICODE);
                    $stmt->execute([$name, $jsonStr]);
                    $updatedSectionsCount++;
                }
            }
        }
    }

    // 2. Also Sync to JSON File for fallback reliability
    if (!file_exists($dataDir)) mkdir($dataDir, 0755, true);
    $existing = file_exists($dataFile) ? (json_decode(file_get_contents($dataFile), true) ?: []) : [];

    if (!empty($input['primary_color']))   $existing['primary_color']   = $input['primary_color'];
    if (!empty($input['secondary_color'])) $existing['secondary_color'] = $input['secondary_color'];
    if (!empty($input['logo_url']))        $existing['logo_url']        = $input['logo_url'];

    if (!isset($existing['sections']) || !is_array($existing['sections'])) {
        $existing['sections'] = [];
    }

    if (isset($input['sections']) && is_array($input['sections'])) {
        foreach ($input['sections'] as $newSec) {
            $name    = $newSec['name'] ?? null;
            $content = $newSec['content'] ?? null;
            if ($name && $content) {
                $parsedContent = is_string($content) ? (json_decode($content, true) ?: $content) : $content;
                $existing['sections'][$name] = $parsedContent;
                if (!$pdo) $updatedSectionsCount++;
            }
        }
    }

    file_put_contents($dataFile, json_encode($existing, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

    http_response_code(200);
    echo json_encode([
        'status'        => 'success',
        'message'       => 'Showcase content successfully updated on cPanel MySQL DB & JSON file!',
        'db_active'     => (bool)$pdo,
        'updated_count' => $updatedSectionsCount,
        'data'          => $existing
    ]);
    exit;
}

// --------------------------------------------------------------------------
// GET REQUEST: SERVE LIVE SHOWCASE CONTENT FOR REACT TEMPLATE
// Called with ?showcase=1&slug=template4 from the React frontend.
// Primary source: Laravel `templates.dummy_content` (edit this JSON in MySQL).
// Fallbacks: local `sections` table, then JSON file.
// --------------------------------------------------------------------------
$slug = $_GET['slug'] ?? 'template4';
$slug = preg_replace('/[^a-z0-9_-]/i', '', $slug) ?: 'template4';

$result = [
    'primary_color'   => '#0B1B3D',
    'secondary_color' => '#C8102E',
    'logo_url'        => '/assets/intime/logo-dark.png',
    'sections'        => [],
    'sections_list'   => [],
    'content_source'  => 'empty'
];

if ($pdo) {
    try {
        $settingsStmt = $pdo->query("SELECT setting_key, setting_value FROM site_settings");
        while ($row = $settingsStmt->fetch()) {
            $result[$row['setting_key']] = $row['setting_value'];
        }
    } catch (Exception $e) {
        // site_settings is optional
    }

    // 1. Primary: templates.dummy_content (central Laravel catalog)
    try {
        $tplStmt = $pdo->prepare("SELECT dummy_content FROM templates WHERE slug = ? LIMIT 1");
        $tplStmt->execute([$slug]);
        $tplRow = $tplStmt->fetch();
        if ($tplRow) {
            $content = decodeDummyContent($tplRow['dummy_content']);
            $seed = loadSeedDummyContent($slug);
            $heroHasLists = isset($content['Hero Slider']['slides']) && is_array($content['Hero Slider']['slides']);
            
            // Force update hero slider to new slides structure
            if (isset($content['Hero Slider']) && !$heroHasLists) {
                $oldHero = $content['Hero Slider'];
                if (is_array($oldHero) && !isset($oldHero['slides']) && (isset($oldHero['heading']) || isset($oldHero['eyebrow']))) {
                    // Migrate old structure to new slides structure
                    $newHeroSlider = [
                        'slides' => [
                            [
                                'id' => 1,
                                'bg' => $oldHero['image_url'] ?? 'https://images.unsplash.com/photo-1551836022-d5d88e9218df?w=800&auto=format&fit=crop&q=80',
                                'eyebrow' => $oldHero['eyebrow'] ?? 'FINANCIAL CENTRE & WEALTH MANAGEMENT',
                                'heading' => $oldHero['heading'] ?? 'Strategic Advisory for Long-Term Growth',
                                'subheading' => $oldHero['subheading'] ?? 'Customized financial planning, investment strategies, and fiduciary advice for leaders and families.',
                                'text' => $oldHero['text'] ?? 'We partner with you to navigate complex economic landscapes with confidence.',
                                'button_text' => $oldHero['button_text'] ?? 'GET IN TOUCH',
                                'button_url' => $oldHero['button_url'] ?? '#appointment',
                                'youtube_url' => 'https://www.youtube.com/watch?v=SF4aHwxHtZ0'
                            ],
                            [
                                'id' => 2,
                                'bg' => 'https://images.unsplash.com/photo-1560472354-b33ff0c44a43?w=800&auto=format&fit=crop&q=80',
                                'eyebrow' => 'FINANCIAL CENTRE & WEALTH MANAGEMENT',
                                'heading' => 'We do the best thing for market funding',
                                'subheading' => 'High-impact financial solutions: institutional-grade portfolio management and risk mitigation strategies.',
                                'text' => 'Our team delivers proven results through disciplined investment approaches.',
                                'button_text' => 'GET IN TOUCH',
                                'button_url' => '#appointment',
                                'youtube_url' => 'https://www.youtube.com/watch?v=SF4aHwxHtZ0'
                            ],
                            [
                                'id' => 3,
                                'bg' => 'https://images.unsplash.com/photo-1486406146926-c627a92ad1ab?w=800&auto=format&fit=crop&q=80',
                                'eyebrow' => 'FINANCIAL CENTRE & WEALTH MANAGEMENT',
                                'heading' => 'We have to do business for your satisfaction',
                                'subheading' => 'Building lasting relationships through transparent communication and exceptional service.',
                                'text' => 'Your financial success is our primary mission and commitment.',
                                'button_text' => 'GET IN TOUCH',
                                'button_url' => '#appointment',
                                'youtube_url' => 'https://www.youtube.com/watch?v=SF4aHwxHtZ0'
                            ]
                        ]
                    ];
                    $content['Hero Slider'] = $newHeroSlider;
                    $upd = $pdo->prepare("UPDATE templates SET dummy_content = ? WHERE slug = ?");
                    $upd->execute([json_encode($content, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), $slug]);
                }
            }
            
            if ($seed && !$heroHasLists && !isset($content['Hero Slider']['slides'])) {
                $content = array_replace_recursive($seed, $content);
                $upd = $pdo->prepare("UPDATE templates SET dummy_content = ? WHERE slug = ?");
                $upd->execute([json_encode($content, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), $slug]);
            }
            if (ensureWhatWeDoSection($content) || ensureAboutSection($content) || ensureCompanyHistorySection($content) || ensureFeaturedServicesSection($content) || ensureAnnualProgressionSection($content) || ensurePortfolioSection($content) || ensureBranchesSection($content) || ensureCounterStatsSection($content) || ensureTestimonialsSection($content) || ensureLatestNewsSection($content) || ensureClientLogosSection($content) || ensureCtaBannerSection($content)) {
                $upd = $pdo->prepare("UPDATE templates SET dummy_content = ? WHERE slug = ?");
                $upd->execute([json_encode($content, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), $slug]);
            }
            if (!empty($content)) {
                [$result['sections'], $result['sections_list']] = dummyToSections($content);
                $result['content_source'] = 'templates.dummy_content';
            }
        }
    } catch (Exception $e) {
        // templates table may not exist on older DBs
    }
}

// 2. Fetch from Laravel backend sections table (advisor_id IS NULL for showcase)
if (empty($result['sections']) && $pdo) {
    try {
        // Get the home page ID
        $pageStmt = $pdo->prepare("SELECT id FROM pages WHERE slug = 'home' LIMIT 1");
        $pageStmt->execute();
        $pageRow = $pageStmt->fetch();
        
        if ($pageRow) {
            $pageId = $pageRow['id'];
            // Fetch sections where advisor_id IS NULL (showcase content)
            $secStmt = $pdo->prepare("SELECT name, content FROM sections WHERE page_id = ? AND advisor_id IS NULL ORDER BY id ASC");
            $secStmt->execute([$pageId]);
            
            while ($row = $secStmt->fetch()) {
                $name = $row['name'];
                $cnt  = json_decode($row['content'], true) ?: $row['content'];
                $result['sections'][$name] = $cnt;
                $result['sections_list'][] = ['name' => $name, 'content' => $cnt];
            }
            if (!empty($result['sections'])) {
                $result['content_source'] = 'laravel_backend_sections';
            }
        }
    } catch (Exception $e) {
        // Fallback to json if DB query fails
    }
}

// 3. Fallback to JSON File if MySQL was empty or disabled
if (empty($result['sections']) && file_exists($dataFile)) {
    $fileData = json_decode(file_get_contents($dataFile), true) ?: [];
    if (!empty($fileData['primary_color']))   $result['primary_color']   = $fileData['primary_color'];
    if (!empty($fileData['secondary_color'])) $result['secondary_color'] = $fileData['secondary_color'];
    if (!empty($fileData['logo_url']))        $result['logo_url']        = $fileData['logo_url'];

    if (isset($fileData['sections']) && is_array($fileData['sections'])) {
        $result['sections'] = $fileData['sections'];
        foreach ($fileData['sections'] as $secName => $secContent) {
            $result['sections_list'][] = [
                'name'    => $secName,
                'content' => is_string($secContent) ? json_decode($secContent, true) ?: $secContent : $secContent
            ];
        }
        $result['content_source'] = 'json_file';
    }
}

echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
exit;
