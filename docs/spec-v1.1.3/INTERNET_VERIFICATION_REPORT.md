# تقرير التحقق العميق من الإنترنت

الإصدار الخارجي المدقق: `1.1.0` — **محمول إلى `1.1.3`**. تغييرات 1.1.1–1.1.3 تقوية عقود/سياسات داخلية ولا تغير خط الأساس الخارجي. 1.1.3 يضيف strict element schemas، error semantics، runtime media capabilities، وخوارزمية chapter slug تستخدم `sanitize_title()` الرسمية؛ uniqueness/retry تبقى قواعد تطبيق MOL.  
تاريخ التحقق: `2026-09-01`  
النطاق: جميع ملفات Master Spec والادعاءات الخارجية القابلة للتحقق فيها.

## الخلاصة التنفيذية

- **لا توجد بعد التصحيحات الحالية مشكلة خارجية معروفة تمنع بدء MVP.**
- لم يُعامل اختيار هندسي على أنه حقيقة لمجرد وجود مصدر يؤيده؛ قرارات المنتج/المعمارية صُنفت `DESIGN_*`.
- أي ادعاء يعتمد على الجهاز أو الاستضافة أو البيانات الفعلية صُنّف `PoC/benchmark` ولم يُعتبر مثبتًا بالبحث.
- تم تصحيح نقاط فعلية أثناء التدقيق: BCP47 length، `wp_json_decode`, WordPress JPEG→WebP behavior، HTTP `412/428`, OpenAPI multipart، WCAG 24px/drag alternative، Transients rate-limit semantics، CPT `custom-fields`, وصياغة Canvas/Arabic.
- `MOL-Idempotency-Key` موثق صراحة كعقد خاص بالمشروع لأن مسودة IETF المعروفة انتهت ولم تكن RFC وقت التحقق.

## معنى الحالات

| الحالة | المعنى |
|---|---|
| `VERIFIED` | مصدر رسمي/أولي يدعم الادعاء كما هو. |
| `VERIFIED_AFTER_CORRECTION` | كان هناك خطأ/مبالغة وتم تصحيح المواصفة. |
| `VERIFIED_WITH_SCOPE` | الادعاء صحيح ضمن نطاق محدد يجب عدم توسيعه. |
| `PARTLY_EXTERNAL_PLUS_POC` | جزء موثق خارجيًا، والجودة/السلوك النهائي يحتاج اختبارًا فعليًا. |
| `DESIGN_BENCHMARK` | رقم/هدف اختاره المشروع؛ لا يوجد مصدر يمكنه ضمانه على بيئتنا. |
| `DESIGN_CONSISTENCY` | قرار معماري/منتج؛ تم تدقيق اتساقه بدل محاولة إثباته من الإنترنت. |

## منهجية التدقيق

1. استخراج الادعاءات الخارجية المتكررة عبر جميع ملفات Markdown وOpenAPI وJSON Schema.
2. تجميع الادعاءات المتطابقة تحت Claim ID واحد حتى لا يُعاد التحقق من الحقيقة نفسها عشرات المرات.
3. تفضيل WordPress Developer/Make WordPress، RFC Editor/IETF، W3C، OpenAPI Initiative، PHP/Node/MySQL/MariaDB الرسمية، OWASP وMDN/web.dev.
4. عند وجود ادعاء لا يمكن إثباته خارج البيئة الفعلية، تحويله إلى test gate بدل صياغته كحقيقة.
5. تشغيل فحص اتساق ثابت على العقود بعد التصحيحات، وتحديد ما يبقى لاختبارات CI/PoC.

## تحقق الوصول إلى سجل المصادر

- `SOURCES.md` يحتوي 60 رابطًا عامًا فريدًا بعد إضافة مرجع WordPress الرسمي لـ`sanitize_title()` في v1.1.3.
- 58 رابطًا استُرجعت صفحاتها الرسمية مباشرة في جولات التدقيق؛ رابطا MariaDB أدناه تم تأكيدهما عبر نتائج البحث الرسمية.
- رابطا MariaDB الخاصان بـ`JSON` و`LONGTEXT` تم تأكيدهما من نتائج البحث الرسمية لنطاق MariaDB بعد تعذر الاسترجاع المباشر عبر أداة التصفح.
- لا يُعتبر مجرد وجود URL إثباتًا؛ سجل الادعاءات أدناه يحدد بدقة ما الذي يدعمه كل تجميع مصادر.


