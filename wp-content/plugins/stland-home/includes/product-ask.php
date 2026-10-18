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
		$extra .= stlh_ask_card( 'ti', stlh_tradein_url(), 'ask_tradein', 'ask_tradein_sub', '<path d="M17 2.5l3.5 3.5L17 9.5"/><path d="M3.5 11V9.5A3.5 3.5 0 0 1 7 6h13.5"/><path d="M7 21.5 3.5 18 7 14.5"/><path d="M20.5 13v1.5A3.5 3.5 0 0 1 17 18H3.5"/>' );
	}
	if ( $phone && '' !== get_post_meta( $p->get_id(), 'condition', true ) && function_exists( 'stlh_compare_url' ) ) {
		$extra .= stlh_ask_card( 'cmp', stlh_compare_url( $p->get_id() ), 'ask_compare', 'ask_compare_sub', '<rect x="3" y="4" width="7" height="16" rx="2"/><rect x="14" y="4" width="7" height="16" rx="2"/><path d="M6.5 8h0M17.5 8h0M5.5 16h2M16.5 16h2"/>' );
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

/** دکمه‌ی کارتیِ زیرِ کادرِ «بپرسید» — همان کاشیِ گرادیان و آیکنِ سفیدِ بقیه‌ی سایت، با فلشِ «برو» */
function stlh_ask_card( string $mod, string $url, string $title, string $sub, string $path ): string {
	return '<a class="stlh-ask__card stlh-ask__card--' . esc_attr( $mod ) . '" href="' . esc_url( $url ) . '">'
		. '<span class="stlh-ask__card-ico" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round">' . $path . '</svg></span>'
		. '<span class="stlh-ask__card-txt"><b>' . esc_html( stlh_label( $title ) ) . '</b><small>' . esc_html( stlh_label( $sub ) ) . '</small></span>'
		// فلشِ «برو» در راست‌به‌چپ رو به چپ؛ SVG، نه نویسه‌ی «‹» که صاحب فروشگاه آن را باگ دید (۲۷ مهر)
		. '<span class="stlh-ask__card-go" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="M14.5 6 8.5 12l6 6"/></svg></span></a>';
}

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
		// دکمه `flex` است و فاصله‌ی «تماس» و `<span dir="ltr">`ِ شماره (`phone-ltr.php`) را می‌خورد: «تماس۰۹۱۲»
		. '.stlh-ask__btns a{gap:.35em}'
		. '.stlh-ask__card{--g1:#7c3aed;--g2:#a78bfa;--tint:#f5f1ff;--line:#e9e1ff;display:flex;align-items:center;gap:12px;margin:10px 0 0;padding:12px 14px;border-radius:14px;background:var(--tint);border:1px solid var(--line);color:#1f2133!important;text-decoration:none!important;transition:transform .15s,box-shadow .15s}'
		. '.stlh-ask__card--cmp{--g1:#0f766e;--g2:#2dd4bf;--tint:#ecfbf8;--line:#cdf3ec}'
		. '.stlh-ask__card:hover{transform:translateY(-1px);box-shadow:0 8px 20px -10px var(--g1)}'
		. '.stlh-ask__card-ico{flex:0 0 42px;width:42px;height:42px;border-radius:12px;display:flex;align-items:center;justify-content:center;background:linear-gradient(140deg,var(--g2),var(--g1));color:#fff;box-shadow:0 6px 14px -8px var(--g1)}.stlh-ask__card-ico svg{width:22px;height:22px}'
		. '.stlh-ask__card-txt{flex:1 1 auto;min-width:0;display:flex;flex-direction:column;gap:2px}.stlh-ask__card-txt b{font-size:14px;font-weight:800}.stlh-ask__card-txt small{font-size:12px;color:#5b5f6b;line-height:1.6}'
		. '.stlh-ask__card-go{flex:0 0 28px;width:28px;height:28px;border-radius:50%;display:flex;align-items:center;justify-content:center;background:#fff;color:var(--g1)}.stlh-ask__card-go svg{width:16px;height:16px}'
		// کادرِ هشدارِ همتا با آیکنِ کاشی (`product-alert.php`)
		. '.stlh-alert .alert-box-wrap{display:flex;align-items:center;gap:12px}.stlh-alert-ico{flex:0 0 36px;width:36px;height:36px;border-radius:11px;display:flex;align-items:center;justify-content:center;color:#fff;background:linear-gradient(140deg,#34c38f,#0e9f6e);box-shadow:0 6px 14px -8px #0e9f6e}.stlh-alert-ico svg{width:20px;height:20px}'
		. '.stlh-alert-ico.is-warn{background:linear-gradient(140deg,#f7b733,#e08a00);box-shadow:0 6px 14px -8px #e08a00}'
		// کادرِ «تیمِ پشتیبانی»ِ قالب: فقط عکس، بی نام و شماره — جایش همین کادر است (`product-alert.php`)
		. '#bakala-support-widget{display:none!important}';
}
