<?php
/**
 * کارت‌های محصول در صفحه‌های دسته و جستجو (قالبِ باکالا) — عکسِ بزرگ‌تر و هم‌اندازه.
 *
 * صاحب فروشگاه (۱۴ مهر ۱۴۰۵): «عکسِ کارت کمی کوچک است؛ ابعادِ کلیِ کارت عوض
 * نشود ولی عکس بزرگ‌تر و همه یک‌اندازه شوند.» باکالا (`woocommerce/content-product.php`
 * و `template-parts/content/archive-mobile.php`) عکس را با اندازه‌ی
 * `woocommerce_thumbnail` (۲۲۰×۲۲۰) می‌گیرد و روی دسکتاپ در کادرِ ۱۸۰ و روی گوشی
 * ۱۳۰ پیکسلی می‌نشاند.
 *
 * سه کار، بی‌دست‌زدن به فایلِ قالب:
 *  ۱. فقط در فهرست‌ها، `woocommerce_thumbnail` همان فایلِ `woocommerce_single`
 *     (۶۰۰ پیکسل) را می‌دهد — عکسِ ۲۲۰ پیکسلی بزرگ‌تر نشان داده شود تار است.
 *  ۲. CSS: دسکتاپ ۱۸۰ ← ۲۳۰ و گوشی ۱۳۰ ← ۱۶۰، با `object-fit: contain` تا عکسِ
 *     افقی و عمودی هم‌اندازه دیده شوند. جایش از فاصله‌ی خالیِ زیرِ عکس (۶۰
 *     پیکسل) و بالای دکمه گرفته می‌شود، پس ارتفاعِ کارت (دسکتاپ ۴۴۵) همان می‌ماند.
 *     اعداد روی خودِ صفحه‌ی زنده از راهِ پلِ حسابداری اندازه گرفته شد.
 *  ۳. عکسِ جایگزینِ ووکامرس (`product-placeholder-1.png`) روی سرور نیست (۴۰۴) و
 *     کالای بی‌عکس آیکنِ عکسِ شکسته می‌گرفت؛ اگر فایل نباشد، آیکنِ خودمان.
 */
defined( 'ABSPATH' ) || exit;

/** صفحه‌ای که فهرستِ کارت دارد: دسته، فروشگاه، برچسب، جستجو */
function stlh_is_catalog(): bool {
	if ( is_admin() || ! function_exists( 'is_shop' ) || ! function_exists( 'is_product_taxonomy' ) ) {
		return false;
	}
	return is_shop() || is_product_taxonomy() || is_search();
}

add_filter( 'image_downsize', function ( $out, $id, $size ) {
	static $busy = false;
	if ( $busy || 'woocommerce_thumbnail' !== $size || ! stlh_is_catalog() ) {
		return $out;
	}
	$busy = true;
	$big  = image_downsize( $id, 'woocommerce_single' );
	$busy = false;
	return $big ?: $out;
}, 10, 3 );

add_action( 'wp_enqueue_scripts', function (): void {
	if ( ! stlh_is_catalog() ) {
		return;
	}
	wp_register_style( 'stland-catalog', false, [], STLH_VER );
	wp_enqueue_style( 'stland-catalog' );
	wp_add_inline_style( 'stland-catalog', implode( '', [
		// دسکتاپ (archive-pc): کادرِ عکس ۱۸۰ ← ۲۳۰؛ فاصله‌ی زیرِ عکس ۶۰ ← ۲۵ و بالای دکمه ۳۵ ← ۲۰
		'body.archive ul.products .products__item-image-wrapper,body.search ul.products .products__item-image-wrapper{height:230px!important;display:flex!important;align-items:center;justify-content:center}',
		'body.archive ul.products img.products__item-image,body.search ul.products img.products__item-image{width:230px!important;height:230px!important;max-width:100%!important;max-height:230px!important;object-fit:contain}',
		'body.archive ul.products .products__item-fatitle,body.search ul.products .products__item-fatitle{margin-top:25px!important}',
		'body.archive ul.products .products__item-info .box-footer,body.search ul.products .products__item-info .box-footer{padding-top:20px!important}',
		// گوشی (archive-mobile): عکس ۱۳۰ با حاشیه‌ی ۲۰ ← ۱۶۰ با حاشیه‌ی ۵ — همان ۱۷۰ پیکسل
		'body.archive .products-list article .product-thumb,body.search .products-list article .product-thumb{height:160px!important;margin:5px 0!important;display:flex;align-items:center;justify-content:center}',
		'body.archive .products-list article .product-thumb img.img-responsive,body.search .products-list article .product-thumb img.img-responsive{width:100%!important;height:160px!important;max-width:100%!important;max-height:160px!important;margin:0!important;object-fit:contain}',
	] ) );
}, 99 );

add_filter( 'woocommerce_placeholder_img_src', function ( $src ) {
	$uploads = wp_get_upload_dir();
	if ( is_string( $src ) && str_starts_with( $src, $uploads['baseurl'] ) ) {
		$file = $uploads['basedir'] . substr( $src, strlen( $uploads['baseurl'] ) );
		if ( file_exists( $file ) ) {
			return $src;
		}
	} elseif ( is_string( $src ) && '' !== $src ) {
		return $src; // جایگزینِ خودِ ووکامرس یا جای دیگری که نمی‌سنجیمش
	}
	return STLH_URL . 'assets/img/placeholder.svg';
} );
