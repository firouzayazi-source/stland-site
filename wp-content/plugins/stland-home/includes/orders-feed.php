<?php
/**
 * سفارش‌های سایت برای Novo — خواندن و تغییرِ وضعیت از Novo (صاحب فروشگاه، ۲۴ آبان ۱۴۰۵: «من وارد سایت نمی‌خوام بشم برای
 * پیگیری یا سفارش. تمام مراحلشو در جای خودش در بخشی که طراحی کردیم بیار که بتونم تغییرات از هسته‌ی Novo انجام بدم.»).
 *
 * مثلِ `tradein-feed.php`: Novo از راهِ دروازه‌ی سایت با رمزِ برنامه‌ی مدیر می‌خواند و می‌نویسد.
 *
 *   GET  /stland/v1/orders?after=<زمانِ آخرین تغییر، GMT>&limit=50   سفارش‌های تغییرکرده بعد از آن زمان (قدیم به جدید)
 *   GET  /stland/v1/orders?id=<شناسه>                                  یک سفارش
 *   GET  /stland/v1/orders/statuses                                     فهرستِ وضعیت‌ها [{key, label}]
 *   POST /stland/v1/orders/<id>  {status?, tracking_code?, note?}      تغییر از Novo
 *
 * • تغییرِ وضعیت با `update_status()`ِ خودِ فروشگاه‌ساز است، پس پیامکِ همان وضعیت (افزونه‌ی پیامک، الگوهای ملی‌پیامک) و
 *   ایمیل‌ها مثلِ پیشخوان می‌روند، و یادداشتِ «از Novo» در سفارش می‌ماند.
 * • کدِ رهگیری در متای `post_barcode` (همان که افزونه‌ی پیامک می‌خواند) و رویدادِ `pws_save_order_post_barcode` → پیامکِ رهگیری.
 * • `sms` در هر سفارش: ۵ ارسالِ آخرِ همان سفارش از بایگانیِ افزونه‌ی پیامک (رسید یا خطا) تا در Novo دیده شود.
 * ⛔ فقط مدیرِ کل؛ قیمت، کالا و موجودیِ سفارش این‌جا عوض نمی‌شود (مالکیتِ داده).
 */
defined( 'ABSPATH' ) || exit;

add_action( 'rest_api_init', static function (): void {
	$admin = static fn() => current_user_can( 'manage_options' );
	register_rest_route( 'stland/v1', '/orders', [
		'methods'             => 'GET',
		'permission_callback' => $admin,
		'callback'            => 'stlh_orders_feed',
		'args'                => [
			'after' => [ 'type' => 'string', 'default' => '' ],
			'id'    => [ 'type' => 'integer', 'default' => 0, 'minimum' => 0 ],
			'limit' => [ 'type' => 'integer', 'default' => 50, 'minimum' => 1, 'maximum' => 50 ],
		],
	] );
	register_rest_route( 'stland/v1', '/orders/statuses', [
		'methods'             => 'GET',
		'permission_callback' => $admin,
		'callback'            => static fn() => stlh_orders_statuses(),
	] );
	register_rest_route( 'stland/v1', '/orders/(?P<id>\d+)', [
		'methods'             => [ 'POST', 'PUT' ],
		'permission_callback' => $admin,
		'callback'            => 'stlh_orders_update',
		'args'                => [
			'status'        => [ 'type' => 'string', 'required' => false ],
			'tracking_code' => [ 'type' => 'string', 'required' => false ],
			'note'          => [ 'type' => 'string', 'required' => false ],
			'action'        => [ 'type' => 'string', 'required' => false, 'enum' => [ 'trash', 'untrash', 'delete' ] ],
		],
	] );
} );

/** [{key: 'processing', label: 'در حال انجام'}, …] — همه‌ی وضعیت‌های فروشگاه‌ساز و افزونه‌ها (جایگاه…) */
function stlh_orders_statuses(): array {
	$out = [];
	foreach ( function_exists( 'wc_get_order_statuses' ) ? wc_get_order_statuses() : [] as $k => $label ) {
		$out[] = [ 'key' => preg_replace( '/^wc-/', '', $k ), 'label' => wp_strip_all_tags( $label ) ];
	}
	$out[] = [ 'key' => 'trash', 'label' => stlh_label( 'order_trash' ) ]; // فقط نمایش؛ با `action` جابه‌جا می‌شود، نه با `status`
	return $out;
}

