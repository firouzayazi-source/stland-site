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

/*
 * مسیرهای مدیرِ کلِ stland/v1 هرگز کش نمی‌شوند. LiteSpeed با «کشِ REST» روشن، پاسخِ
 * درخواستِ رمزدار را (که کوکی ندارد و مهمان دیده می‌شود) کش می‌کرد: گزارشِ `/perf`
 * سه بار پشتِ سرِ هم یک عدد می‌داد. به ناشناس ۴۰۱ می‌دهد (آزموده شد)، ولی گزارشِ کهنه
 * هم به دردِ عیب‌یابی نمی‌خورد.
 */
add_filter( 'rest_pre_dispatch', static function ( $result, $server, $request ) {
	if ( str_starts_with( (string) $request->get_route(), '/stland/v1/' ) ) {
		do_action( 'litespeed_control_set_nocache', 'stland admin api' );
		nocache_headers();
	}
	return $result;
}, 10, 3 );

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
		'cache_backends'  => [ 'redis_ext' => class_exists( 'Redis' ), 'memcached_ext' => class_exists( 'Memcached' ), 'apcu' => function_exists( 'apcu_enabled' ) && apcu_enabled() ],
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
		'litespeed'       => stlh_perf_litespeed_conf(),
		'speed'           => [ 'diet' => stlh_diet_on(), 'db_tune' => get_option( 'stlh_db_tune', null ), 'autoload_off_count' => count( (array) get_option( 'stlh_autoload_off', [] ) ) ],
		'probe'           => ! empty( $_GET['probe'] ) ? stlh_perf_probe() : null,
		'profile'         => function_exists( 'stlh_prof_report' ) ? stlh_prof_report() : null,
		'profile_token'   => function_exists( 'stlh_prof_token' ) ? stlh_prof_token() : '',
		'profile_last'    => get_transient( 'stlh_prof_last' ) ?: null,
		'slow_http'       => array_values( array_filter( (array) get_transient( 'stlh_slow_http' ) ) ),
		'warm_last'       => get_option( 'stlh_warm_last', null ),
		'warm_all_last'   => get_option( 'stlh_warm_all_last', null ),
		'warm_next'       => wp_next_scheduled( 'stlh_warm_all' ) ? gmdate( 'c', (int) wp_next_scheduled( 'stlh_warm_all' ) ) : null,
		'rewrite_flush'   => get_transient( 'stlh_rewrite_flush' ) ?: null,
		'elementor'       => stlh_perf_elementor(),
		'admin_times'     => stlh_perf_admin_times(),
		'usage'           => stlh_perf_usage(),
	];
}

/*
 * ─── ثبتِ همیشگی و سبک (هر درخواست، چند میکروثانیه) ───
 * پیشخوان از راهِ پل دیده نمی‌شود (رمزِ برنامه فقط برای REST است)، پس خودِ سایت
 * یادداشت می‌کند: (۱) هر درخواستِ بیرونیِ کند یا خطادار (api.wordpress.org، ژاکت،
 * Yoast، المنتور…) با زمانش — روی هاستِ ایرانی خیلی از این‌ها تا timeout می‌مانند و
 * پیشخوان را قفل می‌کنند؛ (۲) زمانِ ساختِ هر صفحه‌ی پیشخوان با تعدادِ کوئری. هر دو
 * در `/perf` → `slow_http` و `admin_times`. درخواست به خودِ سایت (cron، probe) ثبت نمی‌شود.
 */
add_filter( 'http_request_args', static function ( array $args ): array {
	$args['stlh_t0'] = microtime( true );
	return $args;
}, 1 );

