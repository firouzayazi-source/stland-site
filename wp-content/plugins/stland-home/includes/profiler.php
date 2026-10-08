<?php
/**
 * پروفایلر — «هر افزونه در هر درخواست چند میلی‌ثانیه می‌خورد؟» و «صفحه کجا وقت می‌گذراند؟»
 *
 * صاحب فروشگاه (مهر ۱۴۰۵): «هر آنچه بلدی پیاده کن؛ افزونه‌ای لازم نیست با اجازه‌ی
 * خودم غیرفعالش کن.» برای این تصمیم باید عدد داشت، نه حدس. این فایل در هر درخواستِ
 * عادی هیچ کاری نمی‌کند (نه هوکی، نه هزینه‌ای). فقط دو جا فعال می‌شود:
 *
 * ۱. `/wp-json/stland/v1/perf?stlh_prof=1` (فقط مدیرِ کل): زمانِ هر افزونه در
 *    بالا آمدنِ وردپرس. نتیجه در همان پاسخ → `profile`.
 * ۲. هر صفحه‌ی سایت با `?stlh_prof=<توکن>` — توکن را `/perf` به مدیرِ کل می‌دهد
 *    (`profile_token`؛ از کلیدهای wp-config ساخته می‌شود، حدس‌زدنی نیست). این‌جا علاوه
 *    بر افزونه‌ها، **زمانِ هر مرحله‌ی ساختِ صفحه** (تا `wp`، تا رندرِ قالب، `wp_head`،
 *    تا پایان)، تعدادِ کوئری در هر مرحله و **کندترین کوئری‌ها** با صدازننده‌شان
 *    (SAVEQUERIES) ثبت می‌شود؛ صفحه کش نمی‌شود؛ نتیجه در ترنزینت `stlh_prof_last`
 *    می‌ماند و `/perf` → `profile_last` برمی‌گرداند.
 *
 * ─── چطور ───
 * • زمانِ بارِ هر افزونه‌ی بعد از ما از فاصله‌ی دو `plugin_loaded` (ترتیبِ الفبایی،
 *   افزونه‌های پیش از ما — المنتور، FiboSearch… — در یک رقمِ «پیش از stland-home» جمع‌اند).
 * • کال‌بک‌های هوک‌های سنگین پیش از اجرا در یک تایمر پیچیده می‌شوند و زمانشان به
 *   فایلِ تعریفشان (پوشه‌ی افزونه یا قالب) نسبت داده می‌شود — با Reflection.
 * ⛔ چیزی را عوض نمی‌کند؛ متنِ کوئری‌ها کوتاه می‌شود و مقدارها بیرون نمی‌رود.
 */

defined( 'ABSPATH' ) || exit;

/** توکنِ پروفایلِ صفحه — ثابت برای هر نسخه، از کلیدهای wp-config (در گزارشِ /perf به مدیر داده می‌شود) */
function stlh_prof_token(): string {
	$salt = ( defined( 'AUTH_KEY' ) ? AUTH_KEY : '' ) . ( defined( 'NONCE_SALT' ) ? NONCE_SALT : '' );
	if ( strlen( $salt ) < 16 ) {
		return '';
	}
	return substr( hash_hmac( 'sha256', 'stlh_prof|' . STLH_VER, $salt ), 0, 32 );
}

$stlh_prof_q = (string) ( $_GET['stlh_prof'] ?? '' );
if ( '' === $stlh_prof_q ) {
	return;
}
$stlh_prof_page = '' !== stlh_prof_token() && hash_equals( stlh_prof_token(), $stlh_prof_q );
if ( ! $stlh_prof_page && ( '1' !== $stlh_prof_q || ! str_contains( (string) ( $_SERVER['REQUEST_URI'] ?? '' ), '/stland/v1/perf' ) ) ) {
	return;
}
unset( $stlh_prof_q );

$GLOBALS['stlh_prof'] = [ 'owner' => [], 'load' => [], 'last_load' => microtime( true ), 'wrapped' => 0, 'page' => $stlh_prof_page, 'phase' => [] ];

