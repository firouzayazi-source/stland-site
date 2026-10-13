<?php
/**
 * نگهبانِ قالب در برابرِ پیش‌فرض‌های نسخه‌های تازه‌ی باکالا (۱۰.۴، مهر ۱۴۰۵).
 *
 * ۱. پاپ‌آپِ «پیشنهاد ویژه» با کدِ نمایشیِ `BAKALA30` بعد از به‌روزرسانی خودبه‌خود روشن شد —
 *    کدی که در فروشگاه وجود ندارد و نامِ قالب را جلوی مشتری می‌آورد (صاحب فروشگاه: هیچ اسمی
 *    از باکالا). اگر نوعِ پاپ‌آپ «کد تخفیف» است و آن کد در ووکامرس تعریف نشده، در صفحه‌ی
 *    مشتری خاموش می‌شود؛ پیشخوان و تنظیمات دست نمی‌خورند.
 * ۳. سربرگِ کامپیوتر در پنجره‌ی باریک (آیپدِ عمودی، پنجره‌ی کوچک‌شده): باکالا گوشی را با User-Agent
 *    تشخیص می‌دهد نه با عرض، و سربرگِ کامپیوتر `min-width: 1009px` دارد — جستجو و منو بیرون از صفحه
 *    می‌افتادند و نیمی از منو زیرِ بنر می‌رفت (صاحب فروشگاه با عکس، ۲۱ مهر ۱۴۰۵). زیرِ ۱۰۰۸ پیکسل:
 *    سربرگ هم‌عرضِ صفحه، لوگو و جستجو یک ردیف، منو چندخطی. سربرگِ گوشی (بی `.site-header`) دست نمی‌خورد.
 *    در ۳۸۰/۴۸۰/۶۰۰/۸۲۰ با `tests/visual/live-cache.mjs` دیده شد.
 * ۴. پیوندِ «تماس» با رقمِ فارسی (`tel:۰۹۱۲…` از شماره‌ی پانویس و سربرگِ گوشی) را همه‌ی گوشی‌ها
 *    شماره‌گیری نمی‌کنند؛ رقم‌ها در `href` لاتین می‌شوند، متنِ نمایشی فارسی می‌ماند.
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

const STLH_NARROW_HEADER_CSS = '@media (max-width:1008px){ .site-header{min-width:0!important;width:100%!important} .site-header .container-bakala{max-width:100%!important} .site-header .container-bakala.not-logged-in>.row{padding-left:12px!important;padding-right:12px!important;row-gap:8px} .site-header .header-logo{min-width:0!important;width:auto!important;flex:0 0 auto} .site-header .header-logo img{max-height:40px;width:auto} .site-header .navbar-search{min-width:0!important;flex:1 1 auto} .site-header #navbar-primary-fixed .main-menu-div{padding-left:8px!important;padding-right:8px!important} .site-header #navbar-primary-fixed,.site-header #navbar-primary-fixed .main-menu-div{height:auto!important} .site-header #navbar-primary-fixed .bk_menu{min-width:0!important;flex:1 1 auto;height:auto!important} .site-header #navbar-primary-fixed .bk_menu>.bakala{height:auto!important;flex-wrap:wrap} .site-header #navbar-primary-fixed .bk_menu>.bakala>ul{position:static!important;display:flex!important;flex-wrap:wrap!important;row-gap:2px;width:100%} } @media (max-width:767px){ .site-header .header-icons{justify-content:space-between!important;padding:0 12px!important} .site-header #navbar-primary-fixed .header-call{display:none!important} }';

add_action( 'wp_head', static function (): void {
	echo "<style id=\"stlh-guard\">html,body{overflow-x:clip}" . STLH_NARROW_HEADER_CSS . "</style>\n";
}, 2 );

add_action( 'wp_footer', static function (): void {
	echo "<script id=\"stlh-tel\">document.querySelectorAll('a[href^=\"tel:\"]').forEach(function(a){var h=a.getAttribute('href'),n=h.replace(/[\\u06F0-\\u06F9]/g,function(d){return d.charCodeAt(0)-1776}).replace(/[\\u0660-\\u0669]/g,function(d){return d.charCodeAt(0)-1632});if(n!==h)a.setAttribute('href',n)});</script>\n";
}, 99 );
