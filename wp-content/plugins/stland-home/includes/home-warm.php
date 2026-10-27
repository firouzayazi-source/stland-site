<?php
/**
 * گرم کردنِ آرامِ **فقط صفحه‌ی اصلی** بعد از پاک شدنِ حافظه‌ی موقت.
 *
 * صاحب فروشگاه (۲۶ مهر ۱۴۰۵، ارتقای افزونه — بندِ سرعت). صفحه‌ی اصلی از حافظه‌ی موقت ~۰٫۰۱ ثانیه است و ساختنش از صفر
 * ~۲ تا ۳ ثانیه؛ با هر به‌روزرسانیِ محصول پاک می‌شود و اولین مشتری پولش را می‌داد. گرم‌کنِ کامل (`warm.php`) روی این هاست
 * عمداً خاموش است: ده‌ها صفحه را پشتِ سرِ هم می‌ساخت و پردازه‌های اندکِ هاست را می‌گرفت. این یکی فرق دارد:
 *
 *   • فقط صفحه‌ی اصلی، گوشی و رایانه — دو درخواست، پشتِ سرِ هم، هر کدام حداکثر ۲۰ ثانیه؛
 *   • ۹۰ ثانیه بعد از پاک شدن (چند پاک شدنِ پشتِ سرِ هم = یک بار)، و حداکثر هر ۱۰ دقیقه یک بار؛
 *   • نتیجه در `/perf` → `home_warm_last`؛ کلیدِ `home_warm` در گزینه‌ها = «0» خاموشش می‌کند.
 *
 * ۵ آبان ۱۴۰۵ (بررسیِ سرعت): بعد از صفحه‌ی اصلی، سه صفحه‌ی پربازدیدِ دیگر هم — «لیست»، پرکالاترین دسته‌ی برگ (الان
 * آیفون کارکرده) و فروشگاه — **هر کدام یک دقیقه بعد از قبلی**، باز هم دو درخواست در هر نوبت (`stlh_home_warm_more`).
 * سرعتِ صفحه‌ی سرد ۲٫۵ تا ۳ ثانیه بود و ربات‌ها هم‌زمان ده‌ها صفحه‌ی سرد را باز می‌کردند. بیشتر از این گرم نکنید.
 */

defined( 'ABSPATH' ) || exit;

const STLH_HOME_WARM_HOOK = 'stlh_home_warm';

function stlh_home_warm_schedule(): void {
	if ( PHP_SAPI === 'cli' || '0' === (string) get_option( 'stlh_home_warm', '1' ) || wp_installing() ) {
		return;
	}
	$last = (int) get_option( 'stlh_home_warm_at', 0 );
	if ( wp_next_scheduled( STLH_HOME_WARM_HOOK ) ) {
		return;
	}
	wp_schedule_single_event( max( time() + 90, $last + 600 ), STLH_HOME_WARM_HOOK );
}

foreach ( [ 'litespeed_purged_all', 'litespeed_purged_frontpage', 'litespeed_purged_front' ] as $stlh_hook ) {
	add_action( $stlh_hook, 'stlh_home_warm_schedule' );
}
add_action( 'litespeed_purge_url', static function ( $url = '' ): void {
	if ( untrailingslashit( (string) $url ) === untrailingslashit( home_url( '/' ) ) ) {
		stlh_home_warm_schedule();
	}
} );

add_action( STLH_HOME_WARM_HOOK, static function (): void {
	if ( PHP_SAPI === 'cli' || ! function_exists( 'stlh_warm_fetch' ) ) {
		return;
	}
	update_option( 'stlh_home_warm_at', time(), false );
	$rows = [];
	foreach ( stlh_warm_uas() as $dev => $ua ) {
		$r          = stlh_warm_fetch( home_url( '/' ), $ua );
		$r['dev']   = $dev;
		$rows[]     = $r;
	}
	update_option( 'stlh_home_warm_last', [ 'at' => gmdate( 'c' ), 'rows' => $rows ], false );
	wp_schedule_single_event( time() + 60, 'stlh_home_warm_more', [ 0 ] );
} );

/** صفحه‌هایی که بعد از صفحه‌ی اصلی گرم می‌شوند — کم و ثابت */
function stlh_home_warm_more_urls(): array {
	$urls = [];
	if ( function_exists( 'stlh_app_list_url' ) ) {
		$urls[] = stlh_app_list_url();
	}
	if ( function_exists( 'stlh_leaf_categories' ) ) {
		$leaves = stlh_leaf_categories( false );
		usort( $leaves, static fn( $a, $b ) => (int) $b->count <=> (int) $a->count );
		$link = $leaves ? get_term_link( $leaves[0] ) : '';
		if ( is_string( $link ) && $link ) {
			$urls[] = $link;
		}
	}
	if ( function_exists( 'wc_get_page_permalink' ) ) {
		$urls[] = wc_get_page_permalink( 'shop' );
	}
	return array_values( array_unique( array_filter( $urls ) ) );
}

add_action( 'stlh_home_warm_more', static function ( $i = 0 ): void {
	$urls = stlh_home_warm_more_urls();
	$i    = (int) $i;
	if ( PHP_SAPI === 'cli' || ! isset( $urls[ $i ] ) || ! function_exists( 'stlh_warm_fetch' ) ) {
		return;
	}
	$last = (array) get_option( 'stlh_home_warm_last', [] );
	foreach ( stlh_warm_uas() as $dev => $ua ) {
		$r                = stlh_warm_fetch( $urls[ $i ], $ua );
		$r['dev']         = $dev;
		$last['rows'][]   = $r;
	}
	update_option( 'stlh_home_warm_last', $last, false );
	if ( isset( $urls[ $i + 1 ] ) ) {
		wp_schedule_single_event( time() + 60, 'stlh_home_warm_more', [ $i + 1 ] );
	}
} );
