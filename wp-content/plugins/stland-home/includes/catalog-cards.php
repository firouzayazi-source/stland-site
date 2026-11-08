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
		// دسکتاپ: ستونِ فیلتر فقط «فیلترِ قیمت» دارد و زیرش تا پایینِ صفحه خالی می‌ماند (صاحب فروشگاه با عکس، ۱۶ آبان ۱۴۰۵: «حاشیه
		// خیلی زیاده، خیلی فضای خالی داریم»). وقتی ستون فقط یک ابزارک دارد، همان ابزارک نوارِ افقیِ بالای فهرست می‌شود و کالاها کلِ
		// عرض را می‌گیرند (۵ ستون از ۱۲۰۰، ۶ ستون از ۱۶۰۰). اگر روزی ابزارکِ دیگری اضافه شود (`:has` دیگر نمی‌خورد)، همان ستونِ قبلی برمی‌گردد.
		'@media (min-width:992px){.shop-page.row:has(>.filters-panel>section.widget:only-of-type)>.filters-panel{flex:0 0 100%!important;max-width:100%!important;width:100%!important;margin-top:14px!important;position:static!important}.shop-page.row:has(>.filters-panel>section.widget:only-of-type)>.col-md-10{flex:0 0 100%!important;max-width:100%!important;width:100%!important}.shop-page.row:has(>.filters-panel>section.widget:only-of-type) .filters-panel .widget_price_filter{display:flex!important;align-items:center;gap:28px;padding:10px 22px!important;margin:0!important}.shop-page.row:has(>.filters-panel>section.widget:only-of-type) .filters-panel .matrix-widget-title{flex:0 0 auto;width:auto!important;margin:0!important;height:auto!important;border:0!important;padding:0!important;white-space:nowrap}.shop-page.row:has(>.filters-panel>section.widget:only-of-type) .filters-panel .matrix-widget-content{flex:1 1 auto;min-width:0;width:auto!important;border:0!important;margin:0!important;max-height:none!important;overflow:visible!important;padding:0!important}.shop-page.row:has(>.filters-panel>section.widget:only-of-type) .bakala-archive-filter-body--price{display:grid!important;grid-template-columns:auto minmax(0,1fr);column-gap:32px;align-items:center}.shop-page.row:has(>.filters-panel>section.widget:only-of-type) .bakala-archive-filter-body--price>*{grid-column:2}.shop-page.row:has(>.filters-panel>section.widget:only-of-type) .bakala-archive-filter-body--price>.bakala-archive-filter-body--price-labels{grid-column:1;grid-row:1/span 4;flex-direction:row!important;width:auto!important;margin:0!important}.shop-page.row:has(>.filters-panel>section.widget:only-of-type) .bakala-archive-filter-body--price-labels>div{min-width:210px;gap:18px}.shop-page.row:has(>.filters-panel>section.widget:only-of-type) .bakala-archive-filter-body--price-slider{margin:0 10px!important}.shop-page.row:has(>.filters-panel>section.widget:only-of-type) .bakala-archive-filter-body--price-steps{width:96%!important;margin:4px auto 2px!important}.shop-page.row:has(>.filters-panel>section.widget:only-of-type) .bakala-archive-filter-body--price-hint{width:100%!important}}@media (min-width:1200px){.shop-page.row:has(>.filters-panel>section.widget:only-of-type) ul.products{grid-template-columns:repeat(5,minmax(0,1fr))!important}}@media (min-width:1600px){.shop-page.row:has(>.filters-panel>section.widget:only-of-type) ul.products{grid-template-columns:repeat(6,minmax(0,1fr))!important}}',
		// قیمتِ کارتِ قالب مثلِ کارتِ صفحه‌ی اصلی (صاحب فروشگاه، ۱۷ آبان، با نمونه): قیمتِ قبلی و قرصِ قرمزِ تخفیف در یک خط، قیمتِ تازه زیرش،
		// هر دو چپ‌چین. باکالا درصد را با آیکن و عددِ لاتین («٪ ۶») جدا زیرِ قیمت می‌گذاشت.
		'body.archive ul.products .products__item-gift-price,body.search ul.products .products__item-gift-price{display:grid!important;grid-template-columns:auto auto 1fr;direction:ltr;align-items:center;justify-items:start;column-gap:6px;row-gap:2px}',
		'body.archive ul.products .products__item-gift-price .products__item-price,body.search ul.products .products__item-gift-price .products__item-price{display:contents!important}',
		'body.archive ul.products .products__item-gift-price del,body.search ul.products .products__item-gift-price del{grid-row:1;grid-column:1;font-size:12px!important;color:#94a3b8!important;margin:0!important}',
		'body.archive ul.products .products__item-gift-price del .woocommerce-Price-currencySymbol,body.search ul.products .products__item-gift-price del .woocommerce-Price-currencySymbol{display:none}',
		'body.archive ul.products .products__item-gift-price ins,body.search ul.products .products__item-gift-price ins{grid-row:2;grid-column:1/-1;direction:rtl;text-decoration:none}',
		'body.archive ul.products .loop-saving-percentage,body.search ul.products .loop-saving-percentage{grid-row:1;grid-column:2;position:static!important;display:inline-flex!important;align-items:center;background:#dc2626!important;color:#fff!important;font-size:11px!important;font-weight:800!important;line-height:1!important;padding:4px 8px!important;border-radius:999px!important;margin:0!important;width:auto!important;height:auto!important;box-shadow:0 3px 10px -4px rgba(220,38,38,.7);direction:rtl}',
		'body.archive ul.products .loop-saving-percentage i,body.search ul.products .loop-saving-percentage i{display:none!important}',
	] ) );
	// متنِ قرص: درصد با عددِ فارسی، یا مبلغ اگر صاحب فروشگاه «مبلغ» را انتخاب کرده (`stlh_sale_display`)
	wp_register_script( 'stland-catalog', false, [], STLH_VER, [ 'in_footer' => true ] );
	wp_enqueue_script( 'stland-catalog' );
	// هر کالا شیوه‌ی خودش را از Novo دارد (`stlh_sale_show_map`، قرارداد ۶)؛ شناسه از کلاسِ `post-…` یا `data-product_id`ِ همان کارت
	wp_add_inline_script( 'stland-catalog', '(function(){var G=' . wp_json_encode( 'amount' === get_option( 'stlh_sale_display', 'percent' ) ? 'amount' : 'percent' ) . ',S=' . wp_json_encode( (object) stlh_sale_show_map() ) . ',fa="۰۱۲۳۴۵۶۷۸۹",'
		. 'P=function(b){var c=b.closest("li,article,.product");if(!c)return 0;var m=/(?:^|\\s)post-(\\d+)/.exec(c.className||"");if(m)return m[1];var a=c.querySelector("[data-product_id]");return a?a.getAttribute("data-product_id"):0},'
		. 'F=function(s){return String(s).replace(/\\d/g,function(d){return fa[d]})},'
		. 'N=function(el){if(!el)return 0;var t=el.textContent.replace(/[۰-۹]/g,function(d){return fa.indexOf(d)});return +t.replace(/[^0-9]/g,"")||0},'
		. 'run=function(){document.querySelectorAll(".loop-saving-percentage:not([data-stl])").forEach(function(b){b.setAttribute("data-stl",1);var g=b.closest(".products__item-gift-price"),o=N(g&&g.querySelector("del")),n=N(g&&g.querySelector("ins")),p=parseInt(b.textContent.replace(/[۰-۹]/g,function(d){return fa.indexOf(d)}).replace(/\\D/g,""))||0,txt,M=S[P(b)]||G;'
		. 'if(M==="amount"&&o>n){var d=o-n;txt=d>=1e6?F((Math.round(d/1e5)/10).toString().replace(".","٫"))+" میلیون":d>=1e3?F(Math.round(d/1e3))+" هزار":F(d)+" تومان"}else{txt=F(p)+"٪"}b.textContent=txt})};'
		. 'document.readyState==="loading"?document.addEventListener("DOMContentLoaded",run):run();if(window.jQuery)jQuery(document).on("ajaxComplete",function(){setTimeout(run,300)})})();' );
}, 99 );

