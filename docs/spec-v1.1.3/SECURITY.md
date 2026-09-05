# الأمن

## 1. REST

### 1.1 إخفاء المسودات

موارد الفصل المنشور عامة. إذا كان الفصل غير منشور، `permission_callback`/service policy تسمح بالقراءة فقط لمن لديه سياق REST موثّق و`mol_use_editor` أو `mol_manage_content`. غير ذلك يعاد `404` بدل `403` لمنع resource enumeration وكشف draft IDs. هذه السياسة تمتد إلى الصفحات والعناصر والمساهمين التابعة للفصل.

- `permission_callback` لكل route.
- `current_user_can()` capability محددة.
- WordPress login cookie هي authentication لطلبات same-origin؛ `X-WP-Nonce` (`wp_rest`) مطلوب لطلبات الكتابة كحماية CSRF. nonce وحده لا يمنح هوية أو صلاحية.
- تحقق أن resource ينتمي للفصل/الصفحة المطلوبة لمنع IDOR.
- validation ثم sanitization؛ escape عند output.

## 2. عنصر الترجمة

- content plain text.
- style allowlist schema.
- font IDs من قائمة.
- colors/أرقام ضمن حدود.
- لا `dangerouslySetInnerHTML` لمحتوى المستخدم.
- SVG shapes يولدها renderer من parameters فقط.

## 3. الرفع

- capability `mol_upload_content`.
- `wp_check_filetype_and_ext` + فحص MIME/decoder.
- رفض SVG في upload pages لـMVP.
- حدود dimensions/filesize قابلة للإعداد.
- اسم ملف آمن عبر WordPress APIs.
- معالجة الصورة لا تعتمد على اسم العميل.

## 4. الأقفال والتزامن

- token عشوائي قوي لا يُعرض لمستخدم آخر.
- lock endpoint atomic داخل transaction/locking query.
- renew يتطلب user+token.
- لا يوجد force-unlock route منفصل: `DELETE /elements/{id}/lock` يتطلب token من مالك القفل، أو يسمح لمن يملك `mol_manage_content` بforce-release دون token وفق policy صريحة.
- `If-Match` يتحقق بعد lock وقبل UPDATE. Controller يقرأه من `WP_REST_Request` ويرجع `ETag` strong quoted version في create/update responses.

## 5. Rate limits

أخف على القراءة، وأشد على:
- login ليس من API المشروع.
- reports.
- lock acquire spam.
- uploads.
- element writes.

الحدود **الناعمة** يمكن أن تستخدم Transients/cache. أما حد أمني/تشغيلي يجب أن يكون enforceable بعدد دقيق فلا يعتمد على Transient وحده، لأن WordPress يسمح باختفائه قبل expiry؛ يستخدم عدادًا ذريًا في persistent object cache مناسب أو جدول DB خفيف مع cleanup.

## 6. Headers

- HTTPS.
- CSP تدريجية، على الأقل منع inline غير الضروري في المحرر.
- `Content-Security-Policy: frame-ancestors ...` هي سياسة framing الأساسية لمنع/تقييد embedding بحسب حاجة الموقع؛ ويمكن إضافة `X-Frame-Options` للتوافق القديم إن رغبت البيئة.
- `X-Content-Type-Options: nosniff` مع Content-Type صحيحة للموارد.

## 7. Secrets

- لا nonce/tokens في logs العميل.
- لا expose stack traces في production.
- مفاتيح CDN/خدمات خارجية في environment/wp-config لا في repo.


## 8. Reverse proxy / header integrity

- مسارات write تحت `/wp-json/mol/v1/` لا تُخزن في proxy/CDN cache.
- إعداد Nginx/Apache/CDN يجب أن يحافظ على `If-Match`, `ETag`, `X-WP-Nonce`, و`X-MOL-Lock-Token` كما يلزم.
- اختبار staging يمر عبر **نفس** reverse-proxy/CDN path المستخدم في الإنتاج ويثبت أن `If-Match` يصل إلى WordPress وأن ETag يعود للعميل؛ لا يكفي اختبار PHP مباشرة خلف localhost.
## صرامة العقود

- كل requestBody object يُغلق بـ`additionalProperties:false` أو `unevaluatedProperties:false`; لا يعتمد الأمان على تجاهل الحقول الغريبة.
- Element style يُتحقق منه مرتين: allowlist العامة ثم قواعد النوع المخزن. في style PATCH يرسل `element_type` كـdiscriminator ثابت ويُرفض إن خالف العنصر.
- `413 mol_payload_too_large` جزء من عقد الرفع، بالإضافة إلى limits الخادم.
- YAML OpenAPI المحزّم بلا anchors/aliases لتجنب مشاركة أمثلة/عقود بالخطأ بين status codes.

