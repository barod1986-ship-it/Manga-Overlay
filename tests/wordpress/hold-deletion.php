<?php
/** Installed only into the disposable CI site's mu-plugins by verify.yml. */
add_action('before_delete_post', static function ($id, $post): void {
	if (wp_get_environment_type() !== 'local' || getenv('MOL_TEST_ENV') !== '1' || $post->post_type !== 'mol_work' || $post->post_name !== 'reader-race-work') {
		return;
	}
	file_put_contents(getenv('MOL_HTTP_FIXTURE') . '.delete-lock', 'held');
	usleep(750000);
}, 10, 2);
