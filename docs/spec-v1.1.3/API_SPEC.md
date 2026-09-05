# REST API

Namespace: `/wp-json/mol/v1`

## 1. المصادقة

- Public GET: بلا مصادقة عند المورد المنشور.
- Private/write: **WordPress login cookie هو authentication**، و`X-WP-Nonce` (`wp_rest`) حماية CSRF/إثبات سياق same-origin؛ nonce وحده ليس authentication.
- كل route له `permission_callback`.
- lock token عبر `X-MOL-Lock-Token`.
- optimistic version عبر `If-Match: "{version}"`. عند غياب الشرط في endpoint يتطلبه العقد: `428 Precondition Required`; عند وجوده لكنه قديم: `412 Precondition Failed`.

## 2. الاستجابة

كل GET عام وعمليات الكتابة التي تعيد موردًا لها **response schema صريح** في `API.openapi.yaml`. هذا العقد هو مصدر DTOs؛ لا يعتمد العميل على `description: OK` أو shape غير موثق.

نجاح resource:
```json
{"data": {}, "meta": {}}
```

خطأ WordPress-style:
```json
{"code":"mol_forbidden","message":"...","data":{"status":403}}
```

## 3. Public

| Method | Route | الوصف |
|---|---|---|
| GET | `/library` | أعمال مع بحث وفلاتر |
| GET | `/capabilities` | قدرات وقت التشغيل: صيغ الرفع/الإخراج والميزات الاختيارية |
| GET | `/works/{id}` | تفاصيل عمل |
| GET | `/works/{id}/chapters` | فصول منشورة |
| GET | `/chapters/{id}` | بيانات فصل |
| GET | `/chapters/{id}/pages` | صفحات الفصل |
| GET | `/chapters/{id}/elements?lang=ar` | عناصر الفصل دفعة واحدة، مجمعة حسب الصفحة |
| GET | `/pages/{id}/elements?lang=ar` | عناصر صفحة واحدة |
| GET | `/chapters/{id}/contributors` | المساهمون |
| GET | `/profiles/{username}` | الملف العام |


### GET `/library`

Query contract:
- `search`
- `type`
- `genre[]`
- `source_lang`
- `work_status`
- `translation_status` (`untranslated|in_progress|completed|needs_review`) — يطابق الأعمال التي لديها فصل منشور واحد على الأقل بهذه الحالة.
- `sort=latest_chapter|latest_work|title_asc|most_read`
- `page` و`per_page` (حد أقصى 100).

الاستجابة `WorkListResponse` وفي `meta`: page/per_page/total/total_pages، و`most_read_available`. إذا لم يوجد backend عداد قراءات، تخفي الواجهة `most_read` ويمكن للخادم رفضه بخطأ `mol_sort_unavailable`.


### GET `/capabilities`

Public runtime contract. يعيد `RuntimeCapabilities`: `upload_mime_types`, `derived_image_formats`, و`most_read_available`. العميل لا يستنتج AVIF من إصدار WordPress وحده؛ يعرض/يرسل AVIF فقط إذا ظهر `image/avif` في `upload_mime_types`.

### GET `/chapters/{id}/elements?lang=ar`

يعيد `ChapterElementsResponse`: array مجمعة حسب `page_id/page_index`، وكل عنصر يحتوي `version` اللازمة لأي تعديل لاحق. هذا هو المسار المفضل للقارئ عندما تكون دفعة الفصل مناسبة للحجم/cache، ويمنع نمط N requests لكل صفحة لمجرد جلب overlay.

### رؤية الفصل المنشور والمسودة

المسارات `GET /chapters/{id}`, `/chapters/{id}/pages`, `/chapters/{id}/elements`, `/pages/{id}/elements`, و`/chapters/{id}/contributors` تطبق policy واحدة:
- إذا كان `is_published=1`: القراءة عامة.
- إذا كان `is_published=0`: القراءة مسموحة فقط إذا كان الطلب يحمل سياق WordPress REST موثّقًا والمستخدم يملك `mol_use_editor` أو `mol_manage_content`.
- أي caller آخر يحصل على `404 mol_not_found`، لا `403`، لتجنب كشف وجود draft IDs.
- `/works/{id}/chapters` يبقى collection عام للفصول المنشورة فقط ولا يعرض المسودات حتى للمستخدم الموثّق.

## 4. Editor