add_action( 'http_api_debug', static function ( $response, $context, $class, $args, $url ): void {
	$t0 = (float) ( $args['stlh_t0'] ?? 0 );
	if ( ! $t0 ) {
		return;
	}
	$host = (string) wp_parse_url( (string) $url, PHP_URL_HOST );
	if ( '' === $host || $host === (string) wp_parse_url( home_url(), PHP_URL_HOST ) ) {
		return;
	}
	$ms  = (int) round( ( microtime( true ) - $t0 ) * 1000 );
	$err = is_wp_error( $response ) ? $response->get_error_message() : '';
	if ( $ms < 700 && '' === $err ) {
		return;
	}
	$where = wp_doing_cron() ? 'cron' : ( wp_doing_ajax() ? 'ajax' : ( ( defined( 'REST_REQUEST' ) && REST_REQUEST ) ? 'rest' : ( is_admin() ? 'admin' : 'front' ) ) );
	$list  = array_filter( (array) get_transient( 'stlh_slow_http' ) );
	array_unshift( $list, [
		'at'      => gmdate( 'c' ),
		'host'    => $host,
		'path'    => substr( (string) wp_parse_url( (string) $url, PHP_URL_PATH ), 0, 60 ),
		'ms'      => $ms,
		'result'  => '' !== $err ? $err : (string) wp_remote_retrieve_response_code( $response ),
		'where'   => $where,
		'timeout' => $args['timeout'] ?? null,
	] );
	set_transient( 'stlh_slow_http', array_slice( $list, 0, 40 ), WEEK_IN_SECONDS );
}, 10, 5 );

add_action( 'shutdown', static function (): void {
	global $wpdb, $pagenow;
	if ( ! is_admin() || wp_doing_ajax() || wp_doing_cron() || ( defined( 'REST_REQUEST' ) && REST_REQUEST ) ) {
		return;
	}
	$start = (float) ( $_SERVER['REQUEST_TIME_FLOAT'] ?? 0 );
	if ( ! $start ) {
		return;
	}
	$page = (string) $pagenow;
	foreach ( [ 'page', 'post_type', 'action', 'tab' ] as $k ) {
		if ( isset( $_GET[ $k ] ) ) {
			$page .= ( str_contains( $page, '?' ) ? '&' : '?' ) . $k . '=' . sanitize_key( (string) $_GET[ $k ] );
		}
	}
	$list = array_filter( (array) get_transient( 'stlh_admin_times' ) );
	array_unshift( $list, [
		'at'      => gmdate( 'c' ),
		'page'    => $page,
		'ms'      => (int) round( ( microtime( true ) - $start ) * 1000 ),
		'queries' => (int) $wpdb->num_queries,
		'mb'      => round( memory_get_peak_usage( true ) / 1048576, 1 ),
	] );
	set_transient( 'stlh_admin_times', array_slice( $list, 0, 50 ), WEEK_IN_SECONDS );
}, PHP_INT_MAX );

/** آخرین صفحه‌های پیشخوان + خلاصه (میانگین و بیشینه) */
function stlh_perf_admin_times(): array {
	$list = array_values( array_filter( (array) get_transient( 'stlh_admin_times' ) ) );
	if ( ! $list ) {
		return [ 'n' => 0, 'rows' => [] ];
	}
	$ms = array_map( static fn( $r ) => (int) ( $r['ms'] ?? 0 ), $list );
	return [ 'n' => count( $list ), 'avg_ms' => (int) round( array_sum( $ms ) / count( $ms ) ), 'max_ms' => max( $ms ), 'rows' => $list ];
}

/** تنظیماتِ کلیدیِ LiteSpeed Cache (هر کدام یک گزینه‌ی `litespeed.conf.*`) */
function stlh_perf_litespeed_conf(): ?array {
	if ( ! defined( 'LSCWP_V' ) ) {
		return null;
	}
	global $wpdb;
	$out  = [ 'version' => LSCWP_V ];
	$rows = $wpdb->get_results( "SELECT option_name, option_value FROM {$wpdb->options} WHERE option_name LIKE 'litespeed.conf.%' AND (option_name LIKE 'litespeed.conf.cache%' OR option_name LIKE 'litespeed.conf.optm%' OR option_name LIKE 'litespeed.conf.object%' OR option_name LIKE 'litespeed.conf.guest%' OR option_name LIKE 'litespeed.conf.media-lazy%' OR option_name LIKE 'litespeed.conf.cdn%')", ARRAY_A );
	foreach ( $rows as $r ) {
		$k = substr( $r['option_name'], strlen( 'litespeed.conf.' ) );
		$v = maybe_unserialize( $r['option_value'] );
		// فهرست‌های بلند (استثناها) فقط تعدادشان
		$out[ $k ] = is_array( $v ) ? count( $v ) . ' items' : ( is_string( $v ) && strlen( $v ) > 120 ? substr( $v, 0, 120 ) . '…' : $v );
	}
	return $out;
}

