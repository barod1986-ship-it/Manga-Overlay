# تقرير تقوية Schema / Error Contract — v1.1.3

الإصدار: `1.1.3`  
التاريخ: `2026-09-01`  
الحالة: **Schema & Error Contract Hardened**

## 1. سبب الإصدار

مراجعة مستقلة لـv1.1.2 كشفت أن YAML alias واحدًا جعل أمثلة `Unauthorized`, `InvalidReorder`, و`LibraryBadRequest` ترث مثال `429 mol_rate_limited`. كما ظهرت ثغرات متوسطة في صرامة `ElementPatch`, حالات الأقفال، 413/404، slug الفصل، nullability التاريخية، وAVIF المشروط.

## 2. الإصلاحات المغلقة

### YAML / error examples
- أزيلت جميع YAML anchors/aliases من `API.openapi.yaml`.
- كل response component يملك content مستقلًا؛ لا مشاركة alias بين status codes.
- `Unauthorized` → `401 mol_not_authenticated`.
- `InvalidReorder` → `400 mol_invalid_reorder`.
- `LibraryBadRequest` يملك مثالين مستقلين: `mol_invalid_params` و`mol_sort_unavailable`, كلاهما 400.
- أضيف validator دلالي يتأكد أن `example.data.status == response status key` بعد حل `$ref`.

### Element write contracts
- `ElementCreate`: `unevaluatedProperties:false` عبر composition.
- `ElementPatch`: `additionalProperties:false` + منع `{}`/no-op.
- حدود `rotation_mdeg` و`z_index` مطابقة لـ`Geometry`.
- style PATCH يتطلب `element_type` كـimmutable discriminator؛ `element_type` وحده لا يعد تعديلًا.
- قيود style النوعية أصبحت machine-readable:
  - bubble: `ellipse|rounded_rect|rect|cloud`, يسمح tail، يمنع burst/scale.
  - narration: `rect|rounded_rect`, يمنع tail/burst/scale.
  - free_text: `none|rect|rounded_rect`, يمنع tail/burst/scale.
  - sfx: `none|burst|impact`, يسمح burst/scale، يمنع tail.

### Errors / missing outcomes
- Upload يعلن `413 mol_payload_too_large`.
- Lock acquire/renew/release تعلن `404` للعنصر المفقود.
- Renew/release للمالك يعيدان `409 mol_lock_lost` عندما lease token منتهي/مزال/مستبدل.
- `GET /works/{id}/chapters` يعلن 404 للعمل غير الموجود.
- `PATCH/DELETE /presets/{id}` و`PATCH /reports/{id}` تعلن 404.

### Chapter slug
- `Chapter.slug` أصبح required في response schema، بما يطابق DB `NOT NULL`.
- `ChapterCreate` لا يفتح slug للعميل؛ الخادم يولده من title أو `chapter-<chapter_label>` عبر `sanitize_title()`.
- suffix collisions: `-2`, `-3`, ... مع retries محدودة و`uq_work_slug` حارس race-safe.
- تعذر الحجز بعد retries → `409 mol_slug_conflict`.

### Attribution nullability
- `Element.created_by`, `updated_by`, `created_at`, `updated_at` أصبحت non-null ومطلوبة في العقد، بما يطابق DB.
- حذف حساب WordPress لا يمحو IDs التاريخية؛ resolver الواجهة قد يعرض المستخدم كـdeleted/unavailable.

### AVIF capability
- baseline upload الثابت: JPEG/PNG/WebP.
- AVIF لم يعد وعدًا مطلقًا في multipart encoding.
- أضيف `GET /capabilities` ليعلن `upload_mime_types`, `derived_image_formats`, و`most_read_available` وفق البيئة.

### ETag/version
- create/update يعيدان HTTP ETag quoted version.
- collection GETs تعيد `version` داخل كل عنصر بدل ETag مستقل لكل child.
- الصيغة الملزمة: `version=7` → `If-Match: "7"`.

## 3. خطأ إضافي التقطه الفاحص أثناء الإصلاح

بعد جعل audit timestamps في `Element` non-null، فشل مثال `ChapterElementsResponse` لأنه افتقد `created_at`. تم تحديث المثال، ثم مر التحقق؛ هذا يثبت أن فحص الأمثلة أصبح يمنع انجراف docs عن العقد.

## 4. تغييرات OpenAPI

- Paths: **22**.
- Operations: **31** (12 GET + 19 write).
- Schemas: **57**.
- Response components: **15**.
- Parameter components: **8**.
- `$ref` occurrences في YAML الخام والمحلل: **241 / 241**، بلا alias inflation.
- كل 57 schema و15 response و8 parameter components مستخدمة.
- أكواد API المركزية: **15** وكل واحد مستخدم في operation response example واحدة على الأقل.

## 5. Validation harness

أضيف `VALIDATION_HARNESS.py` ويمنع Freeze إذا فشل أي من الآتي:

1. duplicate YAML key.
2. YAML anchor/alias.
3. OpenAPI version/info version.
4. operationId uniqueness.
5. broken internal `$ref`.
6. dead schema/response/parameter components.
7. GET بدون 200 JSON schema.
8. error example status لا يطابق HTTP key.
9. requestBody object غير closed.
10. error code في API_SPEC غير مستخدم.
11. ElementStyle mirror drift.
12. schema-tagged data example drift.
13. ElementPatch empty/unknown/bounds/type-style negative cases.
14. 413/404/409 lock/slug contract presence.
15. runtime capabilities وAVIF baseline rules.
16. write security/policy/400 completeness.
17. rate-limit set مطابق لمسارات MVP الستة فقط.
18. عدم إعادة إدخال المتطلب التشغيلي المستبعد في canonical docs.

النتيجة قبل التغليف: **49 PASS / 0 FAIL**.

## 6. ما لم يتغير

لا تغيير في المعمارية الرئيسية: WordPress + custom plugin/theme، React editor فقط، DOM/SVG overlay، Moveable، element-level locks، normalized integer geometry، webtoon+paged، presets، ولا revision history لمحتوى الترجمة.

## 7. القرار

`v1.1.3` يحل مشاكل العقد التي يمكن التقاطها ساكنًا. المخاطر الرئيسية المتبقية أصبحت Runtime/PoC: T-01 العربية/SFX، T-02 Moveable touch، dbDelta على المحركين، وproxy/header smoke tests.
