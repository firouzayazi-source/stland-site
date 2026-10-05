<?php
/**
 * مسیرِ خواندنِ قالب (`includes/theme-files.php`): مدیر فهرست و متن را می‌گیرد،
 * مهمان نه، و هیچ مسیری از پوشه‌ی قالب بیرون نمی‌رود (wp-config).
 */
$_SERVER['HTTP_HOST'] ??= 'localhost';
require getenv( 'WP_DIR' ) . '/wp-load.php';

$fail = [];
$get  = static function ( array $q ) {
	$r = new WP_REST_Request( 'GET', '/stland/v1/theme-files' );
	$r->set_query_params( $q );
	return rest_do_request( $r );
};

wp_set_current_user( 0 );
if ( 401 !== $get( [] )->get_status() ) {
	$fail[] = 'guest must get 401';
}

wp_set_current_user( 1 );
$list = $get( [] );
$data = $list->get_data();
$slug = $data['active'] ?? '';
$files = array_column( $data['themes'][0]['files'] ?? [], 0 );
if ( 200 !== $list->get_status() || ! in_array( 'style.css', $files, true ) ) {
	$fail[] = 'admin list must include style.css';
}
$one = $get( [ 'theme' => $slug, 'path' => 'style.css' ] )->get_data();
if ( ! str_contains( (string) ( $one['content'] ?? '' ), 'Theme Name' ) ) {
	$fail[] = 'admin must read style.css';
}
foreach ( [ '../../../wp-config.php', '/etc/passwd', '../' . $slug . '/style.css/../../../wp-config.php' ] as $bad ) {
	if ( 404 !== $get( [ 'theme' => $slug, 'path' => $bad ] )->get_status() ) {
		$fail[] = "escape not blocked: $bad";
	}
}
echo $fail ? "theme-files FAIL:\n  " . implode( "\n  ", $fail ) . "\n" : "theme-files ok\n";
exit( $fail ? 1 : 0 );
