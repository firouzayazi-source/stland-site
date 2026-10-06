<?php
/**
 * کالای فروش‌رفته در سایت می‌ماند، ولی ته فهرست و با برچسبِ «فروش رفت».
 *
 * صاحب فروشگاه (۱۵ مهر ۱۴۰۵): «برای سئو بهتر محصولی که از هر طریق فروش رفت نباید
 * پاک شود، فقط باید بخورد فروش رفت — عموماً برای گوشی‌های کارکرده که دیگر نمونه
 * ندارند — و همه‌ی فروش‌رفته‌ها با اولویتِ پایین‌تر در جستجو و سایت بیایند؛ اپل،
 * بارگذاریِ جدید و موجودی‌دارها اول.» حسابداری (`soldAction` = ناموجود) صفحه را
 * نگه می‌دارد و موجودی را صفر می‌کند؛ این فایل دو کارِ سمتِ سایت را می‌کند:
 *
 *  ۱. در فروشگاه، دسته‌ها، برچسب‌ها و جستجو، موجودها پیش از ناموجودها — هر
 *     مرتب‌سازیِ دیگری (جدیدترین، ارزان‌ترین…) داخلِ هر گروه سرِ جایش می‌ماند.
 *     ردیف‌های صفحه‌ی اصلی از قبل فقط موجودها را می‌آورند (`render.php`).
 *  ۲. «ناموجود»ِ گوشیِ کارکرده «فروش رفت» می‌شود (`stlh_label('sold')`) — آن گوشی
 *     دیگر برنمی‌گردد. کالای نو همان «ناموجود» می‌ماند، چون شاید دوباره بیاید.
 */
defined( 'ABSPATH' ) || exit;

add_filter( 'posts_clauses', static function ( array $clauses, WP_Query $query ): array {
	if ( is_admin() || ! $query->is_main_query() || ! function_exists( 'is_shop' ) ) {
		return $clauses;
	}
	$catalog = is_shop() || is_product_taxonomy() || ( $query->is_search() && in_array( $query->get( 'post_type' ), [ 'product', '' ], true ) );
	if ( ! $catalog || false !== strpos( $clauses['join'], 'stl_stock' ) ) {
		return $clauses;
	}
	global $wpdb;
	$clauses['join']   .= " LEFT JOIN {$wpdb->postmeta} stl_stock ON ( {$wpdb->posts}.ID = stl_stock.post_id AND stl_stock.meta_key = '_stock_status' )";
	$first              = "CASE WHEN stl_stock.meta_value = 'outofstock' THEN 1 ELSE 0 END ASC";
	$clauses['orderby'] = '' !== trim( (string) $clauses['orderby'] ) ? $first . ', ' . $clauses['orderby'] : $first;
	return $clauses;
}, 20, 2 );

/** گوشیِ کارکرده (متای `condition` که حسابداری می‌فرستد) */
function stlh_is_used( WC_Product $p ): bool {
	$c = trim( (string) $p->get_meta( 'condition' ) );
	return '' !== $c && false !== mb_strpos( $c, 'کارکرده' );
}

add_filter( 'woocommerce_get_availability_text', static function ( $text, $product ) {
	if ( $product instanceof WC_Product && ! $product->is_in_stock() && stlh_is_used( $product ) ) {
		return stlh_label( 'sold' );
	}
	return $text;
}, 20, 2 );
