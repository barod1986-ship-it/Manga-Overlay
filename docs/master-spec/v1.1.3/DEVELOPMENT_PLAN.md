# خطة التطوير

## T-00 البيئة
- WordPress 7.1.x.
- PHP 8.4.
- DB matrix CI: MySQL 8.4 + MariaDB 10.11 على الأقل.
- Node 24 LTS.

## T-01 PoC renderer
يبني صفحة مستقلة: image + normalized DOM/SVG elements + Arabic. DoD: المواضع ثابتة نسبيًا عبر الشاشات.

## T-02 PoC editor input
React + Moveable + iOS/Android actual devices. DoD: drag/resize/rotate + textarea mobile ناجحة.

## T-03 plugin bootstrap
Composer PSR-4 `MOL\\`, activation, db version, roles version.

## T-04 schema/repositories
الجداول التسعة (بما فيها `mol_idempotency_keys`)، repositories، validators، transactions، **تطبيع DECIMAL مثل `sort_order` إلى DTO number**، مع تشغيل migration tests على MySQL 8.4 وMariaDB 10.11.

## T-05 work CPT/taxonomies
`mol_work` + metadata + permalinks.

## T-06 chapter/page admin
CRUD chapters، upload queue، page reorder **two-phase collision-safe**، review-status route/policy.

## T-07 public data APIs
Typed OpenAPI responses لكل GET، library filters/pagination كاملة، work/chapter/pages، page elements، **chapter overlay batch**، contributors/profiles، **ChapterVisibilityPolicy** للمسودات (404 للعامة؛ editor/manage-content فقط في سياق REST موثّق)، وعقود 400/401/403/429 كما في OpenAPI.

## T-08 theme/library/work
server-rendered UI + filters + RTL.

## T-09 reader
webtoon + paged + toggle + renderer + progress.

## T-10 editor shell
React routing/state/stage/properties/layers.

## T-11 element editing
4 types + DOM/SVG renderer + Moveable.

## T-12 REST write + autosave
POST/PATCH/DELETE + save state machine.

## T-13 locks/concurrency
lease table + token + renew + If-Match/ETag + 412/423/428 UI + same-route force-release policy + reverse-proxy header integration test.

## T-14 contributions
UPSERT unique element-user + contributor views.

## T-15 presets/auto-fit/snapping
personal/work/global + built-in Base Styles + single-default semantics + precedence + validation + UI.

## T-16 mobile editor
bottom sheet, touch controls, zoom, device tests.

## T-17 profiles/reports
public profile + report flow + moderation.

## T-18 security/performance
upload hardening، WebP conversion صريح مع graceful fallback، cache headers، image tuning، rate limiting enforceable، CSP/frame-ancestors.

## T-19 test pass
unit + integration + E2E + visual + mobile + accessibility drag alternatives + perf + strict OpenAPI/schema validation + فحص أمثلة `DATA_MODEL_EXAMPLES.md` الموسومة بالمخطط.

## T-20 release candidate
run all Acceptance Criteria، freeze code schema version، create deployment package.

## قاعدة كل مهمة
- tests أول/مع الكود.
- لا اسم أو route جديد خارج هذه الوثائق بلا تحديث Spec version.
- كل PR يذكر FR/AC IDs التي يحققها.
### Gate عقد 1.1.3 قبل DTO/T-07

شغّل `python VALIDATION_HARNESS.py`. لا تولد DTO/mock من OpenAPI ولا تبدأ T-07 إذا فشل semantic error/status check، strict request-schema check، alias check، أو error-code usage check.

