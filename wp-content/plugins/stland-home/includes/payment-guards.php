<?php
/**
 * سقفِ مبلغِ زرین‌پال — مشتری به‌جای گیر کردن، پیامِ روشن می‌گیرد.
 *
 * ۲۲ مهر ۱۴۰۵: سفارشِ ۲۲۰ میلیونی بعد از «ثبت سفارش» در صفحه‌ی order-pay ماند و به درگاه نرفت. تستِ مستقیم:
 * زرین‌پال تراکنشِ بالای ۱٬۰۰۰٬۰۰۰٬۰۰۰ ریال را رد می‌کند و افزونه‌اش آن خطا را به مشتری نشان نمی‌دهد. این‌جا
 * پیش از ساختِ سفارش جلویش گرفته می‌شود و توضیحِ درگاه هم سقف را می‌گوید. سقف در تنظیماتِ افزونه (`zp_cap`، تومان).
 */
defined( 'ABSPATH' ) || exit;

/** مبلغ به تومان (واحدِ فروشگاه IRT است؛ ریال/هزارتومان هم پشتیبانی می‌شود) */
function stlh_pg_toman( float $amount ): float {
	return match ( strtoupper( function_exists( 'get_woocommerce_currency' ) ? get_woocommerce_currency() : 'IRT' ) ) {
		'IRR'   => $amount / 10,
		'IRHT'  => $amount * 1000,
		'IRHR'  => $amount * 100,
		default => $amount,
	};
}

function stlh_pg_zp_over_cap( float $total ): bool {
	$cap = (int) stlh_opt( 'zp_cap' );
	return $cap > 0 && stlh_pg_toman( $total ) > $cap;
}

function stlh_pg_zp_message(): string {
	return sprintf( 'سقفِ هر پرداخت با زرین‌پال %s تومان است. برای این سفارش لطفاً درگاهِ دیگری را انتخاب کنید.', number_format( (int) stlh_opt( 'zp_cap' ) ) );
}

// توضیحِ درگاه در صفحه‌ی پرداخت
add_filter( 'woocommerce_gateway_description', static function ( $desc, $id ) {
	if ( 'WC_ZPal' !== $id || ! function_exists( 'WC' ) || ! WC()->cart ) {
		return $desc;
	}
	if ( stlh_pg_zp_over_cap( (float) WC()->cart->get_total( 'edit' ) ) ) {
		return '<span style="color:#d32f2f;font-weight:700">' . esc_html( stlh_pg_zp_message() ) . '</span>';
	}
	return $desc;
}, 20, 2 );

// پیش از ساختِ سفارش
add_action( 'woocommerce_after_checkout_validation', static function ( $data, $errors ): void {
	if ( ( $data['payment_method'] ?? '' ) === 'WC_ZPal' && function_exists( 'WC' ) && WC()->cart && stlh_pg_zp_over_cap( (float) WC()->cart->get_total( 'edit' ) ) ) {
		$errors->add( 'stlh_zp_cap', stlh_pg_zp_message() );
	}
}, 10, 2 );

// سفارشِ از‌قبل‌ساخته (صفحه‌ی «پرداخت سفارش» از حساب کاربری)
add_action( 'woocommerce_before_pay_action', static function ( $order ): void {
	if ( $order instanceof WC_Order && 'WC_ZPal' === ( $_POST['payment_method'] ?? '' ) && stlh_pg_zp_over_cap( (float) $order->get_total() ) ) { // phpcs:ignore WordPress.Security.NonceVerification -- WC already verified the pay nonce
		wc_add_notice( stlh_pg_zp_message(), 'error' );
		wp_safe_redirect( $order->get_checkout_payment_url() );
		exit;
	}
} );
