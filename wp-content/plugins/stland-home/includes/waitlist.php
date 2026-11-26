<?php
/**
 * «شبیهِ این رسید خبرم کن» (پیشنهادهای ارتقای ۳۰ آبان ۱۴۰۵؛ صاحب فروشگاه: «مابقی را درست کن»).
 *
 * هر گوشیِ کارکرده تک‌نسخه است؛ وقتی فروخته شد صفحه‌اش بن‌بست می‌شد. حالا در صفحه‌ی گوشیِ ناموجود یک کادر می‌آید: «وقتی آیفون
 * {نسل}ِ دیگری رسید خبرتان کنیم؟» با شماره و نام. درخواست در خودِ وردپرس می‌ماند («منتظرانِ گوشی» در پیشخوان) و یک نامه به
 * مدیر می‌رود؛ Novo از `GET stland/v1/waitlist` درخواست‌ها را با «گوشی‌های موجودِ هم‌نسل» (`matches`) می‌گیرد تا صاحب فروشگاه
 * تماس بگیرد. پیامکِ خودکار به مشتری با الگوی ۵۹۹۸۴۵ ملی‌پیامک («استوک لند: آیفون {0} که منتظرش بودید رسید. برای خرید:
 * stland.ir»، صاحب فروشگاه ۶ آذر ۱۴۰۵) — پایینِ همین فایل.
 * ⛔ هرزنامه مثلِ فرمِ معاوضه: فیلدِ پنهان و سقفِ ۵ درخواست در ساعت برای هر آی‌پی؛ صفحه در حافظه‌ی موقت می‌ماند، پس کدِ یک‌بارمصرف نه.
 */
defined( 'ABSPATH' ) || exit;

const STLH_WAIT_PT = 'stlh_wait';

add_action( 'init', static function (): void {
	register_post_type( STLH_WAIT_PT, [
		'label'        => 'منتظرانِ گوشی',
		'labels'       => [ 'name' => 'منتظرانِ گوشی', 'singular_name' => 'درخواستِ «خبرم کن»', 'menu_name' => 'منتظرانِ گوشی' ],
		'public'       => false,
		'show_ui'      => true,
		'show_in_menu' => true,
		'menu_icon'    => 'dashicons-bell',
		'supports'     => [ 'title', 'editor' ],
		'capabilities' => [ 'create_posts' => 'do_not_allow' ],
		'map_meta_cap' => true,
	] );
} );

