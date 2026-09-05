# Manga Overlay Master Spec

الإصدار: `1.1.3`  
الحالة: **Frozen for MVP implementation — Internet-audited + Contract-hardened + API/Visibility-hardened + Schema/Error-hardened**  
تاريخ التثبيت: `2026-09-01`

هذه الحزمة هي **المرجع الوحيد** لتنفيذ مشروع مكتبة المانجا/المانهوا/القصص المصورة مع محرر تعريب عربي بصري فوق الصور الأصلية. اسم `MOL` اختصار تقني مؤقت لـ **Manga Overlay Library** وليس اسم المنتج النهائي.

## قاعدة المرجع

ملفات Work وKimi وClaude التي استُخدمت للمقارنة أصبحت مصادر بحث فقط. عند بدء التطوير لا يُرجع إليها لحسم قرار. المرجع بالترتيب:

1. `DECISIONS.md`.
2. `DATABASE_SCHEMA.md` و`API.openapi.yaml`.
3. `SECURITY.md` و`USER_ROLES_PERMISSIONS.md`.
4. `REQUIREMENTS.md` و`ACCEPTANCE_CRITERIA.md`.
5. بقية ملفات المواصفات.

إذا احتاج التنفيذ لتغيير قرار مثبت، يُنشأ قرار جديد في `DECISIONS.md` ويُرفع إصدار الحزمة إلى إصدار أحدث من `1.1.3` قبل متابعة البرمجة.

## خط الأساس

