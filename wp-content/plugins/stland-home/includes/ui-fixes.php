<?php
/**
 * دو اصلاحِ کوچکِ ظاهری (صاحب فروشگاه، ۲۵ آبان ۱۴۰۵، با عکس).
 *
 * ۱) «پرداخت موفق.» روزِ بعد بالای صفحه‌ی دسته: درگاهِ سامان بعد از پرداخت این پیام را در جلسه‌ی خریدار می‌گذارد، ولی
 *    برگه‌ی تشکرِ باکالا پیام‌ها را چاپ نمی‌کند؛ پس پیام می‌ماند و در اولین صفحه‌ی فروشگاهِ بعدی (حتی فردا) دیده می‌شد و
 *    مشتری فکر می‌کرد دوباره پول داده. برگه‌ی تشکر خودش «سفارش شما دریافت شد» را می‌گوید، پس پیام‌های موفقیتِ مانده همان‌جا پاک می‌شوند.
 * ۲) نوارِ بالای پیشخوان در گوشی: لوگوی سایت روی نامِ «استوک لند» می‌افتاد. در گوشی فقط آیکن بماند (مثلِ خودِ وردپرس).
 */
defined( 'ABSPATH' ) || exit;

add_action( 'woocommerce_thankyou', static function (): void {
	if ( ! function_exists( 'WC' ) || ! WC()->session ) {
		return;
	}
	$all = (array) WC()->session->get( 'wc_notices', [] );
	if ( ! empty( $all['success'] ) ) {
		unset( $all['success'] );
		WC()->session->set( 'wc_notices', $all );
	}
}, 1 );

function stlh_adminbar_fix_css(): string {
	return '@media screen and (max-width:782px){'
		. '#wpadminbar #wp-admin-bar-site-name>.ab-item{font-size:0!important;width:52px!important;padding:0!important;overflow:hidden!important;white-space:nowrap!important;text-indent:0!important;display:flex!important;align-items:center;justify-content:center}'
		. '#wpadminbar #wp-admin-bar-site-name>.ab-item img{position:static!important;width:26px!important;height:26px!important;margin:0!important;border-radius:6px}'
		. '#wpadminbar #wp-admin-bar-site-name>.ab-item::before{font-size:32px!important;margin:0!important}'
		. '#wpadminbar #wp-admin-bar-site-name>.ab-item:has(img)::before{display:none!important}'
		. '}';
}
add_action( 'admin_head', static function (): void {
	echo '<style id="stlh-adminbar">' . stlh_adminbar_fix_css() . "</style>\n"; // phpcs:ignore -- CSSِ ثابت
} );
add_action( 'wp_head', static function (): void {
	if ( is_admin_bar_showing() ) {
		echo '<style id="stlh-adminbar">' . stlh_adminbar_fix_css() . "</style>\n"; // phpcs:ignore -- CSSِ ثابت
	}
} );
