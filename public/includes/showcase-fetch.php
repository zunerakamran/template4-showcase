<?php
/**
 * Showcase-mode content fetchers (hub / Laravel database).
 * Used when DEPLOYMENT_MODE = 'showcase'.
 */

function decodeDummyContent($raw) {
    if (!$raw) return [];
    if (is_array($raw)) return $raw;
    $decoded = json_decode($raw, true);
    return is_array($decoded) ? $decoded : [];
}

function loadSeedDummyContent($slug) {
    $path = __DIR__ . '/../data/' . preg_replace('/[^a-z0-9_-]/i', '', $slug) . '-dummy-content.json';
    if (!file_exists($path)) {
        $path = __DIR__ . '/../data/template4-dummy-content.json';
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
        $list[] = [
            'name'         => $name,
            'section_key'  => canonicalSectionKey($name),
            'display_name' => $name,
            'is_visible'   => true,
            'content'      => $cnt,
        ];
    }
    return [$sections, $list];
}

function fetchShowcaseFromTemplates(PDO $pdo, $slug) {
    try {
        $tplStmt = $pdo->prepare('SELECT dummy_content FROM templates WHERE slug = ? LIMIT 1');
        $tplStmt->execute([$slug]);
        $tplRow = $tplStmt->fetch();
        if (!$tplRow) return null;

        // Prefer file seed over stale DB dummy_content so showcase matches cPanel defaults
        $content = decodeDummyContent($tplRow['dummy_content']);
        $seed = loadSeedDummyContent($slug);
        if ($seed && empty($content)) {
            $content = $seed;
        } elseif ($seed) {
            // File is source of truth for overlapping keys; DB only keeps extra keys
            $content = array_replace_recursive($content, $seed);
        }
        if (empty($content)) return null;

        [$sections, $list] = dummyToSections($content);
        return [
            'sections'        => $sections,
            'sections_list'   => $list,
            'content_source'  => 'templates.dummy_content',
        ];
    } catch (Exception $e) {
        return null;
    }
}

function fetchShowcaseFromLaravelSections(PDO $pdo) {
    try {
        $pageStmt = $pdo->prepare("SELECT id FROM pages WHERE slug = 'home' LIMIT 1");
        $pageStmt->execute();
        $pageRow = $pageStmt->fetch();
        if (!$pageRow) return null;

        $pageId = $pageRow['id'];
        $secStmt = $pdo->prepare(
            'SELECT name, content, display_name, is_visible, section_key
             FROM sections
             WHERE page_id = ? AND advisor_id IS NULL
             ORDER BY id ASC'
        );
        $secStmt->execute([$pageId]);

        $sections = [];
        $list = [];
        $seen = [];

        while ($row = $secStmt->fetch()) {
            $name = $row['name'];
            if (isset($seen[$name])) continue;
            $seen[$name] = true;

            $cnt = decodeDummyContent($row['content']);
            $visible = !isset($row['is_visible']) || !in_array($row['is_visible'], [0, '0', false], true);
            $key = !empty($row['section_key'])
                ? strtolower(preg_replace('/[^a-z0-9]/i', '', $row['section_key']))
                : canonicalSectionKey($name);

            $list[] = [
                'name'         => $name,
                'section_key'  => $key,
                'display_name' => $row['display_name'] ?: $name,
                'is_visible'   => $visible,
                'content'      => $visible ? $cnt : null,
            ];
            if ($visible) {
                $sections[$name] = $cnt;
            }
        }

        if (empty($sections) && empty($list)) return null;

        return [
            'sections'       => $sections,
            'sections_list'  => $list,
            'content_source' => 'laravel_backend_sections',
        ];
    } catch (Exception $e) {
        return null;
    }
}

function fetchShowcaseContent(PDO $pdo, $slug, $dataFile) {
    $fromTemplates = fetchShowcaseFromTemplates($pdo, $slug);
    if ($fromTemplates) return $fromTemplates;

    $fromSections = fetchShowcaseFromLaravelSections($pdo);
    if ($fromSections) return $fromSections;

    if (!file_exists($dataFile)) return null;

    $fileData = json_decode(file_get_contents($dataFile), true) ?: [];
    if (empty($fileData['sections']) || !is_array($fileData['sections'])) return null;

    $sections = [];
    $list = [];
    foreach ($fileData['sections'] as $secName => $secContent) {
        $cnt = is_string($secContent) ? (json_decode($secContent, true) ?: $secContent) : $secContent;
        $name = canonicalSectionName($secName);
        $sections[$name] = $cnt;
        $list[] = [
            'name'         => $name,
            'section_key'  => canonicalSectionKey($name),
            'display_name' => $name,
            'is_visible'   => true,
            'content'      => $cnt,
        ];
    }

    return [
        'sections'       => $sections,
        'sections_list'  => $list,
        'content_source' => 'json_file',
        'primary_color'  => $fileData['primary_color'] ?? null,
        'secondary_color'=> $fileData['secondary_color'] ?? null,
        'logo_url'       => $fileData['logo_url'] ?? null,
    ];
}
