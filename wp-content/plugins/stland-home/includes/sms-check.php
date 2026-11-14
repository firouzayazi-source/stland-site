<?php
/**
 * وارسیِ پیامکِ سفارش (افزونه‌ی «پیامک حرفه‌ای ووکامرس»، `persian-woocommerce-sms`)، فقط خواندن، فقط مدیرِ کل.
 *
 * صاحب فروشگاه (۱۵ آبان ۱۴۰۵): بعد از خریدِ آزمایشی هیچ پیامکی نیامد. ۲۳ آبان معلوم شد افزونه از سایت پاک شده بود و
 * تنظیماتش (`sms_*_settings`) مانده بود؛ صاحب فروشگاه دوباره نصبش کرد. `site-options` همه‌ی این گزینه‌ها را
 * می‌پوشاند (نامشان «sms» دارد)، پس این مسیر فقط چیزهای بی‌خطر را نشان می‌دهد:
 *
 *   GET /stland/v1/sms-check
 *
 * ⛔ نامِ کاربری، رمز، کلید و توکنِ سامانه‌ی پیامکی پوشیده‌اند؛ شماره‌ها فقط چهار رقمِ آخر. هیچ نوشتنی نیست.
 */
defined( 'ABSPATH' ) || exit;

add_action( 'rest_api_init', function (): void {
	register_rest_route( 'stland/v1', '/sms-check', [
		'methods'             => 'GET',
		'permission_callback' => static fn() => current_user_can( 'manage_options' ),
		'callback'            => 'stlh_sms_check',
	] );
} );

/** شماره → فقط چهار رقمِ آخر (برای هر رشته‌ای که شبیهِ شماره است). */
function stlh_sms_mask_phone( string $s ): string {
	return (string) preg_replace_callback( '/\+?\d[\d\s\-]{6,}\d/', static fn( $m ) => '…' . substr( preg_replace( '/\D/', '', $m[0] ), -4 ), $s );
}

/** یک مقدارِ تنظیمات → نسخه‌ی بی‌خطر. */
function stlh_sms_safe( string $key, $val ) {
	if ( preg_match( '/user|pass|key|token|secret|api|sender_?id|signature|auth/i', $key ) ) {
		return ( '' === $val || null === $val || [] === $val ) ? '' : '***';
	}
	if ( is_array( $val ) ) {
		$out = [];
		foreach ( $val as $k => $v ) {
			$out[ $k ] = stlh_sms_safe( is_string( $k ) ? $k : $key, $v );
		}
		return $out;
	}
	if ( is_string( $val ) ) {
		$val = stlh_sms_mask_phone( $val );
		return mb_strlen( $val ) > 160 ? mb_substr( $val, 0, 160 ) . '…' : $val;
	}
	return $val;
}

function stlh_sms_check(): array {
	global $wpdb;
	if ( ! function_exists( 'is_plugin_active' ) ) {
		require_once ABSPATH . 'wp-admin/includes/plugin.php';
	}
	$out = [
		'plugin_active' => is_plugin_active( 'persian-woocommerce-sms/WoocommerceIR_SMS.php' ),
		'settings'      => [],
		'applied'       => get_option( 'stlh_sms_applied', null ),
	];
	foreach ( [ 'sms_main_settings', 'sms_buyer_settings', 'sms_super_admin_settings', 'sms_product_admin_settings', 'sms_notif_settings' ] as $sec ) {
		$v                       = get_option( $sec );
		$out['settings'][ $sec ] = is_array( $v ) ? stlh_sms_safe( $sec . '_', $v ) : ( false === $v ? null : '(' . gettype( $v ) . ')' );
	}
	// آخرین ارسال‌ها و جوابِ سامانه (جدولِ بایگانیِ خودِ افزونه).
	$table = $wpdb->prefix . 'woocommerce_ir_sms_archive';
	if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) ) === $table ) {
		$rows           = $wpdb->get_results( "SELECT id, post_id, type, reciever, sender, result, date, CHAR_LENGTH(message) AS len FROM `{$table}` ORDER BY id DESC LIMIT 15", ARRAY_A ); // phpcs:ignore
		$out['archive'] = array_map( static function ( array $r ): array {
			$r['reciever'] = stlh_sms_mask_phone( (string) $r['reciever'] );
			$r['sender']   = stlh_sms_mask_phone( (string) $r['sender'] );
			$r['result']   = mb_substr( stlh_sms_mask_phone( (string) $r['result'] ), 0, 300 );
			return $r;
		}, (array) $rows );
		$out['archive_total'] = (int) $wpdb->get_var( "SELECT COUNT(*) FROM `{$table}`" ); // phpcs:ignore
	} else {
		$out['archive'] = null;
	}
	return $out;
}
