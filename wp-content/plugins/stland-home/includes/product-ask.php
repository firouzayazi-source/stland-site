<?php
/**
 * کادرِ «درباره‌ی همین گوشی بپرسید» در صفحه‌ی هر محصول.
 *
 * صاحب فروشگاه (۲۶ مهر ۱۴۰۵، ارتقای افزونه): مشتری برای پرسیدن باید نامِ گوشی را دوباره تایپ می‌کرد و فروشنده
 * نمی‌دانست سؤال درباره‌ی کدام دستگاه است. حالا یک دکمه واتس‌اپ را با پیامِ آماده باز می‌کند (نام، قیمت و نشانیِ همان
 * صفحه)، دکمه‌ی دیگر تماس است، و دو لینک به «مقایسه با گوشیِ دیگر» (`compare.php`) و «معاوضه‌ی گوشیِ قدیمی»
 * (`trade-in.php`). شماره از `stlh_store_facts()`؛ متن‌ها از «نام‌ها و متن‌ها».
 *
 * جا: آخرِ ستونِ خلاصه، درست بالای دفترچه‌ی سلامت (`woocommerce_single_product_end`، اولویتِ ۹۸).
 */

defined( 'ABSPATH' ) || exit;

/** ۰۹۱۲… → ۹۸۹۱۲… برای نشانیِ واتس‌اپ */
function stlh_wa_number(): string {
	return ltrim( stlh_intl_phone( stlh_store_facts()['phone'] ?? '' ), '+' );
}

function stlh_wa_link( string $text ): string {
	return 'https://wa.me/' . stlh_wa_number() . '?text=' . rawurlencode( $text );
}

/** قیمت به شکلِ «۱۰۵٬۰۰۰٬۰۰۰ تومان»، یا «تماس بگیرید» */
function stlh_price_text( WC_Product $p ): string {
	$price = (float) $p->get_price();
	return $price > 0 ? stlh_fa( number_format( $price, 0, '.', '٬' ) ) . ' تومان' : stlh_label( 'call' );
}

add_action( 'woocommerce_single_product_end', static function (): void {
	$p = function_exists( 'wc_get_product' ) ? wc_get_product( get_the_ID() ) : null;
	if ( ! $p || '' === stlh_wa_number() ) {
		return;
	}
	$f     = stlh_store_facts();
	$msg   = stlh_label( 'ask_msg', [ '{نام}' => $p->get_name(), '{قیمت}' => stlh_price_text( $p ), '{نشانی}' => (string) get_permalink( $p->get_id() ) ] );
	$links = [];
	if ( '' !== get_post_meta( $p->get_id(), 'condition', true ) && function_exists( 'stlh_compare_url' ) ) {
		$links[] = '<a href="' . esc_url( stlh_compare_url( $p->get_id() ) ) . '">' . esc_html( stlh_label( 'ask_compare' ) ) . '</a>';
	}
	if ( function_exists( 'stlh_tradein_url' ) ) {
		$links[] = '<a href="' . esc_url( stlh_tradein_url() ) . '">' . esc_html( stlh_label( 'ask_tradein' ) ) . '</a>';
	}
	printf(
		'<section class="stlh-ask" aria-label="%1$s"><h3>%1$s</h3><div class="stlh-ask__btns"><a class="stlh-ask__wa" href="%2$s" target="_blank" rel="noopener">%3$s</a><a class="stlh-ask__call" href="tel:%4$s">%5$s</a></div>%6$s</section>',
		esc_html( stlh_label( 'ask_title' ) ),
		esc_url( stlh_wa_link( $msg ) ),
		esc_html( stlh_label( 'ask_whatsapp' ) ),
		esc_attr( $f['phone'] ?? '' ),
		esc_html( stlh_label( 'ask_call' ) . ' ' . ( $f['phone_fa'] ?? '' ) ),
		$links ? '<p class="stlh-ask__links">' . implode( '<span aria-hidden="true">·</span>', $links ) . '</p>' : '' // phpcs:ignore — لینک‌ها escape شده‌اند
	);
}, 98 );

add_action( 'wp_enqueue_scripts', static function (): void {
	if ( ! function_exists( 'is_product' ) || ! is_product() ) {
		return;
	}
	wp_add_inline_style( 'stland-product', stlh_ask_css() );
}, 21 );

function stlh_ask_css(): string {
	return '.stlh-ask{direction:rtl;margin:16px 0 8px;padding:14px 16px;border:1px solid #eef0f3;border-radius:16px;background:#fff;box-sizing:border-box;width:100%}'
		. '.stlh-ask h3{margin:0 0 10px;font-size:15px;font-weight:800;color:#1f2133}'
		. '.stlh-ask__btns{display:flex;gap:8px;flex-wrap:wrap}'
		. '.stlh-ask__btns a{flex:1 1 140px;display:flex;align-items:center;justify-content:center;min-height:44px;border-radius:12px;font-weight:800;font-size:14px;text-decoration:none!important}'
		. '.stlh-ask__wa{background:#1fa855;color:#fff!important}.stlh-ask__call{background:#f2f3f7;color:#1f2133!important}'
		. '.stlh-ask__links{margin:10px 0 0;font-size:13px;display:flex;gap:8px;flex-wrap:wrap;color:#8e8e93}.stlh-ask__links a{color:#0a63ff;font-weight:700}';
}
