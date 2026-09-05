# الأداء والوسائط

## 1. الصور

- accepted: JPEG, PNG, WebP، ويمكن AVIF إذا كانت بيئة الخادم تدعمه جيدًا.
- تحقق MIME فعلي وليس extension فقط.
- إنشاء sub-sizes مناسبة مثل 480/800/1080/1600 حسب الأصل وعدم upscale.
- MOL MediaService ينشئ WebP المشتق **بشكل صريح** عند دعم محرر الصور له، عبر إعداد/استدعاء WordPress image editor المناسب (مثل `image_editor_output_format`) أو مسار معالجة المشروع. WordPress افتراضيًا يُبقي sub-sizes بنفس صيغة الأصل ولا يحول JPEG→WebP تلقائيًا.
- AVIF تحسين اختياري بعد قياس الجودة/CPU. في مسار الرفع المخصص للمشروع لا نفترض أن دعم WordPress 7.1 للمعالجة client-side سيعمل تلقائيًا؛ إما ندمج ذلك المسار صراحةً أو نتحقق من قدرة image editor على الخادم ونسقط بأمان إلى WebP/original.
- `srcset`/`sizes` من WordPress حيث ينطبق.
- width/height صريحان.
- lazy loading خارج viewport.

## 2. الأصل والـCDN

MVP:
- WordPress uploads هو origin.
- CDN origin-pull أمام ملفات الوسائط.
- URLs لا تُخزن كحقائق دائمة داخل عناصر الترجمة؛ attachment IDs هي المرجع.

مرحلة لاحقة:
- Media storage adapter لنقل originals/object storage دون تغيير domain schema.

## 3. القارئ

- لا يحمل كل overlays لكل الفصول.
- fetch/inline per chapter/page حسب strategy.
- preload محدود؛ تجنب preload عشرات الصور.
- `content-visibility:auto` يمكن استخدامه في webtoon بعد اختبار Safari/Chrome.

## 4. API/cache

Public GET قابل للكاش حسب publication state.
- library/work/chapter: cache قصير مع invalidation.
- page elements: ETag/cache key `page_id:lang:last_version`.
- private/editor routes: `no-store`.

Redis اختياري. النظام يعمل بدونه.

## 5. قاعدة البيانات

- تجنب meta_query لعناصر كثيفة.
- pagination cursor أو page limits لقوائم كبيرة.
- query contributor counts عبر indexes أو cache materialization إذا أثبت القياس الحاجة.

## 6. أهداف

- Reader LCP هدف هندسي ≤2.5s في staging profile، وCLS ≤0.1. هذان الرقمان يطابقان حدود Core Web Vitals الجيدة، لكن النجاح الحقيقي في Core Web Vitals يحتاج قياس field عند الـ75th percentile ويشمل INP ≤200ms أيضًا.
- Public API p95 هدف مشروع <300ms بعد warm cache على استضافة الإنتاج المستهدفة؛ ليس ضمانًا عامًا من WordPress.
- المحرر يستهدف frame pacing قريبًا من معدل تحديث الجهاز المستهدف قدر الإمكان؛ لا يوجد رقم FPS مضمون في المواصفة، ويُحسم القبول بقياس الأجهزة الفعلية. لا توجد network writes داخل frame loop.
## Runtime media capability

عقد الرفع الثابت يضمن JPEG/PNG/WebP. AVIF ليس وعدًا ثابتًا: `MediaService` يفحص الـimage editor الفعلي وتعيد `/capabilities` الصيغ المقبولة والمشتقة. الواجهة لا تعرض AVIF كخيار ولا ترسله إلا إذا ظهر `image/avif` في `upload_mime_types`.

