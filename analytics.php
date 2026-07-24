<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=UTF-8');

const ANALYTICS_TOKEN_ENV = 'SITE_STATS_TOKEN';
const ANALYTICS_DEFAULT_TOKEN = 'change-this-token';
const ANALYTICS_DATA_DIR = __DIR__ . '/data';
const ANALYTICS_EVENTS_FILE = ANALYTICS_DATA_DIR . '/analytics-events.jsonl';
const ANALYTICS_GEO_CACHE_FILE = ANALYTICS_DATA_DIR . '/analytics-geo-cache.json';

ensureDataDirectory();

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

if ($method === 'POST') {
    handleTrackRequest();
    exit;
}

$action = isset($_GET['action']) ? (string) $_GET['action'] : 'stats';

if ($action === 'stats') {
    handleStatsRequest();
    exit;
}

http_response_code(404);
echo json_encode(['success' => false, 'message' => 'Unknown action']);

function handleTrackRequest(): void
{
    $userAgent = substr((string) ($_SERVER['HTTP_USER_AGENT'] ?? 'unknown'), 0, 400);
    if (isLikelyBot($userAgent)) {
        echo json_encode(['success' => true, 'ignored' => 'bot']);
        return;
    }

    $page = isset($_POST['page']) ? (string) $_POST['page'] : '/';
    $page = normalizePagePath($page);

    $event = [
        'timestamp' => gmdate('c'),
        'day' => gmdate('Y-m-d'),
        'ip_hash' => hash('sha256', getClientIp() . '|' . $userAgent),
        'page' => $page,
        'referrer' => sanitizeReferrer(isset($_POST['referrer']) ? (string) $_POST['referrer'] : ''),
        'tz_offset' => isset($_POST['tz_offset']) ? (int) $_POST['tz_offset'] : 0,
        'user_agent' => $userAgent,
    ];

    $location = resolveVisitorLocation(getClientIp());
    $event['country'] = $location['country'];
    $event['region'] = $location['region'];
    $event['city'] = $location['city'];
    $event['location_label'] = buildLocationLabel($location['country'], $location['region'], $location['city']);

    appendEvent($event);

    echo json_encode(['success' => true]);
}

function handleStatsRequest(): void
{
    $providedToken = isset($_GET['token']) ? (string) $_GET['token'] : '';
    if (!hash_equals(getAnalyticsToken(), $providedToken)) {
        http_response_code(401);
        echo json_encode(['success' => false, 'message' => 'Unauthorized']);
        return;
    }

    $events = readEvents();
    $stats = buildStats($events);

    echo json_encode([
        'success' => true,
        'generated_at' => gmdate('c'),
        'stats' => $stats,
    ]);
}

function getAnalyticsToken(): string
{
    $token = getenv(ANALYTICS_TOKEN_ENV);
    if (is_string($token) && $token !== '') {
        return $token;
    }
    return ANALYTICS_DEFAULT_TOKEN;
}

function buildStats(array $events): array
{
    $now = time();
    $todayStart = strtotime(gmdate('Y-m-d 00:00:00', $now));
    $weekStart = strtotime('monday this week 00:00:00', $now);
    if ($weekStart === false) {
        $weekStart = $todayStart;
    }
    $monthStart = strtotime(gmdate('Y-m-01 00:00:00', $now));
    $yearStart = strtotime(gmdate('Y-01-01 00:00:00', $now));

    $periods = [
        'daily' => ['from' => $todayStart, 'events' => []],
        'weekly' => ['from' => $weekStart, 'events' => []],
        'monthly' => ['from' => $monthStart, 'events' => []],
        'yearly' => ['from' => $yearStart, 'events' => []],
    ];

    foreach ($events as $event) {
        if (!isset($event['timestamp'])) {
            continue;
        }
        $ts = strtotime((string) $event['timestamp']);
        if ($ts === false) {
            continue;
        }

        foreach ($periods as $name => $period) {
            if ($ts >= $period['from']) {
                $periods[$name]['events'][] = $event;
            }
        }
    }

    $summary = [];
    foreach ($periods as $name => $period) {
        $summary[$name] = [
            'page_views' => count($period['events']),
            'unique_visitors' => countUniqueVisitors($period['events']),
            'top_locations' => topLocations($period['events']),
        ];
    }

    return [
        'totals' => [
            'all_time_page_views' => count($events),
            'all_time_unique_visitors' => countUniqueVisitors($events),
        ],
        'periods' => $summary,
        'all_time_top_locations' => topLocations($events),
    ];
}

