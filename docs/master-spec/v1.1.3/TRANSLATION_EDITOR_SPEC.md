# مواصفات محرر الترجمة

## 1. نموذج العرض

كل صفحة:

```html
<div class="mol-stage" style="position:relative">
  <img class="mol-page-image" ...>
  <div class="mol-overlay-layer">...</div>
</div>
```

النص DOM حقيقي، والخلفيات/ذيول الفقاعات SVG مولدة من parameters موثوقة. نفس renderer يُستخدم في المحرر والقارئ لضمان التطابق.

## 2. أنواع العناصر

### `bubble`
أشكال MVP: `ellipse`, `rounded_rect`, `rect`, `cloud`.
خصائص إضافية: tail enabled/angle/length/width.

### `narration`
أشكال: `rect`, `rounded_rect`، مع background/border.

### `free_text`
بدون خلفية افتراضيًا، ويمكن إضافة خلفية اختيارية.

### `sfx`
نص حر بصري مع rotation، stroke، shadow، transformScaleX/ScaleY ضمن حدود آمنة، وأشكال خلفية اختيارية `none|burst|impact`.

**تنبيه عربي:** stroke على النص العربي قد يُظهر عيوبًا في الوصلات بين الحروف حسب المتصفح/الخط. `paint-order` يحدد ترتيب الرسم لكنه لا يضمن إصلاح وصلات الخط العربي؛ لذلك كل preset SFX بخط عريض يحتاج اختبارًا بصريًا على Chrome/Firefox/Safari وiOS/Android، مع fallback إلى shadow/خلفية shape أو تخفيف stroke عند الحاجة.

لا يوجد SVG حر أو رسم path يدوي في MVP.

## 3. مخطط العنصر

الحقول البنيوية في أعمدة DB، وليس داخل `style_json`:

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
  "style": {}
}
```

## 4. `style_json` schema المنطقي

حقول allowlist:

- `fontId`
- `fontSizeUnit`
- `fontWeight`: 400|500|600|700|800|900
- `lineHeight`: 1.0..2.5
- `textAlign`: start|center|end
- `color`: `#RRGGBB`
- `backgroundColor`: `#RRGGBB`
- `backgroundOpacity`: 0..1
- `borderColor`
- `borderWidthUnit`
- `borderRadiusUnit`
- `paddingUnit`
- `shape`
- `strokeColor`, `strokeWidthUnit`
- `shadow`: structured allowlist
- `tail`: structured allowlist للببل فقط
- `burst`: structured allowlist لـSFX فقط
- `scaleX`, `scaleY`: SFX فقط، حدود مثل 0.5..2.0
- `autoFit`: boolean
- `minFontSizeUnit`

أي property غير معروفة تُرفض في الخادم **وفي request schema**. كما يطبق العقد قيودًا حسب النوع: `tail` للببل، `burst/scaleX/scaleY` لـSFX، narration shape=`rect|rounded_rect`، وSFX shape=`none|burst|impact`.

## 5. الإحداثيات

```text
screenX = imageContentLeft + (x_unit / MOL_UNIT) * displayedImageWidth
screenY = imageContentTop  + (y_unit / MOL_UNIT) * displayedImageHeight
screenW = (w_unit / MOL_UNIT) * displayedImageWidth
screenH = (h_unit / MOL_UNIT) * displayedImageHeight
```

تُستخدم أبعاد **محتوى الصورة الفعلي** لا container قد يحتوي padding.

## 6. سطح المكتب

- Toolbar يسار/أعلى: select, bubble, narration, text, sfx.
- Stage في الوسط مع zoom.
- Properties panel يمين.
- Layers panel قابلة للطي.
- اختصارات: Delete، Ctrl/Cmd+D، arrows للنقل الدقيق، Esc لإلغاء التحديد.
- double click يفتح تحرير النص في textarea/inline control آمن حسب PoC.
- جميع Transform الأساسية (x/y/w/h/rotation) لها أيضًا مدخلات رقمية وأزرار nudge/resize/rotate لا تتطلب drag، حتى لا تكون الوظيفة محصورة بحركة السحب.

## 7. الجوال

مفصل في `MOBILE_SPEC.md`. القاعدة: لا ازدحام panels ثابتة؛ الأدوات bottom bar والخصائص bottom sheet.

## 8. Moveable

فعّال:
- draggable
- resizable
- rotatable
- pinchable على الأجهزة المناسبة
- snappable

الحالة أثناء الحركة تبقى محلية، وتُثبت normalized values عند `dragEnd/resizeEnd/rotateEnd`.

## 9. Snapping

Guidelines:
- حواف ومركز الصفحة.
- حواف ومراكز العناصر الأخرى في الصفحة.
- threshold بصري صغير يتكيف مع zoom.
- إمكانية تعطيله مؤقتًا بزر Modifier على desktop.

## 10. Auto-fit

عند `autoFit=true`:
1. renderer يقيس overflow بعد تحميل الخط.
2. بحث ثنائي بين minFontSize والمقاس المطلوب.
3. يختار أكبر حجم لا يسبب overflow.
4. لا يغير قيمة حجم العنصر نفسه.
5. المستخدم يستطيع تحويل النتيجة إلى حجم ثابت بإلغاء Auto-fit.

## 11. Presets

