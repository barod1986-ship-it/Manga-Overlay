# أمثلة البيانات

> الأمثلة الموسومة `schema:` تُفحص آليًا مقابل `API.openapi.yaml`. تم تحديثها في v1.1.3 لمنع انجراف الأمثلة عن العقد.

## WorkSummary
<!-- schema: WorkSummary -->
```json
{
  "id": 500,
  "slug": "example-series",
  "title": "Example Series",
  "type": "manhwa",
  "genres": ["action", "fantasy"],
  "source_language": "en",
  "work_status": "ongoing",
  "cover": {
    "attachment_id": 1200,
    "url": "/uploads/example-series-cover.webp",
    "width": 800,
    "height": 1200,
    "srcset": null,
    "sizes": null,
    "alt": "Example Series"
  },
  "translation_summary": {
    "total": 12,
    "untranslated": 2,
    "in_progress": 3,
    "completed": 6,
    "needs_review": 1
  },
  "latest_published_chapter_at": "2026-08-31T20:00:00Z",
  "read_count": null
}
```

## Chapter
<!-- schema: Chapter -->
```json
{
  "id": 512,
  "work_id": 500,
  "chapter_label": "12",
  "sort_order": 12.0,
  "title": "Arrival",
  "slug": "chapter-12",
  "translation_status": "in_progress",
  "source_lang_override": null,
  "reader_mode_override": null,
  "direction_override": null,
  "is_published": true,
  "published_at": "2026-08-31T20:00:00Z",
  "created_at": "2026-08-30T18:00:00Z",
  "updated_at": "2026-09-01T01:12:00Z"
}
```

> ملاحظة تنفيذية: `sort_order` مخزن كـ`DECIMAL(14,4)` وقد يصل من `$wpdb` كنص؛ `ChapterRepository` يحوله إلى `float` قبل بناء DTO حتى يطابق `type:number` أعلاه.

## Page
<!-- schema: Page -->
```json
{
  "id": 803,
  "chapter_id": 512,
  "page_index": 0,
  "natural_width": 1600,
  "natural_height": 2400,
  "image": {
    "attachment_id": 15567,
    "url": "/uploads/example-series/chapter-12/page-001.webp",
    "width": 1600,
    "height": 2400,
    "srcset": "/uploads/page-001-800.webp 800w, /uploads/page-001.webp 1600w",
    "sizes": "(max-width: 900px) 100vw, 900px",
    "alt": null
  }
}
```

## Element
<!-- schema: Element -->
```json
{
  "id": 42,
  "page_id": 803,
  "target_lang": "ar",
  "element_type": "bubble",
  "x_unit": 420000,
  "y_unit": 180000,
  "w_unit": 200000,
  "h_unit": 100000,
  "rotation_mdeg": 0,
  "z_index": 3,
  "content": "لماذا أتيت إلى هنا؟",
  "style": {
    "fontId": "cairo",
    "fontSizeUnit": 26000,
    "fontWeight": 700,
    "lineHeight": 1.35,
    "textAlign": "center",
    "color": "#111111",
    "backgroundColor": "#FFFFFF",
    "backgroundOpacity": 0.96,
    "borderColor": "#111111",
    "borderWidthUnit": 1800,
    "borderRadiusUnit": 50000,
    "paddingUnit": 9000,
    "shape": "ellipse",
    "autoFit": true,
    "minFontSizeUnit": 16000
  },
  "version": 7,
  "created_by": 12,
  "updated_by": 12,
  "created_at": "2026-09-01T01:00:00Z",
  "updated_at": "2026-09-01T01:12:00Z"
}
```

