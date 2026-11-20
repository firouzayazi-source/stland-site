<?php
/**
 * «پیک» فقط برای استانِ خودِ فروشگاه (صاحب فروشگاه، ۳۰ آبان ۱۴۰۵: «کرایه‌ی ۱۰۰ هزار تومانیِ پیک برای کسی که از شهرِ دیگری
 * سفارش می‌دهد نباید فعال باشد؛ فقط قم… در غیرِ این صورت همان کرایه‌ی پست»).
 *
 * سفارشِ آزمایشیِ ۴۵۴۰۷ با نشانیِ اردبیل «پیک (ارسال همان روز در قم)» گرفته بود: روش‌های ارسال از افزونه‌ی «حمل و نقل جایگاه»
 * (`mnsjay_shipping`) برای همه‌ی ایران تعریف شده‌اند. این‌جا هر نرخی که نامش «پیک» دارد (`shipping_local_word`) فقط وقتی
 * نشان داده می‌شود که استانِ مقصد همان استانِ فروشگاه باشد (`wc_get_base_location`، قم = `QHM`). تنظیماتِ افزونه دست نمی‌خورد.
 * برای سفارشی که با پیکِ نامعتبر مانده (نشستِ قدیمی)، پیش از ثبت هم خطا می‌دهد.
 */
defined( 'ABSPATH' ) || exit;

function stlh_ship_home_state(): string {
	$base = function_exists( 'wc_get_base_location' ) ? wc_get_base_location() : [];
	return strtoupper( (string) ( $base['state'] ?? '' ) ) ?: 'QHM';
}

function stlh_ship_is_local_rate( $rate ): bool {
	$word = trim( (string) stlh_opt( 'shipping_local_word' ) );
	return '' !== $word && is_object( $rate ) && method_exists( $rate, 'get_label' ) && false !== mb_strpos( (string) $rate->get_label(), $word );
}

add_filter( 'woocommerce_package_rates', static function ( $rates, $package ) {
	if ( ! is_array( $rates ) ) {
		return $rates;
	}
	$state = strtoupper( (string) ( $package['destination']['state'] ?? '' ) );
	if ( '' === $state || $state === stlh_ship_home_state() ) {
		return $rates;
	}
	foreach ( $rates as $id => $rate ) {
		if ( stlh_ship_is_local_rate( $rate ) ) {
			unset( $rates[ $id ] );
		}
	}
	return $rates;
}, 50, 2 );

add_action( 'woocommerce_after_checkout_validation', static function ( $data, $errors ): void {
	if ( ! function_exists( 'WC' ) || ! WC()->shipping() ) {
		return;
	}
	$state  = strtoupper( (string) ( $data['shipping_state'] ?? '' ) ?: (string) ( $data['billing_state'] ?? '' ) );
	$chosen = (array) WC()->session->get( 'chosen_shipping_methods' );
	if ( '' === $state || $state === stlh_ship_home_state() ) {
		return;
	}
	foreach ( WC()->shipping()->get_packages() as $i => $pkg ) {
		$rate = $pkg['rates'][ $chosen[ $i ] ?? '' ] ?? null;
		if ( $rate && stlh_ship_is_local_rate( $rate ) ) {
			$errors->add( 'stlh_ship_local', stlh_label( 'ship_local_only' ) );
			return;
		}
	}
}, 10, 2 );
