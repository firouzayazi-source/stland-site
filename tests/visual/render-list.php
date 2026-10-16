<?php
/**
 * صفحه‌ی «لیست آک و کارکرده» (`includes/app-list.php`) → HTMLِ خودبسنده برای Playwright. فهرست از همان راهِ
 * حسابداری می‌رسد (`stlh_app_list_receive`)؛ گوشیِ فروخته‌شده هم در فهرست است و نباید دیده شود.
 */
$warnings = [];
set_error_handler( static function ( $no, $msg, $file, $line ) use ( &$warnings ) {
	if ( str_contains( $file, 'stland-home' ) ) {
		$warnings[] = "$msg ($file:$line)";
	}
	return false;
} );
$_SERVER['HTTP_HOST'] ??= 'localhost';
require getenv( 'WP_DIR' ) . '/wp-load.php';
$_GET['stlh_nocache'] = '1';

$ids = static fn( string $like ) => array_map(
	static fn( $p ) => [ 'id' => $p->ID, 'chips' => [ 'کارکرده', '256GB', '🔋 88٪' ] ],
	array_filter( get_posts( [ 'meta_key' => 'is_product', 'numberposts' => -1 ] ), static fn( $p ) => str_contains( $p->post_title, $like ) )
);
$req = new WP_REST_Request( 'POST', '/stland/v1/app-list' );
$req->set_param( 'sections', [
	[ 'key' => 'new', 'label' => 'آک', 'items' => array_values( $ids( 'آیفون 17' ) ) ],
	[ 'key' => 'used', 'label' => 'کارکرده', 'items' => array_values( $ids( 'پرو' ) ) ],
	[ 'key' => 'hack', 'label' => '<b>x</b>', 'items' => [ [ 'id' => 1 ] ] ],
] );
$res = stlh_app_list_receive( $req );

$html = do_shortcode( '[stl_app_list]' );
$dir  = WP_PLUGIN_DIR . '/stland-home/assets';
$page = '<!doctype html><html dir="rtl" lang="fa"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">'
	. '<style>body{margin:0;font-family:Tahoma,sans-serif}' . stlh_inline_css() . file_get_contents( "$dir/css/home.css" ) . file_get_contents( "$dir/css/app-list.css" ) . '</style></head>'
	. '<body><main class="stlh-list">' . $html . '</main></body></html>';
file_put_contents( getenv( 'OUT' ), $page );
file_put_contents( getenv( 'OUT' ) . '.expect.json', wp_json_encode( [ 'received' => $res, 'warnings' => $warnings ], JSON_UNESCAPED_UNICODE ) );
echo $warnings ? "PHP warnings:\n" . implode( "\n", $warnings ) . "\n" : "rendered list\n";
exit( $warnings ? 1 : 0 );
