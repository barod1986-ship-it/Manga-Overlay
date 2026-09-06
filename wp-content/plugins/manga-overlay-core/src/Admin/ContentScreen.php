<?php
declare(strict_types=1);

namespace MOL\Admin;

use MOL\Database\ChapterRepository;
use MOL\Media\MediaService;

final class ContentScreen
{
	private string $hook = '';

	public function __construct(private readonly ChapterRepository $chapters)
	{
		add_action('admin_menu', [$this, 'menu']);
		add_action('admin_enqueue_scripts', [$this, 'assets']);
	}

	public function menu(): void
	{
		foreach (['mol_manage_content', 'mol_upload_content', 'mol_review_translations'] as $capability) {
			if (current_user_can($capability)) {
				$this->hook = add_menu_page('Manga Overlay', 'Manga Overlay', $capability, 'manga-overlay', [$this, 'render'], 'dashicons-book-alt', 25);
				break;
			}
		}
	}

	public function assets(string $hook): void
	{
		if ($hook !== $this->hook || $hook === '') {
			return;
		}
		$url = plugin_dir_url(dirname(__DIR__, 2) . '/manga-overlay-core.php');
		wp_enqueue_style('mol-content-admin', $url . 'assets/admin/content.css', [], \MOL\Plugin::VERSION);
		wp_enqueue_script_module('mol-content-admin', $url . 'assets/admin/content.mjs', [], \MOL\Plugin::VERSION);
	}

