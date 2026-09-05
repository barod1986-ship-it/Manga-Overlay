# تصميم قاعدة البيانات

## 1. قواعد عامة

- استخدم `$wpdb->prefix . 'mol_*'` وليس `wp_` ثابتًا.
- InnoDB + charset/collation من `$wpdb->get_charset_collate()`.
- كل التواريخ UTC داخل DB؛ في WordPress تُكتب عبر `current_time('mysql', true)` أو قيمة UTC مكافئة، لا عبر `current_time('mysql')` الافتراضية المحلية.
- جميع قيم JSON في `longtext` مع `wp_json_encode()` وvalidation قبل الكتابة.
- لا `ENUM` ولا `CHECK` كاعتماد أساسي؛ القيم المقيدة تُتحقق في PHP.
- لا يعتمد MVP على Foreign Keys؛ توجد فهارس وعمليات حذف متسلسلة في Service. هذا قرار تبسيط للمهاجرات والمنطق، وليس ادعاءً بأن `dbDelta()` يمنع Foreign Keys مطلقًا.
- `MOL_UNIT = 1000000`.
- SQL الممرر إلى `dbDelta()` يتبع صياغته المحافظة: نوع الحقل lowercase، كل حقل في سطر، `PRIMARY KEY  (...)` بمسافتين، و`KEY` للفهرس، مع display widths رقمية صريحة مثل `bigint(20)`/`int(11)` لأن WordPress Core ما زال يستخدمها ولتفادي اختلافات مقارنة `dbDelta()` خصوصًا مع MariaDB. هذه widths لا تغيّر مدى integer وهي deprecated في MySQL، لكنها هنا قيد توافق migration لا قرار نمذجة بيانات.
- حقول وسوم اللغة `varchar(255)`؛ RFC 5646 لا يضع حدًا أعلى ثابتًا لوسم BCP 47، و255 هو **حد تطبيقي موثق** وليس حدًا من المعيار.
- بما أن منطق الحذف/القفل يعتمد transactions، تُنشأ الجداول صراحةً بـ`ENGINE=InnoDB` ويُتحقق من المحرك بعد migration.

- `sort_order decimal(14,4)` قيمة تخزين/فرز؛ Repository يحولها صراحةً إلى `float` عند بناء Chapter DTO لأن عقد REST يعرفها `number`.
- **لا يوجد read-counter table في MVP.** لذلك `most_read_available=false` و`read_count=null`; `sort=most_read` يعيد `400 mol_sort_unavailable` حتى يضاف backend مخصص في إصدار لاحق.

## 2. WordPress native

### `mol_work` CPT

يستخدم:
- `post_title`: الاسم.
- `post_content`: الوصف.
- featured image: الغلاف.
- taxonomies: `mol_genre`, `mol_work_type`, `mol_source_language`, `mol_work_status`.
- registered post meta:
  - `_mol_alt_titles` JSON array.
  - `_mol_default_reader_mode`: `webtoon|paged`.
  - `_mol_reading_direction`: `rtl|ltr`.

## 3. الجداول

### `mol_chapters`

```sql
CREATE TABLE {prefix}mol_chapters (
  id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  work_id bigint(20) unsigned NOT NULL,
  chapter_label varchar(64) NOT NULL,
  sort_order decimal(14,4) NOT NULL DEFAULT 0,
  title varchar(255) NULL,
  slug varchar(190) NOT NULL,
  translation_status varchar(24) NOT NULL DEFAULT 'untranslated',
  source_lang_override varchar(255) NULL,
  reader_mode_override varchar(16) NULL,
  direction_override varchar(8) NULL,
  is_published tinyint(1) NOT NULL DEFAULT 0,
  published_at datetime NULL,
  created_by bigint(20) unsigned NOT NULL,
  created_at datetime NOT NULL,
  updated_at datetime NOT NULL,
  PRIMARY KEY  (id),
  UNIQUE KEY uq_work_slug (work_id, slug),
  KEY idx_work_sort (work_id, sort_order),
  KEY idx_status (translation_status),
  KEY idx_published (is_published, published_at)
) ENGINE=InnoDB {charset_collate};
```


