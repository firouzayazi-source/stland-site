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
 *
 * صاحب فروشگاه (۲۶ مهر، با عکسِ صفحه‌ی کابل): معاوضه فقط زیرِ گوشی‌ها و به شکلِ یک دکمه‌ی درست، نه لینکِ ریز؛ در لوازم
 * معنی ندارد. پس در صفحه‌ی غیرِ گوشی عنوان «همین کالا» است و معاوضه نمی‌آید (`stlh_is_phone_product`).
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

/** گوشی است؟ — حسابداری برای گوشی «رجیستری» می‌فرستد؛ وگرنه از روی دسته‌ها (همان نگاشتِ آیکنِ دسته) */
function stlh_is_phone_product( int $id ): bool {
	if ( '' !== trim( (string) get_post_meta( $id, 'registry', true ) ) ) {
		return true;
	}
	$terms = get_the_terms( $id, 'product_cat' );
	foreach ( is_array( $terms ) ? $terms : [] as $t ) {
		if ( in_array( stlh_cat_icon_key( rawurldecode( $t->slug ), $t->name ), [ 'phone-new', 'phone-used' ], true ) ) {
			return true;
		}
	}
	return false;
}

add_action( 'woocommerce_single_product_end', static function (): void {
	$p = function_exists( 'wc_get_product' ) ? wc_get_product( get_the_ID() ) : null;
	if ( ! $p || '' === stlh_wa_number() ) {
		return;
	}
	$f     = stlh_store_facts();
	$msg   = stlh_label( 'ask_msg', [ '{نام}' => $p->get_name(), '{قیمت}' => stlh_price_text( $p ), '{نشانی}' => (string) get_permalink( $p->get_id() ) ] );
	$phone = stlh_is_phone_product( $p->get_id() );
	$extra = '';
	if ( $phone && function_exists( 'stlh_tradein_url' ) ) {
		$extra .= '<a class="stlh-ask__ti" href="' . esc_url( stlh_tradein_url() ) . '"><span class="stlh-ask__ti-ico" aria-hidden="true">'
			. '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17 2l4 4-4 4"/><path d="M3 11V9a3 3 0 0 1 3-3h15"/><path d="M7 22l-4-4 4-4"/><path d="M21 13v2a3 3 0 0 1-3 3H3"/></svg>'
			. '</span><span class="stlh-ask__ti-txt"><b>' . esc_html( stlh_label( 'ask_tradein' ) ) . '</b><small>' . esc_html( stlh_label( 'ask_tradein_sub' ) ) . '</small></span>'
			. '<span class="stlh-ask__ti-go" aria-hidden="true">‹</span></a>';
	}
	if ( $phone && '' !== get_post_meta( $p->get_id(), 'condition', true ) && function_exists( 'stlh_compare_url' ) ) {
		$extra .= '<p class="stlh-ask__links"><a href="' . esc_url( stlh_compare_url( $p->get_id() ) ) . '">' . esc_html( stlh_label( 'ask_compare' ) ) . '</a></p>';
	}
	printf(
		'<section class="stlh-ask" aria-label="%1$s"><h3>%1$s</h3><div class="stlh-ask__btns"><a class="stlh-ask__wa" href="%2$s" target="_blank" rel="noopener">%3$s</a><a class="stlh-ask__call" href="tel:%4$s">%5$s</a></div>%6$s</section>',
		esc_html( stlh_label( $phone ? 'ask_title' : 'ask_title_item' ) ),
		esc_url( stlh_wa_link( $msg ) ),
		esc_html( stlh_label( 'ask_whatsapp' ) ),
		esc_attr( $f['phone'] ?? '' ),
		esc_html( stlh_label( 'ask_call' ) . ' ' . ( $f['phone_fa'] ?? '' ) ),
		$extra // phpcs:ignore — بخش‌ها escape شده‌اند
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
		. '.stlh-ask__links{margin:10px 0 0;font-size:13px;display:flex;gap:8px;flex-wrap:wrap;color:#8e8e93}.stlh-ask__links a{color:#0a63ff;font-weight:700}'
		. '.stlh-ask__ti{display:flex;align-items:center;gap:12px;margin:10px 0 0;padding:12px 14px;border-radius:14px;background:linear-gradient(135deg,#eef4ff,#f5f0ff);border:1px solid #dfe8ff;color:#1f2133!important;text-decoration:none!important;transition:transform .15s,box-shadow .15s}'
		. '.stlh-ask__ti:hover{transform:translateY(-1px);box-shadow:0 6px 18px rgba(10,99,255,.12)}'
		. '.stlh-ask__ti-ico{flex:0 0 40px;width:40px;height:40px;border-radius:12px;display:flex;align-items:center;justify-content:center;background:linear-gradient(135deg,#0a63ff,#7c3aed);color:#fff}.stlh-ask__ti-ico svg{width:22px;height:22px}'
		. '.stlh-ask__ti-txt{flex:1 1 auto;min-width:0;display:flex;flex-direction:column;gap:2px}.stlh-ask__ti-txt b{font-size:14px;font-weight:800}.stlh-ask__ti-txt small{font-size:12px;color:#5b5f6b;line-height:1.6}'
		// کادرِ «تیمِ پشتیبانی»ِ قالب: فقط عکس، بی نام و شماره — جایش همین کادر است (`product-alert.php`)
		. '#bakala-support-widget{display:none!important}'
		. '.stlh-ask__ti-go{flex:0 0 auto;font-size:22px;color:#0a63ff;font-weight:800}';
}
