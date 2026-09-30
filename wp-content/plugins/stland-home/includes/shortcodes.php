<?php
defined( 'ABSPATH' ) || exit;

/** بخش‌هایی که در «ترتیب بخش‌ها» می‌شود نوشت */
function stlh_sections(): array {
	return [
		'banner'     => 'بنر',
		'trust'      => 'نوار اعتماد',
		'categories' => 'دسته‌بندی‌ها',
		'flash_deal' => 'پیشنهاد ویژه',
		'rows'       => 'ردیف‌های محصول',
		'group'      => 'لوازم جانبی',
		'social'     => 'شبکه‌های اجتماعی',
		'faq'        => 'سوالات متداول',
		'posts'      => 'مقالات',
	];
}

function stlh_render_rows(): string {
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
	return stlh_cached( 'sec:' . $key, 'rows' === $key ? 'stlh_render_rows' : 'stlh_' . $key );
}

/** کل صفحه اصلی به ترتیبی که در تنظیمات آمده */
add_shortcode( 'stl_home', function (): string {
	stlh_assets();
	$out = '';
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

foreach ( [ 'banner', 'trust', 'categories', 'group', 'flash_deal', 'social', 'faq', 'posts' ] as $part ) {
	add_shortcode( 'stl_' . $part, function () use ( $part ): string {
		stlh_assets();
		return stlh_section( $part );
	} );
}
