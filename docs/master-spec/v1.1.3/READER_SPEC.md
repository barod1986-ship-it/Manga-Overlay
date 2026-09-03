# مواصفات القارئ

## 1. الأوضاع

### Webtoon
- تمرير عمودي مستمر.
- عرض كل صورة بعرض مناسب مع ratio ثابت.
- عناصر الترجمة داخل overlay لكل صورة.
- lazy rendering للصفحات البعيدة إذا ثبتت حاجة على الجوال.

### Paged
- صفحة واحدة في MVP، مع إمكانية تطوير spread لاحقًا.
- أزرار/مناطق لمس التالي والسابق.
- اتجاه `rtl|ltr` حسب العمل/الفصل.
- keyboard arrows على desktop.

## 2. اختيار الوضع

الأولوية:
1. تفضيل المستخدم المحفوظ لهذا العمل.
2. `chapter.reader_mode_override`.
3. `work.default_reader_mode`.

اتجاه paged:
1. chapter override.
2. work reading direction.

## 3. الترجمة

### 3.1 رؤية المسودة في وضع التحرير

القارئ العام لا يرى draft chapter. واجهة المحرر يمكنها استخدام نفس GET resources مع `X-WP-Nonce`/جلسة WordPress REST صالحة؛ الخادم يسمح إذا كان لدى المستخدم `mol_use_editor` أو `mol_manage_content`. غير ذلك يعيد 404.

- المسار المفضل للفصل عند حجم مناسب: `GET /chapters/{id}/elements?lang=ar` يعيد العناصر مجمعة حسب الصفحة.
- `GET /pages/{id}/elements?lang=ar` يبقى متاحًا للتحميل الجزئي/المحرر.
- إن كان للفصل أي عنصر عربي، تكون الطبقة ON افتراضيًا لأول زيارة.
- toggle لا يعيد تحميل الصور.
- تفضيل toggle يحفظ محليًا، ويمكن مزامنته بحساب لاحقًا دون أن يكون شرط MVP.
- عند OFF لا يبقى أي background أو SVG خاص بالترجمة.

## 4. Zoom/Pan

- في webtoon: pinch zoom داخل page viewer مع تجنب كسر scroll.
- في paged: pinch + pan أكثر حرية.
- reset zoom عند الانتقال لصفحة جديدة في paged افتراضيًا.

## 5. التقدم

عضو مسجل:
- `page_index` + `progress_unit` + `reader_mode`.
- save throttled، وليس كل scroll event.

زائر:
- localStorage `mol_progress_{chapterId}`.

## 6. التنقل

- الفصل السابق/التالي.
- قائمة فصول قابلة للفتح.
- العودة للعمل.
- إظهار حالة الترجمة.

## 7. المساهمون

أسفل القارئ أو داخل drawer غير متداخل مع الصورة:
- avatar صغير.
- display name.
- عدد العناصر الفريدة التي ساهم فيها في الفصل.
- رابط الملف الشخصي.

## 8. البلاغ

من القارئ يمكن:
- تقرير عام على الفصل.
- إذا كانت الترجمة ظاهرة: تحديد عنصر و`Report`.
- الأنواع: translation/placement/style/missing/other.

## 9. الأداء

- أول صورة eager + fetchpriority high عند ملاءمة ذلك.
- الصور التالية lazy، مع preload محدود للصفحة القريبة فقط.
- width/height صريحان.
- overlay JSON يمكن تضمينه في HTML أو تحميله عبر chapter batch endpoint أو page endpoint حسب cache/حجم الفصل؛ **لا يتم طلب عنصر-بعنصر**، ولا يُفرض 1 request لكل صفحة إذا كان batch الفصل أنسب.
