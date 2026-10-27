<?php
/**
 * معرفیِ دوخطی و «مقاله‌های مرتبط» در صفحه‌ی دسته‌های آیفون نو و کارکرده (روزِ ۷ برنامه‌ی محتوا، `docs/seo-plan.md`).
 *
 * چرا: صفحه‌ی دسته فقط فهرستِ کالا بود و گوگل برایش متنی نداشت؛ مقاله‌ها هم به دسته لینک می‌دادند ولی دسته به
 * مقاله‌ها نه. حالا:
 * • یک بندِ کوتاه بالای کالاها (`woocommerce_before_shop_loop`؛ دسته‌ی خالی: `woocommerce_no_products_found`) —
 *   دو خط، تا روی گوشی کالاها پایین نروند.
 * • کادرِ «مقاله‌های مرتبط» پایینِ کالاها، کنارِ عنوانِ دسته‌ی خودِ قالب (`woocommerce_archive_description`؛
 *   `archive_description_position` باکالا = `after`). روی گوشی اسلایدرِ افقی.
 * • فقط صفحه‌ی ۱ (صفحه‌های بعدی همان متن را تکرار نکنند)، و فقط مقاله‌ی منتشرشده.
 *
 * نام و توضیحِ خودِ دسته دست نمی‌خورد (مالِ حسابداری، و الان خالی است). متن‌ها از `stlh_label()` می‌گذرند
 * و ادعاها فقط از `stlh_store_facts()`. قالب دست نمی‌خورد؛ CSS داخلِ صفحه است، مثلِ `blog-sidebar.php`.
 */

defined( 'ABSPATH' ) || exit;

/** نامکِ دسته => [کلیدِ متنِ معرفی، مقاله‌ها به ترتیب (شناسه یا نامکِ `STLH_POSTS`)] */
const STLH_CAT_INTRO = [
	'iphone_new'         => [ 'cat_intro_new', [ 44342, 44340, 'iphone-15-vs-16', 'iphone-sealed-active', 44341, 44849 ] ],
	'iphone-second-hand' => [ 'cat_intro_used', [ 'used-iphone-qom', 'used-iphone-test', 'iphone-battery-health', 'iphone-sealed-active', 'trade-in-qom', 44849 ] ],
];

function stlh_cat_intro_def(): ?array {
	if ( ! function_exists( 'is_product_category' ) || ! is_product_category() || is_paged() ) {
		return null;
	}
	$term = get_queried_object();
	return $term instanceof WP_Term ? ( STLH_CAT_INTRO[ $term->slug ] ?? null ) : null;
}

/** @return WP_Post[] */
function stlh_cat_intro_posts( array $refs ): array {
	$out = [];
	foreach ( $refs as $ref ) {
		$p = is_int( $ref ) ? get_post( $ref ) : get_page_by_path( $ref, OBJECT, 'post' );
		if ( $p instanceof WP_Post && 'post' === $p->post_type && 'publish' === $p->post_status ) {
			$out[ $p->ID ] = $p;
		}
	}
	return array_values( $out );
}

function stlh_cat_intro_text(): void {
	static $done = false;
	$def = stlh_cat_intro_def();
	if ( $done || ! $def ) {
		return;
	}
	$done = true;
	echo '<p class="stlh-cat-intro">' . esc_html( stlh_label( $def[0] ) ) . '</p>';
}
// گوشی: before_shop_loop همیشه صدا زده می‌شود؛ دسکتاپ فقط وقتی کالا هست — دسته‌ی خالی از no_products_found
add_action( 'woocommerce_before_shop_loop', 'stlh_cat_intro_text', 5 );
add_action( 'woocommerce_no_products_found', 'stlh_cat_intro_text', 5 );

add_action( 'woocommerce_archive_description', static function (): void {
	$def   = stlh_cat_intro_def();
	$posts = $def ? stlh_cat_intro_posts( $def[1] ) : [];
	if ( ! $posts ) {
		return;
	}
	echo '<nav class="stlh-cat-posts" aria-label="' . esc_attr( stlh_label( 'cat_posts_title' ) ) . '">';
	echo '<h2>' . esc_html( stlh_label( 'cat_posts_title' ) ) . '</h2><ul>';
	foreach ( $posts as $p ) {
		$img = get_the_post_thumbnail( $p, 'medium', [ 'loading' => 'lazy', 'alt' => '' ] );
		echo '<li><a href="' . esc_url( get_permalink( $p ) ) . '">' . $img
			. '<span>' . esc_html( get_the_title( $p ) ) . '</span></a></li>';
	}
	echo '</ul></nav>';
}, 20 );

add_action( 'wp_head', static function (): void {
	if ( ! stlh_cat_intro_def() ) {
		return;
	}
	?>
<style id="stlh-cat-intro">
.stlh-cat-intro{margin:4px 0 14px;font-size:14px;line-height:1.9;color:#4a4a4f}
@media (min-width:768px){
.stlh-cat-intro,.woocommerce-products-header:has(.stlh-cat-posts){flex:0 0 100%;max-width:100%}
}
.stlh-cat-posts{margin:8px 0 28px}
.stlh-cat-posts h2{margin:0 4px 12px;font-size:18px;font-weight:800}
.stlh-cat-posts ul{display:grid;grid-template-columns:repeat(auto-fill,minmax(200px,1fr));gap:14px;list-style:none;margin:0;padding:2px 2px 10px}
.stlh-cat-posts li{margin:0;background:#fff;border-radius:16px;overflow:hidden;box-shadow:0 2px 12px rgba(0,0,0,.08)}
.stlh-cat-posts a{display:block;color:inherit}
.stlh-cat-posts img{display:block;width:100%;height:auto;aspect-ratio:16/9;object-fit:cover;margin:0}
.stlh-cat-posts span{display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical;overflow:hidden;margin:10px 12px 12px;font-size:13px;line-height:1.7;font-weight:700}
@media (max-width:767px){
.stlh-cat-intro{margin:6px 12px 10px;font-size:13px;line-height:1.8}
.stlh-cat-posts{margin:8px 12px 24px}
.stlh-cat-posts ul{display:flex;gap:12px;overflow-x:auto;scroll-snap-type:x mandatory;-webkit-overflow-scrolling:touch;scrollbar-width:none}
.stlh-cat-posts ul::-webkit-scrollbar{display:none}
.stlh-cat-posts li{flex:0 0 62%;max-width:240px;scroll-snap-align:start}
}
</style>
	<?php
}, 20 );
