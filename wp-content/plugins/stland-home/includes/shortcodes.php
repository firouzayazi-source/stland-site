<?php
defined( 'ABSPATH' ) || exit;

/** بخش‌هایی که در «ترتیب بخش‌ها» می‌شود نوشت */
function stlh_sections(): array {
	return [
		'banner'     => 'بنر',
		'trust'      => 'نوار اعتماد',
		'categories' => 'دسته‌بندی‌ها',
		'sale'       => 'فروش ویژه (کالاهای تایمردار، نوارِ قرمز)',
		'flash_deal' => 'پیشنهاد ویژه',
		'rows'       => 'ردیف‌های محصول (آیفون نو، کارکرده، لوازم جانبی…)',
		'group'      => 'لوازم جانبی — اگر در ردیف‌ها نیست',
		'best'       => 'پرفروش‌ها',
		'social'     => 'شبکه‌های اجتماعی',
		'faq'        => 'سوالات متداول',
		'posts'      => 'مقالات',
	];
}

function stlh_render_rows(): string {
	if ( 'manual' !== stlh_opt( 'rows_source' ) ) {
		// ردیف‌هایی که صاحب فروشگاه در تنظیمات چیده، به همان ترتیب؛ وگرنه خودکار از درخت
		return implode( '', array_map( 'stlh_line_html', stlh_lines_effective() ) );
	}
	$out = '';
	foreach ( stlh_lines( (string) stlh_opt( 'product_rows' ) ) as $row ) {
		[ $cat, $limit, $title, $sub, $models ] = array_pad( array_map( 'trim', explode( '|', $row ) ), 5, '' );
		$out .= stlh_products( [ 'category' => $cat, 'limit' => $limit ?: 8, 'title' => $title, 'subtitle' => $sub, 'models' => $models ] );
	}
	return $out;
}

/** یک بخش، از کش */
function stlh_section( string $key ): string {
	if ( ! isset( stlh_sections()[ $key ] ) ) {
		return '';
	}
	if ( function_exists( 'stlh_prof_phase' ) ) {
		stlh_prof_phase( 'section:' . $key . ' start' );
	}
	$html = stlh_cached( 'sec:' . $key, 'rows' === $key ? 'stlh_render_rows' : 'stlh_' . $key );
	if ( function_exists( 'stlh_prof_phase' ) ) {
		stlh_prof_phase( 'section:' . $key . ' end' );
	}
	return $html;
}

/** کل صفحه اصلی به ترتیبی که در تنظیمات آمده */
add_shortcode( 'stl_home', function (): string {
	stlh_assets();
	// قیمت و قرصِ تخفیفِ کارت داخلِ خودِ صفحه هم می‌آید (۱۷ آبان ۱۴۰۵): CSSِ بحرانی و ترکیبیِ LiteSpeed قاعده‌های تازه‌ی home.css
	// را دیر یا اصلاً نمی‌آورد و صاحب فروشگاه «۶٪»ِ سیاه و قیمتِ راست‌چین دید.
	$out = '<style id="stlh-card-price">' . stlh_card_price_css() . '</style>';
	foreach ( array_unique( stlh_lines( (string) stlh_opt( 'order' ) ) ) as $key ) {
		$out .= stlh_section( $key );
	}
	return $out;
} );

add_shortcode( 'stl_products', function ( $atts ): string {
	stlh_assets();
	$atts = (array) $atts;
	ksort( $atts );
	return stlh_cached( 'products:' . wp_json_encode( $atts ), fn() => stlh_products( $atts ) );
} );

foreach ( [ 'banner', 'trust', 'categories', 'group', 'best', 'flash_deal', 'sale', 'social', 'faq', 'posts' ] as $part ) {
	add_shortcode( 'stl_' . $part, function () use ( $part ): string {
		stlh_assets();
		return stlh_section( $part );
	} );
}

/** قاعده‌های قیمتِ کارت (همان‌ها در home.css هم هست) */
function stlh_card_price_css(): string {
	return <<<'CSS'
.st-pr-price .st-pr-was { display: flex; align-items: center; flex-wrap: wrap; gap: 6px; margin-bottom: 2px; }
.st-pr-price .st-pr-was del { display: inline; margin: 0; font-size: 12px; color: #94a3b8; font-weight: 600; }
.st-pr-price .st-pr-was del .woocommerce-Price-currencySymbol { display: none; }
.st-off { display: inline-flex; align-items: center; background: #dc2626; color: #fff; font-size: 11px; font-weight: 800; line-height: 1; padding: 4px 8px; border-radius: 999px; box-shadow: 0 3px 10px -4px rgba(220,38,38,.7); }
.st-pr-price .st-pr-now { display: block; }
@media (max-width: 576px) { .st-off { font-size: 10px; padding: 3px 7px; } .st-pr-price .st-pr-was del { font-size: 11px; } }
.st-pr-foot .st-pr-price { margin-inline-start: auto; text-align: left; }
.st-pr-price .st-pr-was { direction: ltr; justify-content: flex-start; }
.st-pr-price .st-pr-now { direction: rtl; }
CSS;
}
