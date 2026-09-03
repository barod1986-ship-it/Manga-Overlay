# المصادر التقنية الخارجية

آخر تحقق شامل: `2026-09-01`.

هذه القائمة هي سجل المصادر العامة التي استُخدمت للتحقق من الادعاءات الخارجية في Master Spec. يشرح `INTERNET_VERIFICATION_REPORT.md` ما الذي يثبته كل مصدر، وما الذي يبقى قرارًا هندسيًا أو يحتاج PoC/benchmark.

## S01 — WordPress 7.1
- WordPress 7.1 documentation: https://wordpress.org/documentation/wordpress-version/version-7-1/
- WordPress release archive: https://wordpress.org/download/releases/
- WordPress 7.1 Server Compatibility: https://make.wordpress.org/hosting/handbook/compatibility/version/7-1/
- Compatibility overview: https://make.wordpress.org/hosting/handbook/compatibility/

يثبت: صدور WordPress 7.1 في 19 أغسطس 2026، وتوصية Hosting Team للتركيبات الجديدة بـPHP 8.4/8.5، MySQL 8.4، أو MariaDB 10.11/11.4/11.8.

## S02 — PHP / Node / أدوات البناء
- PHP supported versions: https://www.php.net/supported-versions.php
- Node.js releases: https://nodejs.org/en/about/previous-releases
- Vite guide: https://vite.dev/guide/
- Vite 8 announcement: https://v8.vite.dev/blog/announcing-vite8
- Playwright installation/system requirements: https://playwright.dev/docs/intro

يثبت: PHP 8.4 مدعوم أمنيًا حتى 2028-12-31، Node 24 في حالة LTS وقت التحقق، وNode 24 مناسب لمتطلبات Vite/Playwright الحالية.

## S03 — WordPress REST والصلاحيات
- REST authentication: https://developer.wordpress.org/rest-api/using-the-rest-api/authentication/
- `register_rest_route()`: https://developer.wordpress.org/reference/functions/register_rest_route/
- Roles and Capabilities: https://developer.wordpress.org/plugins/users/roles-and-capabilities/
- `current_user_can()`: https://developer.wordpress.org/reference/functions/current_user_can/
- `register_meta()`: https://developer.wordpress.org/reference/functions/register_meta/

يثبت: same-origin REST يعتمد login cookies؛ `X-WP-Nonce` يستخدم nonce `wp_rest` ولا يغني عن capability؛ custom routes تحتاج `permission_callback`; فحص capability مفضل على فحص اسم الدور؛ registered post meta المعروض عبر Core REST يحتاج `custom-fields` support على CPT.

## S04 — WordPress الجداول والتوافق البرمجي
- Creating Tables with Plugins / `dbDelta`: https://developer.wordpress.org/plugins/creating-tables-with-plugins/
- `dbDelta()`: https://developer.wordpress.org/reference/functions/dbdelta/
- `current_time()`: https://developer.wordpress.org/reference/functions/current_time/
- `wp_json_encode()`: https://developer.wordpress.org/reference/functions/wp_json_encode/
- Plugin/content directories: https://developer.wordpress.org/plugins/plugin-basics/determining-plugin-and-content-directories/
- `wp_upload_dir()`: https://developer.wordpress.org/reference/functions/wp_upload_dir/
- `WP_ENVIRONMENT_TYPE`: https://developer.wordpress.org/reference/functions/wp_get_environment_type/
- Transients API: https://developer.wordpress.org/apis/transients/

يثبت: قواعد صياغة `dbDelta`، معنى GMT في `current_time(..., true)`, وجود `wp_json_encode()`، عدم افتراض مسارات `wp-content`, وكون انتهاء transient حدًا أقصى لا مدة بقاء مضمونة.

## S05 — MySQL / MariaDB
- MySQL JSON: https://dev.mysql.com/doc/refman/8.4/en/json.html
- MySQL InnoDB locking: https://dev.mysql.com/doc/refman/8.4/en/innodb-locking.html
- MySQL numeric type syntax/display width: https://dev.mysql.com/doc/refman/8.4/en/numeric-type-syntax.html
- MariaDB JSON: https://mariadb.com/docs/server/reference/data-types/string-data-types/json
- MariaDB LONGTEXT: https://mariadb.com/docs/server/reference/data-types/string-data-types/longtext