## سجل الادعاءات

| ID | الادعاء | النتيجة | المصدر | ملاحظة |
|---|---|---|---|---|
| `C01` | WordPress 7.1 صدر في 2026-08-19، وخط الأساس WordPress 7.1.x حديث صالح للمشروع. | **VERIFIED** | S01 | WordPress.org release docs/archive. |
| `C02` | للتثبيت الجديد على WordPress 7.1: PHP 8.4/8.5، MySQL 8.4، MariaDB 10.11/11.4/11.8 هي توصيات Hosting Team. | **VERIFIED** | S01 | اختيار PHP 8.4 وMySQL 8.4/MariaDB المدعومة جزء من قرار المشروع. |
| `C03` | PHP 8.4 مدعوم أمنيًا حتى 2028-12-31. | **VERIFIED** | S02 | يجعل PHP 8.4 خط إنتاج معقولًا وقت التدقيق. |
| `C04` | Node 24 في حالة LTS وقت التدقيق؛ وهو ضمن نسخ Node المقبولة حاليًا لدى Playwright ومناسب لمتطلبات Vite الحالية. | **VERIFIED** | S02 | رقم Vite/Playwright الفعلي يثبت في lockfile أثناء التنفيذ. |
| `C05` | WordPress REST same-origin يستخدم login cookies؛ X-WP-Nonce يحمل nonce wp_rest لكنه ليس بديلًا عن authentication/capability. | **VERIFIED** | S03 | تمت مواءمة API_SPEC/OpenAPI description مع هذا. |
| `C06` | كل custom REST route يجب أن يسجل permission_callback، وفحص capabilities هو آلية WordPress الصحيحة للصلاحيات. | **VERIFIED** | S03 | public routes يمكن أن تستخدم __return_true صراحةً. |
| `C07` | Registered post meta التي يراد كشفها عبر Core REST على CPT تحتاج show_in_rest ودعم custom-fields على post type. | **VERIFIED_AFTER_CORRECTION** | S03 | أضيف custom-fields إلى supports في WORDPRESS_STRUCTURE.md. |
| `C08` | dbDelta حساس لصياغة DDL: field-per-line، مسافتان بعد PRIMARY KEY، KEY وليس INDEX وغيرها. | **VERIFIED** | S04 | DDL الحالي صيغ وفق القواعد المحافظة. |
| `C09` | current_time(..., true) يستخدم GMT/UTC بينما false/default يتأثر بتوقيت الموقع. | **VERIFIED** | S04 | مواصفة DB تستخدم UTC صراحةً. |
| `C10` | WordPress يوفر wp_json_encode(); لا توجد دالة Core موثقة باسم wp_json_decode(). | **VERIFIED_AFTER_CORRECTION** | S04 | تم استخدام json_decode() من PHP مع فحص الأخطاء. |
| `C11` | لا ينبغي hardcode لمسارات wp-content/plugins/uploads/themes؛ WordPress يوفر دوال مسار/URL وwp_upload_dir(). | **VERIFIED** | S04 | شجرة wp-content في الوثيقة توضيحية فقط. |
| `C12` | WP_ENVIRONMENT_TYPE/wp_get_environment_type يدعمان local/development/staging/production. | **VERIFIED** | S04 | مذكور كإعداد deployment. |
| `C13` | Transient expiration حد أقصى؛ قد تختفي القيمة قبل وقت expiry. | **VERIFIED_AFTER_CORRECTION** | S04 | لذلك Transients فقط soft throttle، وليس عدادًا أمنيًا دقيقًا. |
| `C14` | MariaDB JSON alias لـLONGTEXT بينما MySQL يملك JSON native binary. | **VERIFIED** | S05 | يدعم قرار تخزين JSON في LONGTEXT portable. |
| `C15` | InnoDB يدعم transactions وrow-level locking. | **VERIFIED** | S05 | يلائم عمليات lock/cascade المركبة؛ تفاصيل الاستعلام يجب اختبارها على المحركين. |
| `C16` | Integer display width مثل int(11) لا يغيّر range وهو deprecated في MySQL. | **VERIFIED** | S05 | بقاء widths في DDL سببه توافق dbDelta/MariaDB ويجب مراقبته مستقبلاً. |
| `C17` | RFC 5646 لا يضع حدًا أعلى ثابتًا لطول BCP47 language tag؛ 35 حرفًا حد أدنى للمخازن المحدودة لا حد أقصى. | **VERIFIED_AFTER_CORRECTION** | S06 | تم اعتماد VARCHAR(255) كحد تطبيقي موثق. |
| `C18` | If-Match الفاشل في conditional write يؤدي إلى 412 Precondition Failed وفق HTTP semantics. | **VERIFIED** | S07 | مستخدم لمنع lost update. |
| `C19` | 428 Precondition Required مناسب عندما يشترط الخادم conditional request لتجنب lost update. | **VERIFIED** | S07 | مستخدم عند غياب If-Match المطلوب. |
| `C20` | 423 Locked هو status معرف في WebDAV بمعنى resource locked. | **VERIFIED_WITH_SCOPE** | S07 | استخدامه في MOL عقد API مقصود، وليس سلوك WordPress افتراضيًا. |
| `C21` | 429 Too Many Requests هو status مناسب للـrate limiting. | **VERIFIED** | S07 | سياسة الأرقام/النوافذ نفسها قرار تشغيل. |
| `C22` | مسودة IETF الخاصة بـIdempotency-Key انتهت 2026-04-18 ولم تكن RFC وقت التدقيق. | **VERIFIED_AFTER_CORRECTION** | S08 | لذلك MOL-Idempotency-Key اسم خاص بالمشروع. |
| `C23` | OpenAPI 3.1.2 يدعم multipart/form-data مع schema وEncoding Object؛ raw binary field يمكن أن يكون schema بلا type مع contentType في encoding. | **VERIFIED_AFTER_CORRECTION** | S09 | تمت مواءمة upload endpoint. |
| `C24` | Moveable يعلن دعم draggable/resizable/scalable/rotatable/pinchable/snappable وSVG. | **VERIFIED** | S10 | جودة touch الفعلية على iOS/Android ليست مضمونة من الوثائق وتبقى PoC. |
| `C25` | Canvas 2D الحديث لديه direction=rtl؛ لا يصح الادعاء أن Canvas عاجز عن RTL. | **VERIFIED_AFTER_CORRECTION** | S10 | DOM/SVG بقي قرارًا هندسيًا لسهولة التحرير/النص وليس بسبب عجز Canvas. |
| `C26` | CSS Text يحظر توزيع letter-spacing بطريقة تكسر الوصلات في cursive scripts مثل العربية. | **VERIFIED** | S11 | المحرر لا يستخدم letter-spacing افتراضيًا للعربية. |
| `C27` | paint-order يتحكم في ترتيب رسم fill/stroke لكنه لا يضمن جودة stroke العربي على كل خط/متصفح. | **PARTLY_EXTERNAL_PLUS_POC** | S11 | الجزء الأول موثق؛ جودة الوصلات تحتاج visual regression. |
| `C28` | WCAG 2.2 AA 2.5.7 يتطلب بديل single-pointer للوظيفة التي تعتمد drag؛ 2.5.8 يحدد 24×24 CSS px مع استثناءات. | **VERIFIED_AFTER_CORRECTION** | S12 | 44×44 في MOL هدف UX داخلي أعلى، لا حد WCAG AA. |
| `C29` | Core Web Vitals good: LCP ≤2.5s، INP ≤200ms، CLS ≤0.1، والتقييم الموصى به عند p75. | **VERIFIED** | S13 | قيم staging في المشروع أهداف acceptance؛ نجاح الإنتاج يحتاج field data. |
| `C30` | WordPress يوفر responsive image srcset/sizes وloading/fetchpriority/decoding؛ width/height مهمان لتفادي layout shifts ضمن منطق التحسين. | **VERIFIED** | S14 | طريقة اختيار أول صورة/preload تبقى قرار أداء يُقاس. |
| `C31` | WordPress لا يحول JPEG إلى WebP افتراضيًا لمجرد دعم WebP؛ sub-sizes الافتراضية تبقى بصيغة الأصل ويمكن تغييرها عبر image_editor_output_format. | **VERIFIED_AFTER_CORRECTION** | S14 | MOL MediaService مسؤول صراحةً عن المشتقات. |
| `C32` | WordPress يدعم AVIF بشرط دعم image processing في البيئة؛ custom MOL upload path لا يفترض تلقائيًا الاستفادة من كل مسارات Core client-side processing. | **VERIFIED_WITH_SCOPE** | S14 | AVIF اختياري ويحتاج feature detection/PoC. |
| `C33` | wp_check_filetype_and_ext يحاول تحديد النوع الحقيقي للصور؛ فحص الرفع يجب ألا يثق في extension/Content-Type وحدهما. | **VERIFIED** | S14,S15 | متوافق مع OWASP file upload guidance. |
| `C34` | OWASP يوصي allowlist للامتدادات/الأنواع، filename مولد، limits، وفحص authorization للرفع. | **VERIFIED** | S15 | تفاصيل limits أرقام مشروع. |
| `C35` | CSP/frame-ancestors يقيّد embedding ويساعد ضد clickjacking؛ X-Content-Type-Options:nosniff يمنع MIME sniffing في السياقات المعرّفة. | **VERIFIED** | S15 | CSP الدقيقة تحتاج اختبار مع assets الفعلية. |
| `C36` | Autosave 1200ms، lock TTL 45s/renew15s، API p95<300ms، MOL_UNIT، 100+ element stress وغيرها ليست حقائق إنترنت. | **DESIGN_BENCHMARK** | — | تبقى قرارات/فرضيات يتم قبولها أو تعديلها بناءً على PoC/benchmark دون تغيير معماري صامت. |
| `C37` | React+TypeScript، DOM+safe SVG، element-level lock، presets scopes، webtoon+paged، contribution uniqueness هي قرارات منتج/معمارية وليست ادعاءات قابلة للإثبات من الإنترنت. | **DESIGN_CONSISTENCY** | — | تم فحص اتساقها بين DB/API/editor/roles/tests. |
| `C38` | `WP_REST_Request::get_header()` يقرأ request headers، وNginx proxy caching قد لا يمرر conditional headers ومنها `If-Match` إلى upstream عند تفعيل cache. | **VERIFIED** | S16 | يدعم Controller contract وdeployment proxy gate في v1.1.1. |

