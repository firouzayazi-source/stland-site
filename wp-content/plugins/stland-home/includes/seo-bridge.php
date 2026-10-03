<?php
/**
 * سئوی محصول از حسابداری → فیلدهای Yoast.
 *
 * حسابداری عنوان، توضیح و کلیدواژه‌ی سئو را با کلیدهای خودمان می‌فرستد
 * (`stl_seo_title`، `stl_seo_desc`، `stl_seo_focus` — قرارداد ۳). خودِ کلیدهای
 * Yoast زیرخطی‌اند (`_yoast_wpseo_*`) و از REST مطمئن نوشته نمی‌شوند؛ اینجا،
 * بعد از هر ساخت/ویرایشِ محصول از REST، در همان فیلدها نوشته می‌شوند تا
 * Yoast و گوگل همان را ببینند. خالی = دست نزن (اگر در وردپرس دستی نوشته‌اید).
 */

defined( 'ABSPATH' ) || exit;

function stlh_seo_bridge_map(): array {
	return [
		'stl_seo_title' => '_yoast_wpseo_title',
		'stl_seo_desc'  => '_yoast_wpseo_metadesc',
		'stl_seo_focus' => '_yoast_wpseo_focuskw',
	];
}

/** متای محصول → Yoast؛ جدا تا آزمون بی‌ووکامرس هم بتواند صدایش بزند */
function stlh_seo_bridge_apply( int $post_id ): int {
	$written = 0;
	foreach ( stlh_seo_bridge_map() as $ours => $yoast ) {
		$value = trim( (string) get_post_meta( $post_id, $ours, true ) );
		if ( '' !== $value && get_post_meta( $post_id, $yoast, true ) !== $value ) {
			update_post_meta( $post_id, $yoast, $value );
			$written++;
		}
	}
	return $written;
}

add_action( 'woocommerce_rest_insert_product_object', static function ( $product ): void {
	if ( is_object( $product ) && method_exists( $product, 'get_id' ) ) {
		stlh_seo_bridge_apply( (int) $product->get_id() );
	}
}, 20 );
