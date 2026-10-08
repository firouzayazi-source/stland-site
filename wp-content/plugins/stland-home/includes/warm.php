<?php
/**
 * گرم کردنِ کش بعد از پاک شدنش.
 *
 * پروفایلِ سایتِ زنده (مهر ۱۴۰۵): صفحه از کش ~۰٫۱ ثانیه، ساختِ سرد ۱ تا ۲٫۵ ثانیه
 * روی سرور (و از ایران، با گذر از CDN، چند برابر). هر ارسالِ محصول از حسابداری
 * (روزی ۱۰ تا ۲۰ بار) کشِ صفحه‌ی اصلی، فروشگاه، دسته و خودِ محصول را پاک می‌کند —
 * هم LiteSpeed (`purge-post_*`) و هم نسلِ بخش‌های ما — و **اولین مشتریِ بعدی** پولِ
 * ساختِ سرد را می‌دهد. این فایل آن پول را خودِ سرور می‌دهد: چند ثانیه بعد از پاک
 * شدن، همان صفحه‌ها را مثلِ یک بازدیدکننده (گوشی و دسکتاپ) می‌گیرد تا دوباره کش شوند.
 *
 * ─── چطور ───
 * • هر پاک شدن (نسلِ ما، ذخیره‌ی محصول) نشانی‌ها را در یک صف می‌گذارد و **یک** رویدادِ
 *   cron برای ۲۰ ثانیه بعد می‌چیند (چند ذخیره‌ی پشتِ سرِ هم = یک گرم کردن).
 * • همیشه: صفحه‌ی اصلی و فروشگاه. برای محصول: خودِ محصول و دسته‌هایش. سقف ۱۲ نشانی.
 * • درخواست‌ها پشتِ سرِ هم‌اند (نه هم‌زمان) تا هاستِ اشتراکی خفه نشود؛ هر کدام بیشینه
 *   ۲۵ ثانیه. اگر صفحه از پیش در کش بود (`x-litespeed-cache: hit`) همان‌جا تمام است.
 * ⛔ هیچ‌وقت در خودِ درخواستِ ذخیره اجرا نمی‌شود (حسابداری منتظر نمی‌ماند).
 * ⛔ کلیدِ «گرم کردنِ کش» در تنظیمات خاموشش می‌کند.
 */

defined( 'ABSPATH' ) || exit;

const STLH_WARM_HOOK  = 'stlh_warm';
const STLH_WARM_OPT   = 'stlh_warm_queue';
const STLH_WARM_DELAY = 20;
const STLH_WARM_MAX   = 150;

function stlh_warm_on(): bool {
	return '1' === (string) stlh_opt( 'warm' );
}

/** نشانی‌ها را به صف اضافه می‌کند و (اگر نیست) رویداد را می‌چیند */
function stlh_warm_queue( array $urls ): void {
	if ( ! stlh_warm_on() || wp_installing() ) {
		return;
	}
	$q = (array) get_option( STLH_WARM_OPT, [] );
	foreach ( $urls as $u ) {
		$u = (string) $u;
		if ( '' !== $u && ! in_array( $u, $q, true ) ) {
			$q[] = $u;
		}
	}
	update_option( STLH_WARM_OPT, array_slice( $q, 0, 150 ), false );
	if ( ! wp_next_scheduled( STLH_WARM_HOOK ) ) {
		wp_schedule_single_event( time() + STLH_WARM_DELAY, STLH_WARM_HOOK );
	}
}

/** نشانی‌های همیشگی: صفحه‌ی اصلی و فروشگاه */
function stlh_warm_base(): array {
	$urls = [ home_url( '/' ) ];
	if ( function_exists( 'wc_get_page_permalink' ) ) {
		$shop = wc_get_page_permalink( 'shop' );
		if ( $shop ) {
			$urls[] = $shop;
		}
	}
	return $urls;
}

/** نشانی‌های یک محصول: خودش و دسته‌هایش */
function stlh_warm_product_urls( int $product_id ): array {
	$urls = [];
	if ( 'publish' === get_post_status( $product_id ) ) {
		$urls[] = get_permalink( $product_id );
	}
	foreach ( (array) wp_get_post_terms( $product_id, 'product_cat', [ 'fields' => 'ids' ] ) as $tid ) {
		$link = get_term_link( (int) $tid, 'product_cat' );
		if ( is_string( $link ) ) {
			$urls[] = $link;
		}
	}
	return array_filter( $urls );
}