/**
 * سرور صفحه‌ها را مثلِ یک بازدیدکننده‌ی ناشناس از خودش می‌گیرد — دو بار پشتِ سرِ هم —
 * تا معلوم شود کشِ صفحه واقعاً جواب می‌دهد (`x-litespeed-cache: hit`). از بیرون دیده
 * نمی‌شد: پل با رمز می‌آید و LiteSpeed درخواستِ رمزدار را درست از کش نمی‌دهد.
 */
function stlh_perf_probe(): array {
	$urls = [ 'home' => home_url( '/' ) ];
	if ( function_exists( 'wc_get_page_permalink' ) ) {
		$urls['shop'] = wc_get_page_permalink( 'shop' );
	}
	$p = get_posts( [ 'post_type' => 'product', 'post_status' => 'publish', 'numberposts' => 1, 'fields' => 'ids' ] );
	if ( $p ) {
		$urls['product'] = get_permalink( $p[0] );
	}
	$out = [];
	foreach ( $urls as $name => $url ) {
		foreach ( [ 1, 2 ] as $n ) {
			foreach ( [ 'desktop' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 Chrome/124 Safari/537.36', 'mobile' => 'Mozilla/5.0 (iPhone; CPU iPhone OS 17_0 like Mac OS X) AppleWebKit/605.1.15 Mobile/15E148 Safari/604.1' ] as $dev => $ua ) {
				$t0  = microtime( true );
				$res = wp_remote_get( $url, [ 'timeout' => 20, 'redirection' => 2, 'sslverify' => false, 'user-agent' => $ua, 'cookies' => [] ] );
				$out[] = [
					'page'   => $name,
					'try'    => $n,
					'device' => $dev,
					'ms'     => (int) round( ( microtime( true ) - $t0 ) * 1000 ),
					'code'   => is_wp_error( $res ) ? $res->get_error_message() : wp_remote_retrieve_response_code( $res ),
					'cache'  => is_wp_error( $res ) ? '' : (string) wp_remote_retrieve_header( $res, 'x-litespeed-cache' ),
					'control' => is_wp_error( $res ) ? '' : (string) wp_remote_retrieve_header( $res, 'x-litespeed-cache-control' ),
					'kb'     => is_wp_error( $res ) ? 0 : (int) round( strlen( wp_remote_retrieve_body( $res ) ) / 1024 ),
				];
			}
		}
	}
	return $out;
}

/**
 * چه چیزی واقعاً استفاده می‌شود — برای تصمیمِ «کدام افزونه لازم نیست»:
 * برگه‌های ساخته‌شده با المنتور، تکه‌کدهای فعالِ Code Snippets، محصولِ متغیر
 * (برای swatches)، و گروه‌بندیِ ویژگی‌ها (افزونه‌ی attributes).
 */
function stlh_perf_usage(): array {
	global $wpdb;
	$el = $wpdb->get_results( "SELECT p.ID, p.post_title, p.post_type, p.post_status FROM {$wpdb->posts} p JOIN {$wpdb->postmeta} m ON m.post_id = p.ID AND m.meta_key = '_elementor_edit_mode' AND m.meta_value = 'builder' WHERE p.post_status IN ('publish','draft','private') ORDER BY p.post_type, p.post_title LIMIT 60", ARRAY_A );
	$snips = [];
	$t     = $wpdb->prefix . 'snippets';
	if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $t ) ) ) {
		$snips = $wpdb->get_results( "SELECT id, name, scope, active FROM {$t} ORDER BY active DESC, id", ARRAY_A );
	}
	$variable = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->posts} p JOIN {$wpdb->term_relationships} tr ON tr.object_id = p.ID JOIN {$wpdb->term_taxonomy} tt ON tt.term_taxonomy_id = tr.term_taxonomy_id AND tt.taxonomy = 'product_type' JOIN {$wpdb->terms} te ON te.term_id = tt.term_id AND te.slug = 'variable' WHERE p.post_type = 'product' AND p.post_status = 'publish'" );
	$jcaa = $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->term_taxonomy} WHERE taxonomy LIKE 'jcaa%'" );
	return [
		'elementor_built'   => array_map( static fn( $r ) => [ 'id' => (int) $r['ID'], 'title' => $r['post_title'], 'type' => $r['post_type'], 'status' => $r['post_status'], 'url' => get_permalink( (int) $r['ID'] ) ], $el ),
		'snippets'          => $snips,
		'variable_products' => $variable,
		'attribute_groups'  => (int) $jcaa,
		'theme'             => [ 'name' => wp_get_theme()->get( 'Name' ), 'version' => wp_get_theme()->get( 'Version' ) ],
	];
}