يثبت: MySQL يملك JSON native بينما MariaDB يجعل JSON alias لـLONGTEXT؛ InnoDB يدعم row-level locking/transactions؛ integer display width لا يغير range وهو deprecated في MySQL.

## S06 — وسوم اللغة BCP 47
- RFC 5646: https://www.rfc-editor.org/info/rfc5646/

يثبت: لا يوجد حد أعلى ثابت لطول language tag؛ الأنظمة ذات buffer محدود يجب أن تسمح بما لا يقل عن 35 حرفًا. لذلك `VARCHAR(255)` في MOL حد تطبيقي وليس حدًا من RFC.

## S07 — HTTP concurrency/status codes
- RFC 9110 HTTP Semantics: https://www.rfc-editor.org/rfc/rfc9110.html
- RFC 6585 Additional HTTP Status Codes: https://www.rfc-editor.org/info/rfc6585/
- RFC 4918 WebDAV (`423 Locked`): https://www.rfc-editor.org/info/rfc4918/

يثبت: `If-Match` الفاشل يؤدي إلى `412`، `428` مناسب لطلب precondition لتجنب lost updates، و`423` يعني Locked. استخدام `423` في MOL اختيار عقد API مقصود وليس سلوك WordPress افتراضيًا.

## S08 — Idempotency
- IETF Datatracker history: https://datatracker.ietf.org/doc/draft-ietf-httpapi-idempotency-key-header/history/
- Expired draft: https://datatracker.ietf.org/doc/draft-ietf-httpapi-idempotency-key-header/

يثبت: مسودة `Idempotency-Key` انتهت في 18 أبريل 2026 ولم تصبح RFC وقت التحقق؛ لذلك تستخدم المواصفة `MOL-Idempotency-Key` كعقد خاص بالمشروع ولا تصفه كمعيار HTTP منشور.

## S09 — OpenAPI
- OpenAPI Specification 3.1.2: https://spec.openapis.org/oas/v3.1.2.html

يثبت: قواعد OpenAPI 3.1 للـbinary/multipart وEncoding Object المستخدمة في `API.openapi.yaml`.

## S10 — Moveable / Canvas
- Moveable documentation: https://daybrush.com/moveable/release/latest/doc/
- Pinchable: https://daybrush.com/moveable/release/latest/doc/Moveable.Pinchable.html
- Snappable: https://daybrush.com/moveable/release/latest/doc/Moveable.Snappable.html
- Canvas text direction: https://developer.mozilla.org/en-US/docs/Web/API/CanvasRenderingContext2D/direction

يثبت: Moveable يوفر drag/resize/scale/rotate/pinch/snap ويدعم SVG. كما يثبت أن Canvas الحديث يدعم `direction=rtl`; اختيار DOM/SVG في MOL قرار تحرير/هندسة وليس لأن Canvas عاجز عن RTL.

## S11 — العربية وCSS/SVG
- CSS Text Module Level 4: https://www.w3.org/TR/css-text-4/
- W3C Arabic cursive shaping tests: https://www.w3.org/International/i18n-tests/results/css-text-shaping
- SVG `paint-order`: https://developer.mozilla.org/en-US/docs/Web/SVG/Reference/Attribute/paint-order

يثبت: spacing لا ينبغي أن يكسر الوصلات في النصوص cursive مثل العربية؛ `paint-order` يتحكم بترتيب fill/stroke لكنه لا يضمن جودة شكل stroke العربي على كل خط/متصفح، ولهذا يبقى visual test إلزاميًا.

## S12 — الوصولية
- WCAG 2.2: https://www.w3.org/TR/WCAG22/
- Dragging Movements 2.5.7: https://www.w3.org/WAI/WCAG22/Understanding/dragging-movements
- Target Size Minimum 2.5.8: https://www.w3.org/WAI/WCAG22/Understanding/target-size-minimum.html

يثبت: وظائف drag تحتاج بديل single-pointer بلا drag في Level AA، والحد الأدنى للهدف 24×24 CSS px مع الاستثناءات. 44×44 في MOL هدف UX داخلي أعلى، وليس ادعاءً بأنه حد WCAG AA.

## S13 — Core Web Vitals
- Web Vitals: https://web.dev/articles/vitals
- Threshold rationale: https://web.dev/articles/defining-core-web-vitals-thresholds

