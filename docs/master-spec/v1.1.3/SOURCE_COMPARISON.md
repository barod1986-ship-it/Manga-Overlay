# مقارنة المصادر وقرارات الدمج

هذه الوثيقة تشرح كيف حُسمت النقاط التي اختلفت فيها حزم Work وKimi وClaude. لا تستخدم كمواصفة تنفيذ بدل `DECISIONS.md`.

| الموضوع | Work | Kimi | Claude | Master |
|---|---|---|---|---|
| أساس WordPress | قالب+إضافة مخصصة | قالب+إضافة مخصصة | قالب+إضافة مخصصة | معتمد |
| العمل/الفصل | Work يميل إلى Work CPT + جداول للبقية | Work CPT + جداول للبقية | Series+Chapter CPT | Work CPT + chapters table |
| محرر JS | React + Moveable | Vanilla + Moveable | Vanilla + Moveable | **React/TS + Moveable** للمحرر فقط |
| Rendering | DOM/SVG | DOM/CSS | DOM overlay | **DOM text + safe SVG shapes** |
| Geometry | integers normalized | decimal 0..1 | decimal/percent | **integers normalized** |
| Reader MVP | vertical أساسًا | paged + webtoon | الاثنين | **paged + webtoon** |
| Locking | element-level | page-level | element-level | **element-level** |
| Version conflict | If-Match/version | optimistic + page lock | version + lock | **If-Match + element lock** |
| Lock storage | جدول موحد في Work النهائي | page lock table | أعمدة + جدول متكرر | **جدول واحد** |
| Contributions | aggregate/غير كل save | يميل لتسجيل عمليات أكثر | count مجمع | **unique user-element attribution** |
| Offline durable queue | لا/خارج MVP | IndexedDB outbox | لا | **خارج MVP** |
| Audit log | لا | موجود | لا | **خارج MVP** |
| Auto-fit | محدود/قابل للإضافة | مفصل | غير أساسي | **MVP** |
| Snapping | Moveable | مفصل | مدعوم | **MVP** |
| Presets | presets محدودة | SFX styles | مذكورة جزئيًا | **نظام scopes كامل** |
| Mobile | مفصل | مفصل جدًا | مبسط | **Kimi detail + Work architecture** |
| JSON DB | LONGTEXT portable | LONGTEXT/structured | MySQL JSON | **LONGTEXT validated** |
| DB baseline | احتاج تحديث MySQL | احتاج تحديث MySQL/MariaDB | MySQL 8-centric | **MySQL 8.4 / MariaDB supported LTS** |
| Version History | لا | لا محتوى كامل | لا | **لا** |

## قرارات استُبعدت عمدًا

- قفل الصفحة بالكامل: يمنع تعاون مترجمين على عناصر مختلفة.
- سجل save-by-save للمساهمات: يضخم البيانات ويشوّه معنى المساهمة.
- Canvas كوثيقة تحرير رئيسية: لا يقدم مزايا كافية هنا أمام DOM/SVG في النص والتحرير.
- JSON DB-native كاعتماد: يقلل portability بين MySQL/MariaDB.
- Offline outbox/Audit Log في MVP: تعقيد أعلى من القيمة الحالية لشخص واحد يدير المشروع.
- Vertical-only reader: لا يغطي المانجا/الكوميكس التقليدية كما ينبغي.
