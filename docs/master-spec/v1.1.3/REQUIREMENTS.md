# المتطلبات

## 1. المكتبة والأعمال

- **FR-LIB-001:** عرض شبكة أعمال بأغلفة وعناوين وحالة وترجمة.
- **FR-LIB-002:** بحث نصي بالعنوان والأسماء البديلة.
- **FR-LIB-003:** فلاتر النوع، التصنيف، اللغة الأصلية، حالة العمل، حالة الترجمة.
- **FR-LIB-004:** ترتيب: أحدث فصل، أحدث عمل، أبجدي، الأكثر قراءة عند توفر العدادات؛ API يدعم `page` و`per_page` ويعيد pagination metadata.
- **FR-WORK-001:** صفحة العمل تعرض الغلاف والوصف والبيانات والفصول.
- **FR-WORK-002:** كل عمل يحدد `default_reader_mode` و`reading_direction`.

## 2. الفصول والقارئ

- **FR-CH-001:** حالات الترجمة: `untranslated`, `in_progress`, `completed`, `needs_review`.

- **FR-CH-002:** الفصل المنشور وموارده قابلة للقراءة العامة. الفصل غير المنشور وصفحاته وعناصره ومساهموه لا تُكشف للعامة؛ تعيد المسارات `404` ما لم يكن الطلب في سياق WordPress REST موثّق والمستخدم يملك `mol_use_editor` أو `mol_manage_content`.
- **FR-RDR-001:** دعم `webtoon` و`paged` في MVP.
- **FR-RDR-002:** paged يدعم RTL/LTR.
- **FR-RDR-003:** الترجمة العربية تظهر افتراضيًا إن وجدت، مع toggle فوري بدون reload.
- **FR-RDR-004:** إخفاء الترجمة يعرض الصورة الأصلية وحدها.
- **FR-RDR-005:** zoom/pan مناسب للجوال.
- **FR-RDR-006:** حفظ موضع القراءة للحساب، وlocalStorage للزائر اختياريًا.
- **FR-RDR-007:** تحميل الصور تدريجيًا مع preload محدود للصفحات القريبة.
- **FR-RDR-008:** قسم مساهمين منفصل عن الصور.
- **FR-RDR-009:** يمكن للقارئ جلب عناصر الفصل العربي دفعة واحدة مجمعة حسب الصفحة، مع بقاء endpoint الصفحة المفردة للمحرر/التحميل الجزئي.

## 3. المحرر

- **FR-ED-001:** دخول المحرر يتطلب `mol_use_editor`.
- **FR-ED-002:** أنواع العناصر: `bubble`, `narration`, `free_text`, `sfx`.
- **FR-ED-003:** إنشاء/تحديد/تحريك/resize/rotate/duplicate/delete/reorder.
- **FR-ED-004:** خصائص النص: content، font، size، weight، line-height، alignment، color.
- **FR-ED-005:** خصائص الشكل: shape، background، opacity، border، radius، padding، tail/burst options عند النوع المناسب.
- **FR-ED-006:** z-index قابل للتعديل.
- **FR-ED-007:** RTL صحيح ونص عربي متصل.
- **FR-ED-008:** auto-fit اختياري لتقليل حجم النص حتى يدخل ضمن مساحة العنصر ضمن حد أدنى.
- **FR-ED-009:** snapping وإرشادات محاذاة.
- **FR-ED-010:** Preview mode يخفي أدوات التحرير ويعرض النتيجة كقارئ.
- **FR-ED-011:** Presets: تطبيق، حفظ personal، واستخدام work/global.
- **FR-ED-012:** لا يُعدل الملف الأصلي أثناء التحرير.

## 4. الحفظ والتزامن

- **FR-SAVE-001:** Autosave بعد توقف المستخدم قرابة `1200ms` من آخر تغيير قابل للحفظ.
- **FR-SAVE-002:** تغييرات drag/resize/rotate تُرسل عند نهاية التفاعل، لا مع كل frame.
- **FR-SAVE-003:** حالات UI: `dirty`, `saving`, `saved`, `offline`, `locked`, `conflict`, `error`.
- **FR-LOCK-001:** قفل العنصر قبل تعديل عنصر محفوظ.
- **FR-LOCK-002:** lease 45 ثانية وتجديد كل 15 ثانية أثناء التعديل النشط.
- **FR-LOCK-003:** يستطيع محرران العمل على عنصرين مختلفين في الصفحة نفسها.
- **FR-CONC-001:** كل تحديث يرسل `If-Match` لإصدار العنصر.
- **FR-CONC-002:** `412` يعرض conflict UI ولا يكتب فوق نسخة أحدث صامتًا.