| Method | Route | Capability |
|---|---|---|
| POST | `/elements` | `mol_edit_translations` |
| PATCH | `/elements/{id}` | `mol_edit_translations` + lock |
| DELETE | `/elements/{id}` | `mol_delete_translation_elements` + lock |
| POST | `/elements/{id}/lock` | `mol_edit_translations` |
| PUT | `/elements/{id}/lock` | `mol_edit_translations` + token |
| DELETE | `/elements/{id}/lock` | owner token أو manager |

### POST `/elements`

Body: page_id, target_lang, element_type, geometry, content، و`preset_id`/`style` اختياريان.

حل النمط على الخادم: preset صريح → personal default → work default → global default → built-in base style، ثم `style` الجزئي override أخيرًا. الاستجابة تعيد **resolved style** كاملة و`201` + `ETag: "1"`.

### PATCH `/elements/{id}`

Headers:
- `If-Match: "7"`
- `X-MOL-Lock-Token: ...`

Body جزئي ومغلق (`additionalProperties:false`). لا يقبل `{}` ولا payload يحتوي فقط `element_type`. حدود geometry مطابقة لـcreate.

عند تعديل `style` يجب إرسال `element_type` كـ **immutable discriminator**؛ الخادم يتحقق أنه يساوي النوع المخزن ولا يغيّره. هذا يسمح للعقد بتطبيق قيود style الخاصة بـbubble/narration/free_text/sfx. عند النجاح الاستجابة `ElementResponse` وتعيد `ETag: "8"`.

تنفيذ WordPress: Controller يقرأ `If-Match` من `WP_REST_Request::get_header()` ويضيف `ETag` إلى `WP_REST_Response`. لا يعتمد Service على وصول الهيدر بصورة ضمنية من PHP globals.

عند جلب العناصر عبر collection (`/pages/{id}/elements` أو `/chapters/{id}/elements`) لا يوجد HTTP ETag منفصل لكل child. كل عنصر يعيد `version`; يبني العميل القيمة المطلوبة حرفيًا: `version=7` → `If-Match: "7"`.

### DELETE `/elements/{id}/lock`

- مالك القفل يرسل `X-MOL-Lock-Token`.
- caller يملك `mol_manage_content` يمكنه force-release القفل دون token.
- **لا يوجد endpoint force-unlock منفصل.**
- renew/release العاديان: إذا كان token منتهيًا/مستبدلًا/لم يعد lease الحالي → `409 mol_lock_lost`; إذا كان العنصر نفسه غير موجود → `404 mol_not_found`.

## 5. Presets

| Method | Route | الصلاحية |
|---|---|---|
| GET | `/presets?work_id=&type=` | editor |
| POST | `/presets` | editor personal؛ capabilities إضافية للنطاقات الأخرى |
| PATCH | `/presets/{id}` | owner أو manager حسب scope |
| DELETE | `/presets/{id}` | owner أو manager حسب scope |

### دلالة `is_default`

لنوع عنصر واحد يمكن أن يوجد default فعال واحد لكل نطاق فعلي: `(personal, owner, type)` أو `(work, work_id, type)` أو `(global, type)`. عند جعل preset افتراضيًا، Service داخل transaction يلغي default السابق في النطاق نفسه ثم يثبت الجديد. أولوية الاختيار عند إنشاء عنصر: personal → work → global → built-in.

## 6. التقارير

### المسارات ذات rate limit الإلزامي في MVP

`429 mol_rate_limited` موثق فقط للمسارات التي يطبق عليها limiter فعلي: رفع صفحة، إنشاء/تعديل/حذف عنصر، acquire lock، وإنشاء بلاغ. renew/release lock وعمليات الإدارة الأخرى لا تُعلن `429` ما لم يضاف limiter حقيقي لاحقًا.

- POST `/reports`: member capability `mol_report_issue`.
- GET `/reports`: `mol_moderate_reports`.
- PATCH `/reports/{id}`: `mol_moderate_reports`.

## 7. التقدم

PUT `/reading-progress`
Body: chapter_id, page_index, progress_unit, reader_mode.

## 8. إدارة الفصول والصفحات

- PATCH `/chapters/{id}/review` — `mol_review_translations`; body `translation_status=needs_review|completed`. لا يمنح هذا المسار صلاحيات إدارة المحتوى العامة.