## التدقيق ملفًا بملف

| الملف | النوع | مجموعات الادعاء | النتيجة | يحتاج Runtime/PoC | ملاحظة |
|---|---|---|---|---|---|
| `README.md` | mixed | C01,C02,C03,C04,C06,C37 | **PASS** | لا | خط الأساس الخارجي متحقق؛ بقية المحتوى قاعدة مرجعية. |
| `PROJECT_OVERVIEW.md` | design | C37 | **PASS** | لا | رؤية ونطاق منتج؛ فُحص الاتساق مع المتطلبات والقرارات. |
| `REQUIREMENTS.md` | mixed | C17,C18,C24,C28,C29,C30,C36,C37 | **PASS_WITH_RUNTIME_GATES** | نعم | أهداف الأداء/الجوال/auto-fit تبقى اختبارات قبول وليست وعودًا عامة. |
| `DECISIONS.md` | mixed | C01-C04,C14-C20,C24-C32,C36,C37 | **PASS** | جزئي | القرارات الخارجية مدعومة؛ الأرقام التشغيلية PoC/benchmark. |
| `ARCHITECTURE.md` | mixed | C05,C06,C14,C15,C24,C37 | **PASS** | نعم | اختيار DOM/SVG/React معماري؛ touch/perf عبر PoC. |
| `WORDPRESS_STRUCTURE.md` | mixed | C06,C07,C08,C11,C37 | **PASS_AFTER_CORRECTION** | لا | أضيف custom-fields support للـCPT لسلامة registered meta عبر Core REST. |
| `DATABASE_SCHEMA.md` | mixed | C08-C10,C14-C17,C36,C37 | **PASS** | نعم | DDL يحتاج تشغيل dbDelta فعلي في CI على MySQL 8.4 وMariaDB 10.11. |
| `API_SPEC.md` | mixed | C05,C06,C18-C23,C36-C38 | **PASS_AFTER_CONTRACT_HARDENING** | نعم | اكتملت عقود GET/filters/batch/review/ETag؛ runtime proxy test يبقى gate. |
| `API.openapi.yaml` | structured | C05,C18-C23,C37 | **PASS_STATIC_DEEP** | نعم | v1.1.2: 21 paths، 30 operationId، 11 GET response contracts، error contracts مكتملة للـwrites، وسياسة draft visibility صريحة؛ كل internal refs والأمثلة الموسومة تُفحص في `VALIDATION_REPORT.md`. |
| `TRANSLATION_EDITOR_SPEC.md` | mixed | C24-C28,C36,C37 | **PASS_WITH_POC** | نعم | Moveable features موثقة؛ Arabic SFX/auto-fit/touch تحتاج device visual PoC. |
| `READER_SPEC.md` | mixed | C29-C32,C36,C37 | **PASS_WITH_BENCHMARK** | نعم | lazy/preload/fetchpriority strategy تقاس على فصول حقيقية. |
| `UI_UX_SPEC.md` | design | C37 | **PASS** | نعم | مسارات وتجربة منتج؛ تحقق E2E/visual. |
| `MOBILE_SPEC.md` | mixed | C24,C28,C36,C37 | **PASS_WITH_POC** | نعم | 44px هدف داخلي؛ البديل غير القائم على drag مثبت في المواصفة. |
| `USER_ROLES_PERMISSIONS.md` | mixed | C06,C37 | **PASS** | نعم | فحص capability صحيح نظريًا ويحتاج integration tests. |
| `CONTRIBUTION_SYSTEM.md` | design | C37 | **PASS** | نعم | تعريف مساهمة unique(user,element) متسق مع DB/tests. |
| `CONTENT_MANAGEMENT.md` | mixed | C31-C34,C37 | **PASS** | نعم | رفع ومعالجة الصورة تتطلب integration test مع image editor الفعلي. |
| `PROFILES_AND_COMMUNITY.md` | design | C37 | **PASS** | نعم | متطلبات منتج؛ لا ادعاءات تقنية خارجية جوهرية. |
| `PERFORMANCE_AND_MEDIA.md` | mixed | C13,C29-C32,C36,C37 | **PASS_WITH_BENCHMARK** | نعم | أزيلت صياغة FPS شبه العامة؛ الأداء أرقام قياس للمشروع. |
| `SECURITY.md` | mixed | C05,C06,C13,C18,C33-C35,C37,C38 | **PASS** | نعم | أضيف same-route force release وproxy/header gate؛ CSP/rate limits تختبر في deployment. |
| `ERROR_HANDLING.md` | mixed | C18-C21,C36,C37 | **PASS** | نعم | معاني status codes متحققة؛ UX recovery قرار منتج. |
| `TEST_STRATEGY.md` | mixed | C04,C08,C24,C27,C29-C33,C36,C37 | **PASS** | نعم | الملف نفسه يميز tests التي يجب تنفيذها لاحقًا. |
| `ACCEPTANCE_CRITERIA.md` | mixed | C18-C20,C24,C27-C32,C36,C37 | **PASS_WITH_RUNTIME_GATES** | نعم | معايير device/performance لا تُعلن ناجحة حتى تشغيلها. |
| `MVP_ROADMAP.md` | plan | C37 | **PASS** | لا | خطة ترتيب فقط. |
| `DEVELOPMENT_PLAN.md` | plan | C08,C24,C36,C37 | **PASS** | نعم | PoCs موضوعة قبل التنفيذ الكامل. |
| `DEPLOYMENT_REQUIREMENTS.md` | mixed | C01-C04,C12,C36,C38 | **PASS** | نعم | أضيف smoke عبر reverse proxy لـIf-Match/ETag؛ بقية خيارات التشغيل كما هي. |
| `DATA_MODEL_EXAMPLES.md` | examples | C17,C37 | **PASS_STATIC** | لا | JSON blocks parse؛ style example يمر على schema. |
| `CODING_STANDARDS.md` | mixed | C09-C11,C15,C37 | **PASS_AFTER_CORRECTION** | لا | تم تصحيح wp_json_decode غير الموجودة سابقًا. |
| `GLOSSARY.md` | internal | C37 | **PASS** | لا | المصطلحات متسقة مع الملفات الرسمية. |
| `SOURCE_COMPARISON.md` | internal | C37 | **PASS** | لا | توثيق قرارات المصادر القديمة؛ ليس مرجع تنفيذ أعلى من DECISIONS. |
| `SOURCES.md` | sources | C01-C35,C38 | **PASS** | لا | تم توسيعه إلى سجل مصادر رسمي شامل. |
| `RISK_REGISTER.md` | risk | C08,C13,C24,C27,C31,C32,C36,C37 | **PASS** | نعم | المخاطر ليست وعودًا؛ mitigations متوافقة مع التحقق. |
| `ELEMENT_STYLE.schema.json` | structured | C37 | **PASS_STATIC** | لا | Draft 2020-12 schema صالح محليًا؛ additionalProperties=false. |
| `VALIDATION_REPORT.md` | internal | C37 | **REGENERATED_FOR_1.1.2** | لا | يعكس contract-hardening checks الجديدة بعد التعديلات. |
| `CONTRACT_HARDENING_REPORT.md` | internal | C37,C38 | **DESIGN_CONSISTENCY** | جزئي | يوثق الثغرات الثمانية التي سدت في 1.1.1 ولا يغير رؤية المنتج. |
| `API_VISIBILITY_HARDENING_REPORT.md` | internal | C05,C18-C21,C37,C38 | **DESIGN_CONSISTENCY** | جزئي | يوثق تقوية 1.1.2: أخطاء العقود، draft visibility، أمثلة DTO، most_read المشروط، وإصلاح composition في WorkDetail/LibraryMeta. |
| `SHA256SUMS.txt` | integrity | — | **REGENERATED_AT_PACKAGING** | لا | يعاد توليده بعد آخر تعديل، ويُتحقق منه قبل إنشاء ZIP. |
| `INTERNET_VERIFICATION_REPORT.md` | audit | C01-C38 | **PASS** | لا | هذا التقرير. |
| `INTERNET_VERIFICATION_MATRIX.csv` | audit | C01-C38 | **PASS** | لا | مصفوفة قابلة للفرز لكل ملف. |
| `C39` | WordPress يوفر `sanitize_title()` لتحويل string إلى slug مناسب للاستخدام في URL؛ suffix/uniqueness/retry ليست سلوكًا ضمنيًا للدالة بل قرار MOL. | **VERIFIED_WITH_SCOPE** | S39 | يدعم اختيار الدالة في D-045 دون تحويل خوارزمية uniqueness الخاصة بالمشروع إلى ادعاء عن WordPress. |