### توليد `mol_chapters.slug`

`slug` ليس مدخلًا مطلوبًا من العميل في MVP. `ChapterService` يولده قبل INSERT كالتالي:

1. إن كان `title` غير فارغ: `sanitize_title(title)`.
2. وإلا: `sanitize_title('chapter-' . chapter_label)`.
3. إذا أصبح الناتج فارغًا: base=`chapter`.
4. جرّب base، ثم `base-2`, `base-3`... ضمن عدد retries محدود.
5. `UNIQUE uq_work_slug (work_id, slug)` هو الحارس النهائي ضد race بين طلبين متزامنين؛ duplicate-key يعيد المحاولة باسم تالٍ، وإذا استنفدت المحاولات يرجع API `409 mol_slug_conflict`.

### `mol_pages`

```sql
CREATE TABLE {prefix}mol_pages (
  id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  chapter_id bigint(20) unsigned NOT NULL,
  page_index int(11) unsigned NOT NULL,
  attachment_id bigint(20) unsigned NOT NULL,
  natural_width int(11) unsigned NOT NULL,
  natural_height int(11) unsigned NOT NULL,
  created_at datetime NOT NULL,
  PRIMARY KEY  (id),
  UNIQUE KEY uq_chapter_index (chapter_id, page_index),
  KEY idx_chapter (chapter_id),
  KEY idx_attachment (attachment_id)
) ENGINE=InnoDB {charset_collate};
```

### `mol_elements`

```sql
CREATE TABLE {prefix}mol_elements (
  id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  page_id bigint(20) unsigned NOT NULL,
  target_lang varchar(255) NOT NULL DEFAULT 'ar',
  element_type varchar(24) NOT NULL,
  x_unit int(11) unsigned NOT NULL,
  y_unit int(11) unsigned NOT NULL,
  w_unit int(11) unsigned NOT NULL,
  h_unit int(11) unsigned NOT NULL,
  rotation_mdeg int(11) NOT NULL DEFAULT 0,
  z_index int(11) NOT NULL DEFAULT 0,
  content longtext NOT NULL,
  style_json longtext NOT NULL,
  version bigint(20) unsigned NOT NULL DEFAULT 1,
  created_by bigint(20) unsigned NOT NULL,
  updated_by bigint(20) unsigned NOT NULL,
  created_at datetime NOT NULL,
  updated_at datetime NOT NULL,
  PRIMARY KEY  (id),
  KEY idx_page_lang_z (page_id, target_lang, z_index),
  KEY idx_updated_by (updated_by)
) ENGINE=InnoDB {charset_collate};
```


### Attribution عند حذف مستخدم

حقول `mol_elements.created_by` و`updated_by` تبقى **NOT NULL** حتى إذا حُذف حساب WordPress لاحقًا؛ لا تُعاد كتابة التاريخ إلى `NULL`. طبقة عرض الحساب تحاول resolve الـID، وإذا لم يعد المستخدم موجودًا تعرض label مثل `deleted user` دون تغيير الصف التاريخي.

### `mol_element_locks`

هذا هو **المصدر الوحيد** لحالة القفل.

```sql
CREATE TABLE {prefix}mol_element_locks (
  element_id bigint(20) unsigned NOT NULL,
  user_id bigint(20) unsigned NOT NULL,
  lock_token char(64) NOT NULL,
  acquired_at datetime NOT NULL,
  expires_at datetime NOT NULL,
  PRIMARY KEY  (element_id),
  UNIQUE KEY uq_token (lock_token),
  KEY idx_user (user_id),
  KEY idx_expires (expires_at)
) ENGINE=InnoDB {charset_collate};
```

### `mol_contributions`

صف واحد لكل مستخدم/عنصر. Autosave يقوم UPSERT ولا ينشئ صفًا جديدًا.

