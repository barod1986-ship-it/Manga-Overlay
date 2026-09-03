# Contract Hardening Report — v1.1.1

التاريخ: `2026-09-01`  
الأساس: `v1.1.0 Internet-Audited`  
الهدف: سد ثغرات العقود التي ظهرت في مراجعة مستقلة قبل بدء T-07 وما بعده.

## النتيجة

لم تتغير رؤية المنتج أو المعمارية الأساسية. تم تقوية طبقة العقود في ثمانية محاور:

1. **GET response contracts:** كل GET عام أصبح له schema صريح في OpenAPI، بدل `200: OK` بلا body contract.
2. **Library contract:** أضيفت `genre`, `work_status`, `translation_status`, `sort`, `page`, `per_page` مع pagination envelope.
3. **Chapter overlay batch:** أضيف `GET /chapters/{id}/elements?lang=...` مجمعًا حسب الصفحة، مع بقاء page endpoint.
4. **Review capability:** `mol_review_translations` أصبح مستخدمًا فعليًا عبر `PATCH /chapters/{id}/review`; حُذفت capabilities المعلقة غير المستخدمة من canonical MVP set.
5. **Collision-safe reorder:** وثقت خوارزمية transaction + `FOR UPDATE` + temporary disjoint index range ثم 0..N-1.
6. **ETag/If-Match implementation:** وثق موضع القراءة/الإرجاع في WordPress Controller واختبار مرور الهيدر عبر reverse proxy.
7. **Base Styles / preset defaults:** عنصر جديد له built-in style fallback، ودلالة `is_default` وأولوية personal→work→global→built-in واضحة وatomic.
8. **Force unlock:** لا route منفصل؛ نفس DELETE lock route يطبق owner+token أو `mol_manage_content` override.

## نقاط من المراجعة لم تكن أخطاء

- `content` كان لديه أصلًا `maxLength: 10000`; بقي كما هو.
- WordPress REST يستطيع التعامل مع headers؛ المشكلة لم تكن «عدم دعم ETag»، بل غياب عقد تنفيذ/proxy gate واضح.
- chapter batch يحسن العقد لكنه لا يعني أن النسخة السابقة كانت مجبرة تقنيًا على 80 request؛ reader كان يسمح بالتضمين/التحميل المرن.

## Runtime gates التي لم تتحول إلى افتراضات

- T-01: العربية/auto-fit/SFX visual behavior.
- T-02: Moveable + touch/keyboard على iOS/Android حقيقي.
- dbDelta على MySQL/MariaDB.
- proxy header smoke لـIf-Match/ETag.
- الأداء الفعلي.

هذه gates تبقى في `TEST_STRATEGY.md` و`ACCEPTANCE_CRITERIA.md`.
