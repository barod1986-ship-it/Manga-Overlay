<?php
/**
 * Run explicitly on a staging server:
 * php8.4 /usr/local/bin/wp eval-file /private/path/etag-smoke.php USER_ID DRAFT_PAGE_ID [pretty|query] [origin] [TRUSTED_ORIGIN_CERT]
 * Creates and deletes one synthetic element; expires only its own temporary login session.
 * Never prints cookies, nonces, lease tokens, or response bodies.
 */
if (!defined('WP_CLI') || !WP_CLI || count($args) < 2) {
    throw new RuntimeException('Use wp eval-file with an editor user ID and a draft page ID.');
}
$userId = (int) $args[0];
$pageId = (int) $args[1];
$queryRoute = ($args[2] ?? 'pretty') === 'query';
$origin = ($args[3] ?? '') === 'origin';
$caFile = $args[4] ?? null;
global $wpdb;
$draft = $wpdb->get_var($wpdb->prepare(
    "SELECT c.is_published FROM {$wpdb->prefix}mol_pages p JOIN {$wpdb->prefix}mol_chapters c ON c.id=p.chapter_id WHERE p.id=%d",
    $pageId
));
if ($draft === null || (int) $draft !== 0 || !user_can($userId, 'mol_edit_translations') || !user_can($userId, 'mol_delete_translation_elements')) {
    throw new RuntimeException('Requires a draft page and a user allowed to create and delete translations.');
}
if (wp_parse_url(home_url(), PHP_URL_SCHEME) !== 'https') {
    throw new RuntimeException('HTTPS is required.');
}
$sessions = WP_Session_Tokens::get_instance($userId);
$expires = time() + 300;
$session = $sessions->create($expires);
$cookie = wp_generate_auth_cookie($userId, $expires, 'logged_in', $session);
$_COOKIE[LOGGED_IN_COOKIE] = $cookie;
wp_set_current_user($userId);
$nonce = wp_create_nonce('wp_rest');
$elementId = null;
$version = null;
$lease = null;
$failures = [];
$call = static function ($method, $path, $body = null, $headers = [], $encoding = 'identity') use ($cookie, $nonce, &$queryRoute, $origin, $caFile) {
    $url = $queryRoute ? home_url('/?rest_route=/mol/v1' . $path) : home_url('/wp-json/mol/v1' . $path);
    $responseHeaders = [];
    $ch = curl_init($url);
    if ($origin) {
        curl_setopt($ch, CURLOPT_RESOLVE, [wp_parse_url($url, PHP_URL_HOST) . ':443:127.0.0.1']);
        if ($caFile !== null) {
            curl_setopt($ch, CURLOPT_CAINFO, $caFile);
        }
    }
    curl_setopt_array($ch, [
        CURLOPT_CUSTOMREQUEST => $method,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => false,
        CURLOPT_TIMEOUT => 20,
        CURLOPT_ENCODING => $encoding,
        CURLOPT_HTTPHEADER => array_merge([
            'Cookie: ' . LOGGED_IN_COOKIE . '=' . $cookie,
            'X-WP-Nonce: ' . $nonce,
            'Content-Type: application/json',
        ], $headers),
        CURLOPT_HEADERFUNCTION => static function ($ch, $line) use (&$responseHeaders) {
            if (str_contains($line, ':')) {
                [$name, $value] = explode(':', $line, 2);
                $name = strtolower(trim($name));
                $value = trim($value);
                $responseHeaders[$name] = isset($responseHeaders[$name]) ? $responseHeaders[$name] . ', ' . $value : $value;
            }
            return strlen($line);
        },
    ]);
    if ($body !== null) {
        curl_setopt($ch, CURLOPT_POSTFIELDS, wp_json_encode($body));
    }
    $raw = curl_exec($ch);
    $status = curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    $curlError = curl_errno($ch);
    curl_close($ch);
    if ($raw === false) {
        throw new RuntimeException('HTTP transport failed: ' . $curlError);
    }
    $data = json_decode($raw, true);
    WP_CLI::log(wp_json_encode([
        'method' => $method, 'route' => $queryRoute ? 'query' : 'pretty', 'origin' => $origin, 'accept_encoding' => $encoding,
        'status' => $status, 'etag' => $responseHeaders['etag'] ?? null,
        'content_encoding' => $responseHeaders['content-encoding'] ?? null,
        'server' => $responseHeaders['server'] ?? null, 'cache_control' => $responseHeaders['cache-control'] ?? null,
        'element_id' => $data['data']['id'] ?? null, 'version' => $data['data']['version'] ?? null, 'error_code' => $data['code'] ?? null,
    ]));
    return [$status, $data, $responseHeaders];
};
$expect = static function ($actual, $expected, $label) {
    if ($actual !== $expected) {
        throw new RuntimeException($label . ': expected ' . wp_json_encode($expected) . ', received ' . wp_json_encode($actual));
    }
};
try {
    $key = wp_generate_uuid4();
    $body = ['page_id' => $pageId, 'target_lang' => 'ar', 'element_type' => 'free_text',
        'content' => 'Temporary deployment ETag probe', 'x_unit' => 0, 'y_unit' => 0, 'w_unit' => 100000, 'h_unit' => 100000];
    [$status, $data, $headers] = $call('POST', '/elements', $body, ['MOL-Idempotency-Key: ' . $key], 'gzip, br');
    $expect($status, 201, 'Create');
    $elementId = (int) $data['data']['id'];
    $version = (int) $data['data']['version'];
    $expect($headers['etag'] ?? null, '"' . $version . '"', 'Create ETag');
    [$status, $data] = $call('POST', '/elements', $body, ['MOL-Idempotency-Key: ' . $key], 'gzip, br');
    $expect($status, 201, 'Replay');
    $expect((int) $data['data']['id'], $elementId, 'Idempotent replay');
    [$status, $data] = $call('POST', '/elements/' . $elementId . '/lock');
    $expect($status, 200, 'Lease');
    $lease = $data['data']['lock_token'];
    $lockHeader = ['X-MOL-Lock-Token: ' . $lease];
    [$status] = $call('PUT', '/elements/' . $elementId . '/lock', null, $lockHeader);
    $expect($status, 200, 'Lease renewal');
    [$status] = $call('PATCH', '/elements/' . $elementId, ['content' => 'Missing precondition'], $lockHeader);
    $expect($status, 428, 'Missing If-Match');
    foreach (['identity', 'gzip', 'br'] as $encoding) {
        [$status, $data, $headers] = $call('PATCH', '/elements/' . $elementId,
            ['content' => 'Temporary deployment probe: ' . $encoding . ' ' . str_repeat('ETag ', 80)],
            array_merge($lockHeader, ['If-Match: "' . $version . '"']), $encoding);
        $expect($status, 200, 'Patch');
        $expect((int) $data['data']['version'], $version + 1, 'Version increment');
        $version = (int) $data['data']['version'];
        if (($headers['etag'] ?? null) !== '"' . $version . '"') {
            $failures[] = $encoding;
        }
    }
    [$status] = $call('PATCH', '/elements/' . $elementId, ['content' => 'Stale precondition'], array_merge($lockHeader, ['If-Match: "1"']));
    $expect($status, 412, 'Stale If-Match');
} finally {
    try {
        if ($elementId !== null && $lease !== null && $version !== null) {
            [$status] = $call('DELETE', '/elements/' . $elementId, null,
                ['X-MOL-Lock-Token: ' . $lease, 'If-Match: "' . $version . '"']);
            if ($status !== 204 && $queryRoute) {
                // A routing failure must not strand the synthetic element.
                $queryRoute = false;
                [$status] = $call('DELETE', '/elements/' . $elementId, null,
                    ['X-MOL-Lock-Token: ' . $lease, 'If-Match: "' . $version . '"']);
            }
            $expect($status, 204, 'Cleanup');
        } elseif ($elementId !== null) {
            WP_CLI::warning('Cleanup required for synthetic element ID ' . $elementId);
        }
    } finally {
        $sessions->destroy($session);
        unset($_COOKIE[LOGGED_IN_COOKIE]);
    }
}
if ($failures) {
    WP_CLI::error('ETag contract failed with: ' . implode(', ', $failures));
}
WP_CLI::success('ETag, If-Match, idempotency, lease and cleanup passed.');
