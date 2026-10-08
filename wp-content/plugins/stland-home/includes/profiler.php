<?php
/**
 * پروفایلرِ افزونه‌ها — «هر افزونه در هر درخواست چند میلی‌ثانیه می‌خورد؟»
 *
 * صاحب فروشگاه (مهر ۱۴۰۵): «هر آنچه بلدی پیاده کن؛ افزونه‌ای لازم نیست با اجازه‌ی
 * خودم غیرفعالش کن.» برای این تصمیم باید عدد داشت، نه حدس. این فایل فقط وقتی
 * فعال می‌شود که درخواستِ `/wp-json/stland/v1/perf` با `stlh_prof=1` باشد؛ در
 * هر درخواستِ دیگر هیچ کاری نمی‌کند (نه هوکی، نه هزینه‌ای).
 *
 * ─── چطور ───
 * • زمانِ بارِ هر افزونه‌ی بعد از ما از فاصله‌ی دو `plugin_loaded` (ترتیبِ الفبایی،
 *   افزونه‌های پیش از ما — المنتور، FiboSearch… — در یک رقمِ «پیش از stland-home» جمع‌اند).
 * • کال‌بک‌های هوک‌های سنگین (`plugins_loaded`، `after_setup_theme`، `init`، `wp_loaded`،
 *   `rest_api_init`) پیش از اجرا در یک تایمر پیچیده می‌شوند و زمانشان به فایلِ
 *   تعریفشان (پوشه‌ی افزونه یا قالب) نسبت داده می‌شود — با Reflection.
 * نتیجه در `/perf` → `profile` می‌آید، فقط برای مدیرِ کل.
 */

defined( 'ABSPATH' ) || exit;

if ( empty( $_GET['stlh_prof'] ) || ! str_contains( (string) ( $_SERVER['REQUEST_URI'] ?? '' ), '/stland/v1/perf' ) ) {
	return;
}

$GLOBALS['stlh_prof'] = [ 'owner' => [], 'load' => [], 'last_load' => microtime( true ), 'wrapped' => 0 ];

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

foreach ( [ 'plugins_loaded', 'after_setup_theme', 'init', 'widgets_init', 'wp_loaded', 'rest_api_init' ] as $stlh_h ) {
	add_action( $stlh_h, static function () use ( $stlh_h ): void {
		stlh_prof_wrap( $stlh_h );
	}, PHP_INT_MIN );
}

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
