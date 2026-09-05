# سجل القرارات النهائية

جميع القرارات أدناه ملزمة للإصدار `1.1.3`.

| ID | القرار النهائي |
|---|---|
| D-001 | WordPress `7.1.x`، PHP `8.4.x`، MySQL `8.4 LTS` أو MariaDB `10.11/11.4/11.8`، Node `24 LTS`. |
| D-002 | WordPress Core للحسابات والجلسات والوسائط؛ الإضافة لمنطق المجال؛ القالب للعرض العام. |
| D-003 | `mol_work` هو CPT؛ الفصول والصفحات والعناصر والأقفال والمساهمات والبلاغات والتقدم والـpresets جداول مخصصة. |
| D-004 | محرر الترجمة React + TypeScript داخل الإضافة فقط؛ الموقع العام ليس SPA. |
| D-005 | DOM للنص + SVG مولد من معلمات آمنة للخلفيات/الأشكال؛ لا Canvas كنموذج تحرير رئيسي. |
| D-006 | `react-moveable` لمقابض drag/resize/rotate/snap/pinch، مع تثبيت إصدار مجرّب في lockfile عند التنفيذ. |
| D-007 | الهندسة normalized integers بوحدة `MOL_UNIT=1,000,000`، والدوران milli-degrees. |
| D-008 | `style_json` يخزن كنص JSON في `LONGTEXT` ويُتحقق منه بمخطط داخل PHP؛ لا اعتماد على دوال JSON الخاصة بمحرك قاعدة بيانات في المسار الحرج. |
| D-009 | النص plain text فقط. لا HTML/CSS/SVG خام من المستخدم. |
| D-010 | الحفظ الناجح ينشر آخر نسخة مباشرة للقراء عندما يكون الفصل منشورًا. |
| D-011 | منع التعارض على **مستوى العنصر**: lease 45s، تجديد 15s، وجدول locks واحد فقط. |
| D-012 | optimistic concurrency عبر `ETag`/`If-Match`; غياب الشرط المطلوب يعيد `428 Precondition Required`، الشرط القديم يعيد `412 Precondition Failed`، والقفل النشط لآخر يعيد `423 Locked`. |
| D-013 | لا Version History لمحتوى الترجمة. الحقل `version` للتزامن فقط. |
| D-014 | المساهمات تُحسب على **العنصر الفريد لكل مستخدم**؛ Autosave المتكرر لا يضاعف عدد المساهمات. |
| D-015 | قارئ MVP يدعم `webtoon` و`paged`. اتجاه paged يمكن أن يكون RTL أو LTR لكل عمل. |
| D-016 | العمل يملك default reader mode واتجاه قراءة، ويمكن للفصل override عند الحاجة. |
| D-017 | الصور WordPress attachments؛ صفحة الفصل صف في `mol_pages`; الأصل لا يتغير. |
| D-018 | رفع صفحة واحدة لكل طلب مع queue محدود في العميل؛ لا chunking افتراضي للصور العادية. |
| D-019 | صور مشتقة responsive (WebP أساسًا عبر MOL MediaService بشكل صريح، AVIF إذا كان مدعومًا ونافعًا) مع JPEG/PNG الأصلي كمرجع؛ لا نفترض JPEG→WebP تلقائيًا من WordPress، ولا يُشترط AVIF للإطلاق. |
| D-020 | التخزين المحلي + CDN origin-pull في MVP؛ Object storage خلف adapter لاحقًا. |
| D-021 | الأدوار أربعة: Member, Translator, Moderator, Manager؛ التفويض دائمًا capabilities وليس اسم الدور. |
| D-022 | `mol_manage_content` و`mol_upload_content` صلاحيتان منفصلتان يمكن منحهما فرديًا؛ الأولى لإدارة الأعمال/الفصول/ترتيب الصفحات، والثانية لرفع ملفات الصور. |
| D-023 | Presets جزء من MVP بثلاثة scopes: `personal`, `work`, `global`; المترجم ينشئ personal، والمدير/المخول ينشر work/global. |
| D-024 | Mobile editor ليس نسخة مصغرة من desktop: bottom sheet، مقابض لمس ≥44px، ومحرر نص منفصل عند الحاجة. |
| D-025 | Auto-fit اختياري للفقاعات/السرد، وSnapping لحواف/مراكز الصفحة والعناصر. |
| D-026 | لا Offline durable queue في MVP. عند فقد الشبكة تبقى الحالة dirty في الذاكرة وتظهر رسالة واضحة؛ لا يوعد المستخدم بحفظ دائم محلي. |
| D-027 | لا Audit Log تشغيلي لعمليات التحرير في MVP؛ تخزن فقط attribution الضرورية للمساهمات وآخر محرر للعنصر. |
| D-028 | البحث يبدأ بآليات WordPress/CPT وفهارس مناسبة؛ محرك بحث خارجي يؤجل حتى تظهر حاجة قياسية. |
| D-029 | Redis/CDN تحسينات تشغيلية قابلة للتفعيل حسب القياس وليست شرط صحة للنظام. |
| D-030 | أي تغيير معماري بعد Freeze يحتاج ADR جديد ورفع إصدار Master Spec. |
| D-031 | وظائف التحويل الأساسية لا تعتمد على drag وحده: توجد بدائل Transform بأزرار/قيم رقمية، مع 44×44 CSS px كهدف لمس داخلي أعلى من الحد الأدنى WCAG AA. |
| D-032 | كل Public GET في عقد MOL يملك response schema صريحًا في OpenAPI؛ `/library` يعيد pagination envelope وفلاتره/ترتيبه جزء من العقد. |
| D-033 | القارئ يستطيع جلب overlay الفصل دفعة واحدة عبر `GET /chapters/{id}/elements?lang=...`؛ جلب صفحة-بصفحة يبقى متاحًا، ولا يوجد طلب عنصر-بعنصر. |
| D-034 | مراجعة الترجمة لها workflow صريح: `mol_review_translations` يستخدم `PATCH /chapters/{id}/review` لتعيين `needs_review|completed`; الصلاحيات غير المستخدمة في MVP تُحذف من canonical capability set. |
| D-035 | reorder الصفحات يستخدم transaction + row lock + **مرحلة index مؤقتة في نطاق منفصل** ثم إعادة 0..N-1، حتى لا يصطدم `uq_chapter_index`. |
| D-036 | إنشاء عنصر يحل النمط بالترتيب: preset صريح → personal default → work default → global default → built-in base style، ثم يطبق style overrides. `is_default` يعني default واحد فعال لكل scope/owner/work/type ويُحسم داخل transaction. |
| D-037 | لا يوجد force-unlock endpoint منفصل: `DELETE /elements/{id}/lock` يحرر قفل المالك بالتوكن، ومن يملك `mol_manage_content` يستطيع force-release دون token وفق policy موثقة. |
| D-038 | ETag للعناصر هو strong quoted version (مثل `"7"`); Controllers تقرأ `If-Match` عبر WordPress REST request headers وتعيد ETag على create/update. Collection GETs لا تستطيع إعادة ETag مستقل لكل child؛ يبني العميل `If-Match` من حقل `version` بصيغة quoted decimal. بيئة النشر تمرر `If-Match` ولا cache مسارات الكتابة. |
| D-039 | الفصل المنشور عام. الفصل غير المنشور وموارده تُقرأ فقط في سياق WordPress REST موثّق لمن يملك `mol_use_editor` أو `mol_manage_content`; غير ذلك 404 لتجنب كشف draft IDs. |
| D-040 | كل write operation محمية توثق 401/403، وكل write ذات request body توثق 400. 429 يظهر فقط على routes ذات limiter فعلي. |
| D-041 | لا يوجد read-counter backend في MVP؛ `most_read_available=false`, `read_count=null`, وطلب `most_read` يعيد `mol_sort_unavailable` حتى إضافة backend لاحق. |
| D-042 | قيم DB العددية ذات representation نصي محتمل مثل DECIMAL `sort_order` تُطبع إلى نوع OpenAPI في Repository/DTO (`float`) قبل serialization. |
| D-043 | عقد OpenAPI المحزّم لا يستخدم YAML anchors/aliases؛ كل error example يجب أن يطابق HTTP status الذي يظهر تحته، ويمنع validator الإصدار إذا خالف ذلك. |
| D-044 | Element write contracts strict: الخصائص المجهولة مرفوضة، PATCH الفارغ/no-op مرفوض، وحدود geometry موحدة. style patch يحمل `element_type` كـimmutable discriminator وتطبق قيود style حسب النوع. |
| D-045 | slug الفصل يولده الخادم: `sanitize_title(title)` إن وُجد title غير فارغ، وإلا `sanitize_title("chapter-" + chapter_label)`؛ fallback `chapter`; التصادمات تأخذ `-2`, `-3`... مع retry محدود، و`uq_work_slug` الحارس النهائي ضد السباق. |
| D-046 | lock renew/release للمالك يعيدان `409 mol_lock_lost` عندما token لا يمثل lease نشطًا حاليًا؛ missing element = 404. manager force-release يبقى على DELETE نفسه. |
| D-047 | JPEG/PNG/WebP هي upload baseline الثابتة. AVIF upload/derived output capability وقت تشغيل يُعلن عبر `GET /capabilities`; عدم الإعلان يعني أن AVIF upload قد يعيد 415. |
| D-048 | IDs التاريخية `created_by/updated_by` في العناصر لا تُصفّر عند حذف مستخدم WordPress؛ تبقى أرقام attribution غير null، بينما resolver الواجهة قد يعرض المستخدم كـdeleted/unavailable. |