```sql
CREATE TABLE {prefix}mol_contributions (
  id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  element_id bigint(20) unsigned NOT NULL,
  user_id bigint(20) unsigned NOT NULL,
  work_id bigint(20) unsigned NOT NULL,
  chapter_id bigint(20) unsigned NOT NULL,
  created_element tinyint(1) NOT NULL DEFAULT 0,
  first_contributed_at datetime NOT NULL,
  last_contributed_at datetime NOT NULL,
  PRIMARY KEY  (id),
  UNIQUE KEY uq_element_user (element_id, user_id),
  KEY idx_chapter_user (chapter_id, user_id),
  KEY idx_work_user (work_id, user_id),
  KEY idx_user_last (user_id, last_contributed_at)
) ENGINE=InnoDB {charset_collate};
```

### `mol_reports`

```sql
CREATE TABLE {prefix}mol_reports (
  id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  chapter_id bigint(20) unsigned NOT NULL,
  page_id bigint(20) unsigned NULL,
  element_id bigint(20) unsigned NULL,
  reporter_id bigint(20) unsigned NOT NULL,
  report_type varchar(24) NOT NULL,
  message text NOT NULL,
  status varchar(24) NOT NULL DEFAULT 'open',
  resolved_by bigint(20) unsigned NULL,
  created_at datetime NOT NULL,
  resolved_at datetime NULL,
  PRIMARY KEY  (id),
  KEY idx_status_created (status, created_at),
  KEY idx_chapter (chapter_id),
  KEY idx_element (element_id)
) ENGINE=InnoDB {charset_collate};
```

### `mol_reading_progress`

```sql
CREATE TABLE {prefix}mol_reading_progress (
  user_id bigint(20) unsigned NOT NULL,
  chapter_id bigint(20) unsigned NOT NULL,
  page_index int(11) unsigned NOT NULL DEFAULT 0,
  progress_unit int(11) unsigned NOT NULL DEFAULT 0,
  reader_mode varchar(16) NOT NULL DEFAULT 'webtoon',
  updated_at datetime NOT NULL,
  PRIMARY KEY  (user_id, chapter_id),
  KEY idx_user_updated (user_id, updated_at)
) ENGINE=InnoDB {charset_collate};
```

### `mol_style_presets`

```sql
CREATE TABLE {prefix}mol_style_presets (
  id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  scope varchar(16) NOT NULL,
  owner_user_id bigint(20) unsigned NULL,
  work_id bigint(20) unsigned NULL,
  name varchar(100) NOT NULL,
  element_type varchar(24) NOT NULL,
  style_json longtext NOT NULL,
  is_default tinyint(1) NOT NULL DEFAULT 0,
  created_by bigint(20) unsigned NOT NULL,
  created_at datetime NOT NULL,
  updated_at datetime NOT NULL,
  PRIMARY KEY  (id),
  KEY idx_scope_work (scope, work_id, element_type),
  KEY idx_owner (owner_user_id, element_type)
) ENGINE=InnoDB {charset_collate};
```

#### دلالة `is_default`

- `is_default=1` يعني أن الـpreset هو الاختيار التلقائي داخل **نطاقه الفعلي** ونوع العنصر.
- نطاق personal: مفتاح الدلالة `(scope, owner_user_id, element_type)`.
- نطاق work: `(scope, work_id, element_type)`.
- نطاق global: `(scope, element_type)`.
- عند تعيين preset جديد default، `PresetService` يبدأ transaction، يقفل presets المطابقة للنطاق/النوع، يصفّر `is_default` القديم، ثم يثبت الجديد. لا نعتمد unique index متعدد NULLs لتطبيق هذه القاعدة عبر MySQL/MariaDB.
- أولوية resolution عند إنشاء عنصر بلا preset صريح: personal default → work default → global default → built-in base style.

### `mol_idempotency_keys`

يدعم إعادة محاولة رفع الصفحة دون إنشاء attachment/page مكرر. المفتاح **عقد داخلي للتطبيق** وليس ادعاءً بأنه معيار HTTP منشور.

```sql
CREATE TABLE {prefix}mol_idempotency_keys (
  id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  user_id bigint(20) unsigned NOT NULL,
  scope varchar(64) NOT NULL,
  idempotency_key varchar(100) NOT NULL,
  request_hash char(64) NOT NULL,
  resource_type varchar(32) NULL,
  resource_id bigint(20) unsigned NULL,
  response_code int(11) unsigned NULL,
  response_json longtext NULL,
  created_at datetime NOT NULL,
  expires_at datetime NOT NULL,
  PRIMARY KEY  (id),
  UNIQUE KEY uq_user_scope_key (user_id, scope, idempotency_key),
  KEY idx_expires (expires_at)
) ENGINE=InnoDB {charset_collate};
```

