# UI/UX

## 1. المسارات

| Route | الصفحة |
|---|---|
| `/` | الرئيسية |
| `/library/` | المكتبة |
| `/series/{slug}/` | العمل |
| `/series/{slug}/chapter/{chapter}/` | القارئ |
| `/series/{slug}/chapter/{chapter}/edit/` | المحرر |
| `/u/{username}/` | الملف الشخصي |
| `/wp-admin/admin.php?page=mol-*` | إدارة المشروع |

## 2. الرئيسية

- بحث بارز.
- أحدث الفصول.
- أعمال مميزة/حديثة.
- تصنيفات مختصرة.

## 3. المكتبة

- grid responsive.
- filters drawer على الجوال/sidebar أو toolbar على desktop.
- URL query parameters للحالة والفلترة حتى يمكن مشاركة النتيجة.
- pagination server-side.

## 4. صفحة العمل

- cover + title + alt titles.
- metadata chips.
- description.
- chapter list مع translation status.
- reader mode icon اختياري.

## 5. القارئ

- واجهة قليلة التشتيت.
- toolbar يختفي/يظهر بالنقر/scroll.
- toggle ترجمة واضح.
- mode switch.
- chapter selector.
- contributors بعد المحتوى أو drawer.

## 6. المحرر

- لا يعرض أسماء المساهمين فوق stage/صورة الفصل.
- Save status دائم الرؤية.
- Preview دائم الوصول.
- أدوات متقدمة progressive disclosure.
- destructive delete يحتاج confirm/undo محلي قصير قبل إرسال DELETE، وليس Version History.

## 7. الملف الشخصي

- header بسيط.
- bio/tag.
- stats cards.
- recent contributions grouped by work/chapter.

## 8. RTL

- CSS logical properties.
- `dir=rtl` للواجهة.
- داخل النصوص الأصلية/metadata قد يستخدم `dir=auto`.
- paged navigation يعكس معنى التالي/السابق وفق reading direction، لا وفق CSS فقط.


- فتح محرر فصل draft يعتمد على صلاحية المحرر؛ القارئ العام لا يرى المسودة ولا يتلقى دلالة على وجودها.