## 5. المحتوى والرفع

- **FR-CMS-001:** إنشاء/تعديل الأعمال والفصول وترتيب/حذف الصفحات يتطلب `mol_manage_content`. رفع ملفات الصفحات يتطلب `mol_upload_content`. يمكن منح الصلاحيتين فرديًا دون تغيير الدور.
- **FR-CMS-002:** رفع صور متعددة من الواجهة، لكن كل صفحة تُرسل كطلب مستقل ضمن queue محدود.
- **FR-CMS-003:** ترتيب الصفحات بالسحب أو أرقام الترتيب.
- **FR-CMS-004:** حذف/استبدال صفحة يحدّث العلاقات دون ترك عناصر يتيمة.
- **FR-CMS-005:** دعم لغات مصدر BCP 47 متعددة، والترجمة المستهدفة في MVP `ar`.

## 6. المجتمع

- **FR-CON-001:** المساهم يُسجل مرة لكل `(user, element)` حتى لو حفظ العنصر مرات كثيرة.
- **FR-CON-002:** صفحة الفصل تعرض المساهمين وإجمالي العناصر الفريدة التي لمسها كل مساهم.
- **FR-PROF-001:** ملف المستخدم: avatar، display name، username، bio، tag/rank، إحصاءات، أعمال وفصول ساهم فيها.
- **FR-REP-001:** تبليغ على فصل/صفحة/عنصر مع نوع `translation|placement|style|missing|other`.
- **FR-REP-002:** المشرف يدير حالات `open|in_review|resolved|rejected`.

## 7. الإدارة والصلاحيات

- **FR-ADM-001:** لوحة إدارة للأعمال والفصول والصفحات والبلاغات والصلاحيات؛ إعدادات النظام منخفضة التكرار تبقى Administrator-only عبر WordPress `manage_options`.
- **FR-ADM-002:** منح/سحب `mol_manage_content` و`mol_upload_content` لمستخدم دون تغيير دوره.
- **FR-ADM-003:** سحب `mol_use_editor` يمنع الواجهة وREST معًا.
- **FR-ADM-004:** `mol_review_translations` يسمح للمشرف بوضع الفصل `needs_review` أو إعادته `completed` عبر مسار مراجعة مستقل دون منحه إدارة المحتوى العامة.

## 8. غير وظيفي

- **NFR-RTL-001:** واجهة عربية RTL كاملة.
- **NFR-MOB-001:** القارئ والمحرر قابلان للاستخدام على iOS Safari وAndroid Chrome الحديثين.
- **NFR-PERF-001:** LCP مستهدف ≤2.5s على صفحة قارئ محسنة في staging على شبكة/جهاز متوسطين؛ هذا هدف هندسي مستوحى من Core Web Vitals ولا يعادل وحده قياس field عند الـ75th percentile.
- **NFR-PERF-002:** CLS ≤0.1 مع width/height/aspect-ratio صريحة للصور.
- **NFR-API-001:** لا route كتابة بلا `permission_callback` وفحص كيان.
- **NFR-API-002:** كل GET عام موثق في `API.openapi.yaml` يملك response schema صريحًا صالحًا لتوليد/فحص DTOs typed؛ لا تُترك استجابة `200` بوصف فقط.

- **NFR-API-003:** كل write route موثّق يحدد `401` و`403`، وكل write route ذو request body يحدد `400`. المسارات ذات rate limit تشغيلي فعلي فقط توثق `429`.
- **NFR-API-004:** الأمثلة التنفيذية في `DATA_MODEL_EXAMPLES.md` يجب أن تطابق schemas الحالية آليًا؛ لا تُقبل أمثلة stale.
- **NFR-MNT-001:** طبقات واضحة Controller → Service → Repository.
- **NFR-DB-001:** لا اعتماد على SQL JSON functions الخاصة بمحرك واحد.
- **NFR-TEST-001:** اختبارات unit/integration/E2E للأجزاء الحرجة قبل الإطلاق.

- **NFR-A11Y-001:** الوظائف الحرجة التي تعتمد drag/resize/rotate توفر بدائل pointer بلا سحب عبر أزرار/مدخلات Transform؛ وتستهدف أدوات اللمس 44×44 CSS px حيث تسمح الواجهة.
