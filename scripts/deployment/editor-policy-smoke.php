<?php
/**
 * Explicit staging check: wp eval-file /private/path/editor-policy-smoke.php USER_LOGIN EDITOR_PATH
 * Reads documents only. Creates and destroys only its own five-minute login session.
 * Does not print cookies, nonces, HTML or authentication headers.
 */
if (!defined('WP_CLI') || !WP_CLI || count($args) !== 2) {
    throw new RuntimeException('Requires an editor login and a relative editor path.');
}
$user = get_user_by('login', $args[0]);
$path = $args[1];
if (!$user || !user_can($user, 'mol_use_editor') || !str_starts_with($path, '/series/') || !str_ends_with($path, '/edit/') || str_contains($path, '?') || wp_parse_url(home_url(), PHP_URL_SCHEME) !== 'https') {
    throw new RuntimeException('Requires HTTPS and an authorized editor route.');
}
$sessions = WP_Session_Tokens::get_instance($user->ID);
$expires = time() + 300;
$session = $sessions->create($expires);
try {
    $cookie = wp_generate_auth_cookie($user->ID, $expires, 'logged_in', $session);
    $read = static function (string $route, bool $authenticated) use ($cookie): array {
        $response = wp_remote_get(home_url($route), [
            'timeout' => 20, 'redirection' => 0,
            'headers' => $authenticated ? ['Cookie' => LOGGED_IN_COOKIE . '=' . $cookie] : [],
        ]);
        if (is_wp_error($response)) {
            throw new RuntimeException('Document transport failed: ' . $response->get_error_code());
        }
        $headers = [];
        foreach (wp_remote_retrieve_headers($response) as $name => $value) {
            $headers[strtolower($name)] = is_array($value) ? implode(', ', $value) : $value;
        }
        return [wp_remote_retrieve_response_code($response), $headers, wp_remote_retrieve_body($response)];
    };
    $check = static function (bool $condition, string $label): void {
        if (!$condition) {
            throw new RuntimeException($label . ' failed.');
        }
        WP_CLI::log('PASS ' . $label);
    };
    $lastNonce = null;
    foreach ([false, true, true] as $authenticated) {
        [$status, $headers, $html] = $read($path, $authenticated);
        $check($status === ($authenticated ? 200 : 302), $authenticated ? 'Authenticated editor 200' : 'Anonymous editor 302');
        $policy = (string) ($headers['content-security-policy'] ?? '');
        $check(preg_match("/'nonce-([A-Za-z0-9+\/]{32})'/", $policy, $match) === 1, '192-bit CSP nonce');
        $nonce = $match[1];
        $check($nonce !== $lastNonce, 'Fresh response nonce');
        $lastNonce = $nonce;
        foreach (["script-src-attr 'none'", "frame-ancestors 'self'", "object-src 'none'", "base-uri 'self'", "form-action 'self'"] as $directive) {
            $check(str_contains($policy, $directive), $directive);
        }
        $check(!str_contains($policy, 'unsafe-inline') && !str_contains($policy, 'unsafe-eval'), 'No unsafe script exceptions');
        $check(str_contains((string) ($headers['cache-control'] ?? ''), 'no-store') && str_contains((string) ($headers['cache-control'] ?? ''), 'private'), 'Private uncached response');
        $check(str_contains(strtolower((string) ($headers['x-content-type-options'] ?? '')), 'nosniff'), 'nosniff retained');
        $check(str_contains((string) ($headers['x-frame-options'] ?? ''), 'SAMEORIGIN'), 'SAMEORIGIN retained');
        $check(str_contains((string) ($headers['referrer-policy'] ?? ''), 'same-origin'), 'Same-origin referrer policy');
        if ($authenticated) {
            $document = new DOMDocument();
            $previous = libxml_use_internal_errors(true);
            $document->loadHTML($html);
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
            $bootstrap = $document->getElementById('mol-editor-data');
            $check($bootstrap !== null && $bootstrap->getAttribute('type') === 'application/json' && $bootstrap->getAttribute('nonce') === $nonce, 'Inert bootstrap nonce matches policy');
            $check($document->getElementById('mol-editor-root') !== null, 'Editor shell present');
        }
    }
    [$status, $headers] = $read('/library/', false);
    $check($status === 200, 'Public library 200');
    $check(!isset($headers['content-security-policy']), 'Editor policy stays scoped on this deployment');
    WP_CLI::success('Editor policy headers, nonce freshness and route scope passed.');
} finally {
    $sessions->destroy($session);
}