/** ۲۰ کالا در هر صفحه به‌جای ۱۲ — با ۴ و ۵ ستونِ تازه ردیفِ ناقص و جای خالی نمی‌ماند (همان تاریخ) */
add_filter( 'loop_shop_per_page', static fn() => 20, 30 );

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

/*
 * فیلترِ قیمتِ دوتایی (صاحب فروشگاه با عکس، ۹ آبان ۱۴۰۵: «دو بار قیمت تکرار شده، پایینی لاتین و اضافه است»). ستونِ فیلترِ
 * فروشگاه دو ابزارکِ «فیلتر قیمت» دارد (`woocommerce_price_filter-10` با اعدادِ فارسی و نوار، و `-9` بی‌نوار با اعدادِ لاتین).
 * فقط در خروجیِ سایت، از هر ناحیه‌ی ابزارک اولین فیلترِ قیمت می‌ماند؛ تنظیمِ ابزارک‌ها در پیشخوان دست نمی‌خورد.
 */
add_filter( 'sidebars_widgets', static function ( $areas ) {
	if ( is_admin() || ! is_array( $areas ) || ( defined( 'REST_REQUEST' ) && REST_REQUEST ) ) {
		return $areas;
	}
	foreach ( $areas as $area => $widgets ) {
		if ( ! is_array( $widgets ) || 'wp_inactive_widgets' === $area ) {
			continue;
		}
		$seen = false;
		foreach ( $widgets as $i => $w ) {
			if ( is_string( $w ) && str_starts_with( $w, 'woocommerce_price_filter-' ) ) {
				if ( $seen ) {
					unset( $areas[ $area ][ $i ] );
				}
				$seen = true;
			}
		}
		$areas[ $area ] = array_values( (array) $areas[ $area ] );
	}
	return $areas;
} );
