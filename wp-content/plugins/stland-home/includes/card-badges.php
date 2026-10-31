<?php
/**
 * نشان‌های روی عکسِ کارت — کوچک، گوشه‌ای، با آیکن.
 *
 * صاحب فروشگاه (۱۵ مهر ۱۴۰۵): «رجیستری و درصدِ باتری عالیه ولی جای خوبی نیست،
 * تصویر را شلوغ کرده … یک گزینه‌ی دیگر هم کنارشان: تصویر از خودِ محصول … شکیل‌تر،
 * کوچک‌تر و حرفه‌ای‌تر.» پیش‌تر دو قرصِ پهنِ «باتری ۸۹٪» و «ریجستری شده» وسطِ بالای
 * عکس می‌نشستند. حالا ستونی از قرص‌های ریز در گوشه‌ی بالا، جایی که عکسِ گوشی خالی است:
 *  • باتری — آیکنِ باتری با پرشدگیِ همان درصد، سبز/کهربایی/قرمز (۹۰ و ۸۵ همان مرزهای
 *    «قوت و ضعف» در حسابداری)؛
 *  • رجیستری — سپرِ تیک‌دار؛ «بدون رجیستر» کهربایی؛
 *  • «عکس واقعی» — متای `real_photo` (قرارداد ۵) که حسابداری برای گوشیِ تکی با گالریِ
 *    خودش می‌فرستد.
 * کلیدها همان «نشان‌های کارت» در تنظیمات‌اند؛ `real_photo` اگر آنجا نباشد هم می‌آید.
 */
defined( 'ABSPATH' ) || exit;

/** @return array<int, array{key:string, text:string, tone:string, title:string, level?:int}> */
function stlh_card_badge_items( WC_Product $p, int $max = 3 ): array {
	$lines = stlh_lines( (string) stlh_opt( 'card_badges' ) );
	$keys  = [];
	foreach ( $lines as $line ) {
		[ $key, $label, $suffix ] = array_pad( array_map( 'trim', explode( '|', $line ) ), 3, '' );
		if ( '' !== $key ) {
			$keys[ $key ] = [ $label, $suffix ];
		}
	}
	$keys += [ 'real_photo' => [ 'عکس از خودِ محصول', '' ] ]; // صاحب فروشگاه (۹ آبان): «اول بنویسد عکس از خودِ محصول»

	$out = [];
	foreach ( $keys as $key => [ $label, $suffix ] ) {
		$val = trim( wp_strip_all_tags( (string) $p->get_attribute( $key ) ) );
		if ( '' === $val ) {
			$meta = $p->get_meta( $key );
			$val  = is_scalar( $meta ) ? trim( wp_strip_all_tags( (string) $meta ) ) : '';
		}
		if ( '' === $val ) {
			continue;
		}
		if ( 'battery' === $key ) {
			$pct   = max( 0, min( 100, (int) $val ) );
			$out[] = [ 'key' => 'battery', 'text' => stlh_fa( (string) $pct ) . '٪', 'tone' => $pct >= 90 ? 'good' : ( $pct >= 85 ? 'mid' : 'low' ), 'title' => 'سلامتِ باتری ' . stlh_fa( (string) $pct ) . '٪', 'level' => $pct ];
		} elseif ( 'registry' === $key ) {
			$ok    = false === mb_strpos( $val, 'نشده' );
			$out[] = [ 'key' => 'registry', 'text' => $ok ? 'رجیستر' : 'بدون رجیستر', 'tone' => $ok ? 'good' : 'mid', 'title' => $ok ? 'رجیستر شده' : 'رجیستر نشده' ];
		} elseif ( 'real_photo' === $key ) {
			if ( '1' === $val ) {
				$out[] = [ 'key' => 'photo', 'text' => $label ?: 'عکس واقعی', 'tone' => 'info', 'title' => 'عکس‌ها از خودِ همین گوشی است' ];
			}
		} else {
			$text  = trim( $label . ' ' . stlh_fa( $val ) . $suffix );
			$out[] = [ 'key' => 'other', 'text' => $text, 'tone' => 'plain', 'title' => $text ];
		}
		if ( count( $out ) >= $max ) {
			break;
		}
	}
	return $out;
}

function stlh_card_badge_icon( array $b ): string {
	$svg = static fn( string $body ): string => '<svg viewBox="0 0 24 24" width="12" height="12" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' . $body . '</svg>';
	switch ( $b['key'] ) {
		case 'battery':
			$w = max( 1.5, round( 13 * ( $b['level'] ?? 0 ) / 100, 1 ) );
			return $svg( '<rect x="2" y="7" width="17" height="10" rx="2.5"/><path d="M22 10.5v3"/><rect x="4" y="9" width="' . $w . '" height="6" rx="1" fill="currentColor" stroke="none"/>' );
		case 'registry':
			return $svg( '<path d="M12 3l7 3v6c0 4.5-3 7.7-7 9-4-1.3-7-4.5-7-9V6l7-3z"/><path d="M9 12l2 2 4-4"/>' );
		case 'photo':
			return $svg( '<path d="M4 8h3l2-3h6l2 3h3v11H4z"/><circle cx="12" cy="13" r="3.4"/>' );
		default:
			return '';
	}
}

