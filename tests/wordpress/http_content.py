"""Exercise real WordPress cookie/nonce authentication and concurrent multipart uploads."""
from concurrent.futures import ThreadPoolExecutor
from hashlib import sha256
from html.parser import HTMLParser
from http.cookiejar import CookieJar
import json
import os
from pathlib import Path
import time
from urllib.error import HTTPError, URLError
from urllib.parse import urlencode
from urllib.request import Request, build_opener, HTTPCookieProcessor, urlopen
from uuid import uuid4

base = 'http://localhost:8080'
fixture = json.loads(Path(os.environ['MOL_HTTP_FIXTURE']).read_text())
samples_path = Path(os.environ['MOL_CONTENT_RESPONSES'])
samples = json.loads(samples_path.read_text())
checks = 0


def check(value, label):
    global checks
    if not value:
        raise AssertionError(label)
    checks += 1
    print('PASS ' + label)


for attempt in range(40):
    try:
        urlopen(base + '/wp-login.php', timeout=2).close()
        break
    except (URLError, TimeoutError):
        if attempt == 39:
            raise
        time.sleep(0.25)

jar = CookieJar()
opener = build_opener(HTTPCookieProcessor(jar))
opener.open(base + '/wp-login.php').close()
opener.open(Request(base + '/wp-login.php', data=urlencode({
    'log': fixture['username'], 'pwd': fixture['password'], 'wp-submit': 'Log In',
    'redirect_to': base + '/wp-admin/admin.php?page=manga-overlay', 'testcookie': '1',
}).encode())).close()
admin_html = opener.open(base + '/wp-admin/admin.php?page=manga-overlay').read().decode()


class BootstrapParser(HTMLParser):
    def __init__(self):
        super().__init__()
        self.collect = False
        self.raw = ''

    def handle_starttag(self, tag, attrs):
        if tag == 'script' and dict(attrs).get('id') == 'mol-content-data':
            self.collect = True

    def handle_endtag(self, tag):
        if tag == 'script':
            self.collect = False

    def handle_data(self, data):
        if self.collect:
            self.raw += data


parser = BootstrapParser()
parser.feed(admin_html)
check(bool(parser.raw), 'real logged-in admin page supplies authenticated bootstrap')
boot = json.loads(parser.raw)
check('content.mjs' in admin_html and 'content.css' in admin_html, 'WordPress enqueues admin module and stylesheet')
check('الفصول والصفحات' in admin_html, 'Arabic content administration is rendered by WordPress')
cookie_request = Request(base + '/')
jar.add_cookie_header(cookie_request)
cookie = cookie_request.get_header('Cookie', '')
check(bool(cookie), 'WordPress login issued a session cookie')


def api(method, route, data=None, headers=None, auth=True, nonce=True):
    request_headers = dict(headers or {})
    if auth:
        request_headers['Cookie'] = cookie
    if nonce:
        request_headers['X-WP-Nonce'] = boot['nonce']
    if isinstance(data, dict):
        data = json.dumps(data).encode()
        request_headers['Content-Type'] = 'application/json'
    request = Request(base + '/wp-json/mol/v1' + route, data=data, headers=request_headers, method=method)
    try:
        response = urlopen(request, timeout=90)
    except HTTPError as error:
        response = error
    with response:
        raw = response.read()
        return response.status, json.loads(raw) if raw else None, dict(response.headers)


def expect(result, status, label, schema=None):
    actual, body, headers = result
    check(actual == status, f'{label}: expected {status}, received {actual}' + (f' {body}' if actual != status else ''))
    if schema:
        samples.append({'schema': schema, 'body': body})
    return body, headers


draft, _ = expect(api('POST', '/chapters', {'work_id': fixture['work_id'], 'chapter_label': 'HTTP draft'}), 201, 'cookie and valid nonce authorize actual HTTP write', 'ChapterResponse')
draft_id = draft['data']['id']
expect(api('GET', f'/chapters/{draft_id}', auth=False, nonce=False), 404, 'anonymous HTTP draft request does not reveal it', 'ErrorResponse')
expect(api('GET', f'/chapters/{draft_id}'), 200, 'authenticated HTTP reader sees permitted draft', 'ChapterResponse')
expect(api('PATCH', f'/chapters/{draft_id}', {'title': 'no nonce'}, nonce=False), 401, 'cookie alone cannot write without CSRF nonce', 'ErrorResponse')
unauth = api('POST', '/chapters', {'work_id': fixture['work_id'], 'chapter_label': 'nonce-only'}, auth=False)
check(unauth[0] in (401, 403), 'nonce alone is not authentication')
expect(api('PATCH', f'/chapters/{draft_id}', {'unknown': True}), 400, 'actual HTTP body rejects unknown fields', 'ErrorResponse')

source = Path(fixture['image_path']).read_bytes()


def upload(key, payload=source, filename='http-page.png', mime='image/png'):
    boundary = 'mol-test-' + uuid4().hex
    body = (
        f'--{boundary}\r\nContent-Disposition: form-data; name="image"; filename="{filename}"\r\n'
        f'Content-Type: {mime}\r\n\r\n'
    ).encode() + payload + f'\r\n--{boundary}--\r\n'.encode()
    return api('POST', f'/chapters/{draft_id}/pages', body, {
        'Content-Type': 'multipart/form-data; boundary=' + boundary,
        'MOL-Idempotency-Key': key,
    })


with ThreadPoolExecutor(max_workers=2) as pool:
    responses = list(pool.map(lambda _: upload('concurrent-retry'), range(2)))
first, _ = expect(responses[0], 201, 'first concurrent multipart upload', 'PageResponse')
second, _ = expect(responses[1], 201, 'second concurrent multipart retry', 'PageResponse')
check(first['data']['id'] == second['data']['id'], 'concurrent retry creates one resource')
listing, headers = expect(api('GET', f'/chapters/{draft_id}/pages'), 200, 'page list after concurrent upload', 'PageListResponse')
check(len(listing['data']) == 1, 'concurrent upload leaves exactly one page')
check('no-store' in headers.get('Cache-Control', ''), 'private resource responses prohibit shared cache storage')
with urlopen(first['data']['image']['url']) as image:
    check(sha256(image.read()).digest() == sha256(source).digest(), 'HTTP image resource preserves original bytes')
expect(upload('concurrent-retry', filename='different.png'), 409, 'same key and changed payload rejected over HTTP', 'ErrorResponse')
expect(upload('svg-attempt', b'<svg onload="alert(1)"></svg>', 'unsafe.svg', 'image/svg+xml'), 415, 'actual multipart SVG rejected', 'ErrorResponse')
expect(upload('missing-key' * 20), 400, 'oversized retry key rejected over HTTP', 'ErrorResponse')
expect(upload(''), 400, 'missing retry key rejected over HTTP', 'ErrorResponse')
expect(api('DELETE', f'/chapters/{draft_id}'), 204, 'actual HTTP chapter delete completes')
expect(api('GET', f'/chapters/{draft_id}', auth=False, nonce=False), 404, 'deleted HTTP chapter remains unavailable', 'ErrorResponse')
samples_path.write_text(json.dumps(samples, ensure_ascii=False, indent=2))
print(f'{checks} HTTP content checks passed.')
