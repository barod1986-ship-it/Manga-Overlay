# API & Visibility Hardening Report — v1.1.2

تاريخ: `2026-09-01`

هذا الإصدار لا يغير رؤية المنتج أو المعمارية الأساسية. هدفه إغلاق ثغرات عقدية ظهرت بعد مراجعة مستقلة لـv1.1.1.

## 1. Error contracts

تم تحديث `API.openapi.yaml` بحيث:
- كل write operation محمية تعلن `401` و`403`.
- كل write operation ذات `requestBody` تعلن `400`.
- `429` موجود فقط على routes ذات limiter فعلي في MVP: رفع صفحة، إنشاء/تعديل/حذف عنصر، acquire lock، وإنشاء بلاغ.
- أضيفت/فُعّلت responses: `Unauthorized`, `InvalidParams`, `RateLimited`, `InvalidReorder`, `LibraryBadRequest`.
- `RateLimited` يوثق `Retry-After` عندما يستطيع الخادم توفيره.

## 2. Library errors وmost_read

- `sort=most_read` يبقى جزءًا من العقد كقدرة شرطية.
- MVP لا يملك read-counter backend؛ لذلك `most_read_available=false` و`read_count=null`.
- طلب `most_read` قبل تفعيل backend يعيد `400 mol_sort_unavailable`.
- لا أضيف جدول قراءة وهميًا لمجرد إرضاء العقد؛ الميزة معلنة بوضوح كغير منفذة في MVP.

## 3. Draft visibility

تم تثبيت policy واحدة للـchapter descendants:
- `is_published=1` → قراءة عامة.
- `is_published=0` → قراءة فقط في سياق WordPress REST موثّق لمن يملك `mol_use_editor` أو `mol_manage_content`.
- غير ذلك → `404 mol_not_found` بدل `403` لتجنب كشف draft IDs.

المسارات المشمولة:
- `GET /chapters/{id}`
- `GET /chapters/{id}/pages`
- `GET /chapters/{id}/elements`
- `GET /pages/{id}/elements`
- `GET /chapters/{id}/contributors`

أما `GET /works/{id}/chapters` فيبقى collection عام للفصول المنشورة فقط.

## 4. Data examples وDTO normalization

- أُعيدت كتابة `DATA_MODEL_EXAMPLES.md` لتطابق OpenAPI الحالي.
- `work_type` القديمة استبدلت بـ`type`.
- Page example يستخدم `image: ImageResource` بدل `attachment_id` في الجذر.
- `sort_order` في JSON رقم، لا string.
- `ChapterRepository` ملزم بتحويل قيمة `DECIMAL(14,4)` الخام إلى `float` قبل DTO/REST serialization.
- الأمثلة الموسومة `schema:` تُفحص آليًا مقابل OpenAPI.

## 5. Reorder/library error codes

تم توحيد الأكواد التي كانت في `ERROR_HANDLING.md` فقط:
- `400 mol_invalid_reorder`
- `400 mol_sort_unavailable`

وأصبحت ممثلة في OpenAPI و`API_SPEC.md` واختبارات العقد.

## 6. Editor spec typo

تم إصلاح ترقيم Default preset resolution من `1,2,6` إلى `1,2,3` فقط؛ لا تغيير في السلوك.

## 7. Composition bug اكتُشف أثناء الفحص

أثناء validation للأمثلة ظهر خلل إضافي في OpenAPI:
- `WorkDetail` كان يوسع `WorkSummary` عبر `allOf` بينما `WorkSummary` يملك `additionalProperties:false`، فيرفض خصائص detail الإضافية.
- `LibraryMeta` كان يوسع `PaginationMeta` بالطريقة نفسها، فيرفض `sort` و`most_read_available`.

تم استبدال هذين التركيبين بمخططات object صريحة تجمع الخصائص المطلوبة دون تعارض `additionalProperties`.

## 8. ما لم يتغير

لا تغيير في:
- WordPress + custom theme + custom plugin.
- React/TypeScript للمحرر فقط.
- DOM/SVG + Moveable.
- element-level locking.
- ETag/If-Match.
- Presets/Base Styles.
- Webtoon + Paged.
- عدم وجود Version History أو Offline durable queue في MVP.

## 9. Runtime gates الباقية

لا تزال تحتاج تنفيذًا فعليًا وليست قابلة للإغلاق بالوثائق:
- T-01: العربية + SFX stroke/auto-fit بصريًا.
- T-02: Moveable/Touch على iOS Safari وAndroid Chrome فعليين.
- migration عبر `dbDelta()` على MySQL 8.4 وMariaDB 10.11.
- مرور `If-Match`/`ETag` عبر reverse proxy/CDN الفعلي.

## 10. نتيجة الفحص الساكن

بعد التعديلات: **62/62 PASS، 0 FAIL**. التفاصيل في `VALIDATION_REPORT.md`.
