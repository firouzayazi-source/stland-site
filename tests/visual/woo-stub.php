<?php
/**
 * ووکامرسِ ساختگی برای آزمونِ دیداری — فقط همان چند تابع و کلاسی که
 * stland-home صدا می‌زند. ووکامرسِ واقعی برای رندرِ یک صفحه سنگین است و
 * آزمون باید رفتارِ **افزونه‌ی ما** را بسنجد، نه ووکامرس را.
 * محصول = نوشته‌ای با متای is_product؛ دسته = طبقه‌بندیِ product_cat.
 */
add_action( 'init', static fn() => register_taxonomy( 'product_cat', 'post', [ 'hierarchical' => true, 'public' => true ] ), 0 );
define( 'WC_VERSION', '9.0.0-stub' );
// افزونه از راهِ mu بار می‌شود: «Requires Plugins: woocommerce» بی‌ووکامرسِ واقعی فعال‌شدن را رد می‌کند
add_action( 'plugins_loaded', static function (): void {
	if ( ! function_exists( 'stlh_opt' ) ) {
		require WP_PLUGIN_DIR . '/stland-home/stland-home.php';
	}
}, 1 );

class WC_Product {
	public function __construct( public WP_Post $p ) {}
	public function get_id() { return $this->p->ID; }
	public function get_name() { return $this->p->post_title; }
	public function get_permalink() { return '#p' . $this->p->ID; }
	public function get_image_id() { return 0; }
	public function get_image( $s = '', $a = [] ) { return ''; }
	public function is_in_stock() { return 'out' !== get_post_meta( $this->p->ID, 'stock', true ); }
	public function is_visible() { return true; }
	public function get_status() { return 'publish'; }
	public function is_on_sale() { return (bool) get_post_meta( $this->p->ID, 'sale', true ); }
	public function get_regular_price() { return (float) get_post_meta( $this->p->ID, 'price', true ); }
	public function get_sale_price() { return (float) get_post_meta( $this->p->ID, 'sale', true ); }
	public function get_price_html() {
		$r = $this->get_regular_price();
		$s = $this->get_sale_price();
		return $s ? '<del>' . number_format( $r ) . '</del><ins>' . number_format( $s ) . ' تومان</ins>' : number_format( $r ) . ' تومان';
	}
	public function get_attribute( $k ) { return ''; }
	public function get_meta( $k ) { return get_post_meta( $this->p->ID, $k, true ); }
	public function is_type( $t ) { return 'simple' === $t; }
	public function get_short_description() { return ''; }
	public function get_description() { return ''; }
}

function wc_get_products( $args ) {
	$q = [ 'post_type' => 'post', 'post_status' => 'publish', 'posts_per_page' => $args['limit'] ?? 10, 'orderby' => 'date', 'order' => 'DESC', 'meta_key' => 'is_product' ];
	if ( ! empty( $args['category'] ) ) {
		$q['tax_query'] = [ [ 'taxonomy' => 'product_cat', 'field' => 'slug', 'terms' => $args['category'], 'include_children' => true ] ];
	}
	if ( ! empty( $args['featured'] ) ) {
		return [];
	}
	if ( 'instock' === ( $args['stock_status'] ?? '' ) ) {
		$q['meta_query'] = [ [ 'key' => 'stock', 'compare' => 'NOT EXISTS' ] ];
	}
	return array_map( static fn( $p ) => new WC_Product( $p ), get_posts( $q ) );
}
function wc_get_product( $id ) {
	$p = get_post( $id );
	return $p ? new WC_Product( $p ) : false;
}
function get_woocommerce_currency() { return 'IRT'; }