function stlh_order_summary( WC_Order $o ): array {
	global $wpdb;
	$items = [];
	foreach ( $o->get_items() as $it ) {
		$p       = $it->get_product();
		$items[] = [
			'name'       => $it->get_name(),
			'qty'        => (int) $it->get_quantity(),
			'total'      => (float) $it->get_total(),
			'product_id' => (int) $it->get_product_id(),
			'sku'        => $p ? (string) $p->get_sku() : '',
		];
	}
	$sms   = [];
	$table = $wpdb->prefix . 'woocommerce_ir_sms_archive';
	if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) ) === $table ) {
		$sms = (array) $wpdb->get_results( $wpdb->prepare( "SELECT type, result, date FROM `{$table}` WHERE post_id = %d ORDER BY id DESC LIMIT 5", $o->get_id() ), ARRAY_A ); // phpcs:ignore
	}
	$st     = $o->get_status();
	$labels = function_exists( 'wc_get_order_statuses' ) ? wc_get_order_statuses() : [];
	$ship   = $o->has_shipping_address();
	return [
		'id'           => $o->get_id(),
		'number'       => (string) $o->get_order_number(),
		'status'       => $st,
		'statusLabel'  => 'trash' === $st ? stlh_label( 'order_trash' ) : wp_strip_all_tags( $labels[ 'wc-' . $st ] ?? $st ),
		'created_gmt'  => $o->get_date_created() ? gmdate( 'c', $o->get_date_created()->getTimestamp() ) : '',
		'modified_gmt' => $o->get_date_modified() ? gmdate( 'c', $o->get_date_modified()->getTimestamp() ) : '',
		'paid_gmt'     => $o->get_date_paid() ? gmdate( 'c', $o->get_date_paid()->getTimestamp() ) : '',
		'total'        => (float) $o->get_total(),
		'currency'     => $o->get_currency(),
		'payment'      => $o->get_payment_method_title(),
		'payment_method' => $o->get_payment_method(), // کد: WC_Sep_Payment_Gateway، WC_ZPal، bacs (واریز به شبا) — Novo، ۲۸ آبان ۱۴۰۵
		'shipping'     => $o->get_shipping_method(),
		'customer'     => [
			'name'     => trim( $o->get_billing_first_name() . ' ' . $o->get_billing_last_name() ),
			'phone'    => $o->get_billing_phone(),
			'state'    => $ship ? $o->get_shipping_state() : $o->get_billing_state(),
			'city'     => $ship ? $o->get_shipping_city() : $o->get_billing_city(),
			'address'  => $ship ? trim( $o->get_shipping_address_1() . ' ' . $o->get_shipping_address_2() ) : trim( $o->get_billing_address_1() . ' ' . $o->get_billing_address_2() ),
			'postcode' => $ship ? $o->get_shipping_postcode() : $o->get_billing_postcode(),
			'user_id'  => (int) $o->get_customer_id(),
		],
		'note'         => (string) $o->get_customer_note(),
		'tracking'     => (string) $o->get_meta( 'post_barcode' ),
		'bank'         => function_exists( 'stlh_bank_summary' ) ? stlh_bank_summary( $o ) : null, // واریز به شبا: کد پیگیری، زمان، فیش (bank-receipt.php)
		'items'        => $items,
		'sms'          => $sms,
	];
}

function stlh_orders_feed( WP_REST_Request $req ) {
	if ( ! function_exists( 'wc_get_orders' ) ) {
		return new WP_Error( 'stlh_no_wc', 'فروشگاه‌ساز فعال نیست.', [ 'status' => 500 ] );
	}
	$id = (int) $req->get_param( 'id' );
	if ( $id ) {
		$o = wc_get_order( $id );
		return $o instanceof WC_Order ? [ stlh_order_summary( $o ) ] : new WP_Error( 'stlh_order_not_found', 'سفارش پیدا نشد.', [ 'status' => 404 ] );
	}
	$after = strtotime( (string) $req->get_param( 'after' ) ) ?: 0;
	$args  = [
		'type'    => 'shop_order',
		'limit'   => min( 50, max( 1, (int) $req->get_param( 'limit' ) ) ),
		'orderby' => 'modified',
		'order'   => 'ASC',
		'status'  => array_merge( array_keys( wc_get_order_statuses() ), [ 'trash' ] ), // زباله‌دان هم (Novo، ۳۰ آبان ۱۴۰۵)
	];
	if ( $after ) {
		$args['date_modified'] = '>' . $after;
	}
	return array_map( 'stlh_order_summary', wc_get_orders( $args ) );
}

