<?php
/**
 * Plugin Name:       StockLand Home
 * Description:       صفحه اصلی داینامیک استوک لند — بنر، دسته‌ها، ردیف محصولات، پیشنهاد ویژه، شبکه‌ها، سوالات متداول و مقالات. شورت‌کد: [stl_home]
 * Version:           1.5.0
 * Requires at least: 6.3
 * Requires PHP:      8.1
 * Requires Plugins:  woocommerce
 * Text Domain:       stland-home
 */

defined( 'ABSPATH' ) || exit;

define( 'STLH_VER', '1.5.0' );
define( 'STLH_DIR', plugin_dir_path( __FILE__ ) );
define( 'STLH_URL', plugin_dir_url( __FILE__ ) );
define( 'STLH_OPT', 'stland_home' );

require_once STLH_DIR . 'includes/helpers.php';
require_once STLH_DIR . 'includes/cache.php';
require_once STLH_DIR . 'includes/settings.php';
require_once STLH_DIR . 'includes/render.php';
require_once STLH_DIR . 'includes/shortcodes.php';
require_once STLH_DIR . 'includes/schema.php';

add_action( 'wp_enqueue_scripts', function (): void {
	wp_register_style( 'stland-home-vars', false, [], STLH_VER );
	wp_add_inline_style( 'stland-home-vars', stlh_inline_css() );
	wp_register_style( 'stland-home', STLH_URL . 'assets/css/home.css', [ 'stland-home-vars' ], STLH_VER );
	wp_register_script( 'stland-home', STLH_URL . 'assets/js/home.js', [], STLH_VER, [ 'in_footer' => true, 'strategy' => 'defer' ] );

	// روی صفحه اصلی از اول در <head> لود شود تا صفحه بدون استایل دیده نشود.
	if ( is_front_page() || apply_filters( 'stlh_force_enqueue', false ) ) {
		stlh_assets();
	} elseif ( '1' === stlh_opt( 'font_sitewide' ) ) {
		wp_enqueue_style( 'stland-home-vars' );
	}
} );

// پیش‌بارگذاری فونت برای جلوگیری از پرش متن
add_action( 'wp_head', function (): void {
	if ( ! stlh_font_active() ) {
		return;
	}
	if ( is_front_page() || '1' === stlh_opt( 'font_sitewide' ) ) {
		printf( '<link rel="preload" href="%s" as="font" type="font/woff2" crossorigin>' . "\n", esc_url( stlh_font_url() ) );
	}
}, 1 );

register_activation_hook( __FILE__, function (): void {
	if ( false === get_option( STLH_OPT ) ) {
		add_option( STLH_OPT, stlh_defaults() );
	}
} );

add_filter( 'plugin_action_links_' . plugin_basename( __FILE__ ), function ( array $links ): array {
	array_unshift( $links, '<a href="' . esc_url( admin_url( 'admin.php?page=stland-home' ) ) . '">تنظیمات</a>' );
	return $links;
} );

// نمایش خودکار در صفحه اصلی — بدون نیاز به المنتور یا شورت‌کد
add_filter( 'template_include', function ( string $template ): string {
	if ( is_admin() || ! is_front_page() || '1' !== stlh_opt( 'takeover' ) || isset( $_GET['elementor-preview'] ) ) {
		return $template;
	}
	return STLH_DIR . 'templates/front-page.php';
}, 99 );
