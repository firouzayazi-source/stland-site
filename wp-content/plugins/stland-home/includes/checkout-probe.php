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
	if ( ! empty( $req->get_param( 'myip' ) ) ) {
		// آی‌پیِ عمومیِ خروجیِ هاست — همان که درگاه‌ها (سپ) می‌بینند و باید در پنلشان ثبت شود
		foreach ( [ 'https://api.ipify.org', 'https://ifconfig.me/ip', 'https://icanhazip.com' ] as $u ) {
			$r                = wp_remote_get( $u, [ 'timeout' => 8 ] );
			$out['ip'][ $u ] = is_wp_error( $r ) ? 'ERR ' . $r->get_error_message() : trim( (string) wp_remote_retrieve_body( $r ) );
		}
		restore_error_handler();
		return $out;
	}
	if ( ! empty( $req->get_param( 'gateways' ) ) ) {
		$out['gateway_test'] = stlh_probe_gateways( max( 15000, (int) $req->get_param( 'zp_amount' ) ) );
		restore_error_handler();
		return $out;
	}
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

/**
 * `?gateways=1` — مثلِ دکمه‌ی «تست اتصال»: یک درخواستِ پرداختِ ۱۰٬۰۰۰ ریالیِ پرداخت‌نشده به هر درگاه
 * با همان تنظیماتِ افزونه، بی سفارش. مقدارِ ترمینال/مرچنت هرگز برنمی‌گردد، فقط شکلش.
 */
function stlh_probe_gateways( int $zp_amount = 15000 ): array {
	$res  = [];
	$all  = function_exists( 'WC' ) ? WC()->payment_gateways()->payment_gateways() : [];
	$back = home_url( '/' );
	$shape = static function ( $v ): array {
		$v = (string) $v;
		return [ 'len' => strlen( $v ), 'ascii_digits_only' => (bool) preg_match( '/^[0-9]+$/', $v ), 'has_space' => (bool) preg_match( '/\s/', $v ), 'non_ascii' => (bool) preg_match( '/[^\x20-\x7e]/', $v ) ];
	};
	// زرین‌پال: همان کلاسِ خودِ افزونه
	if ( isset( $all['WC_ZPal'] ) ) {
		$g = $all['WC_ZPal'];
		$r = [ 'merchant_shape' => $shape( $g->get_option( 'merchantcode' ) ), 'amount_rial' => $zp_amount ];
		try {
			$auth          = $g->zarinpal->requestPayment( $zp_amount, $back, 'stland connection test', [] );
			$r['ok']        = true;
			$r['authority'] = substr( (string) $auth, 0, 6 ) . '…';
		} catch ( Throwable $e ) {
			$r['ok']    = false;
			$r['error'] = $e->getMessage();
		}
		$res['zarinpal'] = $r;
	}
	// سپ: همان آدرس از `.env` افزونه و همان بدنه‌ی `get_token`
	if ( isset( $all['WC_Sep_Payment_Gateway'] ) ) {
		$g    = $all['WC_Sep_Payment_Gateway'];
		$term = (string) $g->get_option( 'merchantcode' );
		$r    = [ 'terminal_shape' => $shape( $term ) ];
		$env  = [];
		$file = WP_PLUGIN_DIR . '/zhaket-woo-sep/.env';
		foreach ( is_readable( $file ) ? (array) file( $file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES ) : [] as $l ) {
			if ( str_contains( $l, '=' ) && ! str_starts_with( $l, '#' ) ) {
				[ $k, $v ]   = explode( '=', $l, 2 );
				$env[ $k ] = $v;
			}
		}
		$r['env_keys']  = array_keys( $env );
		$r['token_url'] = $env['TOKEN_URL'] ?? null;
		if ( ! empty( $env['TOKEN_URL'] ) ) {
			$resp = wp_remote_post( $env['TOKEN_URL'], [
				'timeout' => 15,
				'headers' => [ 'Content-Type' => 'application/json' ],
				'body'    => wp_json_encode( [ 'action' => 'token', 'TerminalId' => $term, 'Amount' => 10000, 'ResNum' => 'probe' . time(), 'RedirectUrl' => $back, 'CellNumber' => '' ] ),
			] );
			if ( is_wp_error( $resp ) ) {
				$r['error'] = $resp->get_error_message();
			} else {
				$body       = json_decode( (string) wp_remote_retrieve_body( $resp ), true );
				$r['http']  = wp_remote_retrieve_response_code( $resp );
				$r['status'] = $body['status'] ?? null;
				$r['errorCode'] = $body['errorCode'] ?? null;
				$r['errorDesc'] = $body['errorDesc'] ?? null;
				$r['got_token'] = ! empty( $body['token'] );
			}
		}
		$res['sep'] = $r;
	}
	return $res;
}
