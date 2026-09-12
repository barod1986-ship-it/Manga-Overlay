# المصطلحات

| المصطلح | التعريف |
|---|---|
| Work | العمل الكامل؛ CPT `mol_work` |
| Chapter | فصل داخل عمل؛ `mol_chapters` |
| Page | صورة واحدة داخل فصل؛ `mol_pages` |
| Element | عنصر تعريب بصري فوق صفحة |
| Bubble | فقاعة حوار |
| Narration | صندوق سرد |
| Free text | نص حر |
| SFX | مؤثر صوتي نصي |
| Overlay | طبقة العناصر فوق الصورة الأصلية |
| Normalized unit | قيمة 0..1 ممثلة كعدد صحيح 0..1,000,000 |
| Lock lease | ملكية مؤقتة لتحرير عنصر |
| Optimistic concurrency | منع lost update عبر version/If-Match |
| Preset | نمط تنسيق قابل لإعادة الاستخدام |
| Contribution | علاقة مستخدم بعنصر ساهم فيه، لا عملية حفظ |
| Reader mode | `webtoon` أو `paged` |
| Reading direction | `rtl` أو `ltr` في paged |

| Precondition Required | HTTP 428 عند غياب `If-Match` المطلوب في write مشروط |
| Precondition Failed | HTTP 412 عند إرسال `If-Match` لا يطابق النسخة الحالية |
| MOL-Idempotency-Key | header داخلي للتطبيق لجعل retry لمسارات POST المحددة قابلاً لاكتشاف التكرار؛ ليس معيار HTTP منشورًا |
