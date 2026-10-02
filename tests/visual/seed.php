<?php
/**
 * درختِ آزمون، همان شکلِ فروشگاهِ واقعی بعد از «مرتب کردنِ درخت» — با
 * دامِ واقعیِ مهر ۱۴۰۵: «تلفن همراه» دسته‌ی پیش‌فرضِ ووکامرس است (همان که
 * یک بار همه‌ی آیفون‌ها را از صفحه برد).
 */
$_SERVER['HTTP_HOST'] ??= 'localhost';
require getenv( 'WP_DIR' ) . '/wp-load.php';

foreach ( get_terms( [ 'taxonomy' => 'product_cat', 'hide_empty' => false ] ) as $t ) {
	wp_delete_term( $t->term_id, 'product_cat' );
}
foreach ( get_posts( [ 'meta_key' => 'is_product', 'numberposts' => -1, 'post_status' => 'any' ] ) as $p ) {
	wp_delete_post( $p->ID, true );
}
$mk = static function ( string $name, string $slug, int $parent = 0, int $order = 0 ): int {
	$id = (int) wp_insert_term( $name, 'product_cat', [ 'slug' => $slug, 'parent' => $parent ] )['term_id'];
	update_term_meta( $id, 'order', $order );
	return $id;
};
$phone  = $mk( 'تلفن همراه', 'cellphone', 0, 1 );
$iphone = $mk( 'آیفون', 'iphone-all', $phone, 1 );
$new    = $mk( 'آیفون نو', 'iphone_new', $iphone, 1 );
$used   = $mk( 'آیفون کارکرده', 'iphone-second-hand', $iphone, 2 );
$acc    = $mk( 'لوازم جانبی', 'accessories', 0, 2 );
$air    = $mk( 'ایرپاد', 'airpod', $acc, 1 );
$ch     = $mk( 'شارژر', 'charger', $acc, 2 );
$glass  = $mk( 'محافظ صفحه', 'screen_protector', $acc, 3 );
$best   = $mk( 'پرفروش‌ها', 'best-sellers', 0, 3 );
update_option( 'default_product_cat', $phone );

$prod = static function ( string $title, array $cats, int $price, array $meta = [] ): int {
	$id = wp_insert_post( [ 'post_title' => $title, 'post_status' => 'publish' ] );
	update_post_meta( $id, 'is_product', 1 );
	update_post_meta( $id, 'price', $price );
	foreach ( $meta as $k => $v ) {
		update_post_meta( $id, $k, $v );
	}
	wp_set_object_terms( $id, $cats, 'product_cat' );
	return $id;
};
$prod( 'آیفون 17 پرو مکس 256 گیگ', [ $new ], 235000000, [ 'sale' => 232000000, 'condition' => 'آک / نو', 'color' => 'نارنجی' ] );
$prod( 'آیفون 17 256 گیگ', [ $new, $best ], 122000000, [ 'condition' => 'آک / نو' ] );
foreach ( [ 16, 15, 14, 13, 12, 11, 'XR', 'X' ] as $g ) {
	$prod( "آیفون $g پرو 256 گیگ", [ $used ], 50000000 + (int) $g * 4000000, [ 'condition' => 'کارکرده', 'color' => 'آبی', 'battery' => '89', 'registry' => 'شده' ] );
}
foreach ( range( 1, 6 ) as $i ) {
	$prod( "گلس محافظ صفحه مدل $i", [ $glass ], 350000 + $i * 10000 );
}
$prod( 'سیم شارژر اورجینال USB-C', [ $ch, $best ], 2500000 );
$prod( 'ایرپاد پرو 2', [ $air ], 9000000 );

// همان چیدمانی که صاحب فروشگاه در تنظیمات ساخت؛ ردیف‌ها خالی = خودکار از درخت
update_option( 'stland_home', stlh_sanitize( [
	'order'      => "banner\ncategories\nflash_deal\nrows\ngroup\nbest\nsocial\ntrust\nfaq\nposts",
	'flash_mode' => 'off',
	'faq'        => "سوال؟\nجواب",
] ) );
echo "seeded\n";
