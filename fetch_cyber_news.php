<?php
require_once __DIR__ . '/includes/auth.php';

$admin = require_admin($pdo);

/*
|--------------------------------------------------------------------------
| CISA KEV JSON Sources
|--------------------------------------------------------------------------
| Source 1: Official CISA GitHub mirror.
| Source 2: CISA direct JSON feed fallback.
|--------------------------------------------------------------------------
*/

$sources = [
    'https://raw.githubusercontent.com/cisagov/kev-data/develop/known_exploited_vulnerabilities.json',
    'https://www.cisa.gov/sites/default/files/feeds/known_exploited_vulnerabilities.json',
];

function fetch_remote_json(array $sources): string
{
    $lastError = '';

    foreach ($sources as $url) {
        try {
            if (function_exists('curl_init')) {
                $ch = curl_init($url);

                curl_setopt_array($ch, [
                    CURLOPT_RETURNTRANSFER => true,
                    CURLOPT_FOLLOWLOCATION => true,
                    CURLOPT_TIMEOUT => 25,
                    CURLOPT_SSL_VERIFYPEER => true,
                    CURLOPT_USERAGENT => 'Mozilla/5.0 CyberAware Learning Platform',
                    CURLOPT_HTTPHEADER => [
                        'Accept: application/json,text/plain,*/*',
                    ],
                ]);

                $response = curl_exec($ch);

                if ($response === false) {
                    $lastError = curl_error($ch);
                    curl_close($ch);
                    continue;
                }

                $statusCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
                curl_close($ch);

                if ($statusCode >= 200 && $statusCode < 300) {
                    return $response;
                }

                $lastError = "HTTP {$statusCode} from {$url}";
                continue;
            }

            $context = stream_context_create([
                'http' => [
                    'method' => 'GET',
                    'header' => "User-Agent: Mozilla/5.0 CyberAware Learning Platform\r\nAccept: application/json,text/plain,*/*\r\n",
                    'timeout' => 25,
                ],
            ]);

            $response = file_get_contents($url, false, $context);

            if ($response !== false) {
                return $response;
            }

            $lastError = "file_get_contents failed for {$url}";
        } catch (Throwable $error) {
            $lastError = $error->getMessage();
        }
    }

    throw new RuntimeException('Could not fetch CISA KEV data. Last error: ' . $lastError);
}

try {
    $jsonContent = fetch_remote_json($sources);
    $data = json_decode($jsonContent, true);

    if (!is_array($data) || empty($data['vulnerabilities'])) {
        throw new RuntimeException('Invalid CISA KEV JSON format.');
    }

    $items = array_slice($data['vulnerabilities'], 0, 20);

    $inserted = 0;
    $skipped = 0;

    $checkStatement = $pdo->prepare("
        SELECT id
        FROM articles
        WHERE title = :title
        AND source = :source
        LIMIT 1
    ");

    $insertStatement = $pdo->prepare("
        INSERT INTO articles
          (title, source, url, summary, published_at)
        VALUES
          (:title, :source, :url, :summary, :published_at)
    ");

    foreach ($items as $item) {
        $cveId = trim($item['cveID'] ?? '');
        $vendorProject = trim($item['vendorProject'] ?? '');
        $product = trim($item['product'] ?? '');
        $vulnerabilityName = trim($item['vulnerabilityName'] ?? '');
        $shortDescription = trim($item['shortDescription'] ?? '');
        $requiredAction = trim($item['requiredAction'] ?? '');
        $dateAdded = trim($item['dateAdded'] ?? '');
        $dueDate = trim($item['dueDate'] ?? '');
        $knownRansomwareUse = trim($item['knownRansomwareCampaignUse'] ?? 'Unknown');

        if ($cveId === '') {
            continue;
        }

        $title = $cveId . ' - ' . ($vulnerabilityName ?: 'Known Exploited Vulnerability');

        $summaryParts = [];

        if ($vendorProject || $product) {
            $summaryParts[] = 'Affected: ' . trim($vendorProject . ' ' . $product);
        }

        if ($shortDescription) {
            $summaryParts[] = $shortDescription;
        }

        if ($requiredAction) {
            $summaryParts[] = 'Required action: ' . $requiredAction;
        }

        if ($dueDate) {
            $summaryParts[] = 'Due date: ' . $dueDate;
        }

        $summaryParts[] = 'Known ransomware campaign use: ' . $knownRansomwareUse;

        $summary = implode("\n\n", $summaryParts);

        $publishedAt = null;

        if ($dateAdded !== '') {
            $timestamp = strtotime($dateAdded);

            if ($timestamp !== false) {
                $publishedAt = date('Y-m-d H:i:s', $timestamp);
            }
        }

        $source = 'CISA Known Exploited Vulnerabilities';

        $checkStatement->execute([
            'title' => $title,
            'source' => $source,
        ]);

        if ($checkStatement->fetch()) {
            $skipped++;
            continue;
        }

        $insertStatement->execute([
            'title' => $title,
            'source' => $source,
            'url' => 'https://www.cisa.gov/known-exploited-vulnerabilities-catalog',
            'summary' => mb_substr($summary, 0, 1200),
            'published_at' => $publishedAt,
        ]);

        $inserted++;
    }

    log_activity(
        $pdo,
        (int) $admin['id'],
        'admin',
        "Fetched CISA KEV updates. Inserted {$inserted}, skipped {$skipped}."
    );

    $_SESSION['flash_success'] = "CISA KEV updates fetched. Inserted {$inserted}, skipped {$skipped}.";
    header('Location: ' . app_url('admin/index.php?tab=articles'));
    exit;
} catch (Throwable $error) {
    $_SESSION['flash_error'] = 'News fetch failed: ' . $error->getMessage();
    header('Location: ' . app_url('admin/index.php?tab=articles'));
    exit;
}