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
} );