// صف کردنِ گرم کردن را page-cache.php بعد از هر پاک کردن انجام می‌دهد (فهرست‌ها، محصولِ تغییرکرده، یا همه)

/** همه‌ی نشانی‌ها جز صفحه‌های محصول */
function stlh_warm_listing_urls(): array {
	return array_values( array_filter( stlh_warm_all_urls(), static fn( $u ) => ! preg_match( '#^/product/#i', rawurldecode( (string) wp_parse_url( $u, PHP_URL_PATH ) ) ) ) );
}

/**
 * یک نشانی را مثلِ بازدیدکننده‌ی ناشناس می‌گیرد. درخواست مستقیم به خودِ سرور می‌رود
 * (`CURLOPT_RESOLVE` به آی‌پیِ همین سرور)، نه دور زدن از QUIC.cloud در خارج — هدف پر کردنِ
 * کشِ LiteSpeed روی همین سرور است. اگر نشد، از راهِ عادی.
 */
function stlh_warm_fetch( string $url, string $ua ): array {
	$host = (string) wp_parse_url( $url, PHP_URL_HOST );
	$ip   = (string) ( $_SERVER['SERVER_ADDR'] ?? '' );
	$pin  = static function ( $h ) use ( $host, $ip ) {
		if ( $ip && filter_var( $ip, FILTER_VALIDATE_IP ) && defined( 'CURLOPT_RESOLVE' ) ) {
			curl_setopt( $h, CURLOPT_RESOLVE, [ $host . ':443:' . $ip, $host . ':80:' . $ip ] );
		}
	};
	$args = [ 'timeout' => 25, 'redirection' => 2, 'sslverify' => false, 'user-agent' => $ua, 'cookies' => [] ];
	$s    = microtime( true );
	add_action( 'http_api_curl', $pin );
	$res = wp_remote_get( $url, $args );
	remove_action( 'http_api_curl', $pin );
	$via = 'origin';
	if ( is_wp_error( $res ) ) {
		$res = wp_remote_get( $url, $args );
		$via = 'dns';
	}
	return [
		'url'   => str_replace( home_url(), '', $url ) ?: '/',
		'ms'    => (int) round( ( microtime( true ) - $s ) * 1000 ),
		'code'  => is_wp_error( $res ) ? $res->get_error_message() : wp_remote_retrieve_response_code( $res ),
		'cache' => is_wp_error( $res ) ? '' : (string) wp_remote_retrieve_header( $res, 'x-litespeed-cache' ),
		'via'   => $via,
	];
}

function stlh_warm_uas(): array {
	return [
		'mobile'  => 'Mozilla/5.0 (iPhone; CPU iPhone OS 17_0 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Mobile/15E148 Safari/604.1',
		'desktop' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/124 Safari/537.36',
	];
}

/** نشانی‌ها را با گوشی و دسکتاپ می‌گیرد، تا سقفِ زمان؛ برمی‌گرداند [ردیف‌ها، باقی‌مانده] */
function stlh_warm_run( array $urls, float $budget = 50 ): array {
	$t0   = microtime( true );
	$rows = [];
	$left = [];
	foreach ( array_values( $urls ) as $i => $url ) {
		if ( microtime( true ) - $t0 > $budget ) {
			$left = array_slice( array_values( $urls ), $i );
			break;
		}
		foreach ( stlh_warm_uas() as $dev => $ua ) {
			$rows[] = [ 'dev' => $dev ] + stlh_warm_fetch( $url, $ua );
		}
	}
	return [ $rows, $left ];
}

/** اجرای رویداد: صف را خالی و هر نشانی را با دو مرورگر می‌گیرد؛ خلاصه در `stlh_warm_last` */
add_action( STLH_WARM_HOOK, static function (): void {
	$q = array_values( array_unique( (array) get_option( STLH_WARM_OPT, [] ) ) );
	delete_option( STLH_WARM_OPT );
	if ( ! $q || ! stlh_warm_on() ) {
		return;
	}
	$base = stlh_warm_base();
	$q    = array_slice( array_merge( array_intersect( $base, $q ), array_diff( $q, $base ) ), 0, STLH_WARM_MAX );
	[ $rows, $left ] = stlh_warm_run( $q );
	if ( $left ) {
		stlh_warm_queue( $left );
	}
	update_option( 'stlh_warm_last', [ 'at' => gmdate( 'c' ), 'kind' => 'change', 'rows' => $rows ], false );
} );

