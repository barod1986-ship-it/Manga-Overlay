# هيكل WordPress

## 1. المستودع

```text
wp-content/
├── plugins/
│   └── manga-overlay-core/
│       ├── manga-overlay-core.php
│       ├── composer.json
│       ├── package.json
│       ├── vite.config.ts
│       ├── src/
│       │   ├── Plugin.php
│       │   ├── Activation/
│       │   ├── Admin/
│       │   ├── Content/
│       │   ├── Database/
│       │   ├── Domain/
│       │   ├── Media/
│       │   ├── REST/
│       │   ├── Security/
│       │   ├── Services/
│       │   └── Support/
│       ├── editor-src/          # React + TypeScript
│       ├── assets/dist/         # built editor/reader assets
│       └── templates/editor.php
└── themes/
    └── manga-overlay-theme/
        ├── style.css
        ├── theme.json
        ├── functions.php
        ├── front-page.php
        ├── archive-mol_work.php
        ├── single-mol_work.php
        ├── templates/reader.php
        ├── author.php
        ├── template-parts/
        └── assets/
```

> شجرة `wp-content/...` أعلاه **توضيحية للمستودع فقط**. كود التشغيل لا يفترض أن `wp-content`, plugins, themes أو uploads في مساراتها الافتراضية؛ يستخدم `plugin_dir_path()`, `plugin_dir_url()`, `wp_upload_dir()` ودوال WordPress المناسبة.

## 2. CPT

`mol_work`:
- `public => true`
- `show_in_rest => true`
- `has_archive => 'library'`
- `rewrite => ['slug' => 'series', 'with_front' => false]`
- `supports => ['title', 'editor', 'thumbnail', 'custom-fields']` — لأن registered post meta التي نكشفها عبر WordPress Core REST تحتاج دعم `custom-fields` على الـCPT؛ ويمكن إبقاء الحقول المخفية التي تبدأ `_` خارج واجهة التحرير التقليدية.
- `map_meta_cap => true`
- capabilities الخاصة بإنشاء/تحرير/نشر/حذف الأعمال تُربط بـ`mol_manage_content`، بينما القراءة العامة تبقى public. لا يعتمد الكود على اسم الدور.

Taxonomies:
- `mol_genre`
- `mol_work_type` (`manga`, `manhwa`, `manhua`, `comic`, `webtoon`, `other`)
- `mol_source_language`
- `mol_work_status`

Meta registered with sanitize/auth callbacks:
- `_mol_alt_titles`
- `_mol_default_reader_mode`
- `_mol_reading_direction`

## 3. Rewrite routes

- `/library/`
- `/series/{work-slug}/`
- `/series/{work-slug}/chapter/{chapter-slug}/`
- `/series/{work-slug}/chapter/{chapter-slug}/edit/`
- `/u/{username}/`

## 4. Hooks

Actions:
- `mol_after_element_saved($element_id, $page_id, $chapter_id, $user_id)`
- `mol_after_element_deleted(...)`
- `mol_after_chapter_status_changed(...)`
- `mol_after_page_uploaded(...)`
- `mol_report_created(...)`
- `mol_preset_saved(...)`

Filters:
- `mol_element_style_schema`
- `mol_allowed_font_ids`
- `mol_allowed_upload_mimes`
- `mol_image_sizes`
- `mol_reader_modes`

## 5. إدارة الجداول

- `register_activation_hook` ينشئ/يرقي الجداول عبر `dbDelta`.
- option: `mol_db_version`.
- migrations idempotent.
- لا تحذف بيانات عند deactivation.
- `uninstall.php` لا يحذف البيانات إلا إذا كان option صريح `mol_delete_data_on_uninstall=1` تم تفعيله إداريًا.

## 6. Admin screens

داخل قائمة `Manga Overlay`:
- Chapters
- Upload Chapter
- Reports
- Style Presets
- Permissions
- Settings الأساسية منخفضة التكرار — **WordPress core `manage_options`** (Administrator)، ولا توجد capability مستقلة `mol_manage_settings` في MVP

الأعمال نفسها تُدار من CPT UI.

## 7. Public PHP API

الإضافة توفر functions للعرض بدل SQL في القالب:

```php
mol_get_work_chapters(int $work_id, array $args = []): array
mol_get_chapter(int $chapter_id): ?array
mol_get_chapter_pages(int $chapter_id): array
mol_get_page_elements(int $page_id, string $lang='ar'): array
mol_get_chapter_elements(int $chapter_id, string $lang='ar'): array
mol_get_chapter_contributors(int $chapter_id): array
mol_user_can_edit_chapter(int $user_id, int $chapter_id): bool
```


## 8. REST Controllers المطلوبة في 1.1.3

كل Controller لقراءة chapter descendants يستدعي policy مركزية `ChapterVisibilityPolicy`: published→public؛ draft→`mol_use_editor|mol_manage_content` في سياق REST موثّق؛ غير ذلك `WP_Error` بحالة 404. `ChapterRepository` يقوم بتطبيع `sort_order` إلى float قبل DTO.

- Library/Works responses typed حسب OpenAPI.
- `CapabilitiesController` public ويقرأ قدرات `MediaService` الفعلية (upload/derived formats) وحالة features الاختيارية.
- `ChapterElementsController` للـbatch overlay.
- `ChapterReviewController` يفحص `mol_review_translations`.
- `ElementsController` يقرأ `If-Match` من `WP_REST_Request` ويضيف ETag quoted version إلى `WP_REST_Response`; collection DTOs تعيد `version` لكل child لبناء If-Match. style PATCH يطابق immutable `element_type` قبل validation النوعي.
- `LocksController`: missing element=404؛ renew/release token غير حالي=409 `mol_lock_lost`; delete يطبق owner+token أو `mol_manage_content` force-release؛ لا route force-unlock إضافي.
