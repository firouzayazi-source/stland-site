<?php
/**
 * «واریز به شماره شبا» از صفر تا صد (صاحب فروشگاه، ۲۹ آبان ۱۴۰۵، با عکسِ سفارش‌های آزمایشیِ ۴۵۴۰۶ و ۴۵۴۰۷: «شبا لای متن گم است»).
 *
 *  ۱. کارتِ بزرگ بالای صفحه‌ی «سفارش دریافت شد» (`woocommerce_before_thankyou`) و بالای «حساب من ← سفارش» (`woocommerce_view_order`،
 *     با جابه‌جایی به بالای محتوا): شماره شبا، صاحبِ حساب، بانک، مبلغِ دقیق (تومان و ریال) و شماره‌ی سفارش، هر کدام با دکمه‌ی کپی.
 *     جدولِ پیش‌فرضِ فروشگاه‌ساز در همان صفحه برداشته می‌شود (ایمیل دست نمی‌خورد).
 *  ۲. فرمِ «پرداخت کردم»: کدِ پیگیری و/یا عکسِ فیش (اختیاری؛ دست‌کم یکی). فرم با کلیدِ خودِ سفارش (`order_key`) پذیرفته می‌شود،
 *     پس مهمان هم می‌تواند؛ حداکثر ۶ بار برای هر سفارش.
 *  ۳. بعد از ثبت: وضعیتِ تازه‌ی `bank-sent` («پرداخت ثبت شد، در انتظار تأیید»)، کد و زمان در متای سفارش، فیش در
 *     `uploads/stlh-receipts/` (پوشه‌ی بسته با `.htaccess`، نامِ تصادفی)؛ یادداشتِ سفارش و پیامکِ مدیر (`sms-settings.php`).
 *  ۴. Novo: `bank` در `stland/v1/orders`، فیش از `GET stland/v1/orders/<id>/receipt` (base64)، تأیید = وضعیتِ `processing`،
 *     رد = `on-hold` (کارتِ مشتری «رسیدِ قبلی تأیید نشد» می‌گوید و دوباره می‌فرستد)، لغو = `cancelled`.
 * ⛔ شبا و حساب از تنظیماتِ «انتقال بانکی»ِ پیشخوان خوانده می‌شود، نه از مخزن.
 */
defined( 'ABSPATH' ) || exit;

const STLH_BANK_STATUS    = 'bank-sent';
const STLH_BANK_MAX_TRIES = 6;
const STLH_BANK_MAX_BYTES = 5 * 1024 * 1024;

/* ───────────── وضعیتِ «پرداخت ثبت شد» ───────────── */

add_action( 'init', static function (): void {
	register_post_status( 'wc-' . STLH_BANK_STATUS, [
		'label'                     => stlh_label( 'bank_status' ),
		'public'                    => false,
		'exclude_from_search'       => false,
		'show_in_admin_all_list'    => true,
		'show_in_admin_status_list' => true,
		/* translators: %s: count */
		'label_count'               => _n_noop( 'پرداختِ ثبت‌شده <span class="count">(%s)</span>', 'پرداختِ ثبت‌شده <span class="count">(%s)</span>' ), // phpcs:ignore WordPress.WP.I18n
	] );
} );

add_filter( 'wc_order_statuses', static function ( $statuses ) {
	$out = [];
	foreach ( (array) $statuses as $k => $v ) {
		$out[ $k ] = $v;
		if ( 'wc-on-hold' === $k ) {
			$out[ 'wc-' . STLH_BANK_STATUS ] = stlh_label( 'bank_status' );
		}
	}
	if ( ! isset( $out[ 'wc-' . STLH_BANK_STATUS ] ) ) {
		$out[ 'wc-' . STLH_BANK_STATUS ] = stlh_label( 'bank_status' );
	}
	return $out;
} );

