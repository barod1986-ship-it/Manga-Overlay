# الأدوار والصلاحيات

## 1. الأدوار

| Role | الاستخدام |
|---|---|
| `mol_member` | عضو عادي |
| `mol_translator` | مترجم/محرر |
| `mol_moderator` | مشرف |
| `mol_manager` | مدير |

## 2. capabilities canonical في MVP

- `mol_report_issue`
- `mol_use_editor`
- `mol_edit_translations`
- `mol_delete_translation_elements`
- `mol_review_translations`
- `mol_moderate_reports`
- `mol_upload_content`
- `mol_manage_content`
- `mol_manage_work_presets`
- `mol_manage_global_presets`

> القراءة العامة للمكتبة لا تحتاج `mol_read_library`. ولا توجد في MVP وظائف مستقلة تتطلب `mol_manage_contributors` أو `mol_manage_settings`؛ لذلك حُذفت هذه capabilities بدل إبقائها معلقة بلا route/policy. إعدادات النظام منخفضة التكرار في wp-admin تستخدم WordPress core capability `manage_options`، وليست صلاحية MOL جديدة.

## 3. المصفوفة

| Capability | Member | Translator | Moderator | Manager |
|---|:---:|:---:|:---:|:---:|
| report issue | ✓ | ✓ | ✓ | ✓ |
| use editor/edit | — | ✓ | ✓ | ✓ |
| delete elements | — | ✓ | ✓ | ✓ |
| review translations | — | — | ✓ | ✓ |
| moderate reports | — | — | ✓ | ✓ |
| upload content | grant | grant | grant | ✓ |
| manage content | grant | grant | grant | ✓ |
| manage work presets | — | — | grant/✓ | ✓ |
| manage global presets | — | — | — | ✓ |

`grant` يعني capability يمكن منحها فرديًا دون تغيير الدور.

## 3.1 قراءة المسودات

`mol_use_editor` أو `mol_manage_content` يسمحان بقراءة chapter draft وموارده عبر GETات MOL نفسها عندما يكون سياق WordPress REST موثّقًا. لا تمنح هذه القاعدة عضوًا عاديًا أي كشف للمسودة؛ غير المخول يحصل على 404.

## 4. Review workflow

`mol_review_translations` ليست capability اسمية فقط. تستخدم فعليًا:

- `PATCH /chapters/{id}/review`
- القيم المسموحة للمراجع: `needs_review` أو `completed`.
- لا يسمح هذا المسار بإنشاء/حذف فصل أو رفع صفحة أو تغيير بيانات العمل.

## 5. Force unlock

تحرير القفل يستخدم route واحدًا `DELETE /elements/{id}/lock`:
- مالك القفل: token صحيح.
- من يملك `mol_manage_content`: يستطيع force-release دون token عند قفل عالق/مانع للعمل.

## 6. قواعد

- لا يفحص الكود `current_user_can('mol_translator')`؛ يفحص capability.
- صفحة `/edit/` تعيد 403 أو redirect مناسب لمن لا يملك `mol_use_editor`.
- كل REST write route يعيد فحص capability والكيان.
- المترجم لا يستطيع منح نفسه صلاحيات.
- سحب capability يسري على الطلب التالي مباشرة.
