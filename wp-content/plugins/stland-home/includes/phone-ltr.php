<?php
/**
 * شماره‌ی تلفن در متنِ فارسی به ترتیبِ درست: «۰۹۱۲ ۷۴۸ ۸۴۳۴»، نه «۸۴۳۴ ۷۴۸ ۰۹۱۲».
 *
 * صاحب فروشگاه (۲۶ مهر ۱۴۰۵، با عکسِ کادرِ «بپرسید»): «ترتیبِ شماره اشتباه است … در چند جای دیگر هم دیدم؛ هر جا هست
 * درست شود.» شماره‌ای که با فاصله گروه‌بندی شده، وسطِ متنِ راست‌به‌چپ، گروه‌هایش را برعکس نشان می‌دهد. پس هر شماره‌ی
 * همراهِ ۰۹… با فاصله یا خط‌تیره (رقمِ فارسی یا انگلیسی) در **متنِ** صفحه — نه داخلِ برچسب‌ها، اسکریپت و استایل — در
 * `<span dir="ltr">` پیچیده می‌شود. روی HTMLِ نهایی پیش از رفتن به حافظه‌ی موقت (`litespeed_buffer_after`)، تا شماره‌ی
 * قالب، برگه‌ها، مقاله‌ها و متن‌های افزونه همه یک‌جا درست شوند.
 */

defined( 'ABSPATH' ) || exit;

add_filter( 'litespeed_buffer_after', 'stlh_phone_ltr', 25 );

function stlh_phone_ltr( $html ) {
	if ( ! is_string( $html ) || is_admin() ) {
		return $html;
	}
	$body = stripos( $html, '<body' );
	if ( false === $body ) {
		return $html;
	}
	$d    = '[0-9۰-۹]';
	$re   = '/(?<![0-9۰-۹])[0۰][9۹]' . $d . '{2}([ \x{00A0}\x{200C}\-])' . $d . '{3}\1' . $d . '{4}(?![0-9۰-۹])/u';
	$tail = substr( $html, $body );
	$segs = preg_split( '/(<script\b.*?<\/script>|<style\b.*?<\/style>|<textarea\b.*?<\/textarea>|<[^>]+>)/is', $tail, -1, PREG_SPLIT_DELIM_CAPTURE );
	if ( ! is_array( $segs ) ) {
		return $html;
	}
	foreach ( $segs as $i => $seg ) {
		if ( '' === $seg || '<' === $seg[0] ) {
			continue;
		}
		$fixed = preg_replace( $re, '<span dir="ltr" class="stlh-tel">$0</span>', $seg );
		if ( null !== $fixed ) {
			$segs[ $i ] = $fixed;
		}
	}
	return substr( $html, 0, $body ) . implode( '', $segs );
}

// یک بار: صفحه‌هایی که با ترتیبِ اشتباه در حافظه‌ی موقت مانده‌اند دوباره ساخته شوند
add_action( 'init', static function (): void {
	if ( (int) get_option( 'stlh_phone_ltr_rev', 0 ) >= 1 || ! defined( 'LSCWP_V' ) ) {
		return;
	}
	update_option( 'stlh_phone_ltr_rev', 1, false );
	do_action( 'litespeed_purge_all' );
}, 40 );
