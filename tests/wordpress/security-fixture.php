<?php
/** Trusted inline output used only by the disposable WordPress CSP browser tests. */
if (wp_get_environment_type() !== 'local' || getenv('MOL_TEST_ENV') !== '1') {
	return;
}
add_action('wp_head', static function (): void {
	if ((string) get_query_var('mol_editor') === '1') {
		wp_print_inline_script_tag("document.documentElement.dataset.molTrustedInline = 'ready';", ['id' => 'mol-csp-inline-fixture']);
	}
}, 99);
