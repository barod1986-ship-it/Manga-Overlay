# Manga Overlay

منصة WordPress لقراءة المانجا والمانهوا والقصص المصورة، مع طبقة ترجمة عربية مستقلة ومحرر بصري داخل المتصفح.

## حالة المشروع

المشروع في مرحلة إثبات واجهة التحرير ضمن الـMVP وفق **Manga Overlay Master Spec v1.1.3**. لا توجد حاليًا حزمة WordPress جاهزة للنشر؛ يجري تثبيت سلوك العرض والإدخال أولًا قبل بناء الإضافة والـREST API.

| المهمة | الحالة |
|---|---|
| T-00 — تثبيت بيئة التطوير | بدأ: Node 24 مضبوط، والتحقق من PHP/DB ينتظر CI/بيئة WordPress |
| T-01 — PoC renderer | منفذ ومختبر آليًا في `poc/renderer`؛ الاعتماد البصري النهائي معلّق |
| T-02 — PoC React + Moveable | مرشح اختبار منفذ في `poc/editor-input`؛ أجهزة iOS/Android الفعلية معلّقة |
| T-03 وما بعده | لم يبدأ |

## بنية المستودع

- `docs/master-spec/v1.1.3/`: النسخة المجمدة من المواصفات والعقود والتقارير.
- `docs/IMPLEMENTATION_STATUS.md`: سجل التنفيذ والبوابات المتبقية.
- `docs/T02_DEVICE_VALIDATION.md`: قائمة إثبات T-02 على أجهزة iOS وAndroid الفعلية.
- `poc/renderer/`: نموذج T-01 المستقل لعرض DOM/SVG بإحداثيات normalized.
- `poc/editor-input/`: نموذج T-02 بـReact وMoveable للسحب والتحجيم والدوران وإدخال العربية.
- `scripts/check-environment.sh`: فحص متطلبات بيئة T-00.
- `wp-content/`: سيُضاف عند الوصول إلى T-03؛ لن تُنشأ إضافة شكلية قبل اجتياز PoCs.

## تشغيل نموذج العرض

يتطلب Node.js 24:

```bash
npm ci
npm run check
npm run dev:editor-poc
```

ثم افتح العنوان الذي يعرضه Vite. لتشغيل نموذج T-01 المستقل استخدم `npm run dev:poc`. صورة الاختبار SVG أصلية خاصة بالـPoC وليست صيغة رفع مسموحة لصفحات الإنتاج.

اختبارات المتصفح الكاملة تحتاج محركات Playwright:

```bash
npx playwright install chromium firefox webkit
npm run test:e2e
```

## قاعدة المرجع

عند التعارض تكون الأولوية: `DECISIONS.md`، ثم مخطط قاعدة البيانات وعقد OpenAPI، ثم الأمن والصلاحيات، ثم المتطلبات وشروط القبول. أي تغيير معماري يحتاج ADR ورفع إصدار Master Spec قبل التنفيذ.
