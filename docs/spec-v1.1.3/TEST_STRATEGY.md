# استراتيجية الاختبار

## 1. Unit — PHP

- geometry validator/clamp.
- style schema validator لكل element type.
- chapter status transitions + review endpoint policy (`mol_review_translations` دون manage-content).
- lock acquire/renew/release/expiry.
- version compare.
- contribution UPSERT uniqueness.
- preset scope authorization + single-default transaction semantics + resolution precedence.

## 2. REST integration

لكل write route:
- unauthenticated 401.
- authenticated without cap 403.
- valid success.
- invalid entity ownership/relationship.
- invalid payload 400.

Element:
- lock by A، attempt B →423.
- valid lock but stale If-Match →412.
- missing required If-Match →428.
- different elements on same page can be locked by A/B simultaneously.

Read contracts:
- كل GET 200 response يطابق OpenAPI schema.
- `/library` يغطي genre/work_status/translation_status/sort/page/per_page، ويعيد pagination صحيحة.
- chapter batch elements يساوي اتحاد page element responses لنفس الفصل/اللغة.

- draft visibility: الفصل المنشور يعاد للعامة؛ draft GET بلا سياق موثّق أو لعضو بلا editor/manage-content يعيد 404؛ editor أو content manager مع سياق REST صالح يقرأ chapter/pages/elements/contributors.
- `/works/{id}/chapters` لا يعرض drafts حتى في request موثّق.

Reorder:
- swap صفحتين، reverse، random permutation ينجح دون duplicate key.
- payload ناقص/ID من فصل آخر/duplicate يرفض وتبقى indices الأصلية بعد rollback.

ETag/proxy:
- create/update element يعيدان ETag quoted version.
- integration عبر reverse proxy staging يثبت وصول `If-Match`؛ missing→428، stale→412.
- owner lock release requires token؛ `mol_manage_content` force-release ينجح عبر نفس DELETE route.

Upload:
- valid JPEG/PNG/WebP، وAVIF فقط عندما المسار/المعالج المعلن يدعمه.
- JPEG→WebP derivative يُختبر كوظيفة MOL صريحة عند تفعيلها؛ لا نفترض conversion افتراضي من WordPress.
- fake extension rejected.
- oversized rejected.
- same `MOL-Idempotency-Key` + same payload does not duplicate; same key + different payload →409.

## 3. Frontend unit/component

- renderer maps DTO→DOM/SVG.
- normalized geometry conversions.
- auto-fit.
- preset application.
- save state machine.
- conflict UI.
- built-in Base Styles + preset default precedence.

## 4. E2E

Playwright desktop (Node 24 LTS مدعوم في بيئة الاختبار):
- library → work → chapter.
- toggle translation.
- webtoon↔paged.
- editor create all four types.
- drag/resize/rotate.
- autosave/reload retains state.
- contributor appears once even after many saves.

## 5. أجهزة حقيقية

قبل اكتمال مرحلة المحرر:
- iOS Safari حديث.
- Android Chrome حديث.

اختبارات:
- Arabic input.
- keyboard + bottom sheet.
- touch handles.
- pinch zoom.
- Moveable drag/resize/rotate.
- نفس transform يمكن تنفيذه بمدخلات/أزرار دون drag.
- SFX Arabic stroke visual regression على الخطوط المعتمدة في Chrome/Firefox/Safari وiOS/Android.
- preview.

## 6. Visual regression

مجموعة صفحات مرجعية:
- bubble عربي متعدد الأسطر.
- narration.
- SFX مع stroke/rotation.
- mobile/desktop.

صور screenshot comparison مع tolerance مناسبة لفرق font rendering.

## 7. Performance

- Lighthouse/WebPageTest staging.
- API p95 من sample dataset كبير.
- editor with 100 elements/page stress test.


## 8. Contract validation
- parse `API.openapi.yaml` مع loader يرفض duplicate YAML keys، ثم OpenAPI 3.1 validator.
- كل `$ref` داخلي resolved.
- كل GET operation لها 200 response content schema.
- لا canonical capability بلا استخدام في policy/route إلا إذا وُسمت future صراحةً (لا يوجد ذلك في MVP).
- `ELEMENT_STYLE.schema.json` Draft 2020-12 صالح، والأمثلة تمر عليه.

- كل secured operation يعلن `401` و`403`; كل write operation ذات request body تعلن `400`.
- `429` يظهر فقط في قائمة المسارات ذات rate limiter الفعلي.
- `InvalidParams`, `Unauthorized`, `RateLimited`, `mol_sort_unavailable`, و`mol_invalid_reorder` ليست تعريفات ميتة: لكل منها استخدام عقدي واختبار.
- أمثلة `DATA_MODEL_EXAMPLES.md` الموسومة بمخطط OpenAPI تمر على schema المقابل، بما فيها `WorkSummary`, `Chapter`, `Page`, `Element`, `Preset`, `WorkListResponse`, و`ChapterElementsResponse`.
- migration SQL يُشغّل عبر `dbDelta()` فعليًا على MySQL 8.4 وMariaDB 10.11، وليس parse نصي فقط.
## Contract semantic gates — v1.1.3

- يفشل البناء إذا احتوى `API.openapi.yaml` YAML anchor أو alias.
- كل error example تحت status رقمي يجب أن يملك `data.status` مساويًا له، بما في ذلك component response بعد حل `$ref`.
- كل schema مستخدم كـrequestBody object يجب أن يكون closed (`additionalProperties:false` أو `unevaluatedProperties:false`).
- كل code في جدول `API_SPEC §10` يجب أن يظهر في مثال response لعملية واحدة على الأقل.
- اختبارات ElementPatch: `{}` يفشل؛ unknown property يفشل؛ rotation/z-index خارج الحدود يفشلان؛ style بدون `element_type` يفشل؛ style لا يناسب النوع يفشل.
- lock: acquire/renew/release على element غير موجود =404؛ renew/release token منتهي/مستبدل =409 `mol_lock_lost`.
- upload oversized =413؛ AVIF = accepted فقط إذا `/capabilities` يعلنه، وإلا 415.
- chapter slug: generation، collision suffix، وسباق متزامن مع unique index.

