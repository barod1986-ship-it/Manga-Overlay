# سجل المخاطر

| الخطر | الأثر | المعالجة |
|---|---|---|
| Moveable touch behavior على iOS | عالٍ | PoC على جهاز حقيقي قبل بناء المحرر كاملًا؛ adapter يعزل المكتبة |
| اختلاف قياس الخطوط | عالٍ | self-hosted fonts + renderer واحد + visual tests |
| تعارض محررين | عالٍ | element lock + If-Match |
| بطء صور الفصول | عالٍ | responsive sizes + CDN عند الحاجة + lazy/preload محدود |
| تضخم مساهمات بسبب autosave | متوسط | unique `(element,user)` لا log لكل save |
| تعقيد mobile properties | متوسط | bottom sheets + progressive disclosure |
| اختلاف MySQL/MariaDB | متوسط | LONGTEXT JSON + CI matrix + SQL portable |
| تغيّر صورة أصلية بعد التنسيق | عالٍ | تحذير/سياسة replace تمنع الصمت |
| XSS عبر style/content | عالٍ | plain text + allowlist schema + no raw SVG |
| كثرة العناصر في صفحة | متوسط | stress test 100+ elements، batching reads، no frame network |

| stroke عربي في SFX يقطع/يشوه الوصلات على بعض الخطوط/المتصفحات | متوسط | visual regression متعدد المتصفحات + fallback shadow/shape/تقليل stroke |
| custom upload route لا يرث تلقائيًا WordPress 7.1 client-side media processing | متوسط | MediaService صريح للتحويل + feature detection؛ دمج مسار WP client processing فقط إذا صُمم واختُبر |
| اختلاف قواعد `dbDelta()` يسبب migration ناقصة | متوسط | SQL بصياغة dbDelta المحافظة + migration integration tests على MySQL/MariaDB |
| rate limit يعتمد على Transient قابل للاختفاء المبكر | متوسط | Transient فقط soft throttle؛ enforceable limit يستخدم persistent atomic store/DB |

| unique page_index يتصادم أثناء reorder | عالٍ | permutation validation + row lock + temporary disjoint index range + transaction tests |
| If-Match يُجرد/لا يصل عبر proxy | عالٍ | Controller header tests + staging smoke عبر نفس reverse proxy/CDN + no-cache للwrite routes |
| عقود GET غير مكتوبة الأنواع | متوسط | OpenAPI response schemas إجبارية + contract CI + typed DTO generation/validation |
| defaults للـpresets تصبح متعددة/غامضة | متوسط | transaction single-default semantics + precedence ثابتة + built-in fallback |
