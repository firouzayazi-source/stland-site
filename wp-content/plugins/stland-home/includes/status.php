<?php
/**
 * وضعیتِ سایت برای پنلِ سلامتِ حسابداری.
 *
 * حسابداری پیش از این نمی‌دانست سایت کدام نسخه‌ی افزونه را دارد؛ وقتی
 * کلیدِ تازه‌ای به کارت اضافه می‌شد و افزونه هنوز به‌روز نشده بود، داده
 * بی‌صدا می‌رفت و دیده نمی‌شد. حالا پنلِ «سلامتِ اتصال» این را می‌خواند و
 * اگر قرارداد عقب باشد، می‌گوید «افزونه را به‌روز کنید».
 *
 * ⛔ عمومی نیست: فقط کاربرِ مدیرِ فروشگاه (رمزِ برنامه‌ی وردپرس که
 * حسابداری دارد). نسخه‌ها برای مهاجم اطلاعات است.
 *
 * `/stland/v1/health` مالِ mu-plugin است و با به‌روزرسان نمی‌رسد؛ این مسیر
 * جداست تا با آن قاطی نشود.
 */

defined( 'ABSPATH' ) || exit;

add_action( 'rest_api_init', function (): void {
	register_rest_route( 'stland/v1', '/status', [
		'methods'             => 'GET',
		'permission_callback' => static fn() => current_user_can( 'manage_woocommerce' ) || current_user_can( 'manage_options' ),
		'callback'            => 'stlh_status',
	] );
} );

function stlh_status(): array {
	if ( ! function_exists( 'get_plugins' ) ) {
		require_once ABSPATH . 'wp-admin/includes/plugin.php';
	}
	$plugins = [];
	foreach ( get_plugins() as $file => $data ) {
		$slug = dirname( $file );
		if ( str_starts_with( $slug, 'stland-' ) ) {
			$plugins[ $slug ] = [ 'version' => $data['Version'], 'active' => is_plugin_active( $file ) ];
		}
	}
	$default = get_term( (int) get_option( 'default_product_cat' ), 'product_cat' );
	$kids    = $default instanceof WP_Term ? get_term_children( $default->term_id, 'product_cat' ) : [];
	return [
		'plugin'   => STLH_VER,
		'contract' => STLH_CONTRACT,
		'plugins'  => $plugins,
		'wp'       => get_bloginfo( 'version' ),
		'wc'       => defined( 'WC_VERSION' ) ? WC_VERSION : null,
		'php'      => PHP_VERSION,
		/*
		 * دسته‌ی پیش‌فرض: اگر «دسته‌بندی نشده» نیست، محصولِ بی‌دسته زیرِ یک
		 * دسته‌ی واقعی می‌افتد — پنل هشدار می‌دهد (یک بار همین آیفون‌ها را از
		 * صفحه‌ی اصلی برده بود).
		 */
		'default_cat' => $default instanceof WP_Term ? [
			'id'       => $default->term_id,
			'name'     => $default->name,
			'slug'     => rawurldecode( $default->slug ),
			'children' => is_array( $kids ) ? count( $kids ) : 0,
		] : null,
		'home'     => [
			'lines'    => (array) stlh_opt( 'lines' ) ? 'custom' : 'auto',
			'carousel' => (string) stlh_opt( 'carousel_mode' ),
		],
		'time'     => gmdate( 'c' ),
	];
}