/*
 * ─── گرم نگه داشتنِ همه‌ی صفحه‌ها (هر ساعت) ───
 * صاحب فروشگاه (مهر ۱۴۰۵): «محصولی که تا حالا باز نشده ۵–۶ ثانیه طول می‌کشد.» صفحه‌ای که
 * هیچ‌کس بعد از آخرین پاک شدنِ کش ندیده، برای اولین بازدیدکننده از صفر ساخته می‌شود.
 * پس هر ساعت همه‌ی صفحه‌های فروشگاه (اصلی، فروشگاه، همه‌ی محصولاتِ منتشرشده، دسته‌های
 * دارای محصول، برگه‌های منتشرشده) گرفته می‌شوند. صفحه‌ای که در کش است ~۰٫۱ ثانیه
 * جواب می‌دهد، پس اجرای ساعتی ارزان است؛ فقط صفحه‌های تازه ساخته می‌شوند.
 */
function stlh_warm_all_urls(): array {
	$urls = stlh_warm_base();
	foreach ( get_posts( [ 'post_type' => 'product', 'post_status' => 'publish', 'numberposts' => 300, 'fields' => 'ids', 'orderby' => 'modified', 'order' => 'DESC' ] ) as $id ) {
		$urls[] = get_permalink( $id );
	}
	$terms = get_terms( [ 'taxonomy' => 'product_cat', 'hide_empty' => true ] );
	foreach ( is_array( $terms ) ? $terms : [] as $t ) {
		$l = get_term_link( $t );
		if ( is_string( $l ) ) {
			$urls[] = $l;
		}
	}
	foreach ( get_posts( [ 'post_type' => 'page', 'post_status' => 'publish', 'numberposts' => 40, 'fields' => 'ids' ] ) as $id ) {
		$urls[] = get_permalink( $id );
	}
	$skip = array_filter( [ function_exists( 'wc_get_page_permalink' ) ? wc_get_page_permalink( 'cart' ) : '', function_exists( 'wc_get_page_permalink' ) ? wc_get_page_permalink( 'checkout' ) : '', function_exists( 'wc_get_page_permalink' ) ? wc_get_page_permalink( 'myaccount' ) : '' ] );
	return array_values( array_diff( array_unique( array_filter( $urls ) ), $skip ) );
}

add_action( 'init', static function (): void {
	if ( stlh_warm_on() && ! wp_next_scheduled( 'stlh_warm_all' ) ) {
		wp_schedule_event( time() + 60, 'hourly', 'stlh_warm_all' );
	} elseif ( ! stlh_warm_on() && wp_next_scheduled( 'stlh_warm_all' ) ) {
		wp_clear_scheduled_hook( 'stlh_warm_all' );
	}
} );

add_action( 'stlh_warm_all', static function (): void {
	if ( ! stlh_warm_on() ) {
		return;
	}
	$cursor = (array) get_option( 'stlh_warm_cursor', [] );
	$urls   = $cursor ?: stlh_warm_all_urls();
	[ $rows, $left ] = stlh_warm_run( $urls, 80 );
	update_option( 'stlh_warm_cursor', $left, false );
	if ( $left && ! wp_next_scheduled( 'stlh_warm_all_more' ) ) {
		wp_schedule_single_event( time() + 60, 'stlh_warm_all_more' );
	}
	$miss = count( array_filter( $rows, static fn( $r ) => 'hit' !== $r['cache'] ) );
	update_option( 'stlh_warm_all_last', [ 'at' => gmdate( 'c' ), 'urls' => count( $urls ), 'fetched' => count( $rows ), 'built' => $miss, 'left' => count( $left ), 'sample' => array_slice( $rows, 0, 12 ) ], false );
} );
add_action( 'stlh_warm_all_more', static fn() => do_action( 'stlh_warm_all' ) );