if ( $stlh_prof_page && ! defined( 'SAVEQUERIES' ) ) {
	define( 'SAVEQUERIES', true );
}

add_action( 'plugin_loaded', static function ( string $plugin ): void {
	$now  = microtime( true );
	$slug = basename( dirname( $plugin ) ) ?: basename( $plugin );
	$GLOBALS['stlh_prof']['load'][ $slug ] = (int) round( ( $now - $GLOBALS['stlh_prof']['last_load'] ) * 1000 );
	$GLOBALS['stlh_prof']['last_load']     = $now;
} );

/** فایلِ یک کال‌بک → «plugin:slug» یا «theme:slug» یا «core» */
function stlh_prof_owner( callable|array|string $cb ): string {
	try {
		if ( is_string( $cb ) && str_contains( $cb, '::' ) ) {
			$cb = explode( '::', $cb, 2 );
		}
		$ref = is_array( $cb ) ? new ReflectionMethod( $cb[0], $cb[1] ) : new ReflectionFunction( $cb );
		$file = (string) $ref->getFileName();
	} catch ( Throwable ) {
		return 'unknown';
	}
	return stlh_prof_owner_of_file( $file );
}

function stlh_prof_owner_of_file( string $file ): string {
	$file = str_replace( '\\', '/', $file );
	if ( preg_match( '#/plugins/([^/]+)/#', $file, $m ) ) {
		return 'plugin:' . $m[1];
	}
	if ( preg_match( '#/mu-plugins/([^/]+)#', $file, $m ) ) {
		return 'mu:' . preg_replace( '/\.php$/', '', $m[1] );
	}
	if ( preg_match( '#/themes/([^/]+)/#', $file, $m ) ) {
		return 'theme:' . $m[1];
	}
	return 'core';
}

/** همه‌ی کال‌بک‌های باقی‌مانده‌ی یک هوک را در تایمر می‌پیچد (از اولویتِ فعلی به بعد) */
function stlh_prof_wrap( string $hook ): void {
	global $wp_filter;
	if ( empty( $wp_filter[ $hook ] ) || ! ( $wp_filter[ $hook ] instanceof WP_Hook ) ) {
		return;
	}
	$h = $wp_filter[ $hook ];
	foreach ( $h->callbacks as $prio => &$list ) {
		foreach ( $list as $id => &$entry ) {
			$fn = $entry['function'];
			if ( is_object( $fn ) && ( $fn instanceof Closure ) && str_contains( (string) ( new ReflectionFunction( $fn ) )->getFileName(), 'stland-home' ) ) {
				continue;
			}
			$owner             = stlh_prof_owner( $fn );
			$entry['function'] = static function ( ...$args ) use ( $fn, $owner, $hook ) {
				$t0  = microtime( true );
				$out = $fn( ...$args );
				$ms  = ( microtime( true ) - $t0 ) * 1000;
				$GLOBALS['stlh_prof']['owner'][ $owner ][ $hook ] = ( $GLOBALS['stlh_prof']['owner'][ $owner ][ $hook ] ?? 0 ) + $ms;
				return $out;
			};
			$GLOBALS['stlh_prof']['wrapped']++;
		}
		unset( $entry );
	}
	unset( $list );
}

$stlh_prof_hooks = [ 'plugins_loaded', 'after_setup_theme', 'init', 'widgets_init', 'wp_loaded', 'rest_api_init' ];
if ( $stlh_prof_page ) {
	$stlh_prof_hooks = array_merge( $stlh_prof_hooks, [ 'wp', 'template_redirect', 'wp_enqueue_scripts', 'wp_head', 'wp_footer', 'woocommerce_before_single_product', 'woocommerce_single_product_summary', 'woocommerce_after_single_product_summary', 'woocommerce_single_product_end' ] );
}
foreach ( $stlh_prof_hooks as $stlh_h ) {
	add_action( $stlh_h, static function () use ( $stlh_h ): void {
		stlh_prof_wrap( $stlh_h );
	}, PHP_INT_MIN );
}
unset( $stlh_prof_hooks );