- POST `/chapters` — `mol_manage_content`. `slug` لا يرسله العميل: يولده الخادم من `title` أو `chapter_label`, ويضيف suffix عند التصادم؛ إذا تعذر حجز slug فريد بعد retries المحدودة → `409 mol_slug_conflict`.
- PATCH `/chapters/{id}` — `mol_manage_content`.
- DELETE `/chapters/{id}` — `mol_manage_content`.
- POST `/chapters/{id}/pages` multipart image — `mol_upload_content` + تحقق أن المستخدم مخول لهذا الفصل/المحتوى حسب policy. baseline: JPEG/PNG/WebP؛ AVIF فقط عندما `/capabilities` يعلنه. الحجم المتجاوز → `413 mol_payload_too_large`.
- PATCH `/chapters/{id}/pages/reorder` — `mol_manage_content`. `page_ids` يجب أن تكون permutation كاملة لصفحات الفصل. داخل transaction: `SELECT ... FOR UPDATE`، ثم نقل كل `page_index` إلى نطاق مؤقت منفصل، ثم تعيين 0..N-1 لتفادي تصادم unique index.
- DELETE `/pages/{id}` — `mol_manage_content`.

## 9. عقود request bodies الإدارية

المصدر الآلي الملزم هو `API.openapi.yaml`. كل GET العام له response schema، وأهم request/response schemas:

- إنشاء فصل: `ChapterCreate`; التعديل: `ChapterPatch`.
- ترتيب الصفحات: `PageReorder` (`page_ids` مرتبة وفريدة).
- إنشاء/تعديل preset: `PresetCreate` / `PresetPatch`.
- إنشاء/معالجة بلاغ: `ReportCreate` / `ReportPatch`.
- حفظ موضع القراءة: `ReadingProgressUpdate`.
- القراءة: `WorkListResponse`, `WorkResponse`, `ChapterResponse`, `ChapterListResponse`, `PageListResponse`, `PageElementsResponse`, `ChapterElementsResponse`, `ContributorListResponse`, `ProfileResponse`.
- `x-required-capability` في OpenAPI يوثق capability/policy المطلوبة آليًا؛ التحقق الفعلي يبقى في `permission_callback` على الخادم.

## 10. أكواد الأخطاء

| HTTP | code | المعنى |
|---|---|---|
| 400 | `mol_invalid_params` | validation |
| 400 | `mol_sort_unavailable` | طلب `most_read` بينما backend عداد القراءة غير منفذ |
| 400 | `mol_invalid_reorder` | page_ids ليست permutation كاملة صالحة للفصل |
| 401 | `mol_not_authenticated` | جلسة مطلوبة |
| 403 | `mol_forbidden` | capability/ownership |
| 404 | `mol_not_found` | غير موجود |
| 409 | `mol_idempotency_mismatch` | أُعيد استخدام مفتاح idempotency مع payload مختلف |
| 409 | `mol_lock_lost` | lock token لم يعد يمثل lease نشطًا |
| 409 | `mol_slug_conflict` | تعذر حجز slug فريد للفصل بعد retries محدودة |
| 412 | `mol_version_conflict` | If-Match موجود لكنه لا يطابق النسخة الحالية |
| 428 | `mol_precondition_required` | endpoint يتطلب If-Match ولم يُرسل |
| 413 | `mol_payload_too_large` | حجم |
| 415 | `mol_unsupported_media` | نوع ملف |
| 423 | `mol_element_locked` | قفل نشط لآخر |
| 429 | `mol_rate_limited` | معدل طلبات |

## 11. Idempotency

- طلبات POST الحساسة للتكرار في MVP (`POST /elements` ورفع صفحة الفصل) **تتطلب** header داخليًا باسم `MOL-Idempotency-Key` (حتى 100 حرف). هذا **عقد خاص بالمشروع**؛ لا نصفه كمعيار HTTP منشور.
- المفتاح يُخزن مؤقتًا في `mol_idempotency_keys` مع hash للطلب والنتيجة/المورد؛ لذلك الضمان يعمل حتى مع retry متزامن، وليس مجرد فحص client-side.
- نفس المفتاح ونفس payload يعيدان النتيجة السابقة؛ نفس المفتاح مع payload مختلف يعيد `409 mol_idempotency_mismatch`.
- لا يعيد العميل تلقائيًا POST بدون مفتاح بعد خطأ شبكة غامض؛ يعيد نفس الطلب بنفس المفتاح.

## 12. تطبيع أنواع Repository/DTO

`mol_chapters.sort_order` مخزن `DECIMAL(14,4)` وقد يصل من mysqli/`$wpdb` كنص. `ChapterRepository` يحوله صراحةً إلى `(float)` قبل بناء DTO/REST response، لأن OpenAPI يعرف `sort_order` كـ`number`. نفس القاعدة تطبق على أي DECIMAL يضاف مستقبلًا: لا يُسرّب representation خاص بقاعدة البيانات إلى عقد JSON.
