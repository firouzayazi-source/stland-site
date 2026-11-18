<?php
/**
 * «واریز به شماره شبا» برای سفارش‌های گران (صاحب فروشگاه، ۲۸ آبان ۱۴۰۵: «نباید پرداختی از بین برود… شبا باید فعال شود،
 * کارت به کارت محدود است»).
 *
 * چرا: سفارشِ ۴۵۴۰۴ (آیفون ۱۴ پرو، ۲۲۰ میلیون تومان) و ۴۴۹۱۹ (همان مبلغ، مهر) در درگاهِ سامان پرداخت نشدند، ولی سفارش‌های
 * کوچک از همان درگاه پرداخت شدند — سقفِ خریدِ اینترنتیِ کارتِ مشتری به قیمتِ یک گوشی نمی‌رسد و تنها راه‌های روشن دو درگاهِ
 * اینترنتی بودند. حالا:
 *  • درگاهِ «انتقالِ بانکی»ِ فروشگاه‌ساز (`bacs`) با نام و متنِ «واریز به شماره شبا» روشن می‌شود (یک بار برای هر
 *    `STLH_BANK_SET_VER`؛ مقدارِ پیشین در `stlh_bank_backup`). سفارش «در انتظار بررسی» می‌ماند، گوشی برایش کنار می‌رود
 *    و پیامکِ همان وضعیت می‌رسد؛ صاحب فروشگاه بعد از رسیدنِ پول در Novo آن را «در حال انجام» می‌کند.
 *  • ⛔ شماره‌ی حساب و شبا این‌جا نیست (مخزن عمومی است): صاحب فروشگاه در «فروشگاه‌ساز ← پیکربندی ← پرداخت‌ها ← انتقال بانکی»
 *    وارد می‌کند. تا شبای درست (IR + ۲۴ رقم) ثبت نشده، این روش به مشتری نشان داده نمی‌شود — ردیفِ نمونه‌ی دموی قالب
 *    («IR23_3423…») نباید مشتری را به حسابِ ساختگی بفرستد.
 *  • در صفحه‌ی پرداخت، اگر مبلغ از `big_pay_from` (تومان) بیشتر باشد، زیرِ درگاهِ سامان یادآوری می‌آید که با سقفِ کارت
 *    شبا را انتخاب کنند (`stlh_label( 'pay_big_note' )`).
 * مدتِ نگه‌داشتنِ کالا برای سفارشِ پرداخت‌نشده از ۶۰۰ به ۶۰ دقیقه: `STLH_SITE_OPTIONS` در `theme-settings.php`.
 */
defined( 'ABSPATH' ) || exit;

const STLH_BANK_SET_VER = '1';

function stlh_bank_desired(): array {
	$f     = function_exists( 'stlh_store_facts' ) ? stlh_store_facts() : [];
	$phone = (string) ( $f['phone_fa'] ?? '' );
	$tg    = (string) ( $f['telegram'] ?? '' );
	return [
		'enabled'      => 'yes',
		'title'        => 'واریز به شماره شبا (برای مبلغ‌های بالا)',
		'description'  => 'مبلغ را با پایا یا ساتنا از اینترنت‌بانک یا شعبه به شماره شبای فروشگاه واریز کنید؛ سقفِ کارت محدودش نمی‌کند. شماره شبا بعد از ثبتِ سفارش نشان داده می‌شود و گوشی تا رسیدنِ پول برای شما کنار گذاشته می‌شود.',
		'instructions' => 'سفارش شما ثبت شد و گوشی برایتان کنار گذاشته شد. مبلغ را به شماره شبای زیر واریز کنید و تصویرِ رسید را همراه با شماره‌ی سفارش به ' . $phone . ' یا تلگرامِ @' . $tg . ' بفرستید. بعد از رسیدنِ پول، سفارش ارسال می‌شود و پیامکش را می‌گیرید.',
	];
}

/** شبای درست در حساب‌های ثبت‌شده (IR + ۲۴ رقم، با فاصله یا خط تیره) */
function stlh_bank_has_iban(): bool {
	foreach ( (array) get_option( 'woocommerce_bacs_accounts', [] ) as $acc ) {
		$iban = strtoupper( preg_replace( '/[\s\-]+/', '', (string) ( $acc['iban'] ?? '' ) ) );
		$iban = function_exists( 'stlh_ai_en_digits' ) ? stlh_ai_en_digits( $iban ) : $iban;
		if ( preg_match( '/^IR\d{24}$/', $iban ) ) {
			return true;
		}
	}
	return false;
}

add_action( 'wp_loaded', static function (): void {
	if ( get_option( 'stlh_bank_set_ver' ) === STLH_BANK_SET_VER ) {
		return;
	}
	$cur = get_option( 'woocommerce_bacs_settings', [] );
	$cur = is_array( $cur ) ? $cur : [];
	if ( false === get_option( 'stlh_bank_backup', false ) ) {
		update_option( 'stlh_bank_backup', [ 'woocommerce_bacs_settings' => $cur, 'woocommerce_gateway_order' => get_option( 'woocommerce_gateway_order' ) ], false );
	}
	update_option( 'woocommerce_bacs_settings', array_merge( $cur, stlh_bank_desired() ) );
	// ترتیب: سامان، بعد شبا، بعد بقیه
	$order = get_option( 'woocommerce_gateway_order' );
	if ( is_array( $order ) && isset( $order['WC_Sep_Payment_Gateway'] ) ) {
		$sep = (int) $order['WC_Sep_Payment_Gateway'];
		foreach ( $order as $id => $pos ) {
			if ( 'WC_Sep_Payment_Gateway' !== $id && (int) $pos > $sep ) {
				$order[ $id ] = (int) $pos + 1;
			}
		}
		$order['bacs'] = $sep + 1;
		update_option( 'woocommerce_gateway_order', $order );
	}
	update_option( 'stlh_bank_set_ver', STLH_BANK_SET_VER );
	update_option( 'stlh_bank_applied', [ 'ver' => STLH_BANK_SET_VER, 'at' => gmdate( 'c' ) ], false );
} );