## التصحيحات التي أُدخلت نتيجة التحقق

### 1. WordPress / Server baseline
- ثُبت WordPress `7.1.x` كخط المشروع بعد التحقق من إصدار 19 أغسطس 2026.
- ثُبت PHP `8.4.x`، MySQL `8.4 LTS` أو MariaDB `10.11/11.4/11.8` وفق توصيات Hosting Team للتركيبات الجديدة.
- Node `24 LTS` صالح لبيئة build/test الحالية؛ لا يثبت رقم package في المواصفة، بل في lockfile.

### 2. قاعدة البيانات
- JSON المنقول بين MySQL/MariaDB بقي `LONGTEXT` validated في التطبيق.
- language tags أصبحت `VARCHAR(255)` وحدًا تطبيقيًا، بعد تصحيح فهم 35 حرفًا في RFC 5646.
- DDL صيغ ليحترم قواعد `dbDelta()` المحافظة؛ integer display widths موثقة كتوافق migration فقط لأنها deprecated في MySQL.

### 3. WordPress APIs
- `wp_json_decode()` أزيلت؛ WordPress يملك `wp_json_encode()`، أما decode فهو PHP `json_decode()`.
- أضيف `custom-fields` support إلى `mol_work` CPT لسلامة registered meta إذا عُرضت عبر Core REST.
- مسارات/Uploads لا تُفترض ثابتة؛ تستخدم WordPress APIs.

