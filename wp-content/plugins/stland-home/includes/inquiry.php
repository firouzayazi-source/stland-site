<?php
/**
 * «استعلامِ موجودی» برای کالای امانی (قرارداد ۷؛ درخواستِ نشستِ Novo، ۳۰ آبان ۱۴۰۵ — صاحب فروشگاه، ۲۹ آبان: «کالای امانی
 * (گوشیِ همکار، آک یا کارکرده) ممکن است در فروشگاه نباشد؛ مشتری نباید بی‌هماهنگی بخرد — همه‌ی امانی‌ها پیش از خرید تأیید و
 * استعلامِ موجودی بگیرند»).
 *
 * Novo روی هر محصول متای `stl_inquiry` را می‌فرستد: `'1'` = استعلام لازم، `''` یا نبودن = خریدِ مستقیم مثلِ قبل. تصمیم کلاً در
 * Novo است؛ این‌جا فقط خوانده می‌شود (مالکیتِ داده).
 *  • سمتِ سرور: `woocommerce_is_purchasable` = false — افزودن به سبد با نشانیِ مستقیم (`?add-to-cart=`) هم ممکن نیست؛ قیمت می‌ماند.
 *  • صفحه‌ی محصول: کادرِ «استعلامِ موجودی» با واتس‌اپ (پیامِ آماده با نام و نشانیِ کالا) و تماس، جای دکمه‌ی خرید.
 *  • کارت‌ها: نشانِ «استعلام موجودی» (`card-badges.php`)؛ دکمه‌ی فهرستِ فروشگاه‌ساز «استعلام موجودی» و به صفحه‌ی کالا.
 */
defined( 'ABSPATH' ) || exit;

function stlh_is_inquiry( $p ): bool {
	$p = is_numeric( $p ) ? wc_get_product( (int) $p ) : $p;
	if ( ! $p instanceof WC_Product ) {
		return false;
	}
	$id = ( method_exists( $p, 'get_parent_id' ) ? $p->get_parent_id() : 0 ) ?: $p->get_id();
	return '1' === trim( (string) get_post_meta( $id, 'stl_inquiry', true ) );
}

add_filter( 'woocommerce_is_purchasable', static function ( $ok, $p ) {
	return ( $ok && stlh_is_inquiry( $p ) ) ? false : $ok;
}, 50, 2 );
add_filter( 'woocommerce_variation_is_purchasable', static function ( $ok, $p ) {
	return ( $ok && stlh_is_inquiry( $p ) ) ? false : $ok;
}, 50, 2 );

// دکمه‌ی فهرست‌های فروشگاه‌ساز (دسته، جست‌وجو): «استعلام موجودی» و پیوند به صفحه‌ی کالا
add_filter( 'woocommerce_product_add_to_cart_text', static function ( $text, $p ) {
	return stlh_is_inquiry( $p ) ? stlh_label( 'inquiry_btn' ) : $text;
}, 50, 2 );
add_filter( 'woocommerce_loop_add_to_cart_link', static function ( $html, $p ) {
	if ( ! stlh_is_inquiry( $p ) ) {
		return $html;
	}
	return '<a class="button stlh-inq-loop" href="' . esc_url( $p->get_permalink() ) . '">' . esc_html( stlh_label( 'inquiry_btn' ) ) . '</a>';
}, 50, 2 );

/** کادرِ صفحه‌ی محصول */
function stlh_inquiry_box( WC_Product $p ): string {
	$f    = stlh_store_facts();
	$msg  = stlh_label( 'inquiry_wa', [ '{کالا}' => $p->get_name(), '{نشانی}' => $p->get_permalink() ] );
	$tel  = preg_replace( '/\D+/', '', (string) ( $f['phone'] ?? '' ) );
	return '<div class="stlh-inq" dir="rtl" style="margin:14px 0;padding:14px;border:1.5px solid #f59e0b;border-radius:14px;background:#fffbeb;line-height:1.8;text-align:right">'
		. '<div style="font-weight:900;color:#92400e;font-size:15px;margin-bottom:4px">' . esc_html( stlh_label( 'inquiry_title' ) ) . '</div>'
		. '<div style="color:#78350f;font-size:13px;margin-bottom:10px">' . esc_html( stlh_label( 'inquiry_text' ) ) . '</div>'
		. '<div style="display:flex;gap:8px;flex-wrap:wrap">'
		. '<a href="' . esc_url( stlh_wa_link( $msg ) ) . '" target="_blank" rel="noopener" style="flex:1 1 140px;text-align:center;background:#16a34a;color:#fff;border-radius:12px;padding:11px 12px;font-weight:800;text-decoration:none">' . esc_html( stlh_label( 'inquiry_btn' ) ) . '</a>'
		. ( '' !== $tel ? '<a href="tel:' . esc_attr( $tel ) . '" style="flex:1 1 120px;text-align:center;background:#fff;color:#92400e;border:1.5px solid #f59e0b;border-radius:12px;padding:10px 12px;font-weight:800;text-decoration:none">' . esc_html( stlh_label( 'inquiry_call' ) ) . ' <span dir="ltr">' . esc_html( (string) ( $f['phone_fa'] ?? '' ) ) . '</span></a>' : '' )
		. '</div></div>';
}

/** جای دکمه‌ی خرید (قالب برای کالای خریدنی‌نشدنی فرم نمی‌سازد)؛ اگر قالب این قلاب را صدا نزد، پایینِ صفحه‌ی محصول */
function stlh_inquiry_print(): void {
	static $done = false;
	global $product;
	$p = $product instanceof WC_Product ? $product : ( function_exists( 'wc_get_product' ) ? wc_get_product( get_the_ID() ) : null );
	if ( $done || ! $p instanceof WC_Product || ! stlh_is_inquiry( $p ) ) {
		return;
	}
	$done = true;
	echo stlh_inquiry_box( $p ); // phpcs:ignore WordPress.Security.EscapeOutput -- escaped inside
}
add_action( 'woocommerce_single_product_summary', 'stlh_inquiry_print', 31 );
add_action( 'woocommerce_single_product_end', 'stlh_inquiry_print', 1 );
