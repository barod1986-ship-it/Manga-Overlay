<?php
/** Trusted inline output used only by the disposable WordPress CSP browser tests. */
if (wp_get_environment_type() !== 'local' || getenv('MOL_TEST_ENV') !== '1') {
	return;
}
add_action('wp_head', static function (): void {
	if ((string) get_query_var('mol_editor') === '1') {
		wp_print_inline_script_tag("document.documentElement.dataset.molTrustedInline = 'ready';", ['id' => 'mol-csp-inline-fixture']);
		// Compile this listener as part of the actual document, then invoke it with
		// a normal browser click. DevTools evaluation can bypass CSP's eval check.
		wp_print_inline_script_tag(<<<'JS'
document.addEventListener('click', function (event) {
    if (event.target instanceof Element && event.target.closest('[data-mol-eval-probe]')) {
        try {
            new Function("document.documentElement.dataset.molEval = 'executed'")();
        } catch (error) {
            document.documentElement.dataset.molEval = error.name;
        }
    }
});
JS, ['id' => 'mol-csp-eval-fixture']);
	}
}, 99);
