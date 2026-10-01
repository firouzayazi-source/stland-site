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
		'best'       => 'پرفروش‌ها',
		'social'     => 'شبکه‌های اجتماعی',
		'faq'        => 'سوالات متداول',
		'posts'      => 'مقالات',
	];
}

function stlh_render_rows(): string {
	if ( 'manual' !== stlh_opt( 'rows_source' ) ) {
		/*
		 * هر دسته‌ی «برگ» یک ردیف (آیفون نو، آیفون کارکرده…)، با شکستنِ نسل‌ها.
		 * زیردسته‌های لوازم جانبی بخشِ خودشان را دارند و پرفروش‌ها هم؛ تکرار نمی‌شوند.
		 */
		$skip  = [];
		$group = in_array( 'group', stlh_lines( (string) stlh_opt( 'order' ) ), true ) ? stlh_resolve_cat( (string) stlh_opt( 'group_parent' ) ) : false;
		if ( $group && get_term_children( $group->term_id, 'product_cat' ) ) {
			$skip = array_merge( [ $group->term_id ], array_map( 'intval', get_term_children( $group->term_id, 'product_cat' ) ) );
		}
		if ( $best = stlh_resolve_cat( (string) stlh_opt( 'best_cat' ) ) ) {
			$skip[] = $best->term_id;
		}
		$out = '';
		foreach ( stlh_leaf_categories() as $t ) {
			if ( ! in_array( $t->term_id, $skip, true ) ) {
				$out .= stlh_generation_rows( $t );
			}
		}
		return $out;
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

foreach ( [ 'banner', 'trust', 'categories', 'group', 'best', 'flash_deal', 'social', 'faq', 'posts' ] as $part ) {
	add_shortcode( 'stl_' . $part, function () use ( $part ): string {
		stlh_assets();
		return stlh_section( $part );
	} );
}