function stlh_orders_update( WP_REST_Request $req ) {
	$o = wc_get_order( (int) $req['id'] );
	if ( ! $o instanceof WC_Order ) {
		return new WP_Error( 'stlh_order_not_found', 'سفارش پیدا نشد.', [ 'status' => 404 ] );
	}
	// زباله‌دان / برگرداندن / پاک کردنِ همیشگی از Novo (صاحب فروشگاه، ۳۰ آبان ۱۴۰۵: «مدیریتِ کاملِ سفارش از Novo، بی رفتن به وردپرس»)
	$action = (string) $req->get_param( 'action' );
	if ( '' !== $action ) {
		return stlh_orders_action( $o, $action );
	}
	$status = $req->get_param( 'status' );
	$code   = $req->get_param( 'tracking_code' );
	$note   = $req->get_param( 'note' );
	if ( null !== $status && '' !== $status ) {
		$status = preg_replace( '/^wc-/', '', sanitize_key( (string) $status ) );
		if ( ! array_key_exists( 'wc-' . $status, wc_get_order_statuses() ) ) {
			return new WP_Error( 'stlh_order_bad_status', 'وضعیت نامعتبر است.', [ 'status' => 400 ] );
		}
		if ( $o->get_status() !== $status ) {
			$o->update_status( $status, 'از Novo:' ); // پیامک و ایمیلِ همان وضعیت مثلِ پیشخوان
		}
	}
	if ( null !== $code && '' !== trim( (string) $code ) ) {
		$code = sanitize_text_field( (string) $code );
		if ( $o->get_meta( 'post_barcode' ) !== $code ) {
			$o->update_meta_data( 'post_barcode', $code );
			$o->save();
			$o->add_order_note( 'کدِ رهگیریِ پست از Novo: ' . $code );
			do_action( 'pws_save_order_post_barcode', $o, $code ); // پیامکِ رهگیری (افزونه‌ی پیامک)
		}
	}
	if ( null !== $note && '' !== trim( (string) $note ) ) {
		$o->add_order_note( 'از Novo: ' . sanitize_textarea_field( (string) $note ) );
	}
	$o = wc_get_order( $o->get_id() );
	return stlh_order_summary( $o );
}

/** trash | untrash | delete — پاک کردنِ همیشگی فقط برای سفارشِ داخلِ زباله‌دان، لغوشده یا ناموفق */
function stlh_orders_action( WC_Order $o, string $action ) {
	$id = $o->get_id();
	if ( 'trash' === $action ) {
		if ( 'trash' !== $o->get_status() ) {
			$o->add_order_note( stlh_label( 'order_trash_note' ) );
			$o->delete( false );
		}
	} elseif ( 'untrash' === $action ) {
		if ( 'trash' !== $o->get_status() ) {
			return new WP_Error( 'stlh_order_not_trash', stlh_label( 'order_err_not_trash' ), [ 'status' => 409 ] );
		}
		$store = $o->get_data_store();
		$done  = method_exists( $store, 'untrash_order' ) ? $store->untrash_order( $o ) : (bool) wp_untrash_post( $id );
		if ( ! $done ) {
			return new WP_Error( 'stlh_order_untrash_failed', stlh_label( 'order_err_untrash' ), [ 'status' => 500 ] );
		}
		$o = wc_get_order( $id );
		if ( $o instanceof WC_Order ) {
			$o->add_order_note( stlh_label( 'order_untrash_note' ) );
		}
	} elseif ( 'delete' === $action ) {
		if ( ! in_array( $o->get_status(), [ 'trash', 'cancelled', 'failed' ], true ) ) {
			return new WP_Error( 'stlh_order_delete_refused', stlh_label( 'order_err_delete' ), [ 'status' => 409 ] );
		}
		$o->delete( true );
		return [ 'id' => $id, 'deleted' => true ];
	}
	$o = wc_get_order( $id );
	return $o instanceof WC_Order ? stlh_order_summary( $o ) : new WP_Error( 'stlh_order_not_found', 'سفارش پیدا نشد.', [ 'status' => 404 ] );
}
