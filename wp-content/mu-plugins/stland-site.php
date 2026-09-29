<?php
/**
 * Plugin Name: StockLand Site (deploy check)
 * Description: گزارش نسخه‌ی منتشرشده برای ورک‌فلوی انتشار، و پاک کردن کش بعد از هر انتشار.
 *
 * چرا mu-plugin: خاموش‌شدنی نیست، پس ورک‌فلو همیشه می‌تواند بپرسد «کدام
 * کامیت روی سرور است». اگر FTP فایل‌ها را در پوشه‌ی اشتباه بریزد، همین
 * نشانی کامیتِ قدیمی را برمی‌گرداند و انتشار قرمز می‌شود — به‌جای سبزِ
 * دروغین.
 */

defined( 'ABSPATH' ) || exit;

// build.php را ورک‌فلو هنگام انتشار می‌سازد؛ در مخزن نیست.
$stls_build = __DIR__ . '/stland-site/build.php';
if ( is_readable( $stls_build ) ) {
	require_once $stls_build;
}
unset( $stls_build );

defined( 'STLS_BUILD_SHA' ) || define( 'STLS_BUILD_SHA', 'dev' );
defined( 'STLS_BUILD_AT' ) || define( 'STLS_BUILD_AT', '' );

/** نسخه‌ی افزونه از سرآیند خودش — بدون نیاز به فعال بودنش */
function stls_plugin_version( string $file ): ?string {
	$path = WP_PLUGIN_DIR . '/' . $file;
	if ( ! is_readable( $path ) ) {
		return null;
	}
	$data = get_file_data( $path, [ 'Version' => 'Version' ] );
	return '' !== $data['Version'] ? $data['Version'] : null;
}

add_action( 'rest_api_init', function (): void {
	register_rest_route( 'stland/v1', '/health', [
		'methods'             => 'GET',
		'permission_callback' => '__return_true',
		'callback'            => function (): WP_REST_Response {
			$active = (array) get_option( 'active_plugins', [] );
			$res    = new WP_REST_Response( [
				'build'   => STLS_BUILD_SHA,
				'builtAt' => STLS_BUILD_AT,
				'plugins' => [
					'stland-home' => [
						'version' => stls_plugin_version( 'stland-home/stland-home.php' ),
						'active'  => in_array( 'stland-home/stland-home.php', $active, true ),
					],
				],
				'woocommerce' => class_exists( 'WooCommerce' ),
			] );
			// این پاسخ نباید در هیچ کشی بماند، وگرنه وارسیِ انتشار بی‌معناست.
			$res->header( 'Cache-Control', 'no-store, max-age=0' );
			return $res;
		},
	] );
} );

/**
 * اولین درخواست بعد از انتشار، کش صفحه‌ها را پاک می‌کند.
 * بدون این، CSS و HTML تازه روی سرور هست ولی مشتری نسخه‌ی قدیمی را می‌بیند.
 */
add_action( 'init', function (): void {
	if ( 'dev' === STLS_BUILD_SHA || get_option( 'stls_build' ) === STLS_BUILD_SHA ) {
		return;
	}
	update_option( 'stls_build', STLS_BUILD_SHA, true );

	wp_cache_flush();
	do_action( 'litespeed_purge_all' );            // LiteSpeed Cache
	if ( function_exists( 'rocket_clean_domain' ) ) { // WP Rocket
		rocket_clean_domain();
	}
	if ( function_exists( 'w3tc_flush_all' ) ) {      // W3 Total Cache
		w3tc_flush_all();
	}
	if ( function_exists( 'wp_cache_clear_cache' ) ) { // WP Super Cache
		wp_cache_clear_cache();
	}
	do_action( 'stls_deployed', STLS_BUILD_SHA );
} );
