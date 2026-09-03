# معايير البرمجة

## PHP

- Repositories لا تعيد representations الخام الخاصة بالمحرك إلى REST DTOs؛ حقول DECIMAL مثل `sort_order` تُحوّل صراحةً إلى `float` قبل serialization.
- WordPress Coding Standards + PHPCS.
- PHPStan مستوى عملي مرتفع.
- Composer PSR-4 `MOL\\` → `src/`.
- strict separation: Controller → Service → Repository.
- `$wpdb->prepare` للاستعلامات ذات القيم.
- تواريخ DB بالمشروع UTC: استخدم `current_time('mysql', true)` أو كائن UTC مكافئ، لا تعتمد `current_time('mysql')` الافتراضية المحلية.
- لا hardcode لمسارات `wp-content/plugins/uploads/themes`; استخدم `plugin_dir_path()`, `plugin_dir_url()`, `wp_upload_dir()` ودوال WordPress المناسبة.
- transactions عبر Service للحفظ المركب على جداول InnoDB؛ يبدأ/ينهي Service المعاملة صراحةً ويضمن rollback عند الخطأ.
- استخدم `wp_json_encode()` للترميز، و`json_decode()` لفك JSON مع فحص `json_last_error()`/`JSON_THROW_ON_ERROR` حسب الموضع؛ لا توجد دالة WordPress Core باسم `wp_json_decode()`.

## TypeScript/React
- TypeScript strict.
- ESLint + Prettier.
- no `any` في domain DTOs إلا adapter موثق.
- API client مركزي.
- DTOs للـREST تُولد من `API.openapi.yaml` أو تُفحص آليًا ضده في CI؛ لا تُحافظ يدويًا على shapes متعارضة. كل GET 200 schema يجب أن ينتج type محددًا لا `unknown`.
- renderer pure قدر الإمكان.
- لا network calls داخل pointermove/animation frame.

## CSS
- logical properties.
- RTL-first interface.
- editor component styles scoped/prefixed `mol-`.
- CSS variables للتوكنز.

## Naming
- DB `mol_*`.
- capabilities/hooks/options `mol_*`.
- REST `/mol/v1`.
- PHP classes PascalCase.
- TS components PascalCase; functions camelCase.

## Dependencies
- أقل عدد ممكن.
- أي dependency محرر مهمة مثبتة lockfile ومذكورة في ADR عند الاستبدال.
## Contract generation rules

- لا تولد OpenAPI باستخدام YAML anchors/aliases؛ serializer النهائي يجب أن يكتب كل response content مستقلًا.
- TypeScript DTO generation/validation ينطلق من `API.openapi.yaml` بعد نجاح `VALIDATION_HARNESS.py`.
- `element_type` في ElementPatch ليس mutable field؛ إذا وصل مع style فهو discriminator للمطابقة فقط.
- لا تُسرب `NULL` لتاريخ attribution في element DTO ما دام DB contract NOT NULL؛ user resolution منفصل عن historical IDs.