يثبت: good thresholds هي LCP ≤2.5s، INP ≤200ms، CLS ≤0.1، والتقييم الموصى به عند 75th percentile.

## S14 — WordPress Media
- Responsive images: https://developer.wordpress.org/apis/responsive-images/
- `wp_get_attachment_image()`: https://developer.wordpress.org/reference/functions/wp_get_attachment_image/
- Loading optimization attributes: https://developer.wordpress.org/reference/functions/wp_get_loading_optimization_attributes/
- WebP/output format: https://make.wordpress.org/core/2021/06/28/miscellaneous-developer-focused-changes-in-wordpress-5-8/
- AVIF support: https://make.wordpress.org/core/2024/02/23/wordpress-6-5-adds-avif-support/
- WordPress 7.1 client-side media processing test notes: https://make.wordpress.org/core/2026/06/04/call-for-testing-client-side-media-processing/
- `wp_check_filetype_and_ext()`: https://developer.wordpress.org/reference/functions/wp_check_filetype_and_ext/

يثبت: WordPress يدعم responsive image metadata؛ default sub-sizes تبقى بصيغة الأصل ما لم يغير developer output format؛ AVIF يعتمد على دعم image processing في البيئة؛ custom MOL upload/processing يجب أن يدمج التحويل صراحةً بدل افتراضه.

## S15 — أمان الرفع والـheaders
- OWASP File Upload Cheat Sheet: https://cheatsheetseries.owasp.org/cheatsheets/File_Upload_Cheat_Sheet.html
- CSP guide: https://developer.mozilla.org/en-US/docs/Web/HTTP/Guides/CSP
- `frame-ancestors`: https://developer.mozilla.org/en-US/docs/Web/HTTP/Reference/Headers/Content-Security-Policy/frame-ancestors
- `X-Content-Type-Options`: https://developer.mozilla.org/en-US/docs/Web/HTTP/Reference/Headers/X-Content-Type-Options

يثبت: allowlist، عدم الثقة بـclient Content-Type، filename آمن، limits، authorization، وطبقات CSP/frame-ancestors/nosniff المذكورة في المواصفة.


## S16 — WordPress REST headers / reverse proxy conditional headers
- `WP_REST_Request::get_header()`: https://developer.wordpress.org/reference/classes/wp_rest_request/get_header/
- Nginx `ngx_http_proxy_module`: https://nginx.org/en/docs/http/ngx_http_proxy_module.html

يثبت: WordPress REST request object يستطيع قراءة header مباشرة. كما توثق Nginx أن proxy caching قد يمنع تمرير conditional headers ومنها `If-Match` إلى upstream عند تفعيل caching؛ لذلك اختبار/إعداد proxy جزء من deployment gate في MOL.

## ملاحظة على المصادر

الأرقام مثل `1200ms` للـautosave، lock lease `45s`, renew `15s`, API p95 `<300ms`, عدد `100+` عنصر في stress test، وحد `MOL_UNIT=1,000,000` هي **قرارات/أهداف مشروع** وليست حقائق تستمد صحتها من المصادر أعلاه. يجب إثبات ملاءمتها في PoC/benchmark والاختبارات المحددة في الحزمة.


## فحص الوصول إلى المصادر

في تدقيق `2026-09-01` تمت مراجعة مواقع الروابط الأساسية في هذه القائمة؛ وأضيف في v1.1.1 مصدر WordPress REST header الرسمي ومصدر Nginx proxy الرسمي لدعم تقوية عقد `If-Match`. أمكن استرجاع 55 صفحة مباشرة من المواقع الرسمية. رابطا MariaDB (`JSON`, `LONGTEXT`) أعادا خطأً تقنيًا من مسار الفتح المباشر في أداة التصفح، لكن المحتوى نفسه تم استرجاعه والتحقق منه عبر نتائج البحث الرسمية لنطاق `mariadb.com/docs`. لا يُعامل فشل أداة الفتح كفشل للمصدر.

## S39 — WordPress slug sanitization
- `sanitize_title()` code reference: https://developer.wordpress.org/reference/functions/sanitize_title/

يثبت: WordPress يوفر `sanitize_title()` لتحويل النص إلى slug مناسب للاستخدام في URLs؛ استخدامه في خوارزمية slug للفصول قرار تطبيق MOL، بينما uniqueness/suffix/retry قواعد خاصة بالمشروع.
