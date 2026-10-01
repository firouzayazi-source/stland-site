<?php
/**
 * کش بخش‌های صفحه اصلی.
 *
 * هر بخش (ردیف محصولات، گروه لوازم جانبی، مقالات…) چند کوئری سنگین
 * ووکامرس می‌زند؛ روی هاست اشتراکی این یعنی صفحه‌ی اصلی دیر شروع می‌شود.
 * پس HTML هر بخش در transient می‌نشیند.
 *
 * ⛔ کهنه نشدن مهم‌تر از سرعت است: قیمت و موجودی را حسابداری مدام عوض
 *    می‌کند و گوشیِ فروخته‌شده نباید روی صفحه اصلی بماند. پس به‌جای حدس
 *    زدن زمان انقضا، هر تغییری در محصول، دسته، مقاله یا تنظیمات یک «نسل»
 *    تازه می‌سازد و همه‌ی کلیدهای قبلی بی‌اعتبار می‌شوند. انقضای ۶ ساعته
 *    فقط تورِ ایمنی است (مثلاً حراجِ زمان‌دار).
 */
defined( 'ABSPATH' ) || exit;

const STLH_CACHE_TTL = 6 * HOUR_IN_SECONDS;

function stlh_cache_gen(): int {
	return (int) get_option( 'stlh_cache_gen', 1 );
}

function stlh_cache_flush(): void {
	update_option( 'stlh_cache_gen', stlh_cache_gen() + 1, true );
}

function stlh_cache_off(): bool {
	return is_customize_preview() || isset( $_GET['elementor-preview'] ) || isset( $_GET['stlh_nocache'] );
}

/** خروجیِ $render را با کلید $key کش می‌کند */
function stlh_cached( string $key, callable $render ): string {
	if ( stlh_cache_off() ) {
		return (string) $render();
	}
	$tkey = 'stlh_' . md5( $key . '|' . stlh_cache_gen() . '|' . STLH_VER . '|' . get_locale() );
	$html = get_transient( $tkey );
	if ( is_string( $html ) ) {
		return $html;
	}
	$html = (string) $render();
	set_transient( $tkey, $html, STLH_CACHE_TTL );
	return $html;
}

// ─── هر چیزی که روی صفحه اصلی دیده می‌شود، نسل را عوض می‌کند ───
foreach ( [
	'woocommerce_new_product',
	'woocommerce_update_product',
	'woocommerce_delete_product',
	'woocommerce_trash_product',
	'woocommerce_product_set_stock',
	'woocommerce_variation_set_stock',
	'woocommerce_product_set_stock_status',
	'woocommerce_variation_set_stock_status',
	'created_product_cat',
	'edited_product_cat',
	'delete_product_cat',
	'update_option_' . STLH_OPT,
	'update_option_woocommerce_hide_out_of_stock_items',
	'update_option_default_product_cat',
] as $hook ) {
	add_action( $hook, 'stlh_cache_flush', 10, 0 );
}

// ترتیبِ دسته‌ها (`order`) — کشیدن در وردپرس یا `menu_order` از حسابداری — فقط متای ترم است
foreach ( [ 'added_term_meta', 'updated_term_meta', 'deleted_term_meta' ] as $hook ) {
	add_action( $hook, static function ( $mid, $term_id, string $key ): void {
		if ( 'order' === $key ) {
			stlh_cache_flush();
		}
	}, 10, 3 );
}

// مقاله یا محصولی منتشر، پیش‌نویس یا حذف شد
add_action( 'transition_post_status', function ( string $new, string $old, WP_Post $post ): void {
	if ( in_array( $post->post_type, [ 'post', 'product' ], true ) && ( 'publish' === $new || 'publish' === $old ) ) {
		stlh_cache_flush();
	}
}, 10, 3 );
add_action( 'save_post_post', 'stlh_cache_flush', 10, 0 );
