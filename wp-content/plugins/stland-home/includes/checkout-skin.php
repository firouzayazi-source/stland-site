<?php
/**
 * پوسته‌ی سبد خرید و پرداخت به سبکِ دیجی‌کالا — فقط ظاهر.
 *
 * صاحب فروشگاه (۲۲ مهر ۱۴۰۵): «طراحی مثل دیجی‌کالا، کارآمد، سبک و قوی؛ رنگ آبی ولی متناسب و قابلِ تغییر».
 * ساختار و کارکردِ «سبد/پرداختِ سبکِ دو»ِ باکالا (درخواستِ پس‌زمینه، جایگاه، آدرس‌پلاس، کیف پول، درگاه‌ها)
 * دست نمی‌خورد؛ این فایل فقط یک لایه CSS روی همان markup می‌گذارد. فایلِ قالب ویرایش نمی‌شود.
 *
 *  • کلیدِ خاموش/روشن و رنگ در تنظیماتِ افزونه (`co_skin`، `co_accent`)؛ رنگِ خالی = «رنگ تأکیدی» افزونه.
 *  • همه‌ی قاعده‌ها زیرِ `body.stlh-dk` — بی آن کلاس هیچ اثری ندارد، پس خاموش کردن یعنی دقیقاً رفتارِ قبلی.
 *  • تخفیف/خطا قرمز می‌ماند (معنا دارد)؛ بقیه‌ی تأکیدها همان یک رنگ.
 */
defined( 'ABSPATH' ) || exit;

function stlh_dk_on(): bool {
	if ( '1' !== (string) stlh_opt( 'co_skin' ) || is_admin() ) {
		return false;
	}
	return ( function_exists( 'is_cart' ) && is_cart() ) || ( function_exists( 'is_checkout' ) && is_checkout() );
}

function stlh_dk_accent(): string {
	$c = sanitize_hex_color( (string) stlh_opt( 'co_accent' ) );
	if ( ! $c ) {
		$c = sanitize_hex_color( (string) stlh_opt( 'accent' ) ) ?: '#1f6fd6';
	}
	return $c;
}

add_filter( 'body_class', static function ( array $c ): array {
	if ( stlh_dk_on() ) {
		$c[] = 'stlh-dk';
	}
	return $c;
} );

add_action( 'wp_head', static function (): void {
	if ( ! stlh_dk_on() ) {
		return;
	}
	echo '<style id="stlh-dk">' . str_replace( '__ACCENT__', stlh_dk_accent(), STLH_DK_CSS ) . "</style>\n";
}, 99 );

