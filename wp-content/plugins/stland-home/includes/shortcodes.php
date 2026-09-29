<?php
defined( 'ABSPATH' ) || exit;

/** کل صفحه اصلی به ترتیب */
add_shortcode( 'stl_home', function (): string {
	stlh_assets();
	$out = stlh_banner() . stlh_categories();
	foreach ( stlh_lines( (string) stlh_opt( 'product_rows' ) ) as $row ) {
		[ $cat, $limit, $title, $sub, $models ] = array_pad( array_map( 'trim', explode( '|', $row ) ), 5, '' );
		$out .= stlh_products( [ 'category' => $cat, 'limit' => $limit ?: 8, 'title' => $title, 'subtitle' => $sub, 'models' => $models ] );
	}
	return $out . stlh_group() . stlh_flash_deal() . stlh_social() . stlh_trust() . stlh_faq() . stlh_posts();
} );

add_shortcode( 'stl_products', function ( $atts ): string {
	stlh_assets();
	return stlh_products( (array) $atts );
} );

foreach ( [ 'banner', 'trust', 'categories', 'group', 'flash_deal', 'social', 'faq', 'posts' ] as $part ) {
	add_shortcode( 'stl_' . $part, function () use ( $part ): string {
		stlh_assets();
		return call_user_func( 'stlh_' . $part );
	} );
}