/** خلاصه برای گزارش: به ازای هر صاحب، جمعِ میلی‌ثانیه و تفکیکِ هوک‌ها */
function stlh_prof_report(): array {
	$p = $GLOBALS['stlh_prof'] ?? null;
	if ( ! $p ) {
		return [];
	}
	$rows = [];
	foreach ( $p['owner'] as $owner => $hooks ) {
		$rows[ $owner ] = [ 'hooks_ms' => (int) round( array_sum( $hooks ) ), 'by_hook' => array_map( static fn( $v ) => (int) round( $v ), $hooks ) ];
	}
	foreach ( $p['load'] as $slug => $ms ) {
		$key = 'plugin:' . $slug;
		$rows[ $key ]['load_ms'] = $ms;
		$rows[ $key ]['hooks_ms'] ??= 0;
	}
	foreach ( $rows as &$r ) {
		$r['total_ms'] = ( $r['hooks_ms'] ?? 0 ) + ( $r['load_ms'] ?? 0 );
	}
	unset( $r );
	uasort( $rows, static fn( $a, $b ) => $b['total_ms'] <=> $a['total_ms'] );
	return [ 'wrapped_callbacks' => $p['wrapped'], 'note' => 'load_ms فقط برای افزونه‌های بعد از stland-home (ترتیبِ الفبایی) معلوم است', 'rows' => $rows ];
}

/* ─── حالتِ صفحه: مرحله‌ها، کوئری‌ها، ذخیره‌ی نتیجه ─── */
if ( ! $stlh_prof_page ) {
	return;
}
unset( $stlh_prof_page );

/** علامتِ یک مرحله: زمان از شروعِ درخواست + تعدادِ کوئری تا این‌جا */
function stlh_prof_phase( string $name ): void {
	global $wpdb;
	$start = (float) ( $_SERVER['REQUEST_TIME_FLOAT'] ?? microtime( true ) );
	$GLOBALS['stlh_prof']['phase'][ $name ] ??= [ 'ms' => (int) round( ( microtime( true ) - $start ) * 1000 ), 'queries' => (int) ( $wpdb->num_queries ?? 0 ) ];
}

stlh_prof_phase( 'stland-home loaded' );
foreach ( [ 'plugins_loaded', 'after_setup_theme', 'init', 'wp_loaded', 'wp', 'template_redirect' ] as $stlh_h ) {
	add_action( $stlh_h, static fn() => stlh_prof_phase( $stlh_h ), PHP_INT_MAX );
}
unset( $stlh_h );
// صفحه کش نشود — گزارشِ کش‌شده به دردِ عیب‌یابی نمی‌خورد و LiteSpeed با پارامترِ ناشناس هم کش می‌کند
add_action( 'init', static function (): void {
	do_action( 'litespeed_control_set_nocache', 'stland profiler' );
	nocache_headers();
}, 1 );
add_filter( 'template_include', static function ( $t ) {
	stlh_prof_phase( 'template_include' );
	return $t;
}, PHP_INT_MAX );
add_action( 'wp_head', static fn() => stlh_prof_phase( 'wp_head start' ), PHP_INT_MIN );
add_action( 'wp_head', static fn() => stlh_prof_phase( 'wp_head end' ), PHP_INT_MAX );
add_action( 'wp_footer', static fn() => stlh_prof_phase( 'wp_footer start' ), PHP_INT_MIN );
add_action( 'wp_footer', static fn() => stlh_prof_phase( 'wp_footer end' ), PHP_INT_MAX );
foreach ( [ 'woocommerce_before_single_product', 'woocommerce_single_product_summary', 'woocommerce_after_single_product_summary', 'woocommerce_single_product_end', 'woocommerce_before_shop_loop', 'woocommerce_after_shop_loop' ] as $stlh_h ) {
	add_action( $stlh_h, static fn() => stlh_prof_phase( $stlh_h ), PHP_INT_MAX );
}
unset( $stlh_h );

