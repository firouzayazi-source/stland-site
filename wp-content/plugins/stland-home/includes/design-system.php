<?php
/**
 * یک‌دستیِ ظاهر در همه‌ی صفحه‌ها — صاحب فروشگاه (۹ آبان ۱۴۰۵): «ظاهر را بی شلوغ‌کاری ارتقا بده… همه را با دقت انجام بده».
 * بر پایه‌ی بازبینیِ `docs/design-audit.md` (تا ۱۸ گردیِ گوشه و ۱۵ سایه‌ی مختلف در یک صفحه، متنِ ۹ پیکسلی، دکمه‌های کوچک‌تر از
 * انگشت، دو نوارِ تیره روی هم در صفحه‌ی محصول). قالب دست نمی‌خورد؛ فقط CSS روی خروجی، با متغیرهای یکسان:
 *
 *   --sl-r-sm ۱۲ (دکمه، فیلد، کاشی) · --sl-r-lg ۲۰ (کارت) · --sl-sh-1/2 دو سایه‌ی نرم · کمینه‌ی متن ۱۲ پیکسل · هدفِ لمسی ۴۴ پیکسل
 *
 * کارهای این فایل (بخش‌های صفحه‌ی اصلی در `assets/css/home.css`، بخشِ «۱.۷۸»):
 *   ۱. صفحه‌ی محصول در گوشی: نوارِ پایینِ سایت وقتی نوارِ ثابتِ خرید هست پنهان می‌شود و نوارِ خرید پایین می‌نشیند (دو نوارِ تیره
 *      حدودِ یک‌سومِ صفحه را می‌گرفت)؛ مسیرِ صفحه با محوشدگیِ لبه که نشان می‌دهد می‌شود کشید؛ کادرهای خالی جمع‌تر؛ ردیفِ
 *      اعتماد و برچسب‌ها با متنِ خوانا.
 *   ۲. «تومان» در فهرستِ محصولاتِ دسته‌ها مثلِ صفحه‌ی اصلی و صفحه‌ی محصول با کلمه (نه آیکنِ قالب).
 *   ۳. هدف‌های لمسی: آیکن‌های نوارِ پایین، پیوندهای مسیرِ صفحه، نقطه‌های اسلایدرِ عکس.
 *   ۴. وضوح: متنِ خاکستریِ کم‌رنگِ مسیر و تاریخ‌ها.
 */

defined( 'ABSPATH' ) || exit;

function stlh_design_css(): string {
	return <<<'CSS'
:root{--sl-r-sm:12px;--sl-r-lg:20px;--sl-sh-1:0 1px 2px rgba(15,23,42,.06),0 4px 14px -6px rgba(15,23,42,.10);--sl-sh-2:0 18px 40px -18px rgba(15,23,42,.35);--sl-muted:#5b616e;--sl-blue:#0071e3}
/* ۱) صفحه‌ی محصول در گوشی */
@media (max-width:767px){
 body.single-product:has(form.cart.cart-fixed) .mobile-bottom-nav{display:none!important}
 body.single-product form.cart.cart-fixed .add-to-cart-holder{bottom:calc(10px + env(safe-area-inset-bottom,0px))!important}
 body.single-product:has(form.cart.cart-fixed){padding-bottom:calc(96px + env(safe-area-inset-bottom,0px))}
 .bakala-product-breadcrumb-nav{-webkit-mask-image:linear-gradient(to left,#000 calc(100% - 36px),transparent);mask-image:linear-gradient(to left,#000 calc(100% - 36px),transparent)}
 .bakala-product-breadcrumb-item a{display:inline-flex;align-items:center;min-height:36px;font-size:12.5px!important;color:var(--sl-muted)!important}
 .single-product .product-section:has(.bakala_price_update_better){padding-top:2px!important;padding-bottom:2px!important}
 .bakala_price_update_better .bakala-update-price{margin:0!important;padding:0!important}
 .bakala-update-price-text,.bakala-update-price-date{font-size:12px!important;color:var(--sl-muted)!important}
 .single-product .c-footer__feature-item{font-size:12px!important;line-height:1.5!important;color:#3f4450!important}
 .single-product .product-remain,.single-product .product-remain *{font-size:12.5px!important}
 .swiper-pagination-bullet{width:8px!important;height:8px!important;margin:0 5px!important}
 .mobile-bottom-nav__item-content{min-width:44px!important;min-height:44px!important;justify-content:center}
}
/* کارت‌های اقدامِ زیرِ قیمت (`product-ask.php`) و کادرِ همتا (`product-alert.php`): یک رنگ‌بندی */
.stlh-ask{border-radius:var(--sl-r-lg)!important;box-shadow:var(--sl-sh-1)}
.stlh-ask__card,.stlh-ask__card--cmp{--g1:var(--sl-blue)!important;--tint:#f5f7fa!important;--line:#e6e9ef!important;border-radius:var(--sl-r-sm)!important}
.stlh-ask__card-ico{border-radius:var(--sl-r-sm)!important}
.bakala-alert-box.stlh-alert{border:1px solid #e6e9ef!important;border-radius:var(--sl-r-lg)!important;background:#f7f9fb!important;padding:0!important}
.stlh-alert .alert-box-wrap{border:0!important;padding:14px 16px!important;border-radius:var(--sl-r-lg)!important}
.stl-hc{border-radius:var(--sl-r-lg)!important}
/* ۲) «تومان» با کلمه در فهرستِ محصولاتِ دسته‌ها */
ul.products .woocommerce-Price-currencySymbol{font-size:11.5px!important;font-weight:600;color:#64748b;margin-inline-start:3px}
ul.products .woocommerce-Price-currencySymbol::after{display:none!important}
/* ۴) وضوح */
.bakala-product-breadcrumb-item a,.woocommerce-breadcrumb a{color:var(--sl-muted)!important}
CSS;
}

add_action( 'wp_head', static function (): void {
	if ( is_admin() ) {
		return;
	}
	echo '<style id="stlh-design">' . stlh_design_css() . "</style>\n"; // phpcs:ignore WordPress.Security.EscapeOutput -- CSSِ ثابتِ خودمان
}, 40 );