function countUniqueVisitors(array $events): int
{
    $visitorMap = [];
    foreach ($events as $event) {
        if (!isset($event['ip_hash'])) {
            continue;
        }
        $visitorMap[(string) $event['ip_hash']] = true;
    }
    return count($visitorMap);
}

function topLocations(array $events, int $limit = 8): array
{
    $counts = [];
    foreach ($events as $event) {
        $label = isset($event['location_label']) ? trim((string) $event['location_label']) : '';
        if ($label === '') {
            $label = 'Unknown';
        }
        if (!isset($counts[$label])) {
            $counts[$label] = 0;
        }
        $counts[$label]++;
    }

    arsort($counts);
    $top = array_slice($counts, 0, $limit, true);

    $result = [];
    foreach ($top as $location => $count) {
        $result[] = [
            'location' => $location,
            'visits' => $count,
        ];
    }

    return $result;
}

function readEvents(): array
{
    if (!file_exists(ANALYTICS_EVENTS_FILE)) {
        return [];
    }

    $lines = @file(ANALYTICS_EVENTS_FILE, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    if ($lines === false) {
        return [];
    }

    $events = [];
    foreach ($lines as $line) {
        $decoded = json_decode($line, true);
        if (is_array($decoded)) {
            $events[] = $decoded;
        }
    }

    return $events;
}

function appendEvent(array $event): void
{
    $line = json_encode($event, JSON_UNESCAPED_SLASHES);
    if ($line === false) {
        return;
    }

    $fp = fopen(ANALYTICS_EVENTS_FILE, 'ab');
    if ($fp === false) {
        return;
    }

    if (flock($fp, LOCK_EX)) {
        fwrite($fp, $line . PHP_EOL);
        fflush($fp);
        flock($fp, LOCK_UN);
    }

    fclose($fp);
}

function resolveVisitorLocation(string $ip): array
{
    $fallbackCountry = isset($_SERVER['HTTP_CF_IPCOUNTRY']) ? (string) $_SERVER['HTTP_CF_IPCOUNTRY'] : '';
    $fallbackCountry = preg_replace('/[^A-Za-z]/', '', $fallbackCountry ?? '') ?? '';

    if ($ip === '' || !filter_var($ip, FILTER_VALIDATE_IP)) {
        return ['country' => $fallbackCountry !== '' ? strtoupper($fallbackCountry) : 'Unknown', 'region' => 'Unknown', 'city' => 'Unknown'];
    }

    if (isPrivateIp($ip)) {
        return ['country' => $fallbackCountry !== '' ? strtoupper($fallbackCountry) : 'Local', 'region' => 'Private Network', 'city' => 'Local'];
    }

    $cache = readGeoCache();
    if (isset($cache[$ip]) && is_array($cache[$ip])) {
        return [
            'country' => (string) ($cache[$ip]['country'] ?? 'Unknown'),
            'region' => (string) ($cache[$ip]['region'] ?? 'Unknown'),
            'city' => (string) ($cache[$ip]['city'] ?? 'Unknown'),
        ];
    }

    $resolved = fetchLocationFromIpApi($ip);
    if ($resolved['country'] === 'Unknown' && $fallbackCountry !== '') {
        $resolved['country'] = strtoupper($fallbackCountry);
    }

    $cache[$ip] = [
        'country' => $resolved['country'],
        'region' => $resolved['region'],
        'city' => $resolved['city'],
        'updated_at' => gmdate('c'),
    ];
    writeGeoCache($cache);

    return $resolved;
}

function fetchLocationFromIpApi(string $ip): array
{
    $url = 'https://ipwho.is/' . rawurlencode($ip);
    $context = stream_context_create([
        'http' => [
            'timeout' => 2,
            'method' => 'GET',
            'header' => "Accept: application/json\r\n",
        ],
    ]);

    $response = @file_get_contents($url, false, $context);
    if ($response === false) {
        return ['country' => 'Unknown', 'region' => 'Unknown', 'city' => 'Unknown'];
    }

    $json = json_decode($response, true);
    if (!is_array($json)) {
        return ['country' => 'Unknown', 'region' => 'Unknown', 'city' => 'Unknown'];
    }

    $country = sanitizeLocationPart((string) ($json['country'] ?? 'Unknown'));
    $region = sanitizeLocationPart((string) ($json['region'] ?? 'Unknown'));
    $city = sanitizeLocationPart((string) ($json['city'] ?? 'Unknown'));

    if ($country === '') {
        $country = 'Unknown';
    }
    if ($region === '') {
        $region = 'Unknown';
    }
    if ($city === '') {
        $city = 'Unknown';
    }

    return ['country' => $country, 'region' => $region, 'city' => $city];
}

function buildLocationLabel(string $country, string $region, string $city): string
{
    $parts = [];
    if ($city !== '' && $city !== 'Unknown') {
        $parts[] = $city;
    }
    if ($region !== '' && $region !== 'Unknown') {
        $parts[] = $region;
    }
    if ($country !== '' && $country !== 'Unknown') {
        $parts[] = $country;
    }

    if (empty($parts)) {
        return 'Unknown';
    }

    return implode(', ', array_unique($parts));
}

function sanitizeLocationPart(string $value): string
{
    $value = trim($value);
    if ($value === '') {
        return '';
    }
    return preg_replace('/[^A-Za-z0-9\-\.,\'\s]/', '', $value) ?? '';
}

function readGeoCache(): array
{
    if (!file_exists(ANALYTICS_GEO_CACHE_FILE)) {
        return [];
    }

    $raw = @file_get_contents(ANALYTICS_GEO_CACHE_FILE);
    if ($raw === false || trim($raw) === '') {
        return [];
    }

    $decoded = json_decode($raw, true);
    return is_array($decoded) ? $decoded : [];
}

function writeGeoCache(array $cache): void
{
    $encoded = json_encode($cache, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    if ($encoded === false) {
        return;
    }
    @file_put_contents(ANALYTICS_GEO_CACHE_FILE, $encoded, LOCK_EX);
}

function normalizePagePath(string $rawPath): string
{
    $path = trim($rawPath);
    if ($path === '') {
        return '/';
    }

    $parsed = parse_url($path, PHP_URL_PATH);
    if (!is_string($parsed) || $parsed === '') {
        $parsed = '/';
    }

    if ($parsed[0] !== '/') {
        $parsed = '/' . $parsed;
    }

    return substr($parsed, 0, 255);
}

function sanitizeReferrer(string $referrer): string
{
    $referrer = trim($referrer);
    if ($referrer === '') {
        return '';
    }
    return substr($referrer, 0, 500);
}

function isLikelyBot(string $ua): bool
{
    $pattern = '/bot|crawl|spider|slurp|bingpreview|facebookexternalhit|preview/i';
    return (bool) preg_match($pattern, $ua);
}

function getClientIp(): string
{
    $candidates = [
        'HTTP_CF_CONNECTING_IP',
        'HTTP_X_FORWARDED_FOR',
        'HTTP_X_REAL_IP',
        'REMOTE_ADDR',
    ];

    foreach ($candidates as $key) {
        if (empty($_SERVER[$key])) {
            continue;
        }

        $value = (string) $_SERVER[$key];
        $parts = array_map('trim', explode(',', $value));

        foreach ($parts as $candidate) {
            if (filter_var($candidate, FILTER_VALIDATE_IP)) {
                return $candidate;
            }
        }
    }

    return '';
}

function isPrivateIp(string $ip): bool
{
    return !filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE);
}

function ensureDataDirectory(): void
{
    if (!is_dir(ANALYTICS_DATA_DIR)) {
        mkdir(ANALYTICS_DATA_DIR, 0775, true);
    }
}