function stlh_wait_box( WC_Product $p ): string {
	$gen = stlh_model_number( $p->get_name() );
	if ( $gen < 7 ) {
		return '';
	}
	$model = stlh_label( 'finder_model', [ '{نسل}' => stlh_fa( (string) ( 10 === $gen ? 'X' : ( 9 === $gen ? 'SE' : $gen ) ) ) ] );
	$flag  = isset( $_GET['stlh_wait'] ) ? sanitize_key( wp_unslash( $_GET['stlh_wait'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification
	$box   = 'margin:14px 0;padding:14px;border:1.5px solid #1d4ed8;border-radius:14px;background:#f3f6ff;line-height:1.8;text-align:right';
	if ( 'ok' === $flag ) {
		return '<div class="stlh-wait" dir="rtl" style="' . $box . '"><b style="color:#166534">' . esc_html( stlh_label( 'wait_done', [ '{مدل}' => $model ] ) ) . '</b></div>';
	}
	$err = in_array( $flag, [ 'bad', 'many' ], true ) ? '<div style="color:#991b1b;font-weight:700;margin-bottom:6px">' . esc_html( stlh_label( 'many' === $flag ? 'wait_err_many' : 'wait_err_bad' ) ) . '</div>' : '';
	$in  = 'width:100%;height:44px;border:1px solid #cbd5e1;border-radius:10px;padding:0 10px;font-size:15px;background:#fff;font-family:inherit;box-sizing:border-box';
	return '<div class="stlh-wait" id="stlh-wait" dir="rtl" style="' . $box . '">'
		. '<div style="font-weight:900;color:#1e3a8a;font-size:15px">' . esc_html( stlh_label( 'wait_title', [ '{مدل}' => $model ] ) ) . '</div>'
		. '<div style="color:#334155;font-size:13px;margin:2px 0 10px">' . esc_html( stlh_label( 'wait_text' ) ) . '</div>' . $err
		. '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" style="display:grid;grid-template-columns:1fr 1fr;gap:8px">'
		. '<input type="hidden" name="action" value="stlh_wait"><input type="hidden" name="pid" value="' . (int) $p->get_id() . '">'
		. '<input type="text" name="website" value="" tabindex="-1" autocomplete="off" aria-hidden="true" style="position:absolute;left:-9999px;width:1px;height:1px;opacity:0">'
		. '<input type="tel" name="phone" required inputmode="tel" dir="ltr" placeholder="' . esc_attr( stlh_label( 'wait_phone' ) ) . '" style="' . $in . '">'
		. '<input type="text" name="name" placeholder="' . esc_attr( stlh_label( 'wait_name' ) ) . '" style="' . $in . '">'
		. '<button type="submit" style="grid-column:1/-1;height:44px;border:0;border-radius:12px;background:#1d4ed8;color:#fff;font-weight:800;font-size:15px;font-family:inherit;cursor:pointer">' . esc_html( stlh_label( 'wait_btn' ) ) . '</button>'
		. '</form></div>';
}

function stlh_wait_print(): void {
	static $done = false;
	global $product;
	$p = $product instanceof WC_Product ? $product : ( function_exists( 'wc_get_product' ) ? wc_get_product( get_the_ID() ) : null );
	if ( $done || ! $p instanceof WC_Product || $p->is_in_stock() || ! is_product() ) {
		return;
	}
	$html = stlh_wait_box( $p );
	if ( '' !== $html ) {
		$done = true;
		echo $html; // phpcs:ignore WordPress.Security.EscapeOutput -- escaped inside
	}
}
add_action( 'woocommerce_single_product_summary', 'stlh_wait_print', 31 );
add_action( 'woocommerce_single_product_end', 'stlh_wait_print', 3 );

function stlh_wait_handle(): void {
	$pid  = isset( $_POST['pid'] ) ? absint( $_POST['pid'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification -- صفحه در حافظه‌ی موقت؛ فیلدِ پنهان و سقفِ آی‌پی
	$back = $pid ? get_permalink( $pid ) : home_url( '/' );
	$go   = static function ( string $f ) use ( $back ): void {
		wp_safe_redirect( add_query_arg( 'stlh_wait', $f, $back ) . '#stlh-wait' );
		exit;
	};
	if ( ! empty( $_POST['website'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification
		$go( 'ok' ); // ربات چیزی نفهمد
	}
	$phone = preg_replace( '/\D+/', '', stlh_ai_en_digits( sanitize_text_field( wp_unslash( $_POST['phone'] ?? '' ) ) ) ); // phpcs:ignore WordPress.Security.NonceVerification
	$name  = mb_substr( sanitize_text_field( wp_unslash( $_POST['name'] ?? '' ) ), 0, 60 ); // phpcs:ignore WordPress.Security.NonceVerification
	$p     = $pid ? wc_get_product( $pid ) : null;
	if ( ! $p || ! preg_match( '/^(?:98|0)?9\d{9}$/', $phone ) ) {
		$go( 'bad' );
	}
	$phone = '0' . substr( $phone, -10 );
	$ip    = preg_replace( '/[^0-9a-f:.]/i', '', (string) ( $_SERVER['REMOTE_ADDR'] ?? '' ) );
	$rate  = 'stlh_wait_rate_' . md5( $ip );
	$n     = (int) get_transient( $rate );
	if ( $n >= 5 ) {
		$go( 'many' );
	}
	set_transient( $rate, $n + 1, HOUR_IN_SECONDS );
	$gen = stlh_model_number( $p->get_name() );
	$id  = wp_insert_post( [
		'post_type'    => STLH_WAIT_PT,
		'post_status'  => 'publish',
		'post_title'   => sprintf( 'آیفون %s — %s', 10 === $gen ? 'X' : ( 9 === $gen ? 'SE' : $gen ), $phone ),
		'post_content' => sprintf( "مشتری منتظرِ گوشیِ شبیهِ «%s» است.\nشماره: %s\nنام: %s", $p->get_name(), $phone, $name ?: '—' ),
	] );
	if ( $id && ! is_wp_error( $id ) ) {
		update_post_meta( $id, 'stlh_gen', $gen );
		update_post_meta( $id, 'stlh_phone', $phone );
		update_post_meta( $id, 'stlh_name', $name );
		update_post_meta( $id, 'stlh_product', $pid );
		wp_mail( get_option( 'admin_email' ), 'منتظرِ تازه‌ی گوشی: ' . get_the_title( $id ), get_post_field( 'post_content', $id ) );
	}
	$go( 'ok' );
}
add_action( 'admin_post_stlh_wait', 'stlh_wait_handle' );
add_action( 'admin_post_nopriv_stlh_wait', 'stlh_wait_handle' );

/* ───── Novo: درخواست‌ها با گوشی‌های موجودِ هم‌نسل ───── */

add_action( 'rest_api_init', static function (): void {
	register_rest_route( 'stland/v1', '/waitlist', [
		'methods'             => 'GET',
		'permission_callback' => static fn() => current_user_can( 'manage_options' ),
		'args'                => [ 'days' => [ 'type' => 'integer', 'default' => 60, 'minimum' => 1, 'maximum' => 365 ] ],
		'callback'            => static function ( WP_REST_Request $req ) {
			$items = get_posts( [ 'post_type' => STLH_WAIT_PT, 'post_status' => 'publish', 'numberposts' => 200, 'date_query' => [ [ 'after' => (int) $req['days'] . ' days ago' ] ] ] );
			$stock = [];
			foreach ( function_exists( 'stlh_finder_items' ) ? stlh_finder_items() : [] as [ $p, $gen, $price ] ) {
				$stock[ $gen ][] = [ 'id' => $p->get_id(), 'name' => $p->get_name(), 'price' => $price, 'url' => $p->get_permalink(), 'created_gmt' => $p->get_date_created() ? gmdate( 'c', $p->get_date_created()->getTimestamp() ) : '' ];
			}
			return array_map( static function ( WP_Post $w ) use ( $stock ) {
				$gen  = (int) get_post_meta( $w->ID, 'stlh_gen', true );
				$from = get_post_meta( $w->ID, 'stlh_product', true );
				return [
					'id'          => $w->ID,
					'created_gmt' => gmdate( 'c', strtotime( $w->post_date_gmt ) ),
					'gen'         => $gen,
					'phone'       => (string) get_post_meta( $w->ID, 'stlh_phone', true ),
					'name'        => (string) get_post_meta( $w->ID, 'stlh_name', true ),
					'from'        => [ 'id' => (int) $from, 'name' => get_the_title( (int) $from ) ],
					'matches'     => $stock[ $gen ] ?? [],
				];
			}, $items );
		},
	] );
} );

/* ───── پیامکِ «رسید» (۶ آذر ۱۴۰۵) ─────
 * وقتی گوشیِ هم‌نسل منتشر/موجود شد (Novo می‌فرستد)، یک دقیقه بعد در پس‌زمینه به هر منتظرِ ۶۰ روزِ اخیرِ همان نسل یک پیامک با
 * الگوی ملی‌پیامک از راهِ افزونه‌ی «پیامک حرفه‌ای ووکامرس» (`PWSMS()->send_sms`، بایگانی در همان افزونه). هر درخواست فقط یک
 * بار: «فرستاده شد» (`stlh_notified`) فقط با جوابِ درستِ سامانه نوشته می‌شود — تا الگو تأیید نشده، بارِ بعد دوباره امتحان می‌شود.
 * ⛔ فقط خواندنِ محصول؛ موجودی و قیمت دست نمی‌خورند. */
const STLH_WAIT_SMS_PATTERN = '599845';

function stlh_wait_gen_label( int $gen ): string {
	return stlh_fa( 10 === $gen ? 'X' : ( 9 === $gen ? 'SE' : (string) $gen ) );
}

function stlh_wait_queue( int $id ): void {
	if ( 'product' !== get_post_type( $id ) || wp_next_scheduled( 'stlh_wait_notify', [ $id ] ) ) {
		return;
	}
	wp_schedule_single_event( time() + MINUTE_IN_SECONDS, 'stlh_wait_notify', [ $id ] );
}
add_action( 'woocommerce_product_set_stock_status', static function ( $id, $status ): void {
	if ( 'instock' === $status ) {
		stlh_wait_queue( (int) $id );
	}
}, 10, 2 );
add_action( 'transition_post_status', static function ( $new, $old, $post ): void {
	if ( 'publish' === $new && 'publish' !== $old && 'product' === $post->post_type ) {
		stlh_wait_queue( (int) $post->ID );
	}
}, 10, 3 );

/** @return array<int,string> شناسه‌ی درخواست => نتیجه */
function stlh_wait_notify( int $id ): array {
	if ( ! function_exists( 'PWSMS' ) || ! function_exists( 'wc_get_products' ) ) {
		return [];
	}
	// همان شرطِ «آیفونِ من را پیدا کن»: منتشر، موجود، در دسته‌ی گوشی (با زیردسته‌ها)
	$p = wc_get_products( [ 'include' => [ $id ], 'status' => 'publish', 'stock_status' => 'instock', 'category' => [ (string) stlh_opt( 'finder_cat' ) ], 'limit' => 1 ] )[0] ?? null;
	$gen = $p ? stlh_model_number( $p->get_name() ) : 0;
	if ( $gen < 7 ) {
		return [];
	}
	$out = [];
	$waits = get_posts( [
		'post_type'   => STLH_WAIT_PT,
		'post_status' => 'publish',
		'numberposts' => 50,
		'date_query'  => [ [ 'after' => '60 days ago' ] ],
		'meta_query'  => [
			[ 'key' => 'stlh_gen', 'value' => $gen, 'type' => 'NUMERIC' ],
			[ 'key' => 'stlh_notified', 'compare' => 'NOT EXISTS' ],
		],
	] );
	foreach ( $waits as $w ) {
		$phone = (string) get_post_meta( $w->ID, 'stlh_phone', true );
		$res   = PWSMS()->send_sms( [ 'mobile' => $phone, 'message' => 'pattern:' . STLH_WAIT_SMS_PATTERN . "\nmodel:" . stlh_wait_gen_label( $gen ), 'post_id' => $id ] );
		$out[ $w->ID ] = true === $res ? 'ok' : (string) $res;
		if ( true === $res ) {
			update_post_meta( $w->ID, 'stlh_notified', gmdate( 'c' ) . ' #' . $id );
		}
	}
	return $out;
}
add_action( 'stlh_wait_notify', 'stlh_wait_notify' );
