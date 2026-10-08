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
const STLH_WARM_MAX   = 12;

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
	update_option( STLH_WARM_OPT, array_slice( $q, 0, 40 ), false );
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

add_action( 'stlh_cache_flushed', static fn() => stlh_warm_queue( stlh_warm_base() ) );
foreach ( [ 'woocommerce_new_product', 'woocommerce_update_product' ] as $stlh_h ) {
	add_action( $stlh_h, static function ( $id ): void {
		stlh_warm_queue( array_merge( stlh_warm_base(), stlh_warm_product_urls( (int) $id ) ) );
	}, 20, 1 );
}
unset( $stlh_h );

/** اجرای رویداد: صف را خالی و هر نشانی را با دو مرورگر می‌گیرد؛ خلاصه در `stlh_warm_last` */
add_action( STLH_WARM_HOOK, static function (): void {
	$q = array_values( array_unique( (array) get_option( STLH_WARM_OPT, [] ) ) );
	delete_option( STLH_WARM_OPT );
	if ( ! $q || ! stlh_warm_on() ) {
		return;
	}
	// صفحه‌ی اصلی و فروشگاه اول، بعد بقیه؛ سقفِ تعداد
	$base = stlh_warm_base();
	$q    = array_slice( array_merge( array_intersect( $base, $q ), array_diff( $q, $base ) ), 0, STLH_WARM_MAX );
	$uas  = [
		'mobile'  => 'Mozilla/5.0 (iPhone; CPU iPhone OS 17_0 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Mobile/15E148 Safari/604.1 stlh-warm',
		'desktop' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/124 Safari/537.36 stlh-warm',
	];
	$t0   = microtime( true );
	$rows = [];
	foreach ( $q as $url ) {
		foreach ( $uas as $dev => $ua ) {
			if ( microtime( true ) - $t0 > 50 ) {
				break 2; // بقیه با پاک شدنِ بعدی
			}
			$s   = microtime( true );
			$res = wp_remote_get( $url, [ 'timeout' => 25, 'redirection' => 2, 'sslverify' => false, 'user-agent' => $ua, 'cookies' => [], 'headers' => [ 'Accept-Encoding' => 'gzip' ] ] );
			$rows[] = [
				'url'   => str_replace( home_url(), '', $url ) ?: '/',
				'dev'   => $dev,
				'ms'    => (int) round( ( microtime( true ) - $s ) * 1000 ),
				'code'  => is_wp_error( $res ) ? $res->get_error_message() : wp_remote_retrieve_response_code( $res ),
				'cache' => is_wp_error( $res ) ? '' : (string) wp_remote_retrieve_header( $res, 'x-litespeed-cache' ),
			];
		}
	}
	update_option( 'stlh_warm_last', [ 'at' => gmdate( 'c' ), 'rows' => $rows ], false );
} );
