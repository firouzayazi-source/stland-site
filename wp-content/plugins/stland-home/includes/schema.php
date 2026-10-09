<?php
/**
 * داده‌ی ساختاریافته‌ی «فروشگاه محلی» برای گوگل — فقط روی صفحه اصلی.
 *
 * آدرس و تلفن در صفحه نوشته شده بود ولی گوگل آن را فقط متن می‌دید. این
 * بلوک همان را به زبان schema.org می‌گوید (MobilePhoneStore) تا در
 * جستجوی محلی «خرید آیفون در قم» و پنل کسب‌وکار دیده شود.
 *
 * ⛔ فقط چیزی که در تنظیمات هست نوشته می‌شود. ساعت کاری، امتیاز و مختصات
 *    را حدس نمی‌زنیم — دادهٔ ساختاریافته‌ی غلط از نبودنش بدتر است.
 */
defined( 'ABSPATH' ) || exit;

/** ۰۹۱۲… → +98912… */
function stlh_intl_phone( string $raw ): string {
	$d = preg_replace( '/\D/', '', strtr( $raw, [ '۰' => '0', '۱' => '1', '۲' => '2', '۳' => '3', '۴' => '4', '۵' => '5', '۶' => '6', '۷' => '7', '۸' => '8', '۹' => '9' ] ) );
	if ( '' === $d ) {
		return '';
	}
	if ( str_starts_with( $d, '0098' ) ) {
		$d = substr( $d, 4 );
	} elseif ( str_starts_with( $d, '98' ) ) {
		$d = substr( $d, 2 );
	} elseif ( str_starts_with( $d, '0' ) ) {
		$d = substr( $d, 1 );
	}
	return '+98' . $d;
}

function stlh_local_business(): array {
	$data = [
		'@context' => 'https://schema.org',
		'@type'    => 'MobilePhoneStore',
		'@id'      => home_url( '/#store' ),
		'name'     => get_bloginfo( 'name' ) ?: 'استوک لند',
		'url'      => home_url( '/' ),
	];
	if ( $desc = get_bloginfo( 'description' ) ) {
		$data['description'] = $desc;
	}
	if ( $logo = get_site_icon_url( 512 ) ) {
		$data['logo'] = $logo;
	}
	$img = (int) stlh_opt( 'banner_desktop' ) ?: (int) stlh_opt( 'banner_mobile' );
	if ( $img && ( $src = wp_get_attachment_image_url( $img, 'full' ) ) ) {
		$data['image'] = $src;
	} elseif ( ! empty( $data['logo'] ) ) {
		$data['image'] = $data['logo'];
	}
	if ( $tel = stlh_intl_phone( (string) stlh_opt( 'phone' ) ) ) {
		$data['telephone'] = $tel;
	}
	$address = trim( (string) stlh_opt( 'address' ) );
	if ( '' !== $address ) {
		$data['address'] = array_filter( [
			'@type'           => 'PostalAddress',
			'streetAddress'   => $address,
			'addressLocality' => trim( (string) stlh_opt( 'city' ) ),
			'addressCountry'  => 'IR',
		] );
	}
	$same = [];
	if ( $ig = ltrim( (string) stlh_opt( 'instagram' ), '@' ) ) {
		$same[] = 'https://instagram.com/' . $ig;
	}
	if ( $ch = ltrim( (string) stlh_opt( 'telegram' ), '@' ) ) {
		$same[] = 'https://t.me/' . $ch;
	}
	if ( $bot = ltrim( (string) stlh_opt( 'telegram_bot' ), '@' ) ) {
		$same[] = 'https://t.me/' . $bot;
	}
	if ( $same ) {
		$data['sameAs'] = $same;
	}
	// ساعت کاری، ایمیل، پرداخت… (includes/ai-seo.php)
	return (array) apply_filters( 'stlh_local_business', $data );
}

add_action( 'wp_head', function (): void {
	if ( ! is_front_page() ) {
		return;
	}
	echo '<script type="application/ld+json">' . wp_json_encode( stlh_local_business(), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ) . "</script>\n";
}, 20 );
