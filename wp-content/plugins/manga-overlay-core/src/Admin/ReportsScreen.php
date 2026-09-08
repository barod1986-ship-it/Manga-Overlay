<?php
declare(strict_types=1);

namespace MOL\Admin;

final class ReportsScreen
{
	private string $hook = '';
	public function __construct()
	{
		add_action('admin_menu', [$this, 'menu']); add_action('admin_enqueue_scripts', [$this, 'assets']);
		add_action('admin_post_mol_report_context', [$this, 'context']);
	}
	public function menu(): void
	{
		$this->hook = add_menu_page('بلاغات Manga Overlay', 'بلاغات الترجمة', 'mol_moderate_reports', 'mol-reports', [$this, 'render'], 'dashicons-flag', 26);
	}
	public function assets(string $hook): void
	{
		if ($hook !== $this->hook || !$hook) { return; }
		$url = plugin_dir_url(dirname(__DIR__, 2) . '/manga-overlay-core.php');
		wp_enqueue_style('mol-reports-admin', $url . 'assets/admin/reports.css', [], \MOL\Plugin::VERSION);
		wp_enqueue_script_module('mol-reports-admin', $url . 'assets/admin/reports.mjs', [], \MOL\Plugin::VERSION);
	}
	public function render(): void
	{
		if (!current_user_can('mol_moderate_reports')) { wp_die('لا تملك صلاحية مراجعة البلاغات.', '', ['response' => 403]); }
		$data = ['api' => rest_url('mol/v1/'), 'nonce' => wp_create_nonce('wp_rest'), 'contextUrl' => admin_url('admin-post.php?action=mol_report_context&id=')];
		?>
		<main class="wrap mol-reports-admin" dir="rtl">
			<header><p>MANGA OVERLAY</p><h1>بلاغات الترجمة</h1><p>راجع المشكلة وموضعها، ثم حدّث حالة البلاغ بعد اتخاذ الإجراء المناسب.</p></header>
			<form id="mol-report-filters"><label for="mol-report-status">حالة البلاغ</label><select id="mol-report-status"><option value="open">مفتوح</option><option value="in_review">قيد المراجعة</option><option value="resolved">تم الحل</option><option value="rejected">مرفوض</option><option value="">كل الحالات</option></select>
				<label for="mol-report-chapter">رقم الفصل</label><input id="mol-report-chapter" type="number" min="1" step="1" placeholder="جميع الفصول"><button class="button" type="submit">عرض البلاغات</button></form>
			<p id="mol-reports-notice" role="status" aria-live="polite"></p><button class="button" id="mol-reports-retry" hidden>إعادة تحميل القائمة</button>
			<section id="mol-reports-list" aria-label="قائمة البلاغات" aria-busy="true"></section>
			<nav aria-label="صفحات البلاغات"><button class="button" id="mol-reports-previous" disabled>السابق</button><span id="mol-reports-count"></span><button class="button" id="mol-reports-next" disabled>التالي</button></nav>
			<script type="application/json" id="mol-reports-data"><?php echo wp_json_encode($data, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?></script>
		</main>
		<?php
	}

	/** Read-only navigation; report access and resource visibility are checked again at click time. */
	public function context(): void
	{
		if (!current_user_can('mol_moderate_reports')) { wp_die('لا تملك صلاحية مراجعة البلاغات.', '', ['response' => 403]); }
		global $wpdb;
		$id = isset($_GET['id']) && is_scalar($_GET['id']) ? absint($_GET['id']) : 0;
		$report = (new \MOL\Database\ReportRepository($wpdb))->find($id);
		$chapter = $report ? (new \MOL\Database\ChapterRepository($wpdb))->find($report['chapter_id']) : null;
		$editor = current_user_can('mol_use_editor');
		$work_ok = $chapter && get_post_type($chapter['work_id']) === 'mol_work' && get_post_status($chapter['work_id']) !== 'trash';
		if (!$work_ok || (!$editor && (!$chapter['is_published'] || get_post_status($chapter['work_id']) !== 'publish'))) { wp_die('موضع البلاغ غير متاح.', '', ['response' => 404]); }
		$url = \MOL\Frontend\PublicSite::chapter_url($chapter);
		if ($editor) {
			$url .= 'edit/';
			if ($report['page_id']) { $url .= '#page=' . $report['page_id'] . ($report['element_id'] ? '&element=' . $report['element_id'] : ''); }
		} elseif ($report['page_id']) { $url .= '#mol-page-' . $report['page_id']; }
		wp_safe_redirect($url); exit;
	}
}