// رد در Novo (برگشت از «پرداخت ثبت شد» به «در انتظار بررسی»): کارت به مشتری می‌گوید دوباره بفرستد
add_action( 'woocommerce_order_status_' . STLH_BANK_STATUS . '_to_on-hold', static function ( $order_id ): void {
	$o = wc_get_order( $order_id );
	if ( $o instanceof WC_Order ) {
		$o->update_meta_data( '_stlh_bank_rejected', gmdate( 'c' ) );
		$o->save_meta_data();
	}
} );

/* ───────────── داده ───────────── */

/** نخستین حسابِ «انتقال بانکی» که شبای درست دارد */
function stlh_bank_account(): ?array {
	foreach ( (array) get_option( 'woocommerce_bacs_accounts', [] ) as $acc ) {
		$iban = strtoupper( preg_replace( '/[\s\-]+/', '', stlh_ai_en_digits( (string) ( $acc['iban'] ?? '' ) ) ) );
		if ( preg_match( '/^IR\d{24}$/', $iban ) ) {
			return [
				'iban'   => $iban,
				'holder' => trim( (string) ( $acc['account_name'] ?? '' ) ),
				'bank'   => trim( (string) ( $acc['bank_name'] ?? '' ) ),
			];
		}
	}
	return null;
}

/** کارت برای این سفارش لازم است؟ */
function stlh_bank_card_applies( $order ): bool {
	return $order instanceof WC_Order && 'bacs' === $order->get_payment_method()
		&& $order->has_status( [ 'on-hold', 'pending', STLH_BANK_STATUS ] ) && null !== stlh_bank_account();
}

/** مبلغ به ریال (واحدِ فروشگاه تومان است) */
function stlh_bank_rial( float $amount ): int {
	return (int) round( stlh_pg_toman( $amount ) * 10 );
}

function stlh_bank_fa_num( int $n ): string {
	return stlh_fa( number_format( $n, 0, '.', '٬' ) );
}

/* ───────────── کارت ───────────── */