## Preset
<!-- schema: Preset -->
```json
{
  "id": 91,
  "scope": "personal",
  "owner_user_id": 12,
  "work_id": null,
  "name": "حوار نظيف",
  "element_type": "bubble",
  "style": {
    "fontId": "cairo",
    "fontSizeUnit": 26000,
    "fontWeight": 700,
    "lineHeight": 1.35,
    "textAlign": "center",
    "color": "#111111",
    "backgroundColor": "#FFFFFF",
    "backgroundOpacity": 0.96,
    "borderColor": "#111111",
    "borderWidthUnit": 1800,
    "borderRadiusUnit": 50000,
    "paddingUnit": 9000,
    "shape": "ellipse",
    "autoFit": true,
    "minFontSizeUnit": 16000
  },
  "is_default": true,
  "created_by": 12,
  "created_at": "2026-09-01T00:30:00Z",
  "updated_at": "2026-09-01T00:45:00Z"
}
```

## Contribution
```json
{
  "element_id": 42,
  "user_id": 12,
  "work_id": 500,
  "chapter_id": 512,
  "created_element": true,
  "first_contributed_at": "2026-09-01T01:00:00Z",
  "last_contributed_at": "2026-09-01T01:12:00Z"
}
```

## Idempotency key
```json
{
  "user_id": 12,
  "scope": "upload_chapter_page:512",
  "idempotency_key": "c03b7c2d-1a65-4f78-aacc-5a9a5b2e8e3d",
  "request_hash": "<sha256-hex>",
  "resource_type": "page",
  "resource_id": 803,
  "response_code": 201,
  "expires_at": "2026-09-02T01:00:00Z"
}
```

## Library response envelope
<!-- schema: WorkListResponse -->
```json
{
  "data": [
    {
      "id": 500,
      "slug": "example-series",
      "title": "Example Series",
      "type": "manhwa",
      "genres": ["action", "fantasy"],
      "source_language": "en",
      "work_status": "ongoing",
      "cover": {
        "attachment_id": 1200,
        "url": "/uploads/example-series-cover.webp",
        "width": 800,
        "height": 1200,
        "srcset": null,
        "sizes": null,
        "alt": "Example Series"
      },
      "translation_summary": {
        "total": 12,
        "untranslated": 2,
        "in_progress": 3,
        "completed": 6,
        "needs_review": 1
      },
      "latest_published_chapter_at": "2026-08-31T20:00:00Z",
      "read_count": null
    }
  ],
  "meta": {
    "page": 1,
    "per_page": 24,
    "total": 1,
    "total_pages": 1,
    "sort": "latest_chapter",
    "most_read_available": false
  }
}
```

## Chapter overlay batch response
<!-- schema: ChapterElementsResponse -->
```json
{
  "data": [
    {
      "page_id": 803,
      "page_index": 0,
      "elements": [
        {
          "id": 42,
          "page_id": 803,
          "target_lang": "ar",
          "element_type": "bubble",
          "x_unit": 420000,
          "y_unit": 180000,
          "w_unit": 200000,
          "h_unit": 100000,
          "rotation_mdeg": 0,
          "z_index": 3,
          "content": "لماذا أتيت إلى هنا؟",
          "style": {
            "fontId": "cairo",
            "fontSizeUnit": 26000,
            "fontWeight": 700,
            "lineHeight": 1.35,
            "textAlign": "center",
            "color": "#111111",
            "backgroundColor": "#FFFFFF",
            "backgroundOpacity": 0.96,
            "borderColor": "#111111",
            "borderWidthUnit": 1800,
            "borderRadiusUnit": 50000,
            "paddingUnit": 9000,
            "shape": "ellipse",
            "autoFit": true,
            "minFontSizeUnit": 16000
          },
          "version": 7,
          "created_by": 12,
          "updated_by": 12,
          "created_at": "2026-09-01T01:00:00Z",
          "updated_at": "2026-09-01T01:12:00Z"
        }
      ]
    }
  ],
  "meta": {
    "chapter_id": 512,
    "target_lang": "ar",
    "page_count": 1,
    "element_count": 1
  }
}
```
## RuntimeCapabilities
<!-- schema: CapabilitiesResponse -->
```json
{
  "data": {
    "upload_mime_types": ["image/jpeg", "image/png", "image/webp"],
    "derived_image_formats": ["jpeg", "webp"],
    "most_read_available": false
  },
  "meta": {}
}
```