/** تنظیماتِ المنتور که روی سرعت اثر دارند (آزمایش‌ها، کشِ عنصر، روشِ چاپِ CSS) — بی هیچ رمزی */
function stlh_perf_elementor(): ?array {
	if ( ! defined( 'ELEMENTOR_VERSION' ) ) {
		return null;
	}
	global $wpdb;
	$out  = [ 'version' => ELEMENTOR_VERSION, 'pro' => defined( 'ELEMENTOR_PRO_VERSION' ) ? ELEMENTOR_PRO_VERSION : null ];
	$rows = $wpdb->get_results( "SELECT option_name, option_value FROM {$wpdb->options} WHERE option_name LIKE 'elementor\\_experiment-%' OR option_name IN ('elementor_element_cache_ttl','elementor_css_print_method','elementor_optimized_dom_output','elementor_font_display','elementor_load_fa4_shim','elementor_disable_color_schemes','elementor_disable_typography_schemes')", ARRAY_A );
	foreach ( $rows as $r ) {
		$out[ $r['option_name'] ] = mb_substr( (string) $r['option_value'], 0, 40 );
	}
	return $out;
}

/*
 * ─── چه کسی قواعدِ نشانی (rewrite_rules) را در هر درخواست پاک می‌کند؟ ───
 * پروفایلِ زنده (مهر ۱۴۰۵): در **هر** بازدید، هسته در `wp_loaded` همه‌ی ۱٬۳۶۱ قاعده را
 * از نو می‌سازد و در پایگاه داده می‌نویسد (`WP_Rewrite->flush_rules`، ۳۰ تا ۸۰ میلی‌ثانیه
 * و یک UPDATEِ بزرگ). هسته فقط وقتی این کار را می‌کند که کسی پیش‌تر در همان درخواست
 * گزینه را خالی کرده باشد (`flush_rewrite_rules()` در `init`). این‌جا صدازننده ثبت
 * می‌شود تا با نام پیدا شود؛ نتیجه در `/perf` → `rewrite_flush`.
 */
add_action( 'update_option', static function ( string $option, $old, $value ): void {
	if ( 'rewrite_rules' !== $option || ! empty( $value ) ) {
		return;
	}
	$trace = wp_debug_backtrace_summary( null, 0, false );
	$trace = array_values( array_filter( $trace, static fn( $f ) => ! preg_match( '/^(do_action|apply_filters|WP_Hook|require|include)/', $f ) ) );
	$GLOBALS['stlh_rewrite_flush'] = [
		'at'    => gmdate( 'c' ),
		'uri'   => substr( (string) ( $_SERVER['REQUEST_URI'] ?? '' ), 0, 80 ),
		'hook'  => current_filter(),
		'admin' => is_admin(),
		'trace' => array_slice( $trace, 0, 14 ),
	];
}, 10, 3 );
add_action( 'shutdown', static function (): void {
	if ( empty( $GLOBALS['stlh_rewrite_flush'] ) ) {
		return;
	}
	$prev = (array) get_transient( 'stlh_rewrite_flush' );
	$n    = (int) ( $prev['count'] ?? 0 ) + 1;
	set_transient( 'stlh_rewrite_flush', $GLOBALS['stlh_rewrite_flush'] + [ 'count' => $n, 'first' => $prev['first'] ?? gmdate( 'c' ) ], DAY_IN_SECONDS );
}, 1 );