- WordPress `7.1.x`.
- PHP `8.4.x` كخط الإنتاج المستهدف.
- MySQL `8.4 LTS` **أو** MariaDB `10.11 / 11.4 / 11.8`.
- InnoDB + `utf8mb4`.
- Node.js `24 LTS` لأدوات بناء المحرر واختبارات الواجهة.
- إضافة: `manga-overlay-core`.
- قالب: `manga-overlay-theme`.
- PHP namespace: `MOL\`.
- بادئة hooks/options/capabilities: `mol_`.
- بادئة الجداول: `{$wpdb->prefix}mol_`.
- REST namespace: `/wp-json/mol/v1`.
- لغة واجهة المنتج الأساسية: العربية.
- لغة الترجمة المستهدفة في MVP: `ar`، مع نموذج بيانات لا يمنع لغات هدف إضافية مستقبلًا.

## الملفات

1. `PROJECT_OVERVIEW.md`
2. `REQUIREMENTS.md`
3. `DECISIONS.md`
4. `ARCHITECTURE.md`
5. `WORDPRESS_STRUCTURE.md`
6. `DATABASE_SCHEMA.md`
7. `API_SPEC.md`
8. `API.openapi.yaml`
9. `TRANSLATION_EDITOR_SPEC.md`
10. `READER_SPEC.md`
11. `UI_UX_SPEC.md`
12. `MOBILE_SPEC.md`
13. `USER_ROLES_PERMISSIONS.md`
14. `CONTRIBUTION_SYSTEM.md`
15. `CONTENT_MANAGEMENT.md`
16. `PROFILES_AND_COMMUNITY.md`
17. `PERFORMANCE_AND_MEDIA.md`
18. `SECURITY.md`
19. `ERROR_HANDLING.md`
20. `TEST_STRATEGY.md`
21. `ACCEPTANCE_CRITERIA.md`
22. `MVP_ROADMAP.md`
23. `DEVELOPMENT_PLAN.md`
24. `DEPLOYMENT_REQUIREMENTS.md`
25. `DATA_MODEL_EXAMPLES.md`
26. `CODING_STANDARDS.md`
27. `GLOSSARY.md`
28. `SOURCE_COMPARISON.md`
29. `SOURCES.md`
30. `RISK_REGISTER.md`
31. `ELEMENT_STYLE.schema.json`
32. `INTERNET_VERIFICATION_REPORT.md`
33. `INTERNET_VERIFICATION_MATRIX.csv`
34. `VALIDATION_REPORT.md`
35. `CONTRACT_HARDENING_REPORT.md`
36. `API_VISIBILITY_HARDENING_REPORT.md`
37. `SCHEMA_ERROR_HARDENING_REPORT.md`
38. `VALIDATION_HARNESS.py`
39. `SHA256SUMS.txt`

## تعليمات لوكيل برمجي

- لا تعيد تصميم المشروع أثناء التنفيذ.
- نفذ `DEVELOPMENT_PLAN.md` مرحلة بمرحلة.
- كل endpoint كتابة يتحقق من capability في الخادم.
- لا تستخدم `postmeta` للصفحات أو عناصر الترجمة أو الأقفال أو المساهمات.
- لا تخزن HTML/CSS/SVG خامًا من المستخدم داخل عنصر ترجمة.
- لا تضف Version History لمحتوى الترجمة في MVP.
- لا تضف Offline durable queue أو WebSocket collaboration أو Audit Log إلى MVP.
- لا تعتبر الميزة مكتملة حتى تحقق معاييرها في `ACCEPTANCE_CRITERIA.md`.


## ملاحظة الإصدار 1.1.1

> 1.1.1 هو إصدار Contract Hardening تاريخي. الإصدار الحالي 1.1.2 يضيف تقوية جانب الأخطاء وسياسة رؤية المسودات وتحديث الأمثلة والعقود.

## ملاحظة الإصدار 1.1.3

- أزيلت YAML anchors/aliases من العقد النهائي، وكل مثال خطأ يحمل code/status متطابقين مع HTTP response.
- `ElementCreate` و`ElementPatch` أصبحا strict؛ PATCH الفارغ مرفوض وحدود geometry متطابقة مع create.
- style overrides أصبحت مقيدة حسب `element_type`; وعند PATCH للنمط يرسل العميل `element_type` كـdiscriminator ثابت لا كحقل قابل للتغيير.
- أضيفت حالات `413`, `404` الناقصة، و`409 mol_lock_lost` لتجديد/تحرير lease منتهي أو مستبدل.
- slug الفصل يولده الخادم وفق خوارزمية موثقة مع `uq_work_slug` كحارس نهائي و`409 mol_slug_conflict` عند فشل الحجز بعد retries محدودة.
- AVIF أصبح capability وقت تشغيل عبر `GET /capabilities`; JPEG/PNG/WebP فقط baseline ثابت في عقد الرفع.
- `version` داخل element collection هو المصدر لبناء `If-Match: "<version>"`; لا يوجد ETag HTTP منفصل لكل child داخل collection.
- أضيف `VALIDATION_HARNESS.py` لفحص semantics للأخطاء، منع YAML aliases، صرامة request schemas، واستخدام أكواد الأخطاء.

## ملاحظة الإصدار 1.1.2
- استجابات `400/401/403/429` موثقة ومستخدمة حيث تنطبق.
- موارد الفصل غير المنشور تعيد `404` للعامة، وتُقرأ فقط في سياق WordPress REST موثّق لمن لديه `mol_use_editor` أو `mol_manage_content`.
- `DATA_MODEL_EXAMPLES.md` محدث ليطابق OpenAPI، و`sort_order` يُحوّل إلى رقم في Repository/DTO.
- `most_read` موثق كقدرة شرطية غير منفذة في MVP.
- أضيف `API_VISIBILITY_HARDENING_REPORT.md` لتوثيق تغييرات 1.1.2.

هذا الإصدار لا يغيّر رؤية المنتج أو المعمارية الأساسية. هو إصدار **تقوية عقود** بعد مراجعة مستقلة: اكتملت response schemas للقراءة، فلاتر المكتبة، batch overlay للفصل، review workflow، خوارزمية reorder الآمنة، قواعد ETag/If-Match، Base Styles، ودلالة preset defaults/force-unlock. التفاصيل في `CONTRACT_HARDENING_REPORT.md`.
