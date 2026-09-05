# Manga Overlay — دفعة التنفيذ الأولى

تنفيذ **تجربتي العارض وإدخال المحرر T-01 / T-02** وفق Master Spec `1.1.3` المرفقة، مع تجهيز أدوات التطوير وفحوص العقد. اسم MOL تقني مؤقت كما في المواصفات.

**هذه حزمة تطوير أولية، وليست الموقع المكتمل أو إضافة WordPress قابلة للتفعيل.** لا توجد قاعدة بيانات أو حسابات أو حفظ على الخادم في هذه التجربة. التغييرات في ذاكرة الجلسة فقط وتُفقد بإغلاق الصفحة. المستودع المعتمد هو [barod1986-ship-it/Manga-Overlay](https://github.com/barod1986-ship-it/Manga-Overlay). لم يُنشر أي شيء على خادم xCloud.

## التشغيل

المتطلب: Node.js 24 LTS. من جذر المشروع:

```bash
npm ci
npm run dev
```

افتح `http://127.0.0.1:5173`. تعرض التجربة صفحة مرجعية أصلية أُنشئت لاختبار مواضع الترجمة، وليست محتوى للمكتبة.

للبناء:

```bash
npm run build
```

ينتج أمر البناء نسخة في `wp-content/plugins/manga-overlay-core/assets/dist/poc/`، وملف `preview.html` لمعاينة محلية. لم يُتحقق من فتح الملف في متصفح هذه الجلسة بسبب قيود بيئة الفحص. المسار المعتمد للتجربة هو HTTP محلي؛ يمكن تشغيله دون تثبيت Node:

```bash
python -m http.server 8080 --bind 127.0.0.1 --directory wp-content/plugins/manga-overlay-core/assets/dist/poc
```

ثم افتح `http://127.0.0.1:8080`. ملفات JavaScript والخطوط محلية؛ افتحها عبر خادم HTTP المحلي لأن فتح HTML مباشرة من نظام الملفات لا يكفي لوحدات JavaScript.

## المنفذ

- عارض DOM للنص العربي وSVG للأشكال، مستخدم في التحرير والمعاينة.
- أربعة أنواع: bubble / narration / free_text / sfx، مع الأنماط الأساسية المحددة في المواصفات.
- إحداثيات نسبية بوحدة مليون، ودوران milli-degrees، وتحويل مستقل عن عرض النافذة.
- React + TypeScript strict + react-moveable مع lockfile مثبت.
- تحديد، إضافة، نسخ، حذف مع تراجع مؤقت، ترتيب طبقات، تحرير نص، ألوان، نمط خط، تحريك، تحجيم ودوران.
- بدائل رقمية وأزرار نقل دقيق؛ اختصارات لا تعترض الكتابة داخل textarea.
- معاينة وإخفاء الترجمة دون تغيير الصورة الأصلية، وملاءمة النص ضمن حد الحجم الأدنى.
- خصائص الجوال في لوحة سفلية، وتكبير/تحريك سطح الصفحة، وخطوط عربية مستضافة محليًا.
- أنواع API مولدة من العقد بعد اجتياز فحصه؛ لا mock endpoints ولا نسخة مخترعة من عقد البيانات.
- Workflow لـGitHub Actions جاهز لتشغيل العقد وTypeScript والبناء واختبارات المتصفح عند رفع المشروع.

## الاختبارات

```bash
python -m pip install -r requirements-dev.txt
npm run check
npx playwright install chromium firefox webkit
npm run test:e2e
```

إعادة توليد أنواع OpenAPI:

```bash
npm run generate:types
```

يفحص `scripts/check-spec.py` بصمات الملفات ثم يشغّل `VALIDATION_HARNESS.py` الأصلي. لا تعدّل `generated/api.d.ts` يدويًا.

راجع `docs/IMPLEMENTATION_STATUS.md` و`docs/verification/RESULTS.md` لمعرفة الاختبارات المنفذة والبوابات المتبقية. اختبارات المتصفح مؤلفة لكنها لم تعمل في هذه الجلسة لعدم توفر متصفحات محلية وتعذر تنزيلها. محاكاة الهاتف في Playwright لا تحل محل اختبار iPhone وAndroid فعليين الذي تفرضه المواصفات.

## هيكل التنفيذ

```text
docs/spec-v1.1.3/                 المواصفات الأصلية كاملة دون تعديل
docs/verification/                نتائج الفحوص والقيود المسجلة
wp-content/plugins/manga-overlay-core/
  editor-src/domain/              الأنماط والتحويلات والأنواع المشتقة
  editor-src/renderer/            العارض المشترك
  editor-src/poc/                 تجربة مستقلة؛ الحالة محلية فقط
  editor-src/generated/           أنواع OpenAPI المولدة
  public/reference-page.svg       صورة اختبار أصلية
tests/                            اختبارات المجال والمتصفحات
```

مجلد `poc` غلاف تجربة؛ العارض والتحويلات منفصلان لاستخدامهما لاحقًا داخل الإضافة والقارئ. المسار الحالي داخل `wp-content` هيكل مستودع كما تصفه المواصفات، ولا يمثل افتراضًا لمسارات تثبيت WordPress الفعلية.

## السياق والمرحلة التالية

النشر المستهدف يبقى WordPress على xCloud Managed / NGINX / MySQL، بخادم 6 GB RAM و4 vCPU و100 GB تخزين. خط الإنتاج المحدد في المواصفات: WordPress 7.1.x، PHP 8.4.x وMySQL 8.4 LTS. لم تُفحص نسخ البرامج المثبتة فعليًا على خادم المستخدم.

تُراجع هذه الدفعة في فرع `codex/initial-renderer-editor`، ثم يستمر تنفيذ `DEVELOPMENT_PLAN.md` مع توثيق نتائج اختبار الأجهزة قبل بناء بقية المحرر. ما زالت T-03–T-20، ومنها الإضافة والقالب والجداول وREST والحفظ والأقفال والصلاحيات والنشر، غير منفذة في هذه الحزمة.

لا يحتوي المشروع أسرارًا أو بيانات دخول. تراخيص الخطوط الموزعة مرفقة في `THIRD_PARTY_NOTICES.md` و`licenses/`.
