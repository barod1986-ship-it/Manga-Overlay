# متطلبات النشر

## 1. Production baseline

- WordPress 7.1 latest maintenance in 7.1 line.
- PHP 8.4.x مع extensions المعتادة لـWordPress + Imagick موصى به للصور.
- MySQL 8.4 LTS أو MariaDB 10.11/11.4/11.8.
- HTTPS.
- OPcache.
- web server Nginx أو Apache حديث.

## 2. Build

- Composer install production optimized.
- Node 24 LTS.
- build React editor assets via Vite.
- لا يحتاج Node runtime على production بعد build.

## 3. CDN

origin-pull اختياري لكنه موصى به عند حركة صور ملحوظة. cache للملفات المشتقة، وليس REST الخاصة.

## 4. Configuration

في wp-config/environment:
- `WP_ENVIRONMENT_TYPE`.
- debug off في production.
- limits للرفع حسب سياسة الموقع.
- أي مفاتيح CDN خارج repository.

## 5. Compatibility gate

قبل ترقية WordPress/PHP/DB major/minor مهمة:
- CI matrix.
- smoke reader/editor.
- mobile editor sanity.


## 6. REST conditional-header gate

قبل الإنتاج:
- reverse proxy/CDN لا cache مسارات write `/wp-json/mol/v1/*`.
- `If-Match`, `ETag`, `X-WP-Nonce`, `X-MOL-Lock-Token` لا تُحذف في المسار الفعلي.
- smoke test عبر hostname الإنتاجي/staging proxy: GET/POST عنصر → ETag، ثم PATCH بـIf-Match صحيح ينجح، stale يعيد 412، missing يعيد 428.
- إذا استُخدم Nginx proxy caching أو CDN rules، توثق الاستثناءات ضمن deployment config وليس داخل PHP فقط.
