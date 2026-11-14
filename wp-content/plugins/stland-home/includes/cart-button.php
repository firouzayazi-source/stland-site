<?php
/**
 * دکمه‌ی «افزودن به سبد» — جلوگیری از درگیریِ دو گرداننده (صاحب فروشگاه، ۲۴ آبان ۱۴۰۵: «روی سبد می‌زنم چیزی نمی‌شه»).
 *
 * فروشگاه‌ساز (۱۱.x، `add-to-cart.js`) روی `.add_to_cart_button:not(.wc-interactive)` و باکالا (`general.js`، گوشی و رایانه)
 * روی همان دکمه‌ها گرداننده دارند. فروشگاه‌ساز زودتر ثبت شده: کلاسِ `loading` می‌گذارد و خودش `wc-ajax=add_to_cart`
 * را می‌فرستد؛ گرداننده‌ی باکالا با دیدنِ `loading` کنار می‌رود و پنجره‌ی «به سبد اضافه شد» هرگز باز نمی‌شود. پیوندِ
 * «مشاهده‌ی سبد»ِ فروشگاه‌ساز را هم قالب پنهان کرده — پس کالا (احتمالاً) اضافه می‌شد ولی مشتری هیچ نشانه‌ای نمی‌دید.
 *
 * چاره: پیش از هر دو (مرحله‌ی capture)، به دکمه‌ی کلیک‌شده `wc-interactive` می‌دهیم تا فروشگاه‌ساز کنار برود و راهِ خودِ
 * باکالا (`wc-ajax=bk_add_to_cart` + پنجره) اجرا شود. در مرورگرِ آزمون روی صفحه‌ی محصولِ گوشی سنجیده شد.
 * ⛔ کدِ قالب دست نمی‌خورد؛ کلیدِ `cart_button_fix` (پیش‌فرض روشن) برای برگرداندن.
 */
defined( 'ABSPATH' ) || exit;

add_action( 'wp_head', static function (): void {
	if ( is_admin() || '0' === (string) stlh_opt( 'cart_button_fix' ) ) {
		return;
	}
	echo '<script data-no-optimize="1" data-no-defer="1">document.addEventListener("click",function(e){var b=e.target&&e.target.closest&&e.target.closest(".ajax_add_to_cart,.single_add_to_cart_button");if(b)b.classList.add("wc-interactive")},true);</script>' . "\n";
}, 1 );
