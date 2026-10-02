<?php
/**
 * صفحه‌ی اصلی → یک فایلِ HTMLِ خودبسنده (CSS و JS درون‌خطی) برای Playwright.
 * هر هشدار/خطای PHP از افزونه‌ی ما شمرده می‌شود؛ یکی هم باشد آزمون قرمز است.
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
$html = do_shortcode( '[stl_home]' );
$dir  = WP_PLUGIN_DIR . '/stland-home/assets';
$page = '<!doctype html><html dir="rtl" lang="fa"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">'
	. '<style>body{margin:0;font-family:Tahoma,sans-serif;background:#f7f7f8}' . stlh_inline_css() . file_get_contents( "$dir/css/home.css" ) . '</style></head>'
	. '<body class="home">' . $html . '<script>window.stlhAuto=0;</script><script>' . file_get_contents( "$dir/js/home.js" ) . '</script></body></html>';
file_put_contents( getenv( 'OUT' ), $page );
file_put_contents( getenv( 'OUT' ) . '.expect.json', wp_json_encode( [
	'lines'    => array_map( static fn( $l ) => [ 'cat' => get_term( $l['cat'] )->name, 'mode' => $l['mode'] ], stlh_lines_effective() ),
	'warnings' => $warnings,
], JSON_UNESCAPED_UNICODE ) );
echo $warnings ? "PHP warnings:\n" . implode( "\n", $warnings ) . "\n" : "rendered\n";
exit( $warnings ? 1 : 0 );