function stlh_card_badges_html( WC_Product $p ): string {
	$items = stlh_card_badge_items( $p );
	if ( ! $items ) {
		return '';
	}
	$html = '';
	foreach ( $items as $b ) {
		$html .= '<span class="st-cb st-cb--' . esc_attr( $b['key'] ?? 'other' ) . ' is-' . esc_attr( $b['tone'] ) . '" title="' . esc_attr( $b['title'] ) . '">' . stlh_card_badge_icon( $b ) . '<span>' . esc_html( $b['text'] ) . '</span></span>';
	}
	return '<span class="st-cbs">' . $html . '</span>';
}

/*
 * همین نشان‌ها روی عکسِ صفحه‌ی محصول — صاحب فروشگاه (۹ آبان ۱۴۰۵، با عکسِ صفحه‌ی آیفون ۱۴ پرو): «داخلِ خودِ محصول هم همین تگ‌ها
 * بیاید یا پایین بیاید که کاربر بداند چه می‌خواهد بخرد». کادرِ قالب برای این نیست، پس مثلِ دفترچه‌ی سلامت روی
 * `woocommerce_single_product_end` چاپ می‌شود (یک ردیفِ کوچک) و یک تکه‌اسکریپت آن را به گوشه‌ی راست‌وپایینِ عکسِ اصلی می‌برد
 * (گوشی: اسلایدرِ عکس، رایانه: عکسِ اول). بی اسکریپت همان ردیف بالای دفترچه می‌ماند. ترتیب مثلِ کارت: از پایین «عکس از خودِ
 * محصول»، رجیستر، باتری.
 */
add_action( 'woocommerce_single_product_end', static function (): void {
	$p = function_exists( 'wc_get_product' ) ? wc_get_product( get_the_ID() ) : null;
	$html = $p instanceof WC_Product ? stlh_card_badges_html( $p ) : '';
	if ( '' === $html ) {
		return;
	}
	echo '<style>'
		. '.st-cbs--pdp{display:flex;flex-wrap:wrap;gap:6px;direction:rtl;margin:12px 0 4px;pointer-events:none}'
		. '.st-cbs--pdp.is-on{position:absolute;z-index:5;bottom:12px;right:12px;margin:0;flex-direction:column;align-items:flex-start;gap:5px}'
		. '.st-cbs--pdp .st-cb{display:inline-flex;align-items:center;gap:4px;height:26px;padding:0 9px 0 8px;border-radius:9px;background:rgba(255,255,255,.92);'
		. 'box-shadow:0 1px 2px rgba(15,23,42,.08),0 0 0 1px rgba(15,23,42,.06);color:#334155;font-size:12px;font-weight:700;line-height:1;white-space:nowrap;-webkit-backdrop-filter:blur(4px);backdrop-filter:blur(4px)}'
		. '.st-cbs--pdp .st-cb svg{width:14px;height:14px;flex:0 0 auto}'
		. '.st-cbs--pdp .is-good svg{color:#0f9f6e}.st-cbs--pdp .is-mid svg{color:#d98b06}.st-cbs--pdp .is-mid span{color:#92590a}.st-cbs--pdp .is-low svg{color:#e0244d}.st-cbs--pdp .is-info svg{color:#2563eb}'
		. '.st-cbs--pdp .st-cb--battery{order:1}.st-cbs--pdp .st-cb--registry{order:2}.st-cbs--pdp .st-cb--photo{order:3}.st-cbs--pdp .st-cb--other{order:4}'
		. '@media (max-width:767px){.st-cbs--pdp{margin:10px 10px 4px}.st-cbs--pdp.is-on{bottom:8px;right:10px}}'
		. '</style>';
	echo str_replace( '<span class="st-cbs">', '<span class="st-cbs st-cbs--pdp" id="stlh-pdp-badges">', $html ); // phpcs:ignore — داخلِ تابع escape شده
	echo "<script>(function(){var b=document.getElementById('stlh-pdp-badges');if(!b)return;"
		. "var t=document.querySelector('.swiper-responsive-product-slider')||document.querySelector('.woocommerce-product-gallery__image');"
		. "if(!t||!t.offsetWidth)return;if(getComputedStyle(t).position==='static')t.style.position='relative';t.appendChild(b);b.classList.add('is-on');})();</script>";
}, 98 );
