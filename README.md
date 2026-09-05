# Manga Overlay

منصة WordPress لقراءة المانجا والمانهوا والقصص المصورة، مع طبقة ترجمة عربية مستقلة ومحرر بصري داخل المتصفح.

## حالة المشروع

المشروع في مرحلة تنفيذ الـMVP وفق **Manga Overlay Master Spec v1.1.3**. اكتملت نواة الإضافة ومخطط البيانات وإدارة المحتوى وواجهات القراءة العامة والقالب والقارئ ومحرر العناصر حتى T‑13 على مستوى التنفيذ وCI، مع تثبيت Core 0.10.0 واختبار دورة القفل على موقع التجارب.

| المهمة | الحالة |
|---|---|
| T-00 — تثبيت بيئة التطوير | Node 24 وPHP 8.4 وWordPress 7.1 ومصفوفة DB تعمل في CI |
| T-01 — PoC renderer | منفذ ومختبر آليًا في `poc/renderer`؛ الاعتماد البصري النهائي معلّق |
| T-02 — PoC React + Moveable | مرشح اختبار منفذ في `poc/editor-input`؛ أجهزة iOS/Android الفعلية معلّقة |
| T-03 — Plugin bootstrap | مكتمل ومختبر بالتفعيل داخل WordPress 7.1 حقيقي في CI |
| T-04 — Schema/repositories | مكتمل: 9 جداول + repositories + validators + transactions على المحركين |
| T-05 — Work CPT/taxonomies | مكتمل: `mol_work` + 4 taxonomies + meta + permalinks + Core REST permissions |
| T-06 — Chapter/page management | مكتمل: CRUD + upload queue + MIME/decoder checks + idempotency + reorder ثنائي المرحلة + review policy |
| T-07 — Public data APIs | مكتمل: library/work/chapter/page/element/contributor/profile reads مع visibility وcache contracts |
| T-08 — Public library theme | مكتمل ومختبر على موقع التجارب: الرئيسية والمكتبة وصفحة العمل العربية RTL |
| T-09 — Chapter reader | مكتمل في CI ومثبّت على staging: Webtoon/Paged وRTL/LTR والتكبير وحفظ التقدم وطبقة ترجمة فعلية قابلة للإخفاء والإظهار |
| T-10 — Editor shell | مكتمل في CI وموقع التجارب: بوابة صلاحيات وقالب مستقل وReact state/routing/stage/properties/layers مع Core 0.7.1 |
| T-11 — Element editing | مكتمل في CI وموقع التجارب مع Core 0.8.0: الأنواع الأربعة وDOM/SVG آمن وMoveable والبدائل الرقمية والخصائص والطبقات؛ الحفظ الشبكي لـT-12 |
| T-12 — Element writes/autosave | مكتمل في التنفيذ وCI وstaging مع Core 0.9.0: REST صارم وETag/If-Match وأقفال قصيرة وIdempotency-Key وحفظ تلقائي واستعادة داخل التبويب |
| T-13 — Locks/concurrency | مكتمل في التنفيذ وCI ومثبّت كـCore 0.10.0: renew/release/force-release وواجهة 412/423/428؛ يبقى smoke كامل لـIf-Match عبر reverse proxy الفعلي |

## بنية المستودع

- `docs/master-spec/v1.1.3/`: النسخة المجمدة من المواصفات والعقود والتقارير.
- `docs/IMPLEMENTATION_STATUS.md`: سجل التنفيذ والبوابات المتبقية.
- `docs/T02_DEVICE_VALIDATION.md`: قائمة إثبات T-02 على أجهزة iOS وAndroid الفعلية.
- `poc/renderer/`: نموذج T-01 المستقل لعرض DOM/SVG بإحداثيات normalized.
- `poc/editor-input/`: نموذج T-02 بـReact وMoveable للسحب والتحجيم والدوران وإدخال العربية.
- `scripts/check-environment.sh`: فحص متطلبات بيئة T-00.
- `wp-content/plugins/manga-overlay-core/`: نواة الإضافة وطبقات البيانات والإدارة والقراءة العامة وحفظ تقدم القراءة وقالب/مصدر محرر React.
- `wp-content/themes/manga-overlay-theme/`: القالب العام للمكتبة وصفحات الأعمال وقارئ الفصول غير المعتمد على SPA.

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