/** کوئری‌ها: جمعِ زمان، تعداد، کندترین‌ها با صدازننده، و جمعِ زمان به تفکیکِ صدازننده‌ی اصلی */
function stlh_prof_queries(): array {
	global $wpdb;
	$qs = is_array( $wpdb->queries ?? null ) ? $wpdb->queries : [];
	$total = 0.0;
	$rows  = [];
	$by    = [];
	foreach ( $qs as $q ) {
		$ms     = (float) ( $q[1] ?? 0 ) * 1000;
		$total += $ms;
		$sql    = preg_replace( '/\s+/', ' ', trim( (string) ( $q[0] ?? '' ) ) );
		$caller = (string) ( $q[2] ?? '' );
		// صدازننده‌ی اصلی: اولین تابعِ غیرِ هسته در زنجیره (apply_filters, do_action, WP_Query… حذف)
		$parts = array_values( array_filter( array_map( 'trim', explode( ',', $caller ) ), static fn( $f ) => '' !== $f && ! preg_match( '/^(require|include|do_action|apply_filters|WP_Hook|wpdb|WP->|wp\b|get_option|get_post_meta|get_metadata|update_meta_cache|WP_Query|get_posts|get_terms|WP_Term_Query|wp_cache)/', $f ) ) );
		$top   = $parts ? end( $parts ) : ( $caller ? substr( $caller, 0, 80 ) : '?' );
		$by[ $top ] = [ 'ms' => ( $by[ $top ]['ms'] ?? 0 ) + $ms, 'n' => ( $by[ $top ]['n'] ?? 0 ) + 1 ];
		$rows[] = [ 'ms' => round( $ms, 1 ), 'sql' => mb_substr( $sql, 0, 220 ), 'caller' => mb_substr( $caller, 0, 240 ) ];
	}
	usort( $rows, static fn( $a, $b ) => $b['ms'] <=> $a['ms'] );
	foreach ( $by as &$b ) {
		$b['ms'] = (int) round( $b['ms'] );
	}
	unset( $b );
	uasort( $by, static fn( $a, $b ) => $b['ms'] <=> $a['ms'] );
	// کوئری‌های تکراری (یک متن، چند بار) — نشانه‌ی نبودِ کشِ شیء
	$dups = [];
	foreach ( $qs as $q ) {
		$k = md5( (string) ( $q[0] ?? '' ) );
		$dups[ $k ] = ( $dups[ $k ] ?? 0 ) + 1;
	}
	$dup_n = count( array_filter( $dups, static fn( $n ) => $n > 1 ) );
	return [
		'count'      => count( $qs ),
		'total_ms'   => (int) round( $total ),
		'duplicates' => $dup_n,
		'slowest'    => array_slice( $rows, 0, 20 ),
		'by_caller'  => array_slice( $by, 0, 25, true ),
	];
}

add_action( 'shutdown', static function (): void {
	global $wpdb;
	stlh_prof_phase( 'shutdown' );
	$start = (float) ( $_SERVER['REQUEST_TIME_FLOAT'] ?? microtime( true ) );
	$uri   = (string) ( $_SERVER['REQUEST_URI'] ?? '' );
	$report = [
		'at'             => gmdate( 'c' ),
		'uri'            => preg_replace( '/stlh_prof=[^&]+/', 'stlh_prof=…', $uri ),
		'mobile'         => wp_is_mobile(),
		'total_ms'       => (int) round( ( microtime( true ) - $start ) * 1000 ),
		'queries'        => (int) $wpdb->num_queries,
		'peak_memory_mb' => round( memory_get_peak_usage( true ) / 1048576, 1 ),
		'phases'         => $GLOBALS['stlh_prof']['phase'],
		'plugins'        => stlh_prof_report(),
		'db'             => stlh_prof_queries(),
		'template'       => stlh_prof_owner_of_file( (string) ( $GLOBALS['template'] ?? '' ) ) . ':' . basename( (string) ( $GLOBALS['template'] ?? '' ) ),
	];
	set_transient( 'stlh_prof_last', $report, HOUR_IN_SECONDS );
}, PHP_INT_MIN );