- نفس المفتاح + نفس `request_hash`: يرجع المورد/الاستجابة السابقة عند توفرها.
- نفس المفتاح + payload مختلف: `409 mol_idempotency_mismatch`.
- مفاتيح منتهية تنظف دوريًا؛ مدة MVP الافتراضية 24 ساعة قابلة للإعداد.

## 4. قواميس القيم المقيدة (تتحقق في التطبيق)

لأن الجداول لا تعتمد `ENUM`/`CHECK` كعقد محمول بين المحركات، فهذه القيم **جزء من مخطط البيانات** ويجب على Services/REST validators رفض غيرها:

- `mol_chapters.translation_status`: `untranslated|in_progress|completed|needs_review`.
- `mol_chapters.reader_mode_override`: `NULL|webtoon|paged`.
- `mol_chapters.direction_override`: `NULL|rtl|ltr`.
- `mol_elements.element_type`: `bubble|narration|free_text|sfx`.
- `mol_reports.report_type`: `translation|placement|style|missing|other`.
- `mol_reports.status`: `open|in_review|resolved|rejected`.
- `mol_reading_progress.reader_mode`: `webtoon|paged`.
- `mol_style_presets.scope`: `personal|work|global`.
- `mol_style_presets.element_type`: `bubble|narration|free_text|sfx`.

## 5. العلاقات

```text
mol_work CPT 1 ── N mol_chapters
mol_chapters 1 ── N mol_pages
mol_pages 1 ── N mol_elements
mol_elements 1 ── 0..1 mol_element_locks
mol_elements N ── N wp_users عبر mol_contributions
mol_work / wp_users ── presets بحسب scope
wp_users 1 ── N mol_idempotency_keys (مؤقتة لطلبات الكتابة الحساسة لإعادة المحاولة)
```

## 6. قواعد الحذف

- حذف work: يمنع إن كانت هناك فصول إلا عند `force`, ثم Service يحذف chapters → pages → elements → locks/contributions/reports/progress وفق transaction batches.
- حذف element: يحذف lock وcontribution rows المرتبطة، ويجعل report.element_id = NULL إن أريد الاحتفاظ بالبلاغ؛ MVP يربط البلاغ بالصفحة/الفصل حتى بعد حذف العنصر.
- حذف user: contributions تبقى attribution عبر `user_id` إن كان WordPress يسمح بالحذف النهائي؛ عند تنفيذ حذف مستخدم فعلي يجب تحويل العرض إلى اسم مجهول أو سياسة حذف محددة في الإدارة. لا يؤثر ذلك على الترجمة الحالية.

## 7. التحقق من الهندسة

`0 <= x,y <= MOL_UNIT`, `1 <= w,h <= MOL_UNIT`, و`x+w <= MOL_UNIT`, `y+h <= MOL_UNIT` بعد clamp/validation.


## 8. خوارزمية `mol_pages` reorder الآمنة

لأن `uq_chapter_index (chapter_id, page_index)` فريد، يمنع تنفيذ swap مباشر لقيمتين. `PageService::reorder()` يطبق:

1. `START TRANSACTION`.
2. `SELECT id,page_index FROM mol_pages WHERE chapter_id=? ORDER BY page_index FOR UPDATE`.
3. التحقق أن `page_ids` الواردة هي permutation كاملة للـIDs الحالية.
4. `offset = current_max_page_index + page_count + 1`.
5. تحديث كل صفوف الفصل إلى `page_index = page_index + offset` ليصبح النطاق المؤقت منفصلًا تمامًا عن 0..current_max.
6. تحديث نهائي بـ`CASE id WHEN ... THEN 0 ... END` إلى 0..N-1.
7. التحقق من count ثم `COMMIT`; أي خطأ → `ROLLBACK`.

يختبر هذا السيناريو تحديدًا: swap أول صفحتين، reverse كامل، reorder عشوائي، وpayload ناقص/مكرر.
