<?php
/**
 * عیب‌یابیِ صفحه‌ی پرداخت بی SSH و بی سفارشِ واقعی — فقط خواندن.
 *
 * صاحب فروشگاه (۲۲ مهر ۱۴۰۵): «ثبت سفارش» کاری نمی‌کند؛ کادرهای «روش ارسال» و «روش پرداخت» خاکستری
 * می‌مانند. آن دو را درخواستِ پس‌زمینه‌ی `update_order_review` پر می‌کند و پل فقط GET است و سبد نگه
 * نمی‌دارد، پس این‌جا همان کارِ آن درخواست برای یک سبدِ موقت در حافظه انجام می‌شود و هر خطا/هشدار
 * گزارش می‌شود.
 *
 *   GET /stland/v1/checkout-probe?product=ID[&state=QOM&city=قم]
 *
 * ⛔ هیچ سفارشی ساخته نمی‌شود؛ سبد در پایان خالی می‌شود و session ذخیره نمی‌شود. فقط `manage_options`.
 */
defined( 'ABSPATH' ) || exit;

add_action( 'rest_api_init', function (): void {
	register_rest_route( 'stland/v1', '/checkout-probe', [
		'methods'             => 'GET',
		'permission_callback' => static fn() => current_user_can( 'manage_options' ),
		'callback'            => 'stlh_checkout_probe',
	] );
} );

function stlh_checkout_probe( WP_REST_Request $req ): array {
	$out      = [ 'warnings' => [] ];
	$ob_level = ob_get_level();
	$warnings = &$out['warnings'];
	set_error_handler( static function ( $no, $str, $file, $line ) use ( &$warnings ) {
		if ( count( $warnings ) < 40 ) {
			$warnings[] = $str . ' @ ' . str_replace( ABSPATH, '', (string) $file ) . ':' . $line;
		}
		return true;
	} );
	try {
		if ( ! function_exists( 'wc_load_cart' ) ) {
			throw new RuntimeException( 'woocommerce not loaded' );
		}
		wc_load_cart();
		$cart = WC()->cart;
		$cart->empty_cart( false );
		$pid = (int) $req->get_param( 'product' );
		$key = $pid ? $cart->add_to_cart( $pid, 1 ) : false;
		$out['added'] = (bool) $key;
		if ( $req->get_param( 'state' ) ) {
			WC()->customer->set_billing_state( (string) $req->get_param( 'state' ) );
			WC()->customer->set_shipping_state( (string) $req->get_param( 'state' ) );
		}
		if ( $req->get_param( 'city' ) ) {
			WC()->customer->set_billing_city( (string) $req->get_param( 'city' ) );
			WC()->customer->set_shipping_city( (string) $req->get_param( 'city' ) );
		}
		WC()->customer->set_billing_country( 'IR' );
		WC()->customer->set_shipping_country( 'IR' );
		$cart->calculate_shipping();
		$cart->calculate_totals();
		$out['needs_shipping'] = $cart->needs_shipping();
		$out['total']          = $cart->get_total( 'edit' );
		$rates                 = [];
		foreach ( WC()->shipping()->get_packages() as $i => $pkg ) {
			foreach ( (array) ( $pkg['rates'] ?? [] ) as $rid => $rate ) {
				$rates[ $i ][] = $rid . ' = ' . $rate->get_label() . ' (' . $rate->get_cost() . ')';
			}
		}
		$out['shipping_rates'] = $rates;
		$out['gateways']       = array_keys( WC()->payment_gateways()->get_available_payment_gateways() );
		// همان دو قطعه‌ای که update_order_review برمی‌گرداند
		ob_start();
		woocommerce_order_review();
		$review = (string) ob_get_clean();
		ob_start();
		woocommerce_checkout_payment();
		$payment                = (string) ob_get_clean();
		$out['review_bytes']    = strlen( $review );
		$out['payment_bytes']   = strlen( $payment );
		$out['has_place_order'] = str_contains( $payment, 'place_order' );
		$out['payment_text']    = mb_substr( trim( preg_replace( '/\s+/', ' ', wp_strip_all_tags( $payment ) ) ), 0, 600 );
	} catch ( Throwable $e ) {
		$out['exception'] = get_class( $e ) . ': ' . $e->getMessage() . ' @ ' . str_replace( ABSPATH, '', $e->getFile() ) . ':' . $e->getLine();
		$out['trace']     = array_slice( explode( "\n", str_replace( ABSPATH, '', $e->getTraceAsString() ) ), 0, 12 );
	} finally {
		while ( ob_get_level() > $ob_level ) {
			ob_end_clean();
		}
		if ( function_exists( 'WC' ) && WC()->cart ) {
			WC()->cart->empty_cart( true );
		}
		restore_error_handler();
	}
	return $out;
}
