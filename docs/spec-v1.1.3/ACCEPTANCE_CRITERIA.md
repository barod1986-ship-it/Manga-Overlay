# شروط القبول

## AC-01 المكتبة
- البحث وكل فلاتر FR-LIB-003 والترتيبات FR-LIB-004 تعمل وتظهر في URLs قابلة للمشاركة.
- `page/per_page` يعيدان pagination metadata صحيحة، و`per_page` يحترم الحد الأقصى.
- كل GET عام في OpenAPI لديه 200 response schema؛ generated/validated TypeScript DTO لا يعتمد على `unknown` shape.

## AC-02 القارئ

- **رؤية المسودة:** draft chapter/resource يعيد 404 للعامة ولعضو غير مخول، ويعمل لنفس route لمستخدم يملك `mol_use_editor` أو `mol_manage_content` مع سياق REST صالح.
- `webtoon` و`paged` يعملان في MVP.
- paged يحترم RTL/LTR للعمل.
- toggle الترجمة فوري ولا يغير الصورة الأصلية.
- chapter overlay batch يعيد كل العناصر للمجال اللغوي مجمعة حسب الصفحة، ويطابق مجموع نتائج page endpoints لنفس الفصل/اللغة.
- عنصر يظهر في الموضع النسبي نفسه على widths 360/768/1440 ضمن tolerance مرئية.

## AC-03 العربية
- النص متصل ويعرض RTL صحيحًا على Chrome/Firefox/Safari وiOS/Android المستهدفين.
- لا HTML من المحتوى ينفذ.

## AC-04 المحرر
- الأنواع الأربعة تنشأ وتعدل وتحذف.
- drag/resize/rotate/z/font/colors/border/shape تعمل وتسترجع بعد reload.
- SFX stroke/rotation يعملان، والنص العربي لا يظهر بكسور وصلات غير مقبولة بصريًا على مجموعة المتصفحات/الأجهزة المستهدفة؛ عند فشل stroke يستخدم preset/fallback معتمد.
- preview يطابق reader renderer.

## AC-05 Auto-fit/Snapping
- auto-fit يمنع overflow ضمن الحد الأدنى المحدد ولا يغير box.
- snapping يظهر guides ويمكن محاذاة عنصر إلى مركز الصفحة/عنصر آخر.

## AC-06 Presets
- مترجم يحفظ personal preset ويطبقه.
- work preset يظهر لمحرري العمل فقط.
- global preset يظهر لكل المحررين.
- preset لا يغير content أو geometry.
- لكل scope فعلي/type يوجد default فعال واحد؛ تعيين default جديد يلغي السابق atomically.
- عنصر جديد بلا preset/style يخرج بـBase Style المدمجة، وبوجود defaults تكون الأولوية personal→work→global→built-in، ثم explicit style override.

## AC-07 Autosave
- text/style dirty يحفظ عادة خلال ~2s بعد التوقف على شبكة سليمة.
- transforms لا ترسل writes أثناء كل frame.
- reload بعد `saved` يعرض آخر نسخة.

## AC-08 التزامن
- A يقفل element 1، B لا يستطيع تعديل element 1 →423.
- B يستطيع تعديل element 2 في الصفحة نفسها.
- stale If-Match →412 ولا يحدث lost update.
- حذف/تحديث endpoint مشروط بدون `If-Match` →428.
- ETag يعاد على create/update ويصل If-Match عبر reverse proxy إلى WordPress في staging.
- مالك القفل يحتاج token للتحرير؛ مستخدم `mol_manage_content` يستطيع force-release عبر نفس DELETE route دون endpoint إضافي.

## AC-09 المساهمات
- 10 autosaves لنفس element من نفس user = مساهمة فريدة واحدة.
- تحرير مستخدم ثانٍ لنفس element يضيف مساهمًا ثانيًا دون Version History.

## AC-10 الصلاحيات

- كل write route المحمي يعلن ويختبر 401/403؛ كل write body يعلن ويختبر 400؛ و429 موجود فقط للمسارات ذات limiter فعلي.
- `mol_sort_unavailable` و`mol_invalid_reorder` موثقان في OpenAPI/API_SPEC ويظهران في الاختبارات المناسبة.
- عضو بلا editor cap لا يصل للمحرر ولا writes.
- منح `mol_upload_content` يتيح رفع الملفات فقط ولا يمنح إدارة الإعدادات. منح `mol_manage_content` يتيح إدارة الأعمال/الفصول/الترتيب دون منح إعدادات النظام.

## AC-11 الرفع
- upload صالح ينشئ attachment + page row.
- عند تفعيل WebP derivative، رفع JPEG ينتج مشتق WebP عبر MOL إذا كان image editor يدعمه؛ وإلا يُسجل fallback واضح دون فشل الفصل.
- ملف مزيف النوع يرفض.
- reorder يحافظ على unique page_index حتى في swap/reverse كامل؛ خوارزمية النطاق المؤقت تمنع duplicate-key ولا تترك indices مؤقتة بعد commit/rollback.

## AC-12 الجوال
- كل controls الأساسية لها targets لمس مريحة، ووظائف transform قابلة للتنفيذ أيضًا بدون drag عبر Transform controls.
- لا يغطي keyboard حقل النص.
- drag/resize/rotate tested على iOS/Android فعليين.

## AC-13 Review workflow
- moderator يملك `mol_review_translations` ولا يملك `mol_manage_content`: يستطيع تعيين `needs_review` ثم `completed` عبر `/chapters/{id}/review`.
- نفس moderator لا يستطيع إنشاء/حذف فصل أو reorder الصفحات بهذه capability وحدها.

## AC-14 الأداء
- صور بلا CLS ملحوظ وCLS ≤0.1 في profile staging المحدد.
- LCP target ≤2.5s في profile staging المحدد. هذه شروط أداء داخلية؛ تقييم Core Web Vitals الإنتاجي يعتمد field data عند 75th percentile ويقيس INP أيضًا.
## AC-CONTRACT-1 — Schema/Error hardening

- OpenAPI raw YAML بلا anchors/aliases.
- error examples تطابق HTTP status دلاليًا.
- ElementCreate/Patch يرفضان unknown fields؛ PATCH الفارغ مرفوض وحدود geometry موحدة.
- type-specific style rules تمر باختبارات positive/negative.
- upload يعلن 413 ويستخدم `/capabilities` للـAVIF الاختياري.
- renew/release lost lease يعيدان 409 `mol_lock_lost`; missing resource 404.
- Chapter response يجعل slug required، وخوارزمية collision موثقة ومختبرة.
- `VALIDATION_HARNESS.py` ينجح بالكامل قبل Freeze/ZIP.