	public function render(): void
	{
		$manage = current_user_can('mol_manage_content');
		$upload = current_user_can('mol_upload_content');
		$review = current_user_can('mol_review_translations');
		if (!$manage && !$upload && !$review) {
			wp_die(esc_html__('لا تملك صلاحية إدارة المحتوى.', 'manga-overlay-core'));
		}
		$works = get_posts(['post_type' => 'mol_work', 'post_status' => $manage ? ['publish', 'draft', 'private', 'pending'] : ['publish'], 'numberposts' => -1, 'orderby' => 'title', 'order' => 'ASC']);
		$work_id = isset($_GET['work_id']) ? absint($_GET['work_id']) : (int) ($works[0]->ID ?? 0);
		if (!in_array($work_id, array_map(static fn ($work) => (int) $work->ID, $works), true)) {
			$work_id = 0;
		}
		// wp-admin is separately capability-protected. This is not the public PHP read API.
		$chapters = $work_id ? $this->chapters->for_work($work_id, !($manage || current_user_can('mol_use_editor'))) : [];
		$data = [
			'api' => esc_url_raw(rest_url('mol/v1/')), 'nonce' => wp_create_nonce('wp_rest'), 'workId' => $work_id,
			'chapters' => $chapters, 'manage' => $manage, 'upload' => $upload, 'review' => $review,
			'editorBaseUrl' => current_user_can('mol_use_editor') && $work_id ? home_url('/series/' . get_post_field('post_name', $work_id) . '/chapter/') : null,
			'capabilities' => MediaService::capabilities(),
		];
		?>
		<div class="wrap mol-admin" dir="rtl">
			<header class="mol-admin-header"><div><p class="mol-eyebrow">MANGA OVERLAY</p><h1>الفصول والصفحات</h1><p>أضف الفصول، وارفع صورها، ورتّب صفحات القراءة.</p></div>
				<?php if ($manage) : ?><a class="button" href="<?php echo esc_url(admin_url('edit.php?post_type=mol_work')); ?>">إدارة الأعمال</a><?php endif; ?>
			</header>
			<div id="mol-notice" role="status" aria-live="polite"></div>
			<div class="mol-selectors">
				<label>العمل<select id="mol-work"><?php foreach ($works as $work) : ?><option value="<?php echo (int) $work->ID; ?>" <?php selected($work_id, $work->ID); ?>><?php echo esc_html($work->post_title); ?></option><?php endforeach; ?></select></label>
				<label>الفصل<select id="mol-chapter"><option value="">اختر فصلًا</option></select></label>
				<?php if (current_user_can('mol_use_editor')) : ?><a class="button" id="mol-open-editor" hidden>مساحة الترجمة</a><?php endif; ?>
				<?php if ($manage) : ?><button type="button" class="button button-primary" id="mol-new-chapter" <?php disabled(!$work_id); ?>>فصل جديد</button><?php endif; ?>
			</div>
			<?php if (!$works) : ?><p class="mol-empty">لا توجد أعمال متاحة. أضف عملًا من إدارة الأعمال لبدء إنشاء الفصول.</p><?php endif; ?>
			<div class="mol-admin-grid">
				<section class="mol-panel" id="mol-chapter-panel">
					<h2>بيانات الفصل</h2>
					<form id="mol-chapter-form">
						<fieldset <?php disabled(!$manage); ?>>
							<label>تسمية الفصل<input name="chapter_label" maxlength="64" required placeholder="مثال: 10.5 أو فصل خاص"></label>
							<label>العنوان <span>(اختياري)</span><input name="title" maxlength="255"></label>
							<div class="mol-fields-row"><label>ترتيب الفصل<input name="sort_order" type="number" step="0.0001" value="0"></label>
								<label>حالة الترجمة<select name="translation_status"><option value="untranslated">لم يُترجم</option><option value="in_progress">قيد الترجمة</option><option value="needs_review">يحتاج مراجعة</option><option value="completed">مكتمل</option></select></label></div>
							<label>لغة المصدر <span>(فارغ لاستخدام لغة العمل)</span><input name="source_lang_override" maxlength="255" placeholder="ja، ko، en" dir="ltr"></label>
							<div class="mol-fields-row"><label>طريقة القراءة<select name="reader_mode_override"><option value="">إعداد العمل</option><option value="webtoon">تمرير عمودي</option><option value="paged">صفحات</option></select></label>
								<label>اتجاه القراءة<select name="direction_override"><option value="">إعداد العمل</option><option value="rtl">من اليمين إلى اليسار</option><option value="ltr">من اليسار إلى اليمين</option></select></label></div>
							<label class="mol-checkbox"><input name="is_published" type="checkbox">نشر الفصل للقراء</label>
						</fieldset>
						<?php if ($manage) : ?><div class="mol-actions"><button class="button button-primary" type="submit" id="mol-save-chapter">حفظ الفصل</button><button class="button mol-danger" type="button" id="mol-delete-chapter" disabled>حذف الفصل</button></div><?php endif; ?>
					</form>
					<?php if ($review) : ?><div class="mol-actions mol-review"><button type="button" class="button" data-review="needs_review">يحتاج مراجعة</button><button type="button" class="button" data-review="completed">اعتماد الترجمة</button></div><?php endif; ?>
				</section>
				<section class="mol-panel mol-pages-panel"><div class="mol-section-heading"><h2>صفحات الفصل <span id="mol-page-count"></span></h2><button type="button" class="button" id="mol-refresh-pages">تحديث</button></div>
					<p class="mol-muted">تُحفظ تغييرات الترتيب عند الضغط على «حفظ ترتيب الصفحات».</p>
					<div id="mol-pages" class="mol-pages"><p class="mol-empty">اختر فصلًا لعرض صفحاته.</p></div>
					<?php if ($manage) : ?><button class="button" type="button" id="mol-save-order" disabled>حفظ ترتيب الصفحات</button><?php endif; ?>
				</section>
			</div>
			<?php if ($upload) : ?>
			<section class="mol-panel mol-upload-panel"><h2>رفع صفحات</h2><p>اختر الصور ثم راجع ترتيبها قبل بدء الرفع. الصورة الأصلية تُحفظ في مكتبة الوسائط.</p>
				<label class="mol-drop-zone" id="mol-drop-zone"><strong>اسحب الصور هنا أو اختر الملفات</strong><input id="mol-images" type="file" multiple accept="<?php echo esc_attr(implode(',', $data['capabilities']['upload_mime_types'])); ?>"></label>
				<ol id="mol-upload-queue" class="mol-upload-queue"></ol>
				<div class="mol-actions"><button class="button button-primary" type="button" id="mol-start-upload" disabled>بدء الرفع</button><button class="button" type="button" id="mol-retry-upload" hidden>إعادة محاولة الملفات المتعثرة</button></div>
			</section>
			<?php endif; ?>
			<script type="application/json" id="mol-content-data"><?php echo wp_json_encode($data, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?></script>
		</div>
		<?php
	}
}
