# معالجة الأخطاء

## 1. حالات المحرر

| الحالة | UI |
|---|---|
| dirty | نقطة/نص «تغييرات غير محفوظة» |
| saving | spinner «جارٍ الحفظ» |
| saved | check «تم الحفظ» |
| offline | «غير متصل — لم تُرسل تغييرات هذه الجلسة» |
| locked | اسم الشخص الذي يحرر العنصر + read-only |
| conflict | بطاقة مقارنة/إعادة تطبيق |
| error | رسالة قابلة لإعادة المحاولة |

## 2. Network retry

- GET: retry محدود exponential jitter.
- PATCH autosave: retry تلقائي مرة واحدة فقط لأخطاء شبكة مؤقتة **إذا كان lock/version ما زالا صالحين**؛ وإلا إعادة فحص الحالة.
- 4xx validation/permission لا يُعاد تلقائيًا.
- upload/POST حساس: يمكن retry آمن باستخدام عقد التطبيق `MOL-Idempotency-Key` عند المسارات التي تدعمه.

## 3. رسائل HTTP

- 401: اطلب إعادة تسجيل الدخول/refresh session.
- 403: لا صلاحية.
- 412: conflict workflow عندما `If-Match` قديم.
- 423: acquire/update workflow عندما عنصر عليه lock نشط لمحرر آخر.
- 409 `mol_lock_lost`: renew/release token لم يعد lease الحالي؛ أوقف autosave على العنصر وأعد acquire بدل retry أعمى.
- 413: الملف/الطلب أكبر من الحد؛ لا retry بنفس payload.
- 428: الطلب يحتاج `If-Match` ولم يرسله العميل؛ أعد جلب العنصر/ETag ثم أعد المحاولة.
- 429: backoff مع Retry-After إن توفر.

## 4. Recovery داخل الجلسة

إذا فشل الحفظ بسبب network، تبقى state في ذاكرة التطبيق ويستطيع المستخدم إعادة المحاولة ما دام التبويب مفتوحًا. لا يدعي MVP أنه يحفظ تغييرات غير مرسلة بعد إغلاق المتصفح.


## 5. أخطاء عقود جديدة

- `400 mol_sort_unavailable`: طلب `most_read` بينما backend عداد القراءة غير مفعل.
- `400 mol_invalid_reorder`: `page_ids` ليست permutation كاملة للفصل؛ لا يحدث تعديل جزئي.

- `401 mol_not_authenticated`: write/private request بلا جلسة WordPress REST صالحة.
- `429 mol_rate_limited`: فقط للمسارات التي يطبق عليها limiter فعلي؛ يعيد `Retry-After` عندما يستطيع الخادم حسابه.
- `403 mol_forbidden`: reviewer يحاول استخدام review route بلا `mol_review_translations`.
- force-release lock لا يحتاج code جديد؛ نفس DELETE ينجح فقط وفق owner-token أو `mol_manage_content`.
- `409 mol_lock_lost`: token القفل انتهى أو أزيل أو استُبدل؛ لا يُعامل كـpermission error.
- `409 mol_slug_conflict`: تعذر توليد/حجز slug فريد للفصل بعد عدد retries المحدد.
- `413 mol_payload_too_large`: الرفع تجاوز حد الحجم الفعلي.
- أمثلة error في OpenAPI ملزمة دلاليًا: `data.status` يجب أن يساوي مفتاح HTTP response، ولا تستخدم الحزمة YAML aliases لمشاركة content بين استجابات مختلفة.

