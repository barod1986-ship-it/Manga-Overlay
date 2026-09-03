# إدارة المحتوى

## 0. الصلاحيات

- إنشاء/تعديل الأعمال والفصول وترتيب/حذف الصفحات: `mol_manage_content` (قابلة للمنح فرديًا).
- رفع ملفات الصور: `mol_upload_content` (قابلة للمنح فرديًا).
- امتلاك upload وحدها لا يسمح بتغيير بيانات فصل/عمل، والعكس لا يسمح بتمرير ملف دون upload cap.

## 1. العمل

يُنشأ من CPT `mol_work`:
- عنوان.
- أسماء بديلة.
- غلاف.
- وصف.
- type.
- genres.
- source language.
- work status.
- default reader mode.
- reading direction.

## 2. الفصل

شاشة الإضافة توفر:
- اختيار work.
- chapter label (مثل `1`, `10.5`, `Special 1`).
- sort order مستقل للفرز.
- title اختياري.
- translation status.
- نشر/مسودة.
- overrides للقارئ عند الضرورة.

## 3. رفع الصفحات

واجهة drop zone متعددة الملفات:
1. ترتيب أولي حسب filename natural sort.
2. معاينة thumbnails.
3. إمكانية reorder قبل/بعد الرفع.
4. queue بحد أقصى طلبين متزامنين افتراضيًا.
5. كل صورة تُرفع عبر endpoint مستقل.
6. الخادم يتحقق من النوع والحجم ويفك الصورة عبر image editor/decoder؛ ثم يسجل attachment و`mol_pages`. WebP derivative إن فُعّل ينشئه MOL صراحةً، لا اعتمادًا على تحويل WordPress الافتراضي.

## 4. ترتيب الصفحات

PATCH reorder يرسل **كل** page IDs الحالية للفصل كـpermutation كاملة بالترتيب المطلوب. الخادم:
1. يبدأ transaction ويقفل صفوف الفصل `SELECT ... FOR UPDATE`.
2. يتحقق أن المجموعة المرسلة تطابق مجموعة الصفحات الحالية دون نقص/زيادة/تكرار.
3. يحسب offset أكبر من `max(page_index)` + عدد الصفحات + 1، ثم ينقل كل `page_index` إلى نطاق مؤقت منفصل (`page_index = page_index + offset`).
4. يعيد تعيين القيم النهائية `0..N-1` عبر CASE/update.
5. commit.

هذه المرحلتان تمنعان اصطدام `uq_chapter_index` أثناء تبديل صفحتين أو أكثر.

## 5. حالة الفصل

### رؤية المسودة

`is_published=0` لا يعني أن المحرر عاجز عن فتح الفصل. Controllers الخاصة بموارد الفصل تسمح بالقراءة الخاصة في سياق REST موثّق لمن لديه `mol_use_editor` أو `mol_manage_content`; لكل شخص آخر تُرجع `404` حتى لا تكشف وجود المسودة. قائمة `/works/{id}/chapters` العامة لا تشمل المسودات.

- `untranslated`: لا عناصر عربية.
- أول عنصر عربي محفوظ يمكن أن يحولها تلقائيًا إلى `in_progress`.
- `completed` يحددها مخول يدويًا.
- `needs_review` يحددها من لديه `mol_review_translations` عبر `/chapters/{id}/review`؛ ويمكنه بعد المراجعة إعادتها إلى `completed`. مدير المحتوى يستطيع أيضًا إدارة الحالة عبر مسار الفصل العام.

الحالة وصفية؛ في فصل منشور آخر ترجمة محفوظة تظل ظاهرة.

## 6. استبدال صورة

استبدال page image يحافظ على page id وعناصرها فقط إذا أكد المدير أن أبعاد/تركيب الصفحة مكافئ. افتراضيًا يحذر النظام لأن overlays قد لا تتطابق. خيار آمن: إنشاء صفحة جديدة وإعادة التنسيق.
## توليد slug الفصل

الواجهة لا تحتاج حقل slug في MVP. عند إنشاء الفصل يولد الخادم slug من `title`، أو من `chapter-<chapter_label>` عند غياب العنوان، باستخدام `sanitize_title`. التصادمات تُحل suffix `-2`, `-3`... مع الاعتماد على `uq_work_slug` كحارس race-safe؛ استنفاد retries يعيد `409 mol_slug_conflict`.

