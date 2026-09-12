# المعمارية

## 1. الطبقات

```text
Browser
 ├─ Public Theme (PHP server-rendered + small JS)
 ├─ Reader JS
 └─ Translation Editor (React/TypeScript bundle)
        ↓ REST + WordPress login cookie + X-WP-Nonce (CSRF)
manga-overlay-core plugin
 ├─ REST Controllers
 ├─ Application Services
 ├─ Domain validators/models
 ├─ Repositories
 ├─ Media services
 └─ Security/capabilities
        ↓
WordPress + InnoDB + wp_uploads
        ↓
Optional origin-pull CDN / Redis
```

## 2. WordPress Core

مسؤول عن:

- المستخدمين والجلسات وكلمات المرور.
- Media Library والـattachments.
- CPT العمل والتصنيفات.
- واجهة الإدارة الأساسية وhooks.
- REST same-origin authentication عبر WordPress login cookie؛ `X-WP-Nonce` يحمي من CSRF ولا يُعد وسيلة authentication مستقلة.

## 3. الإضافة `manga-overlay-core`

تمتلك:

- جداول المجال وترحيلاتها.
- chapters/pages/elements/locks/contributions/reports/progress/presets.
- capabilities والأدوار.
- REST API `/mol/v1`.
- محرر React وrenderer المشترك.
- منطق رفع/ربط صفحات الفصل.
- admin screens الخاصة بالمشروع.

الإضافة لا تعتمد على القالب لتنفيذ منطق أعمال.

## 4. القالب `manga-overlay-theme`

يمتلك:

- الصفحة الرئيسية.
- المكتبة.
- صفحة العمل.
- قالب القارئ.
- الملفات الشخصية العامة.
- CSS العام وRTL.

لا يقوم القالب بـSQL مباشر إلى جداول MOL. يستخدم public API PHP من الإضافة أو REST عند الحاجة.

## 5. المحرر

- React + TypeScript.
- renderer مركزي يحول `Element DTO` إلى DOM text + SVG shape.
- Moveable مسؤول عن handles/events فقط.
- state محلي في React؛ لا تُخزن state خاصة بالمكتبة في قاعدة البيانات.
- كل إحداثيات العرض مشتقة من normalized units وأبعاد الصورة المعروضة.

## 6. مبادئ التوافق

- قاعدة البيانات قابلة للعمل على MySQL 8.4 وMariaDB المدعومة.
- JSON مخزن في LONGTEXT مع validation في التطبيق.
- لا Foreign Keys إلزامية في MVP لتبسيط migrations والحذف المنطقي عبر Service؛ هذا ليس لأن `dbDelta()` يحظرها مطلقًا. الخدمات تنفذ cascading transactions منطقيًا على InnoDB.
- cache لا يكون مصدر حقيقة.

## 7. تدفق تحرير عنصر

1. GET عناصر الصفحة للمحرر، أو chapter batch للقارئ. العنصر يحمل `version`.
2. المستخدم يحدد عنصرًا.
3. POST lock للعنصر.
4. تحرير محلي.
5. بعد debounce/end interaction: PATCH مع `If-Match` وlock token.
6. transaction: verify cap → verify lock → verify version → validate → update → upsert contribution → commit.
7. response ETag جديد.
8. reader cache لذلك الفصل/الصفحة يُبطل.

## 8. الاستمرارية عند تغيير القالب

كل بيانات المحتوى والترجمة داخل WordPress Core/CPT أو جداول الإضافة. تغيير القالب لا يحذف أو يعيد صياغة بيانات المشروع.


## 9. عقود القراءة

`API.openapi.yaml` يعرّف response schemas لكل GET عام. القالب قد يستخدم PHP API داخليًا، لكن أي عميل TypeScript/JS يعتمد REST يستخدم نفس DTO shapes. الفصل الكبير لا يفرض request لكل صفحة: reader يستطيع استخدام chapter overlay bundle المجمّع حسب الصفحة.


- `ChapterVisibilityPolicy` مركزية: published resources عامة؛ drafts متاحة فقط في REST authenticated context لمن يملك `mol_use_editor|mol_manage_content`، وإلا 404.