// تا شبای درست ثبت نشده، روش به مشتری نشان داده نمی‌شود
add_filter( 'woocommerce_available_payment_gateways', static function ( $gateways ) {
	if ( is_array( $gateways ) && isset( $gateways['bacs'] ) && ! is_admin() && ! stlh_bank_has_iban() ) {
		unset( $gateways['bacs'] );
	}
	return $gateways;
}, 20 );

// یادآوری زیرِ درگاهِ سامان: سقفِ درگاه/بانک یا ساعت‌هایی که فقط پایا پذیرفته می‌شود → «واریز به شبا».
// صاحب فروشگاه (۲۸ آبان ۱۴۰۵، از نشستِ Novo): «اولویت با درگاه باشد اما شبا هم بتوانند بزنند». `big_pay_from` (تومان): ۰ = همیشه.
add_filter( 'woocommerce_gateway_description', static function ( $desc, $id ) {
	if ( 'WC_Sep_Payment_Gateway' !== $id || ! function_exists( 'WC' ) || ! stlh_bank_has_iban() ) {
		return $desc;
	}
	$from  = (int) stlh_opt( 'big_pay_from' );
	$total = WC()->cart ? (float) WC()->cart->get_total( 'edit' ) : 0.0;
	if ( $from <= 0 || stlh_pg_toman( $total ) >= $from || is_checkout_pay_page() ) {
		$desc .= '<br><span style="color:#b45309;font-weight:700">' . esc_html( stlh_label( 'pay_big_note' ) ) . '</span>';
	}
	return $desc;
}, 25, 2 );

/** کادرِ «پرداخت انجام نشد» با پیوندِ پرداختِ دوباره (همان صفحه‌ی پرداختِ سفارش، که «واریز به شبا» هم در آن هست) */
function stlh_bank_failed_box( WC_Order $order ): string {
	if ( ! $order->needs_payment() || ! stlh_bank_has_iban() ) {
		return '';
	}
	return '<div class="woocommerce-info stlh-pay-failed" style="border-right:4px solid #b45309;padding:14px 16px;margin:0 0 18px;line-height:1.9">'
		. esc_html( stlh_label( 'pay_failed_note', [ '{سفارش}' => stlh_fa( (string) $order->get_order_number() ) ] ) )
		. ( is_checkout_pay_page() ? '' : ' <a class="button" style="margin-top:8px;display:inline-block" href="' . esc_url( $order->get_checkout_payment_url() ) . '">' . esc_html( stlh_label( 'pay_failed_btn' ) ) . '</a>' )
		. '</div>';
}

// برگشتِ ناموفق از درگاه: صفحه‌ی «پرداختِ سفارش» (سفارشِ پرداخت‌نشده همیشه به این‌جا می‌آید)
add_action( 'before_woocommerce_pay', static function (): void {
	$id    = absint( get_query_var( 'order-pay' ) );
	$order = $id ? wc_get_order( $id ) : null;
	if ( $order instanceof WC_Order ) {
		echo stlh_bank_failed_box( $order ); // phpcs:ignore WordPress.Security.EscapeOutput -- escaped inside
	}
} );

// برگشت به صفحه‌ی تسویه با سفارشِ نیمه‌کاره در نشست
add_action( 'woocommerce_before_checkout_form', static function (): void {
	if ( ! function_exists( 'WC' ) || ! WC()->session ) {
		return;
	}
	$id    = absint( WC()->session->get( 'order_awaiting_payment' ) );
	$order = $id ? wc_get_order( $id ) : null;
	if ( $order instanceof WC_Order && $order->has_status( [ 'pending', 'failed' ] ) && $order->get_payment_method() !== 'bacs' ) {
		echo stlh_bank_failed_box( $order ); // phpcs:ignore WordPress.Security.EscapeOutput -- escaped inside
	}
}, 5 );

// صفحه‌ی «سفارش دریافت شد» با سفارشِ ناموفق/پرداخت‌نشده
add_action( 'woocommerce_before_thankyou', static function ( $order_id ): void {
	$order = wc_get_order( $order_id );
	if ( $order instanceof WC_Order && $order->has_status( [ 'pending', 'failed' ] ) ) {
		echo stlh_bank_failed_box( $order ); // phpcs:ignore WordPress.Security.EscapeOutput -- escaped inside
	}
}, 5 );

// جدولِ حساب در صفحه‌ی «سفارش دریافت شد» و ایمیل: «کد شعبه» و «بیک» در ایران به کار نمی‌آیند و در ردیفِ ذخیره‌شده
// از دموی قالب مانده بودند (۲۸ آبان ۱۴۰۵)؛ شماره‌ی ۱۶ رقمی شماره‌ی کارت است، نه حساب.
add_filter( 'woocommerce_bacs_account_fields', static function ( $fields ) {
	if ( ! is_array( $fields ) ) {
		return $fields;
	}
	unset( $fields['sort_code'], $fields['bic'] );
	if ( isset( $fields['iban'] ) ) {
		$fields['iban']['label'] = stlh_label( 'bacs_iban' );
	}
	if ( isset( $fields['account_number'] ) ) {
		$digits = preg_replace( '/\D+/', '', stlh_ai_en_digits( (string) ( $fields['account_number']['value'] ?? '' ) ) );
		$fields['account_number']['label'] = stlh_label( 16 === strlen( $digits ) ? 'bacs_card' : 'bacs_account' );
	}
	return $fields;
}, 20 );