Scopes:
- **personal:** مرئي لصاحبه فقط، ويمكن للمترجم `Save style as preset`.
- **work:** مرئي لكل محرري العمل، ينشره من لديه `mol_manage_work_presets`.
- **global:** لكل الأعمال، يديره `mol_manage_global_presets`.

Preset يخزن style فقط، لا content ولا x/y/w/h، وهو type-specific.

UI:
- شريط سريع أعلى Properties.
- `Apply preset` لا يحفظ تلقائيًا إلا كجزء من تغيير العنصر العادي.
- `Save as preset` يطلب الاسم والنطاق المسموح.


### 11.1 Base Styles المدمجة

حتى لو لم توجد أي presets محفوظة، عنصر جديد لا يبدأ بـ`{}` بصريًا. الخادم يملك Base Style ثابتة لكل نوع، ثم يعيد **resolved style** كاملة. القيم الابتدائية:

| النوع | Base Style MVP |
|---|---|
| `bubble` | `cairo`, 26000, weight 700, lineHeight 1.35, center, `#111111`, bg `#FFFFFF` opacity .96, border 1800, radius 50000, padding 9000, shape `ellipse`, autoFit=true, minFont=16000 |
| `narration` | `noto-sans-arabic`, 24000, weight 600, lineHeight 1.4, center, `#111111`, bg `#FFFFFF` opacity .94, border 1500, radius 18000, padding 10000, shape `rounded_rect`, autoFit=true, minFont=15000 |
| `free_text` | `cairo`, 26000, weight 700, lineHeight 1.3, center, `#111111`, backgroundOpacity=0, borderWidth=0, shape `none`, autoFit=false |
| `sfx` | `sfx-display-1`, 52000, weight 900, lineHeight 1.1, center, `#FFFFFF`, backgroundOpacity=0, stroke `#111111` width 3500, shape `none`, scaleX/scaleY=1, autoFit=false |

هذه قيم منتج قابلة للتعديل بإصدار Spec لاحق، وليست ادعاءً أنها المقاسات المثالية لكل عمل.

### 11.2 Default preset resolution

عند `POST /elements`:
1. إذا أرسل `preset_id` صالحًا ومتاحًا، يبدأ منه.
2. وإلا يطبق التسلسل: **personal default → work default → global default → built-in Base Style** لنوع العنصر.
3. `style` الجزئي الوارد في الطلب يدمج أخيرًا كـoverride.

`is_default=1` يعني default واحد فعّال لكل scope/owner-or-work/type؛ `PresetService` يبدل الافتراضي داخل transaction.

## 12. Autosave

- dirty عند تغيير قابل للحفظ.
- debounce `~1200ms` للنص/الخصائص.
- transforms تحفظ في end events.
- عنصر جديد: POST عند أول حفظ؛ بعدها يحصل على id/version.
- مؤشر: `غير محفوظ` → `جارٍ الحفظ` → `تم الحفظ`.
- عند offline: `غير متصل — تغييرات هذه الجلسة لم تُرسل بعد`، بدون وعد بحفظ دائم محلي.

## 13. Locking

- عند بدء تعديل عنصر محفوظ: POST lock.
- TTL 45s.
- renew كل 15s أثناء العنصر selected/dirty أو text editing.
- lock token مطلوب في PATCH/DELETE.
- عند blur/selection change يمكن release إذا لا توجد عملية pending.
- المستخدم الآخر يستطيع تحديد العنصر للقراءة لكنه لا يعدله، ويظهر اسم المحرر الحالي.
- الصفحة نفسها لا تُقفل؛ يمكن العمل على عناصر مختلفة.

## 14. Conflict

PATCH يرسل `If-Match: "<version>"`.
- 200: version جديد.
- 412: يعرض `نسختك` و`النسخة الحالية` مع خيارين: `استخدام الحالية` أو `إعادة تطبيق تغييري على الحالية ثم الحفظ`.
- لا overwrite صامت.

## 15. Preview

زر Preview يخفي handles/panels/guides ويستخدم نفس renderer وإعداد reader scale. يمكن تبديل الترجمة لعرض الأصل.

## 16. خطوط البداية

قائمة مغلقة ذاتية الاستضافة WOFF2، مع IDs لا URLs. البداية المقترحة:
- `noto-sans-arabic`
- `cairo`
- `tajawal`
- `noto-kufi-arabic`
- خط زخرفي واحد مجرب لـSFX

لا يُستخدم `letter-spacing` على العربية كإعداد افتراضي للمحرر.
## 13. عقد PATCH الصارم

- `{}` مرفوض ولا يرفع `version`.
- الحقول غير المعروفة مرفوضة.
- `rotation_mdeg` و`z_index` تستخدم نفس حدود `Geometry` في create.
- عند PATCH لـ`style` يرسل العميل `element_type` كـdiscriminator ثابت؛ الخادم يطابقه مع النوع المخزن، ثم يتحقق من style schema الخاصة بالنوع. `element_type` وحده ليس تغييرًا صالحًا.
- collection responses تعيد `version` لكل عنصر؛ autosave يبني `If-Match` من النسخة: `7` → `"7"`.

## 14. فقد lease

إذا أعاد renew/release `409 mol_lock_lost`، تتوقف الكتابة لذلك العنصر ويعاد acquire. لا يُعامل كـ403، ولا يعاد PATCH على نسخة قديمة قبل استعادة lease/version.

