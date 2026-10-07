<?php
/**
 * عیب‌یابیِ سرعت — `GET /wp-json/stland/v1/perf` (فقط مدیرِ کل، فقط خواندن).
 *
 * صاحب فروشگاه (مهر ۱۴۰۵): «سایتم خیلی کند است». از بیرون فقط زمانِ کل دیده
 * می‌شد (~۲ ثانیه ساختِ هر صفحه، حتی یک درخواستِ کوچکِ REST). این مسیر از
 * داخل می‌گوید آن ۲ ثانیه کجا می‌رود: نوعِ سرور، opcache، کشِ صفحه و کشِ شیء،
 * حجمِ گزینه‌های autoload (که در هر درخواست خوانده می‌شوند)، cron و زمانِ
 * رسیدن به هر مرحله‌ی بارگذاریِ وردپرس.
 *
 * ⛔ هیچ چیزی را عوض نمی‌کند و هیچ رمز/مسیرِ فایلی بیرون نمی‌دهد.
 */

defined( 'ABSPATH' ) || exit;

/** زمانِ رسیدن به هر مرحله، از شروعِ درخواست (میلی‌ثانیه) */
$GLOBALS['stlh_perf_marks'] = [ 'stland-home loaded' => microtime( true ) ];
foreach ( [ 'plugins_loaded', 'setup_theme', 'after_setup_theme', 'init', 'wp_loaded', 'rest_api_init' ] as $stlh_hook ) {
	add_action( $stlh_hook, static function () use ( $stlh_hook ): void {
		$GLOBALS['stlh_perf_marks'][ $stlh_hook ] ??= microtime( true );
	}, PHP_INT_MIN );
}

add_action( 'rest_api_init', static function (): void {
	register_rest_route( 'stland/v1', '/perf', [
		'methods'             => 'GET',
		'permission_callback' => static fn() => current_user_can( 'manage_options' ),
		'callback'            => 'stlh_perf_report',
	] );
} );

function stlh_perf_report(): array {
	global $wpdb;
	$start = (float) ( $_SERVER['REQUEST_TIME_FLOAT'] ?? microtime( true ) );
	$marks = [];
	foreach ( $GLOBALS['stlh_perf_marks'] as $k => $t ) {
		$marks[ $k ] = (int) round( ( $t - $start ) * 1000 );
	}
	$marks['report'] = (int) round( ( microtime( true ) - $start ) * 1000 );

	$autoload_where = "autoload IN ('yes','on','auto','auto-on')";
	$autoload_bytes = (int) $wpdb->get_var( "SELECT SUM(LENGTH(option_value)) FROM {$wpdb->options} WHERE $autoload_where" );
	$autoload_count = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->options} WHERE $autoload_where" );
	$top = $wpdb->get_results( "SELECT option_name AS name, LENGTH(option_value) AS bytes FROM {$wpdb->options} WHERE $autoload_where ORDER BY bytes DESC LIMIT 15", ARRAY_A );
	$transients = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->options} WHERE option_name LIKE '\\_transient\\_%'" );

	$t0 = microtime( true );
	$wpdb->get_var( 'SELECT 1' );
	$db_ping = (int) round( ( microtime( true ) - $t0 ) * 1000 );

	$opcache = function_exists( 'opcache_get_status' ) ? @opcache_get_status( false ) : false;
	$cron    = _get_cron_array();
	$events  = 0;
	foreach ( (array) $cron as $hooks ) {
		foreach ( (array) $hooks as $instances ) {
			$events += count( (array) $instances );
		}
	}
	$active = (array) get_option( 'active_plugins', [] );

	return [
		'server'          => (string) ( $_SERVER['SERVER_SOFTWARE'] ?? '' ),
		'php'             => PHP_VERSION,
		'sapi'            => PHP_SAPI,
		'memory_limit'    => (string) ini_get( 'memory_limit' ),
		'opcache'         => $opcache ? [
			'enabled'  => (bool) ( $opcache['opcache_enabled'] ?? false ),
			'hit_rate' => round( (float) ( $opcache['opcache_statistics']['opcache_hit_rate'] ?? 0 ), 1 ),
			'used_mb'  => round( ( $opcache['memory_usage']['used_memory'] ?? 0 ) / 1048576, 1 ),
			'free_mb'  => round( ( $opcache['memory_usage']['free_memory'] ?? 0 ) / 1048576, 1 ),
			'full'     => (bool) ( $opcache['cache_full'] ?? false ),
		] : null,
		'zlib_compression' => (string) ini_get( 'zlib.output_compression' ),
		'page_cache'      => [
			'WP_CACHE'          => defined( 'WP_CACHE' ) && WP_CACHE,
			'advanced_cache'    => file_exists( WP_CONTENT_DIR . '/advanced-cache.php' ),
		],
		'object_cache'    => wp_using_ext_object_cache(),
		'db_ping_ms'      => $db_ping,
		'db_queries'      => (int) $wpdb->num_queries,
		'autoload'        => [ 'kb' => (int) round( $autoload_bytes / 1024 ), 'count' => $autoload_count, 'top' => $top ],
		'transients'      => $transients,
		'cron_events'     => $events,
		'wp_cron_disabled' => defined( 'DISABLE_WP_CRON' ) && DISABLE_WP_CRON,
		'debug'           => defined( 'WP_DEBUG' ) && WP_DEBUG,
		'active_plugins'  => array_values( array_map( static fn( $p ) => dirname( $p ), $active ) ),
		'products'        => (int) ( wp_count_posts( 'product' )->publish ?? 0 ),
		'timeline_ms'     => $marks,
		'peak_memory_mb'  => round( memory_get_peak_usage( true ) / 1048576, 1 ),
	];
}
