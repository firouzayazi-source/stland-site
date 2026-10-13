<?php
/**
 * تنظیماتِ قالب (باکالا) از مخزن، نه از پیشخوان.
 *
 * صاحب فروشگاه (مهر ۱۴۰۵): «تغییراتی که می‌خواهیم خودت لحاظ کن، من دستکاری نکنم.» پلِ حسابداری
 * عمداً فقط‌خواندنی است (رمزِ برنامه‌ای که لو برود نباید بتواند سایت را عوض کند)، پس نوشتن از بیرون
 * نیست: مقدارِ دلخواه همین‌جا در `STLH_BAKALA_SET` می‌آید، با نسخه‌ی افزونه منتشر می‌شود، و خودِ
 * افزونه یک بار در `bakala_options` می‌نویسد — پیشخوانِ باکالا هم همان را نشان می‌دهد.
 *
 *  • فقط وقتی فهرست عوض شده می‌نویسد (`stlh_bakala_set_hash`)؛ اگر کسی بعداً از پیشخوان عوضش کرد،
 *    تا تغییرِ بعدیِ فهرست دوباره زور نمی‌شود.
 *  • مقدارِ پیشین هر کلید پیش از اولین نوشتن در `stlh_bakala_backup` می‌ماند (برگشت = همان مقدار
 *    را این‌جا بگذارید). هر دو از `/wp-json/stland/v1/site-options?prefix=stlh` خواندنی‌اند.
 *  • کلیدی که در تنظیماتِ قالب نیست نوشته نمی‌شود (غلطِ تایپی گزینه‌ی تازه نمی‌سازد) و در
 *    `stlh_bakala_applied` با `missing` گزارش می‌شود.
 */
defined( 'ABSPATH' ) || exit;

/** کلیدِ `bakala_options` => مقدار. هر خط: چرا، و درخواستِ چه کسی. */
const STLH_BAKALA_SET = [];

add_action( 'init', 'stlh_bakala_apply', 20 );

function stlh_bakala_apply(): void {
	$hash = md5( (string) wp_json_encode( STLH_BAKALA_SET ) );
	if ( get_option( 'stlh_bakala_set_hash' ) === $hash ) {
		return;
	}
	global $wpdb;
	// خامِ دیتابیس، نه `get_option`: نگهبانِ قالب (`theme-guard.php`) مقدارِ صفحه‌ی مشتری را دست می‌برد
	$raw  = $wpdb->get_var( $wpdb->prepare( "SELECT option_value FROM {$wpdb->options} WHERE option_name = %s", 'bakala_options' ) );
	$opts = maybe_unserialize( (string) $raw );
	if ( ! is_array( $opts ) ) {
		return;
	}
	$backup  = (array) get_option( 'stlh_bakala_backup', [] );
	$changed = [];
	$missing = [];
	foreach ( STLH_BAKALA_SET as $key => $value ) {
		if ( ! array_key_exists( $key, $opts ) ) {
			$missing[] = $key;
			continue;
		}
		if ( $opts[ $key ] === $value ) {
			continue;
		}
		if ( ! array_key_exists( $key, $backup ) ) {
			$backup[ $key ] = $opts[ $key ];
		}
		$opts[ $key ] = $value;
		$changed[]    = $key;
	}
	if ( $changed ) {
		update_option( 'stlh_bakala_backup', $backup, false );
		update_option( 'bakala_options', $opts );
		delete_option( 'bakala_options-transients' );
		if ( function_exists( 'litespeed_purge_all' ) ) {
			litespeed_purge_all();
		} else {
			do_action( 'litespeed_purge_all' );
		}
	}
	update_option( 'stlh_bakala_applied', [ 'at' => gmdate( 'c' ), 'ver' => STLH_VER, 'changed' => $changed, 'missing' => $missing ], false );
	update_option( 'stlh_bakala_set_hash', $hash, false );
}