### 4. REST / HTTP
- أُكملت `API.openapi.yaml` لتصف machine-readable request bodies لإنشاء/تعديل الفصول، إعادة ترتيب الصفحات، presets، البلاغات، وحفظ موضع القراءة؛ كما أضيف `x-required-capability` كتوثيق آلي لسياسة كل write operation.
- missing required `If-Match` → `428`; stale/false `If-Match` → `412`; active editor lock → `423` كعقد MOL.
- OpenAPI multipart image schema صُحح وفق 3.1.2.
- أضيف `x-wordpress-authentication` إلى OpenAPI لشرح أن cookie session ديناميكية الاسم وأن `X-WP-Nonce` ليس authentication مستقلًا.
- idempotency header أصبح `MOL-Idempotency-Key` بدل الإيحاء بأن `Idempotency-Key` RFC منشور.
- v1.1.3 يمنع YAML anchors/aliases في العقد المحزّم ويطابق error example status مع response key دلاليًا؛ هذه قاعدة اتساق داخلية لا ادعاء خارجي.
- chapter slug يستخدم `sanitize_title()` الموثقة رسميًا؛ suffix/retry/unique guard قواعد MOL.

### 5. Media
- أزيل افتراض أن WordPress يحول JPEG إلى WebP تلقائيًا؛ MOL MediaService ينفذ التحويل صراحةً عند الدعم.
- AVIF اختياري ومشروط بقدرة image editor/المسار المدمج فعلًا.
- responsive/lazy/fetchpriority تبقى مرتبطة بسياق القارئ وليست وصفة ثابتة لكل صورة.

