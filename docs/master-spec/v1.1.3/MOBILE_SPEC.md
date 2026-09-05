# مواصفات الجوال

## 1. المبدأ

المحرر على الجوال واجهة مستقلة في التخطيط مع نفس نموذج البيانات، وليس desktop compressed.

## 2. Layout

- Top bar: رجوع، اسم الصفحة، حالة الحفظ، Preview.
- Stage يملأ بقية الشاشة.
- Bottom toolbar: Select / Bubble / Narration / Text / SFX / Layers.
- Properties تفتح كـ bottom sheet بمستويات 45% / 85%.
- keyboard opening لا يجب أن يخفي الحقل الفعلي.

## 3. اللمس

- hit target مستهدف ≥44×44 CSS px للأزرار والمقابض كهدف UX داخلي قوي؛ WCAG 2.2 AA يحدد 24×24 CSS px كحد أدنى مع استثناءات، لذا 44×44 ليس ادعاءً بأنه حد AA.
- drag بإصبع واحد.
- resize handles واضحة.
- rotation handle منفصل؛ pinch rotation لا يُعتمد وحده.
- Transform sheet يوفر x/y/w/h/rotation عبر قيم رقمية وأزرار خطوة، بحيث يمكن تنفيذ الوظائف نفسها دون dragging.
- pinch zoom للstage مع gesture arbitration واضح.
- أثناء تحريك عنصر، يمنع scroll الخاص بالstage فقط؛ باقي الصفحة لا تُقفل بلا حاجة.

## 4. تحرير النص

القرار الأساسي: textarea داخل bottom sheet أو modal صغير مرتبط بالعنصر. هذا **قرار تقليل مخاطر** للمشروع لتجنب تعقيدات keyboard/selection داخل transformed contenteditable؛ ليس ادعاءً بأنه أفضل عالميًا على كل متصفح، ولذلك يُحسم بالـPoC على أجهزة الهدف. Desktop قد يوفر inline edit أيضًا.

## 5. Panels

الأقسام:
- Text
- Font
- Shape
- Fill/Border
- Transform
- SFX (إذا النوع sfx)
- Presets

لا تعرض جميع الحقول دفعة واحدة.

## 6. Zoom

- زر 100% / Fit width.
- pinch zoom.
- عند zoom مرتفع يسمح pan.
- overlays تظل مرتبطة بإحداثيات الصورة normalized.

## 7. PoC إلزامي قبل بناء المحرر كاملًا

يُختبر على جهاز iPhone/iPad حقيقي وجهاز Android حقيقي:
- drag/resize/rotate.
- pinch zoom.
- فتح/إغلاق keyboard.
- إدخال العربية.
- apply preset.
- autosave.
- lock conflict.

إذا فشل Moveable في سيناريو حرج على أجهزة الهدف، يُراجع adapter قبل بناء بقية المحرر؛ لا يُستبدل نموذج البيانات.