const STLH_DK_CSS = <<<'CSS'
body.stlh-dk{--dk-a:__ACCENT__;--dk-ink:#23254e;--dk-text:#3f4064;--dk-muted:#81858b;--dk-line:#e0e0e6;--dk-soft:#f5f6f8;--dk-red:#ef4056;--dk-green:#00a049;--dk-a-soft:color-mix(in srgb,var(--dk-a) 8%,#fff);background:#fff}
body.stlh-dk .bk-cart{--c-a1:var(--dk-a);--c-a2:var(--dk-a);--c-ink:var(--dk-ink);--c-text:var(--dk-text);--c-muted:var(--dk-muted);--c-line:var(--dk-line);--c-soft:var(--dk-soft);--c-a1-soft:var(--dk-a-soft);--c-a2-soft:var(--dk-a-soft);--apb-color:var(--dk-a);padding-top:16px}
/* کارت‌ها: سفید، خطِ نازک، گوشه‌ی کم، بی سایه */
body.stlh-dk .bk-cart-card,body.stlh-dk .bk-co-card{border:1px solid var(--dk-line);border-radius:8px;box-shadow:none;background:#fff}
body.stlh-dk .bk-cart-grid{gap:16px;grid-template-columns:minmax(0,1fr) 340px}
/* سرتیترِ سبد: زبانه‌ی زیرخط‌دار به‌جای دکمه‌ی تیره */
body.stlh-dk .bk-cart-tabs{gap:0;padding:0;border:0;border-bottom:1px solid var(--dk-line);border-radius:0;background:none}
body.stlh-dk .bk-cart .bk-cart-tab{height:44px;padding:0 16px;border-radius:0;border-bottom:3px solid transparent;margin-bottom:-1px;font-weight:700;color:var(--dk-muted)}
body.stlh-dk .bk-cart .bk-cart-tab.is-active{background:none;color:var(--dk-a);border-bottom-color:var(--dk-a)}
body.stlh-dk .bk-cart .bk-cart-tab b,body.stlh-dk .bk-cart .bk-cart-tab.is-active b,body.stlh-dk .bk-cart-title b{background:var(--dk-soft);color:var(--dk-muted);font-weight:700}
body.stlh-dk .bk-cart .bk-cart-tab.is-active b{background:var(--dk-a);color:#fff}
/* ردیفِ کالا */
body.stlh-dk .bk-cart-items-head{padding:16px 20px;border-bottom:1px solid var(--dk-line)}
body.stlh-dk .bk-cart-item{padding:20px;border-bottom-color:var(--dk-line)}
body.stlh-dk .bk-cart .bk-cart-thumb{background:#fff;border:1px solid var(--dk-line);border-radius:8px}
body.stlh-dk .bk-cart .bk-cart-name{font-weight:600;color:var(--dk-ink);height:auto;max-height:52px}
body.stlh-dk .bk-cart-chip{background:none;padding:0;height:auto;color:var(--dk-text);font-weight:500}
body.stlh-dk .bk-cart-chip+.bk-cart-chip::before{content:"•";margin-inline-end:6px;color:var(--dk-line)}
body.stlh-dk .bk-cart-meta svg{color:var(--dk-muted)}
body.stlh-dk .bk-cart-now{font-size:17px;font-weight:800;color:var(--dk-ink)}
body.stlh-dk .bk-cart-off{background:var(--dk-red);border-radius:16px}
body.stlh-dk .bk-cart-rows .is-minus dd,body.stlh-dk .bk-cart-rows .is-minus dd .woocommerce-Price-currencySymbol{color:var(--dk-red)}
body.stlh-dk .bk-cart-was del{color:var(--dk-muted)}
/* شمارنده‌ی تعداد: قابِ خاکستری، دکمه‌ها رنگِ تأکید */
body.stlh-dk .bk-cart-qty{border:1px solid var(--dk-line);border-radius:8px;box-shadow:0 1px 2px rgb(0 0 0/.04)}
body.stlh-dk .bk-cart .bk-cart-qty button{color:var(--dk-a);border-radius:6px}
body.stlh-dk .bk-cart .bk-cart-qty button:hover{background:var(--dk-a-soft)}
body.stlh-dk .bk-cart-qty output{color:var(--dk-a)}
body.stlh-dk .bk-cart .bk-cart-act.is-danger:hover{background:#fff0f2;color:var(--dk-red)}
/* خلاصه/جزئیاتِ پرداخت: کارتِ سفید به‌جای کارتِ تیره‌ی گرادیانی */
body.stlh-dk .bk-cart .bk-cart-summary,body.stlh-dk .bk-cart .bk-co-summary{background:#fff;color:var(--dk-text);border:1px solid var(--dk-line);border-radius:8px;box-shadow:none;padding:20px}
body.stlh-dk .bk-cart .bk-cart-summary::before,body.stlh-dk .bk-cart .bk-cart-summary::after,body.stlh-dk .bk-co-summary::before,body.stlh-dk .bk-co-summary::after{display:none}
body.stlh-dk .bk-cart .bk-cart-summary h2,body.stlh-dk .bk-cart .bk-co-summary h2,body.stlh-dk .bk-co-summary-head h2{color:var(--dk-ink);font-size:15px;font-weight:800}
body.stlh-dk .bk-cart .bk-cart-summary .bk-cart-rows dt,body.stlh-dk .bk-co-summary .bk-cart-rows dt{color:var(--dk-muted)}
body.stlh-dk .bk-cart .bk-cart-summary .bk-cart-rows dd,body.stlh-dk .bk-cart .bk-cart-summary .bk-cart-rows dd .amount,body.stlh-dk .bk-co-summary .bk-cart-rows dd,body.stlh-dk .bk-co-summary .bk-cart-rows dd .amount{color:var(--dk-text)}
body.stlh-dk .bk-cart .bk-cart-summary .woocommerce-Price-currencySymbol,body.stlh-dk .bk-co-summary .woocommerce-Price-currencySymbol{color:var(--dk-muted)}
body.stlh-dk .bk-cart .bk-cart-summary .bk-cart-total,body.stlh-dk .bk-co-summary .bk-cart-total{border-top:1px solid var(--dk-line)}
body.stlh-dk .bk-cart .bk-cart-summary .bk-cart-total>span:first-child,body.stlh-dk .bk-cart .bk-cart-summary .bk-cart-sum,body.stlh-dk .bk-co-summary .bk-cart-total>span:first-child,body.stlh-dk .bk-co-summary .bk-cart-sum{color:var(--dk-ink)}
body.stlh-dk .bk-cart-sum{font-size:18px;font-weight:800}
body.stlh-dk .bk-cart .bk-cart-summary .bk-cart-sum small,body.stlh-dk .bk-co-summary .bk-cart-sum small{color:var(--dk-muted)}
body.stlh-dk .bk-cart .bk-cart-summary .bk-cart-profit,body.stlh-dk .bk-co-summary .bk-cart-profit,body.stlh-dk .bk-cart-profit{background:#fff0f2;color:var(--dk-red);border-radius:8px}
body.stlh-dk .bk-co-summary .bk-co-stack span{border-color:#fff}
body.stlh-dk .bk-cart .bk-cart-summary .bk-cart-sk,body.stlh-dk .bk-co-summary .bk-cart-sk{background:linear-gradient(90deg,var(--dk-soft) 25%,#fff 50%,var(--dk-soft) 75%);background-size:200% 100%}
/* دکمه‌ی اصلی */
body.stlh-dk .bk-cart .bk-cart-cta,body.stlh-dk .bk-co-cta{height:48px;border-radius:8px;background:var(--dk-a);box-shadow:none;font-size:14px;font-weight:700}
body.stlh-dk .bk-cart .bk-cart-cta:hover{box-shadow:none;filter:brightness(.93)}
body.stlh-dk .bk-cart .bk-cart-btn{border-color:var(--dk-a);color:var(--dk-a);border-radius:8px}
body.stlh-dk .bk-cart-note{padding:12px 4px;color:var(--dk-muted)}
/* کد تخفیف */
body.stlh-dk .bk-cart-coupon-toggle .ic{background:var(--dk-a-soft);color:var(--dk-a);border-radius:8px}
body.stlh-dk .bk-cart .bk-cart-coupon-form input{border-radius:8px;background:#fff;border:1px solid var(--dk-line)}
body.stlh-dk .bk-cart .bk-cart-coupon-form button{border-radius:8px;background:var(--dk-a)}
/* پیشرفتِ ارسالِ رایگان و مراحل */
body.stlh-dk .bk-cart-fill,body.stlh-dk .bk-co-stp-line.is-done{background:var(--dk-a)}
body.stlh-dk .bk-co-stp.is-current i{border-color:var(--dk-a);background:var(--dk-a);box-shadow:0 0 0 4px var(--dk-a-soft)}
body.stlh-dk .bk-co-step{border-radius:50%;background:var(--dk-a);width:28px;height:28px}
body.stlh-dk .bk-co-card-head h2{color:var(--dk-ink);font-weight:800}
/* آدرس، روشِ ارسال و پرداخت: گزینه‌های قاب‌دار، انتخاب‌شده با خطِ رنگی */
body.stlh-dk .bk-co .apb-card,body.stlh-dk .bk-co #shipping_method li,body.stlh-dk .bk-cart.bk-co #payment ul.payment_methods li.wc_payment_method{border:1px solid var(--dk-line);border-radius:8px;background:#fff;box-shadow:none}
body.stlh-dk .bk-co .apb-card.is-selected,body.stlh-dk .bk-co #shipping_method li:has(input:checked),body.stlh-dk .bk-co #shipping_method li:only-child,body.stlh-dk .bk-cart.bk-co #payment ul.payment_methods li.wc_payment_method:has(>input:checked),body.stlh-dk .bk-cart.bk-co #payment ul.payment_methods li.wc_payment_method:only-child{border-color:var(--dk-a);background:var(--dk-a-soft)}
body.stlh-dk .bk-co .apb-badge{border-radius:6px}
body.stlh-dk .bk-co .apb-btn.apb-add{border-radius:8px;border-color:var(--dk-line)}
body.stlh-dk.bk-co-two .apb-btn.apb-submit{background:var(--dk-a);border-radius:8px}
body.stlh-dk .bk-cart.bk-co .form-row input.input-text,body.stlh-dk .bk-cart.bk-co .form-row select,body.stlh-dk .bk-cart.bk-co .form-row textarea,body.stlh-dk .bk-cart.bk-co .select2-container .select2-selection--single{border-radius:8px;background:#fff;border:1px solid var(--dk-line)}
body.stlh-dk .bk-cart.bk-co .form-row input.input-text:focus,body.stlh-dk .bk-cart.bk-co .form-row textarea:focus{border-color:var(--dk-a);box-shadow:0 0 0 3px var(--dk-a-soft)}
body.stlh-dk .bk-co .woocommerce-error{border-radius:8px}
/* نوارِ پایینِ گوشی */
@media (max-width:767px){
body.stlh-dk .bk-cart-grid{grid-template-columns:minmax(0,1fr);gap:12px}
body.stlh-dk .bk-cart-card,body.stlh-dk .bk-co-card{border-radius:8px}
body.stlh-dk .bk-cart-bar,body.stlh-dk .bk-co-bar{border-top:1px solid var(--dk-line);box-shadow:0 -4px 12px rgb(0 0 0/.06)}
body.stlh-dk .bk-cart .bk-cart-bar .bk-cart-cta,body.stlh-dk .bk-co-bar .bk-co-cta{height:46px;border-radius:8px}
body.stlh-dk .bk-cart .bk-cart-tab{flex:1;justify-content:center}
}
CSS;