### 6. العربية والمحرر والجوال
- لم يعد Canvas مرفوضًا بادعاء أنه لا يدعم RTL؛ DOM/SVG اختيار تحرير وصيانة.
- `letter-spacing` لا يستخدم كإعداد عربي افتراضي؛ SFX stroke يحتاج visual regression على الخطوط والأجهزة الفعلية.
- 44×44 أصبح هدف UX داخلي؛ WCAG AA minimum هو 24×24 مع استثناءات، وdrag functionality لها بدائل pointer بدون drag.

### 7. الأداء والأمان
- Core Web Vitals صيغت بدقة: LCP/INP/CLS + p75.
- API p95 وautosave/lock timings وframe pacing أهداف مشروع لا ادعاءات عامة.
- Transient لا يستخدم وحده لrate limit يجب أن يكون enforceable بدقة.
- file upload/CSP/nosniff متسقة مع المراجع الأمنية الأولية.

## ما لا يمكن للإنترنت إثباته قبل التنفيذ

- أن `1200ms` هو أفضل debounce للمستخدمين الحقيقيين.
- أن lock `45s` وتجديد `15s` هما أفضل توازن على الاستضافة النهائية.
- أن Moveable سيعطي تجربة ممتازة على كل iPhone/Android مستهدف؛ الميزة موجودة لكن جودة اللمس تحتاج PoC.
- أن Auto-fit العربي سيحافظ على الجمالية لكل خط/فقاعة.
- أن SFX stroke سيبدو سليمًا مع كل خط/متصفح.
- أن Public API سيحقق p95 `<300ms` أو LCP الهدف على بيانات/استضافة لم تُبن بعد.
- أن WebP/AVIF processing متاح فعليًا على الخادم النهائي حتى يمر Site Health/feature detection والاختبار.
- أن 100 عنصر/صفحة هو حد الضغط المناسب؛ هذا dataset بداية للاختبار وليس حدًا تقنيًا مثبتًا.

## حكم الاعتماد

**الحزمة صالحة لتكون مرجع تنفيذ MVP من ناحية الحقائق الخارجية بعد هذه التصحيحات، بشرط عدم تجاوز PoC/CI/benchmark gates المكتوبة في `DEVELOPMENT_PLAN.md`, `TEST_STRATEGY.md`, و`ACCEPTANCE_CRITERIA.md`.**

لا يجوز لوكيل التطوير تحويل أي هدف benchmark إلى ضمان، أو حذف gate بحجة أن مكتبة/متصفح “يدعمه” نظريًا.

## المصادر

السجل الكامل وروابط المصادر المباشرة موجود في `SOURCES.md`. المصفوفة القابلة للفرز موجودة في `INTERNET_VERIFICATION_MATRIX.csv`.