function stlh_bank_card( WC_Order $o ): string {
	$acc = stlh_bank_account();
	if ( ! $acc ) {
		return '';
	}
	$toman = (int) round( stlh_pg_toman( (float) $o->get_total() ) );
	$rial  = stlh_bank_rial( (float) $o->get_total() );
	$num   = (string) $o->get_order_number();
	$sent  = $o->has_status( STLH_BANK_STATUS );
	$rej   = ! $sent && $o->get_meta( '_stlh_bank_rejected' );
	$flag  = isset( $_GET['stlh_bank'] ) ? sanitize_key( wp_unslash( $_GET['stlh_bank'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification
	$iban_show = trim( chunk_split( $acc['iban'], 4, ' ' ) );

	$row = static function ( string $label, string $show, string $copy, bool $ltr = false ): string {
		return '<div class="stlh-bk-row"><span class="stlh-bk-l">' . esc_html( $label ) . '</span>'
			. '<span class="stlh-bk-v"' . ( $ltr ? ' dir="ltr"' : '' ) . '>' . esc_html( $show ) . '</span>'
			. ( '' !== $copy ? '<button type="button" class="stlh-bk-copy" data-copy="' . esc_attr( $copy ) . '">' . esc_html( stlh_label( 'bank_copy' ) ) . '</button>' : '<span></span>' )
			. '</div>';
	};

	$h  = '<div id="stlh-bank-card" class="stlh-bk" dir="rtl">';
	$h .= '<style>' . stlh_bank_css() . '</style>';
	if ( 'ok' === $flag ) {
		$h .= '<div class="stlh-bk-msg stlh-bk-ok">' . esc_html( stlh_label( 'bank_saved' ) ) . '</div>';
	} elseif ( $flag && 'ok' !== $flag ) {
		$h .= '<div class="stlh-bk-msg stlh-bk-err">' . esc_html( stlh_label( 'bank_err_' . ( in_array( $flag, [ 'empty', 'file', 'many' ], true ) ? $flag : 'bad' ) ) ) . '</div>';
	}
	if ( $sent ) {
		$ref = (string) $o->get_meta( '_stlh_bank_ref' );
		$h  .= '<div class="stlh-bk-state">' . esc_html( stlh_label( 'bank_status' ) ) . '</div>'
			. '<p class="stlh-bk-p">' . esc_html( stlh_label( 'bank_sent_note' ) )
			. ( '' !== $ref ? ' ' . esc_html( stlh_label( 'bank_ref' ) ) . ': <b dir="ltr">' . esc_html( $ref ) . '</b>' : '' ) . '</p>';
	} elseif ( $rej ) {
		$h .= '<div class="stlh-bk-msg stlh-bk-err">' . esc_html( stlh_label( 'bank_rejected' ) ) . '</div>';
	}
	$h .= '<h3 class="stlh-bk-t">' . esc_html( stlh_label( 'bank_card_title' ) ) . '</h3>';
	$h .= '<div class="stlh-bk-rows">'
		. $row( stlh_label( 'bacs_iban' ), $iban_show, $acc['iban'], true )
		. ( '' !== $acc['holder'] ? $row( stlh_label( 'bank_holder' ), $acc['holder'], '' ) : '' )
		. ( '' !== $acc['bank'] ? $row( stlh_label( 'bank_bank' ), $acc['bank'], '' ) : '' )
		. $row( stlh_label( 'bank_amount' ), stlh_bank_fa_num( $toman ) . ' ' . stlh_label( 'bank_toman' ), (string) $toman )
		. $row( stlh_label( 'bank_amount_rial' ), stlh_bank_fa_num( $rial ) . ' ' . stlh_label( 'bank_rial' ), (string) $rial )
		. $row( stlh_label( 'bank_order' ), stlh_fa( $num ), $num )
		. '</div>';
	$h .= '<p class="stlh-bk-p">' . esc_html( stlh_label( 'bank_howto', [ '{سفارش}' => stlh_fa( $num ) ] ) ) . '</p>';

	// فرمِ «پرداخت کردم»
	$open = $sent ? '' : ' open';
	$h   .= '<details class="stlh-bk-form"' . $open . '><summary>' . esc_html( stlh_label( $sent ? 'bank_resend' : 'bank_paid_btn' ) ) . '</summary>'
		. '<form method="post" enctype="multipart/form-data" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">'
		. '<input type="hidden" name="action" value="stlh_bank_paid">'
		. '<input type="hidden" name="order_id" value="' . esc_attr( (string) $o->get_id() ) . '">'
		. '<input type="hidden" name="key" value="' . esc_attr( $o->get_order_key() ) . '">'
		. wp_nonce_field( 'stlh_bank_' . $o->get_id(), '_stlh_nonce', true, false )
		. '<label>' . esc_html( stlh_label( 'bank_ref' ) ) . '<input type="text" name="ref" maxlength="40" inputmode="numeric" dir="ltr" autocomplete="off" placeholder="' . esc_attr( stlh_label( 'bank_ref_ph' ) ) . '"></label>'
		. '<label>' . esc_html( stlh_label( 'bank_file' ) ) . '<input type="file" name="receipt" accept="image/*,application/pdf"></label>'
		. '<p class="stlh-bk-hint">' . esc_html( stlh_label( 'bank_form_hint' ) ) . '</p>'
		. '<button type="submit" class="stlh-bk-submit">' . esc_html( stlh_label( 'bank_paid_btn' ) ) . '</button>'
		. '</form></details>';
	$h .= '</div>';
	$h .= '<script data-no-optimize="1" data-no-defer="1">(function(){var c=document.getElementById("stlh-bank-card");if(!c)return;'
		. 'var t=document.querySelector(".woocommerce-MyAccount-content");if(t&&c.parentNode!==t&&t.contains(c))t.insertBefore(c,t.firstChild);'
		. 'c.addEventListener("click",function(e){var b=e.target.closest(".stlh-bk-copy");if(!b)return;var v=b.getAttribute("data-copy"),o=b.textContent;'
		. 'function ok(){b.textContent=' . wp_json_encode( stlh_label( 'bank_copied' ) ) . ';b.classList.add("is-ok");setTimeout(function(){b.textContent=o;b.classList.remove("is-ok")},1600)}'
		. 'if(navigator.clipboard&&window.isSecureContext){navigator.clipboard.writeText(v).then(ok,fb)}else{fb()}'
		. 'function fb(){var a=document.createElement("textarea");a.value=v;a.setAttribute("readonly","");a.style.position="fixed";a.style.opacity="0";document.body.appendChild(a);a.select();try{document.execCommand("copy");ok()}catch(x){}document.body.removeChild(a)}});'
		. 'var f=c.querySelector("form");if(f)f.addEventListener("submit",function(e){var r=f.ref.value.trim(),fl=f.receipt.files&&f.receipt.files[0];'
		. 'if(!r&&!fl){e.preventDefault();alert(' . wp_json_encode( stlh_label( 'bank_err_empty' ) ) . ');return}'
		. 'if(fl&&fl.size>' . STLH_BANK_MAX_BYTES . '){e.preventDefault();alert(' . wp_json_encode( stlh_label( 'bank_err_file' ) ) . ');return}'
		. 'f.querySelector("button[type=submit]").disabled=true})})();</script>';
	return $h;
}

function stlh_bank_css(): string {
	return '.stlh-bk{border:2px solid #1d4ed8;border-radius:16px;background:#fff;padding:18px;margin:0 0 22px;box-shadow:0 6px 24px rgba(29,78,216,.10);text-align:right;font-size:15px;line-height:1.8;max-width:100%;box-sizing:border-box}'
		. '.stlh-bk *{box-sizing:border-box}.stlh-bk-t{font-size:18px;font-weight:800;margin:0 0 12px;color:#111827}'
		. '.stlh-bk-rows{display:flex;flex-direction:column;gap:8px}'
		. '.stlh-bk-row{display:grid;grid-template-columns:118px 1fr auto;align-items:center;gap:10px;background:#f3f6ff;border-radius:12px;padding:10px 12px}'
		. '.stlh-bk-l{color:#4b5563;font-size:13px}.stlh-bk-v{font-weight:800;color:#111827;word-break:break-all;font-size:16px}'
		. '.stlh-bk-v[dir=ltr]{text-align:left;font-family:ui-monospace,Menlo,Consolas,monospace;letter-spacing:.5px}'
		. '.stlh-bk-copy{border:1px solid #1d4ed8;background:#fff;color:#1d4ed8;border-radius:10px;padding:6px 12px;font-size:13px;font-weight:700;cursor:pointer;white-space:nowrap;font-family:inherit}'
		. '.stlh-bk-copy.is-ok{background:#16a34a;border-color:#16a34a;color:#fff}'
		. '.stlh-bk-p{margin:12px 0 0;color:#374151;font-size:14px}.stlh-bk-hint{margin:0;color:#6b7280;font-size:13px}'
		. '.stlh-bk-state{display:inline-block;background:#fef3c7;color:#92400e;border-radius:999px;padding:4px 14px;font-weight:800;margin-bottom:6px}'
		. '.stlh-bk-msg{border-radius:12px;padding:10px 14px;margin-bottom:12px;font-weight:700}.stlh-bk-ok{background:#dcfce7;color:#166534}.stlh-bk-err{background:#fee2e2;color:#991b1b}'
		. '.stlh-bk-form{margin-top:14px;border-top:1px dashed #cbd5e1;padding-top:12px}'
		. '.stlh-bk-form summary{cursor:pointer;font-weight:800;color:#1d4ed8;list-style:none;font-size:16px}.stlh-bk-form summary::-webkit-details-marker{display:none}'
		. '.stlh-bk-form form{display:flex;flex-direction:column;gap:10px;margin-top:10px}'
		. '.stlh-bk-form label{display:flex;flex-direction:column;gap:4px;font-size:14px;color:#374151;font-weight:700}'
		. '.stlh-bk-form input[type=text],.stlh-bk-form input[type=file]{width:100%;border:1px solid #cbd5e1;border-radius:10px;padding:10px;font-size:15px;background:#fff;font-family:inherit}'
		. '.stlh-bk-submit{background:#1d4ed8;color:#fff;border:0;border-radius:12px;padding:12px;font-size:16px;font-weight:800;cursor:pointer;font-family:inherit}.stlh-bk-submit[disabled]{opacity:.6}'
		. '@media(max-width:560px){.stlh-bk{padding:14px}.stlh-bk-v[dir=ltr]{font-size:14px;letter-spacing:0}.stlh-bk-row{grid-template-columns:1fr auto;grid-template-areas:"l c" "v v"}.stlh-bk-l{grid-area:l}.stlh-bk-copy{grid-area:c}.stlh-bk-v{grid-area:v}.stlh-bk-row>span:empty{display:none}}';
}

/* ───────────── جاها ───────────── */

add_action( 'woocommerce_before_thankyou', static function ( $order_id ): void {
	$o = wc_get_order( $order_id );
	if ( stlh_bank_card_applies( $o ) ) {
		echo stlh_bank_card( $o ); // phpcs:ignore WordPress.Security.EscapeOutput -- escaped inside
	}
}, 1 );

// جدول و متنِ پیش‌فرضِ فروشگاه‌ساز در همان صفحه (کارت جایش هست)
add_action( 'woocommerce_thankyou_bacs', static function ( $order_id ): void {
	if ( ! stlh_bank_card_applies( wc_get_order( $order_id ) ) || ! function_exists( 'WC' ) ) {
		return;
	}
	$gw = WC()->payment_gateways()->payment_gateways()['bacs'] ?? null;
	if ( $gw ) {
		remove_action( 'woocommerce_thankyou_bacs', [ $gw, 'thankyou_page' ] );
	}
}, 1 );

add_action( 'woocommerce_view_order', static function ( $order_id ): void {
	$o = wc_get_order( $order_id );
	if ( stlh_bank_card_applies( $o ) && (int) $o->get_customer_id() === get_current_user_id() ) {
		echo stlh_bank_card( $o ); // phpcs:ignore WordPress.Security.EscapeOutput -- escaped inside
	}
}, 1 );

// فهرستِ «سفارش‌ها»: دکمه‌ی «پرداخت / ثبتِ فیش»
add_filter( 'woocommerce_my_account_my_orders_actions', static function ( $actions, $order ) {
	if ( stlh_bank_card_applies( $order ) && ! $order->has_status( STLH_BANK_STATUS ) ) {
		$actions = [ 'stlh_bank' => [ 'url' => $order->get_view_order_url(), 'name' => stlh_label( 'bank_list_btn' ) ] ] + (array) $actions;
	}
	return $actions;
}, 10, 2 );

/* ───────────── ثبتِ «پرداخت کردم» ───────────── */

function stlh_bank_dir(): array {
	$up   = wp_upload_dir( null, false );
	$dir  = trailingslashit( $up['basedir'] ) . 'stlh-receipts';
	if ( ! is_dir( $dir ) ) {
		wp_mkdir_p( $dir );
	}
	if ( ! file_exists( $dir . '/.htaccess' ) ) {
		file_put_contents( $dir . '/.htaccess', "Require all denied\nDeny from all\n" ); // phpcs:ignore WordPress.WP.AlternativeFunctions
	}
	if ( ! file_exists( $dir . '/index.php' ) ) {
		file_put_contents( $dir . '/index.php', "<?php // silence\n" ); // phpcs:ignore WordPress.WP.AlternativeFunctions
	}
	return [ 'dir' => $dir, 'base' => $up['basedir'] ];
}

function stlh_bank_paid_handle(): void {
	$id   = isset( $_POST['order_id'] ) ? absint( $_POST['order_id'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification -- checked below
	$key  = isset( $_POST['key'] ) ? sanitize_text_field( wp_unslash( $_POST['key'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification
	$o    = $id ? wc_get_order( $id ) : null;
	$back = wp_get_referer() ?: home_url( '/' );
	$back = remove_query_arg( 'stlh_bank', $back );
	$go   = static function ( string $flag ) use ( $back ): void {
		wp_safe_redirect( add_query_arg( 'stlh_bank', $flag, $back ) . '#stlh-bank-card' );
		exit;
	};
	if ( ! $o instanceof WC_Order || '' === $key || ! hash_equals( $o->get_order_key(), $key ) || ! stlh_bank_card_applies( $o )
		|| ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['_stlh_nonce'] ?? '' ) ), 'stlh_bank_' . $id ) ) {
		$go( 'bad' );
	}
	$tries = (int) $o->get_meta( '_stlh_bank_tries' );
	if ( $tries >= STLH_BANK_MAX_TRIES ) {
		$go( 'many' );
	}
	$ref  = trim( preg_replace( '/[^\p{L}\p{N}\-\/ ]+/u', '', stlh_ai_en_digits( sanitize_text_field( wp_unslash( $_POST['ref'] ?? '' ) ) ) ) );
	$ref  = mb_substr( $ref, 0, 40 );
	$file = '';
	if ( ! empty( $_FILES['receipt']['name'] ) && UPLOAD_ERR_NO_FILE !== (int) ( $_FILES['receipt']['error'] ?? 0 ) ) { // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
		$f = $_FILES['receipt']; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
		if ( UPLOAD_ERR_OK !== (int) $f['error'] || (int) $f['size'] <= 0 || (int) $f['size'] > STLH_BANK_MAX_BYTES ) {
			$go( 'file' );
		}
		$mimes = [ 'jpg|jpeg' => 'image/jpeg', 'png' => 'image/png', 'webp' => 'image/webp', 'heic' => 'image/heic', 'pdf' => 'application/pdf' ];
		$chk   = wp_check_filetype_and_ext( $f['tmp_name'], $f['name'], $mimes );
		if ( empty( $chk['ext'] ) || empty( $chk['type'] ) ) {
			$go( 'file' );
		}
		require_once ABSPATH . 'wp-admin/includes/file.php';
		$d         = stlh_bank_dir();
		$f['name'] = 'r' . $id . '-' . wp_generate_password( 24, false, false ) . '.' . $chk['ext'];
		$to_dir    = static fn( $u ) => array_merge( $u, [ 'path' => $d['dir'], 'url' => '', 'subdir' => '/stlh-receipts' ] );
		add_filter( 'upload_dir', $to_dir );
		$up = wp_handle_upload( $f, [ 'test_form' => false, 'mimes' => $mimes ] );
		remove_filter( 'upload_dir', $to_dir );
		if ( empty( $up['file'] ) || ! empty( $up['error'] ) ) {
			$go( 'file' );
		}
		$file = ltrim( str_replace( wp_normalize_path( $d['base'] ), '', wp_normalize_path( $up['file'] ) ), '/' );
	}
	if ( '' === $ref && '' === $file ) {
		$go( 'empty' );
	}
	$o->update_meta_data( '_stlh_bank_tries', $tries + 1 );
	$o->update_meta_data( '_stlh_bank_ref', $ref );
	$o->update_meta_data( '_stlh_bank_sent', gmdate( 'c' ) );
	if ( '' !== $file ) {
		$o->update_meta_data( '_stlh_bank_file', $file );
	} elseif ( $o->get_meta( '_stlh_bank_rejected' ) ) {
		$o->delete_meta_data( '_stlh_bank_file' ); // فیشِ ردشده‌ی قبلی، فیشِ این پرداخت نیست
	}
	$o->delete_meta_data( '_stlh_bank_rejected' );
	$o->save();
	$note = stlh_label( 'bank_note_admin', [ '{کد}' => '' !== $ref ? $ref : '—', '{فیش}' => '' !== $file ? stlh_label( 'bank_note_file' ) : '' ] );
	if ( $o->has_status( STLH_BANK_STATUS ) ) {
		$o->add_order_note( $note );
	} else {
		$o->update_status( STLH_BANK_STATUS, $note ); // پیامکِ مدیر (sms-settings)
	}
	$go( 'ok' );
}
add_action( 'admin_post_stlh_bank_paid', 'stlh_bank_paid_handle' );
add_action( 'admin_post_nopriv_stlh_bank_paid', 'stlh_bank_paid_handle' );

/* ───────────── Novo ───────────── */

/** خلاصه‌ی پرداختِ شبا برای `stland/v1/orders` */
function stlh_bank_summary( WC_Order $o ): ?array {
	if ( 'bacs' !== $o->get_payment_method() ) {
		return null;
	}
	return [
		'ref'          => (string) $o->get_meta( '_stlh_bank_ref' ),
		'sent_gmt'     => (string) $o->get_meta( '_stlh_bank_sent' ),
		'has_file'     => '' !== (string) $o->get_meta( '_stlh_bank_file' ),
		'rejected_gmt' => (string) $o->get_meta( '_stlh_bank_rejected' ),
		'tries'        => (int) $o->get_meta( '_stlh_bank_tries' ),
	];
}

add_action( 'rest_api_init', static function (): void {
	register_rest_route( 'stland/v1', '/orders/(?P<id>\d+)/receipt', [
		'methods'             => 'GET',
		'permission_callback' => static fn() => current_user_can( 'manage_options' ),
		'callback'            => static function ( WP_REST_Request $req ) {
			$o   = wc_get_order( (int) $req['id'] );
			$rel = $o instanceof WC_Order ? (string) $o->get_meta( '_stlh_bank_file' ) : '';
			if ( '' === $rel || str_contains( $rel, '..' ) ) {
				return new WP_Error( 'stlh_no_receipt', 'فیشی برای این سفارش نیست.', [ 'status' => 404 ] );
			}
			$path = trailingslashit( wp_upload_dir( null, false )['basedir'] ) . $rel;
			if ( ! is_readable( $path ) ) {
				return new WP_Error( 'stlh_no_receipt', 'فایلِ فیش پیدا نشد.', [ 'status' => 404 ] );
			}
			$type = wp_check_filetype( $path )['type'] ?: 'application/octet-stream';
			return [
				'mime'        => $type,
				'name'        => basename( $path ),
				'size'        => (int) filesize( $path ),
				'data_base64' => base64_encode( (string) file_get_contents( $path ) ), // phpcs:ignore WordPress.WP.AlternativeFunctions
			];
		},
	] );
} );

// ایمیلِ مشتری: پیوندِ ثبتِ پرداخت (همان صفحه‌ی «سفارش دریافت شد» با کارت و فرم)
add_action( 'woocommerce_email_before_order_table', static function ( $order, $sent_to_admin, $plain = false ): void {
	if ( $sent_to_admin || ! stlh_bank_card_applies( $order ) || $order->has_status( STLH_BANK_STATUS ) ) {
		return;
	}
	$url = $order->get_checkout_order_received_url();
	echo $plain
		? esc_html( stlh_label( 'bank_email_link' ) ) . ': ' . esc_url_raw( $url ) . "\n\n"
		: '<p style="margin:0 0 16px"><a href="' . esc_url( $url ) . '" style="display:inline-block;background:#1d4ed8;color:#fff;padding:10px 18px;border-radius:10px;text-decoration:none;font-weight:bold">' . esc_html( stlh_label( 'bank_email_link' ) ) . '</a></p>'; // phpcs:ignore WordPress.Security.EscapeOutput
}, 5, 3 );
