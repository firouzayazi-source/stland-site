<?php
/**
 * نگهبانِ قالب در برابرِ پیش‌فرض‌های نسخه‌های تازه‌ی باکالا (۱۰.۴، مهر ۱۴۰۵).
 *
 * ۱. پاپ‌آپِ «پیشنهاد ویژه» با کدِ نمایشیِ `BAKALA30` بعد از به‌روزرسانی خودبه‌خود روشن شد —
 *    کدی که در فروشگاه وجود ندارد و نامِ قالب را جلوی مشتری می‌آورد (صاحب فروشگاه: هیچ اسمی
 *    از باکالا). اگر نوعِ پاپ‌آپ «کد تخفیف» است و آن کد در ووکامرس تعریف نشده، در صفحه‌ی
 *    مشتری خاموش می‌شود؛ پیشخوان و تنظیمات دست نمی‌خورند.
 * ۲. منوی تب‌دارِ تازه‌ی قالب (`.bakala-tab-menu-items`) پنهان ولی بیرون از صفحه می‌ماند و در
 *    دسکتاپ اسکرولِ افقی می‌سازد (scrollWidth ۱۵۲۴ در پنجره‌ی ۱۳۶۶). `overflow-x: clip`
 *    برخلافِ `hidden` چسبندگیِ سربرگ (sticky) را نمی‌شکند.
 */
defined( 'ABSPATH' ) || exit;

add_filter( 'option_bakala_options', static function ( $opts ) {
	if ( is_admin() || ! is_array( $opts ) || empty( $opts['bk_popup_enable'] ) || 'coupon' !== ( $opts['bk_popup_type'] ?? '' ) ) {
		return $opts;
	}
	static $exists = null;
	if ( null === $exists ) {
		$code   = trim( (string) ( $opts['bk_popup_coupon_code'] ?? '' ) );
		$exists = '' !== $code && function_exists( 'wc_get_coupon_id_by_code' ) && wc_get_coupon_id_by_code( $code ) > 0;
	}
	if ( ! $exists ) {
		$opts['bk_popup_enable'] = '0';
	}
	return $opts;
} );

add_action( 'wp_head', static function (): void {
	echo "<style id=\"stlh-guard\">html,body{overflow-x:clip}</style>\n";
}, 2 );
