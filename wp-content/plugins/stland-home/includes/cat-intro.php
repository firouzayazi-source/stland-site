<?php
/**
 * معرفیِ کوتاه و «مقاله‌های مرتبط» در صفحه‌ی دسته‌های آیفون نو و کارکرده (برنامه‌ی محتوا، روزِ ۷ — ۵ آبان ۱۴۰۵).
 *
 * نامِ دسته و توضیحِ خودِ ترم مالِ حسابداری است و دست نمی‌خورد (هر دو خالی بودند)؛ اینجا فقط روی صفحه چاپ می‌شود.
 * • متنِ معرفی: `woocommerce_before_shop_loop` — در گوشی زیرِ عنوانِ دسته، در رایانه بالای کالاها (پیش از «مرتب‌سازی»،
 *   اولویتِ ۵). دوخطی تا کالاها روی گوشی پایین نروند. باکالا در رایانه این هوک را فقط وقتی دسته کالا دارد صدا می‌زند.
 * • مقاله‌ها: `woocommerce_archive_description` — باکالا آن را زیرِ کالاها و صفحه‌بندی صدا می‌زند
 *   (`archive_description_position` ≠ `before`)، بعد از `<h1>`ِ خودش (اولویتِ ۲۰).
 * متن‌ها از `stlh_label()` (بخشِ «نام‌ها و متن‌ها»)؛ مقاله‌ای که منتشر نشده یا پیدا نشد بی‌صدا کنار می‌رود.
 */

defined( 'ABSPATH' ) || exit;

/** شناسه‌ی دسته => [کلیدِ متنِ معرفی, مقاله‌ها (شناسه یا نامک)] */
const STLH_CAT_INTROS = [
	2955 => [ 'cat_intro_new', [ 44342, 44340, 'iphone-15-vs-16', 'iphone-sealed-active', 44341, 44849 ] ],   // آیفون نو
	2954 => [ 'cat_intro_used', [ 'used-iphone-qom', 'used-iphone-test', 'iphone-battery-health', 'iphone-sealed-active', 'trade-in-qom', 44849 ] ], // آیفون کارکرده
];

function stlh_cat_intro_def(): ?array {
	if ( ! function_exists( 'is_product_category' ) || ! is_product_category() || is_paged() ) {
		return null;
	}
	return STLH_CAT_INTROS[ get_queried_object_id() ] ?? null;
}

function stlh_cat_intro_style(): string {
	static $done = false;
	if ( $done ) {
		return '';
	}
	$done = true;
	return '<style>.stlh-cat-intro{margin:8px 12px 12px;padding:10px 14px;text-align:right;background:#f7f7f9;border-radius:12px;font-size:13px;line-height:1.9;color:#333}'
		. '.stlh-cat-posts{clear:both;margin:20px 0 8px;padding:14px 16px;border:1px solid #ebeced;border-radius:14px;background:#fff}'
		. '.stlh-cat-posts h2{font-size:15px;font-weight:700;margin:0 0 8px}'
		. '.stlh-cat-posts ul{list-style:none;margin:0;padding:0;display:grid;grid-template-columns:repeat(auto-fill,minmax(min(100%,260px),1fr));gap:4px 16px}'
		. '.stlh-cat-posts li a{display:block;padding:6px 0;font-size:13px;line-height:1.7;color:#03174d;overflow-wrap:anywhere}'
		. '.stlh-cat-posts li a:before{content:"‹ ";color:#f5a742;font-weight:700}</style>';
}

add_action( 'woocommerce_before_shop_loop', static function (): void {
	$def = stlh_cat_intro_def();
	$txt = $def ? trim( stlh_label( $def[0] ) ) : '';
	if ( '' !== $txt ) {
		echo stlh_cat_intro_style() . '<p class="stlh-cat-intro">' . esc_html( $txt ) . '</p>'; // phpcs:ignore WordPress.Security.EscapeOutput
	}
}, 5 );

add_action( 'woocommerce_archive_description', static function (): void {
	$def = stlh_cat_intro_def();
	if ( ! $def ) {
		return;
	}
	$items = '';
	foreach ( $def[1] as $ref ) {
		$post = is_int( $ref ) ? get_post( $ref ) : get_page_by_path( $ref, OBJECT, 'post' );
		if ( $post && 'publish' === $post->post_status && 'post' === $post->post_type ) {
			$items .= '<li><a href="' . esc_url( get_permalink( $post ) ) . '">' . esc_html( get_the_title( $post ) ) . '</a></li>';
		}
	}
	if ( '' !== $items ) {
		echo stlh_cat_intro_style() . '<section class="stlh-cat-posts"><h2>' . esc_html( stlh_label( 'cat_posts_title' ) ) . '</h2><ul>' . $items . '</ul></section>'; // phpcs:ignore WordPress.Security.EscapeOutput
	}
}, 20 );
