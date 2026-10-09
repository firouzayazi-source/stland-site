<?php
/**
 * دیده شدن برای موتورهای جستجو و هوش مصنوعی (ChatGPT، Perplexity، Gemini، Claude…).
 *
 * صاحب فروشگاه (مهر ۱۴۰۵): «سایت را جوری کن که همه‌ی مرورگرها و هوش مصنوعی‌ها ما را
 * راحت پیدا کنند؛ عکس‌ها، کالای دست‌دوم، لوازم جانبی، اقساط، آدرس، ساعت کاری…»
 *
 * بررسیِ سایتِ زنده پیش از این فایل: ربات‌ها راه دارند، عنوان/توضیح/og:image درست است،
 * ولی دادهٔ ساختاریافته‌ی محصول **وضعیت (نو/کارکرده)، برند، مشخصات، باتری و توضیح**
 * نداشت و فقط **یک** عکس معرفی می‌کرد؛ نقشه‌ی سایت هم فقط عکسِ اول؛ `llms.txt` نبود؛
 * و صفحه‌ی «روش‌های ارسال» (از دموی قالب) چند لینکِ اسپمِ قمار داشت.
 *
 * ─── چه می‌کند ───
 * ۱. Product (فیلترِ ووکامرس): itemCondition، brand، همه‌ی عکس‌ها، description از دادهٔ
 *    حسابداری، additionalProperty (حافظه، رنگ، پارت‌نامبر، باتری، چرخه‌ی شارژ، رجیستر،
 *    نتیجه‌ی دفترچه‌ی سلامت)، seller = همان فروشگاهِ صفحه‌ی اصلی (@id).
 * ۲. فروشگاه: ساعت کاری، ایمیل، پرداخت، محدوده‌ی خدمت — از تنظیمات («اطلاعات فروشگاه»).
 * ۳. `/llms.txt`: معرفیِ فروشگاه + فهرستِ زنده‌ی محصولات با قیمت و وضعیت، برای هوش مصنوعی.
 * ۴. نقشه‌ی سایت (Yoast): همه‌ی عکس‌های گالری با متنِ جایگزین.
 * ۵. پاک کردنِ اسپمِ قمار از خروجیِ هر صفحه (پایگاه داده دست نمی‌خورد).
 *
 * ⛔ چیزی حدس زده نمی‌شود: هر فیلدی که داده‌اش نیست، نوشته نمی‌شود.
 */

defined( 'ABSPATH' ) || exit;

/* ───────────────────────── ابزار ───────────────────────── */

/** ارقامِ فارسی → انگلیسی */
function stlh_ai_en_digits( string $s ): string {
	return strtr( $s, [ '۰' => '0', '۱' => '1', '۲' => '2', '۳' => '3', '۴' => '4', '۵' => '5', '۶' => '6', '۷' => '7', '۸' => '8', '۹' => '9', '٪' => '%' ] );
}

/** نامک‌های همه‌ی دسته‌های محصول و والدهایشان */
function stlh_ai_cat_slugs( int $product_id ): array {
	$slugs = [];
	foreach ( (array) wp_get_post_terms( $product_id, 'product_cat' ) as $t ) {
		if ( ! $t instanceof WP_Term ) {
			continue;
		}
		$slugs[] = rawurldecode( $t->slug );
		foreach ( get_ancestors( $t->term_id, 'product_cat' ) as $aid ) {
			$a = get_term( $aid, 'product_cat' );
			if ( $a instanceof WP_Term ) {
				$slugs[] = rawurldecode( $a->slug );
			}
		}
	}
	return array_values( array_unique( $slugs ) );
}

/** نو / کارکرده — از متای حسابداری، بعد از دسته */
function stlh_ai_condition( int $product_id ): string {
	$c = (string) get_post_meta( $product_id, 'condition', true );
	if ( '' !== $c ) {
		if ( preg_match( '/کارکرده|استوک|دست\s*دوم|used/ui', $c ) ) {
			return 'used';
		}
		if ( preg_match( '/نو|آکبند|new/ui', $c ) ) {
			return 'new';
		}
	}
	foreach ( stlh_ai_cat_slugs( $product_id ) as $s ) {
		if ( preg_match( '/second-hand|used|stock|کارکرده/i', $s ) ) {
			return 'used';
		}
	}
	return 'new';
}

/** برند — فقط وقتی از روی نام/ویژگی معلوم است */
function stlh_ai_brand( WC_Product $p ): string {
	$hay = $p->get_name() . ' ' . (string) $p->get_attribute( 'برند' ) . ' ' . (string) get_post_meta( $p->get_id(), 'product_english_name', true );
	if ( preg_match( '/iphone|ipad|airpod|apple|macbook|imac|آیفون|ایرپاد|اپل|آیپد|مک.?بوک/ui', $hay ) ) {
		return 'Apple';
	}
	if ( preg_match( '/samsung|سامسونگ/ui', $hay ) ) {
		return 'Samsung';
	}
	if ( preg_match( '/xiaomi|شیائومی/ui', $hay ) ) {
		return 'Xiaomi';
	}
	if ( preg_match( '/belkin|بلکین/ui', $hay ) ) {
		return 'Belkin';
	}
	if ( preg_match( '/anker|انکر/ui', $hay ) ) {
		return 'Anker';
	}
	return '';
}

/** همه‌ی عکس‌های محصول (اصلی + گالری)، اندازه‌ی کامل */
function stlh_ai_images( WC_Product $p ): array {
	$ids  = array_filter( array_merge( [ (int) $p->get_image_id() ], array_map( 'intval', $p->get_gallery_image_ids() ) ) );
	$urls = [];
	foreach ( array_unique( $ids ) as $id ) {
		$u = wp_get_attachment_image_url( $id, 'full' );
		if ( $u ) {
			$urls[] = $u;
		}
	}
	return $urls;
}

/** مشخصاتِ کلیدی: [نام => مقدار] از main_features، ویژگی‌ها و متای حسابداری */
function stlh_ai_specs( WC_Product $p ): array {
	$id    = $p->get_id();
	$specs = [];
	$mf    = get_post_meta( $id, 'main_features', true );
	if ( is_string( $mf ) ) {
		$mf = json_decode( $mf, true ) ?: maybe_unserialize( $mf );
	}
	foreach ( is_array( $mf ) ? $mf : [] as $row ) {
		if ( is_array( $row ) && ! empty( $row['title'] ) && isset( $row['value'] ) && '' !== (string) $row['value'] ) {
			$specs[ trim( (string) $row['title'] ) ] = trim( wp_strip_all_tags( (string) $row['value'] ) );
		}
	}
	foreach ( $p->get_attributes() as $attr ) {
		if ( ! $attr instanceof WC_Product_Attribute ) {
			continue;
		}
		$name = wc_attribute_label( $attr->get_name(), $p );
		if ( isset( $specs[ $name ] ) ) {
			continue;
		}
		$val = $attr->is_taxonomy() ? implode( '، ', wc_get_product_terms( $id, $attr->get_name(), [ 'fields' => 'names' ] ) ) : implode( '، ', $attr->get_options() );
		if ( '' !== trim( $val ) ) {
			$specs[ $name ] = $val;
		}
	}
	$h = function_exists( 'stlh_health_data' ) ? stlh_health_data( $id ) : null;
	if ( $h ) {
		if ( null !== $h['cycles'] && ! isset( $specs['تعداد چرخه‌ی شارژ'] ) ) {
			$specs['تعداد چرخه‌ی شارژ'] = (string) $h['cycles'];
		}
		$ok = count( array_filter( $h['items'], static fn( $i ) => 'ok' === $i['t'] ) );
		$specs['دفترچه‌ی سلامت'] = $ok . ' از ' . count( $h['items'] ) . ' مورد سالم';
		foreach ( $h['items'] as $i ) {
			if ( 'ok' !== $i['t'] ) {
				$specs[ $i['l'] ] = $i['v'];
			}
		}
	}
	return $specs;
}

/** توضیحِ کوتاه و واقعی برای ماشین‌ها — وقتی محصول خودش توضیح ندارد */
function stlh_ai_description( WC_Product $p ): string {
	$own = trim( wp_strip_all_tags( $p->get_short_description() ?: $p->get_description() ) );
	if ( '' !== $own ) {
		return mb_substr( preg_replace( '/\s+/u', ' ', $own ), 0, 600 );
	}
	$id    = $p->get_id();
	$specs = stlh_ai_specs( $p );
	$used  = 'used' === stlh_ai_condition( $id );
	$parts = [ $p->get_name() . ( $used ? ' کارکرده' : '' ) . ' از فروشگاه استوک لند (قم).' ];
	$keys  = [ 'حافظه', 'رنگ', 'پارت نامبر', 'سلامت باتری', 'تعداد چرخه‌ی شارژ', 'رجیستر', 'جعبه', 'دفترچه‌ی سلامت' ];
	$bits  = [];
	foreach ( $keys as $k ) {
		if ( ! empty( $specs[ $k ] ) ) {
			$bits[] = $k . ': ' . $specs[ $k ];
		}
	}
	if ( $bits ) {
		$parts[] = implode( '، ', $bits ) . '.';
	}
	$adv = get_post_meta( $id, 'advantages_metas', true );
	$dis = get_post_meta( $id, 'disadvantages_metas', true );
	if ( is_array( $adv ) && $adv ) {
		$parts[] = 'نقاط قوت: ' . implode( '، ', array_map( 'strval', $adv ) ) . '.';
	}
	if ( is_array( $dis ) && $dis ) {
		$parts[] = 'نکات: ' . implode( '، ', array_map( 'strval', $dis ) ) . '.';
	}
	if ( $used ) {
		$parts[] = 'تست کامل پیش از فروش، با ضمانت اصالت و مهلت تست.';
	}
	return implode( ' ', $parts );
}

/* ───────────────────── ۱. دادهٔ ساختاریافته‌ی محصول ───────────────────── */

add_filter( 'woocommerce_structured_data_product', static function ( $markup, $product ) {
	if ( ! is_array( $markup ) || ! $product instanceof WC_Product ) {
		return $markup;
	}
	$id   = $product->get_id();
	$cond = 'used' === stlh_ai_condition( $id ) ? 'https://schema.org/UsedCondition' : 'https://schema.org/NewCondition';

	$markup['itemCondition'] = $cond;
	if ( $brand = stlh_ai_brand( $product ) ) {
		$markup['brand'] = [ '@type' => 'Brand', 'name' => $brand ];
	}
	if ( $imgs = stlh_ai_images( $product ) ) {
		$markup['image'] = count( $imgs ) > 1 ? $imgs : $imgs[0];
	}
	if ( empty( $markup['description'] ) ) {
		$markup['description'] = stlh_ai_description( $product );
	}
	if ( $en = trim( (string) get_post_meta( $id, 'product_english_name', true ) ) ) {
		$markup['alternateName'] = $en;
	}
	$model = (string) $product->get_attribute( 'مدل' );
	if ( '' !== $model ) {
		$markup['model'] = $model;
	}
	if ( $color = (string) ( get_post_meta( $id, 'color', true ) ?: $product->get_attribute( 'رنگ' ) ) ) {
		$markup['color'] = $color;
	}
	$props = [];
	foreach ( stlh_ai_specs( $product ) as $name => $value ) {
		$prop = [ '@type' => 'PropertyValue', 'name' => (string) $name, 'value' => (string) $value ];
		if ( 'سلامت باتری' === $name && preg_match( '/(\d+)/', stlh_ai_en_digits( (string) $value ), $m ) ) {
			$prop['value']    = (int) $m[1];
			$prop['unitText'] = 'percent';
		}
		$props[] = $prop;
	}
	if ( $props ) {
		$markup['additionalProperty'] = $props;
	}
	// فروشنده = همان فروشگاهِ صفحه‌ی اصلی (یک موجودیت، نه چند نامِ جدا)
	if ( ! empty( $markup['offers'] ) && is_array( $markup['offers'] ) ) {
		foreach ( $markup['offers'] as &$offer ) {
			if ( is_array( $offer ) ) {
				$offer['itemCondition'] = $cond;
				$offer['seller']        = [ '@type' => 'MobilePhoneStore', '@id' => home_url( '/#store' ), 'name' => get_bloginfo( 'name' ) ?: 'استوک لند', 'url' => home_url( '/' ) ];
			}
		}
		unset( $offer );
	}
	return $markup;
}, 20, 2 );

/* ───────────────────── ۲. فروشگاه: ساعت کاری و … ───────────────────── */

/**
 * «شنبه تا پنج‌شنبه|10:00|23:00» در هر خط → openingHoursSpecification.
 * روزها: شنبه، یکشنبه… یا «شنبه تا پنج‌شنبه».
 */
function stlh_ai_hours(): array {
	$days  = [ 'شنبه' => 'Saturday', 'یکشنبه' => 'Sunday', 'دوشنبه' => 'Monday', 'سه‌شنبه' => 'Tuesday', 'چهارشنبه' => 'Wednesday', 'پنج‌شنبه' => 'Thursday', 'جمعه' => 'Friday' ];
	$order = array_keys( $days );
	$norm  = static fn( string $s ) => str_replace( [ 'سه شنبه', 'پنجشنبه', 'پنج شنبه', 'یک شنبه', 'سهشنبه' ], [ 'سه‌شنبه', 'پنج‌شنبه', 'پنج‌شنبه', 'یکشنبه', 'سه‌شنبه' ], trim( $s ) );
	$out   = [];
	foreach ( stlh_lines( (string) stlh_opt( 'opening_hours' ) ) as $line ) {
		$p = array_map( 'trim', explode( '|', stlh_ai_en_digits( $line ) ) );
		if ( count( $p ) < 3 || ! preg_match( '/^\d{1,2}:\d{2}$/', $p[1] ) || ! preg_match( '/^\d{1,2}:\d{2}$/', $p[2] ) ) {
			continue;
		}
		$range = array_map( $norm, preg_split( '/\s+تا\s+/u', $p[0] ) );
		$list  = [];
		if ( 2 === count( $range ) && isset( $days[ $range[0] ], $days[ $range[1] ] ) ) {
			$a = array_search( $range[0], $order, true );
			$b = array_search( $range[1], $order, true );
			for ( $i = $a; $i <= $b; $i++ ) {
				$list[] = $days[ $order[ $i ] ];
			}
		} else {
			foreach ( preg_split( '/[،,]\s*/u', $p[0] ) as $d ) {
				if ( isset( $days[ $norm( $d ) ] ) ) {
					$list[] = $days[ $norm( $d ) ];
				}
			}
		}
		if ( $list ) {
			$out[] = [ '@type' => 'OpeningHoursSpecification', 'dayOfWeek' => $list, 'opens' => str_pad( $p[1], 5, '0', STR_PAD_LEFT ), 'closes' => str_pad( $p[2], 5, '0', STR_PAD_LEFT ) ];
		}
	}
	return $out;
}

/** متنِ خوانای ساعت کاری (برای llms.txt) */
function stlh_ai_hours_text(): string {
	$lines = [];
	foreach ( stlh_lines( (string) stlh_opt( 'opening_hours' ) ) as $line ) {
		$p = array_map( 'trim', explode( '|', $line ) );
		if ( count( $p ) >= 3 ) {
			$lines[] = $p[0] . ': ' . $p[1] . ' تا ' . $p[2];
		}
	}
	return implode( '؛ ', $lines );
}

add_filter( 'stlh_local_business', static function ( array $data ): array {
	if ( $h = stlh_ai_hours() ) {
		$data['openingHoursSpecification'] = $h;
	}
	if ( $email = sanitize_email( (string) stlh_opt( 'store_email' ) ) ) {
		$data['email'] = $email;
	}
	if ( $pay = trim( (string) stlh_opt( 'payment_methods' ) ) ) {
		$data['paymentAccepted'] = $pay;
	}
	$data['currenciesAccepted'] = 'IRR';
	$data['areaServed']         = [ '@type' => 'Country', 'name' => 'Iran' ];
	$data['knowsAbout']         = [ 'iPhone', 'آیفون نو', 'آیفون کارکرده', 'AirPods', 'لوازم جانبی اپل' ];
	if ( $map = esc_url_raw( (string) stlh_opt( 'map_url' ) ) ) {
		$data['hasMap'] = $map;
	}
	return $data;
} );

/* ───────────────────── ۳. llms.txt ───────────────────── */

function stlh_ai_llms_txt(): string {
	$name  = get_bloginfo( 'name' ) ?: 'استوک لند';
	$home  = home_url( '/' );
	$fa    = static fn( $n ) => function_exists( 'stlh_fa' ) ? stlh_fa( (string) $n ) : (string) $n;
	$out   = [];
	$out[] = '# ' . $name . ' (StockLand) — ' . wp_parse_url( $home, PHP_URL_HOST );
	$out[] = '';
	$out[] = '> فروشگاه تخصصی آیفون نو و کارکرده و لوازم جانبی اپل (ایرپاد، شارژر، محافظ لنز) در قم، با خرید حضوری، ارسال به سراسر ایران، خرید اقساطی، ضمانت اصالت و مهلت تست. هر گوشی کارکرده «دفترچه‌ی سلامت» دارد: درصد سلامت باتری، تعداد چرخه‌ی شارژ، وضعیت ریجستری و نتیجه‌ی تستِ قطعه‌به‌قطعه.';
	$out[] = '';
	$out[] = '## اطلاعات فروشگاه';
	$addr = trim( (string) stlh_opt( 'address' ) );
	if ( '' !== $addr ) {
		$out[] = '- آدرس: ' . $addr;
	}
	if ( $tel = trim( (string) stlh_opt( 'phone' ) ) ) {
		$out[] = '- تلفن: ' . $tel;
	}
	if ( $hours = stlh_ai_hours_text() ) {
		$out[] = '- ساعت کاری: ' . $hours;
	}
	if ( $email = trim( (string) stlh_opt( 'store_email' ) ) ) {
		$out[] = '- ایمیل: ' . $email;
	}
	if ( $pay = trim( (string) stlh_opt( 'payment_methods' ) ) ) {
		$out[] = '- روش‌های پرداخت: ' . $pay;
	}
	if ( $ig = ltrim( (string) stlh_opt( 'instagram' ), '@' ) ) {
		$out[] = '- اینستاگرام: https://instagram.com/' . $ig;
	}
	if ( $bot = ltrim( (string) stlh_opt( 'telegram_bot' ), '@' ) ) {
		$out[] = '- تلگرام: https://t.me/' . $bot;
	}
	$out[] = '- نماد اعتماد الکترونیکی (اینماد): دارد';
	foreach ( stlh_lines( (string) stlh_opt( 'store_facts' ) ) as $fact ) {
		$out[] = '- ' . $fact;
	}
	$out[] = '';

	// سؤالات متداول از همان تنظیماتِ صفحه‌ی اصلی
	$faq = function_exists( 'stlh_faq_items' ) ? stlh_faq_items() : [];
	if ( $faq ) {
		$out[] = '## پرسش‌های متداول';
		foreach ( $faq as $qa ) {
			$out[] = '- ' . $qa['q'] . ' — ' . preg_replace( '/\s+/u', ' ', $qa['a'] );
		}
		$out[] = '';
	}

	// دسته‌ها
	$terms = get_terms( [ 'taxonomy' => 'product_cat', 'hide_empty' => true ] );
	if ( is_array( $terms ) && $terms ) {
		$out[] = '## دسته‌بندی‌ها';
		foreach ( $terms as $t ) {
			if ( 'uncategorized' === $t->slug ) {
				continue;
			}
			$link = get_term_link( $t );
			if ( is_string( $link ) ) {
				$out[] = '- [' . $t->name . '](' . $link . ') — ' . $fa( $t->count ) . ' کالا';
			}
		}
		$out[] = '';
	}

	// محصولاتِ موجود با قیمت و وضعیت
	$ids = wc_get_products( [ 'status' => 'publish', 'stock_status' => 'instock', 'limit' => 200, 'orderby' => 'date', 'order' => 'DESC', 'return' => 'ids' ] );
	if ( $ids ) {
		$out[] = '## محصولات موجود (به‌روز)';
		foreach ( $ids as $pid ) {
			$p = wc_get_product( $pid );
			if ( ! $p ) {
				continue;
			}
			$specs = stlh_ai_specs( $p );
			$bits  = [ 'used' === stlh_ai_condition( $pid ) ? 'کارکرده' : 'نو' ];
			foreach ( [ 'حافظه', 'رنگ', 'سلامت باتری', 'تعداد چرخه‌ی شارژ', 'رجیستر', 'پارت نامبر' ] as $k ) {
				if ( ! empty( $specs[ $k ] ) ) {
					$bits[] = $k . ' ' . $specs[ $k ];
				}
			}
			$price = (float) $p->get_price();
			if ( $price > 0 ) {
				$bits[] = 'قیمت ' . html_entity_decode( wp_strip_all_tags( wc_price( $price ) ) );
			}
			$out[] = '- [' . $p->get_name() . '](' . get_permalink( $pid ) . ') — ' . implode( '، ', $bits );
		}
		$out[] = '';
	}

	$out[] = '## صفحه‌های مهم';
	foreach ( [ 'about', 'contact', 'faq', 'how-to-order', 'terms-conditions', 'return-policy', 'payment-terms', 'delivery' ] as $slug ) {
		$page = get_page_by_path( $slug );
		if ( $page && 'publish' === $page->post_status ) {
			$out[] = '- [' . get_the_title( $page ) . '](' . get_permalink( $page ) . ')';
		}
	}
	if ( function_exists( 'wc_get_page_permalink' ) ) {
		$out[] = '- [فروشگاه](' . wc_get_page_permalink( 'shop' ) . ')';
	}
	$out[] = '';
	$out[] = 'به‌روزرسانی: ' . wp_date( 'Y-m-d H:i' ) . ' (به وقت تهران). قیمت‌ها به تومان و لحظه‌ای است.';
	return implode( "\n", $out ) . "\n";
}

/** نشانیِ /llms.txt — پیش از هر کارِ دیگرِ وردپرس جواب می‌دهد؛ تا تغییرِ بعدی (نسلِ کش) نگه داشته می‌شود */
add_action( 'init', static function (): void {
	$path = (string) wp_parse_url( (string) ( $_SERVER['REQUEST_URI'] ?? '' ), PHP_URL_PATH );
	if ( '/llms.txt' !== $path || ! in_array( $_SERVER['REQUEST_METHOD'] ?? 'GET', [ 'GET', 'HEAD' ], true ) ) {
		return;
	}
	$key  = 'stlh_llms_' . ( function_exists( 'stlh_cache_gen' ) ? stlh_cache_gen() : 0 ) . '_' . STLH_VER;
	$body = get_transient( $key );
	if ( ! is_string( $body ) ) {
		$body = stlh_ai_llms_txt();
		set_transient( $key, $body, 6 * HOUR_IN_SECONDS );
	}
	status_header( 200 );
	header( 'Content-Type: text/plain; charset=UTF-8' );
	header( 'Cache-Control: public, max-age=3600' );
	header( 'X-Robots-Tag: noindex' );
	echo $body; // phpcs:ignore WordPress.Security.EscapeOutput -- متنِ ساده
	exit;
}, 20 ); // بعد از ثبتِ دسته‌های ووکامرس (init 5)

/* ───────────────────── ۴. نقشه‌ی سایت: همه‌ی عکس‌ها ───────────────────── */

add_filter( 'wpseo_sitemap_urlimages', static function ( $images, $post_id ) {
	if ( 'product' !== get_post_type( $post_id ) || ! function_exists( 'wc_get_product' ) ) {
		return $images;
	}
	$p = wc_get_product( $post_id );
	if ( ! $p ) {
		return $images;
	}
	$images = is_array( $images ) ? $images : [];
	$have   = array_column( $images, 'src' );
	$n      = 0;
	foreach ( array_filter( array_merge( [ (int) $p->get_image_id() ], array_map( 'intval', $p->get_gallery_image_ids() ) ) ) as $aid ) {
		$n++;
		$src = wp_get_attachment_image_url( $aid, 'full' );
		if ( ! $src || in_array( $src, $have, true ) ) {
			continue;
		}
		$alt      = trim( (string) get_post_meta( $aid, '_wp_attachment_image_alt', true ) );
		$images[] = [ 'src' => $src, 'title' => $p->get_name(), 'alt' => '' !== $alt ? $alt : $p->get_name() . ( $n > 1 ? ' — تصویر ' . $n : '' ) ];
		$have[]   = $src;
	}
	return $images;
}, 10, 2 );

/* ───────────────────── ۵. اسپمِ قمار ───────────────────── */

/**
 * صفحه‌ی «روش‌های ارسال» (از دموی قالب) لینک‌های اسپمِ قمارِ اندونزیایی داشت
 * («situs judi slot gacor…»). هوش مصنوعی و گوگل سایت را با آن می‌سنجند. خروجی پاک
 * می‌شود؛ متنِ اصلیِ صفحه را صاحب فروشگاه با محتوای واقعی عوض می‌کند.
 */
function stlh_ai_strip_spam( string $html ): string {
	if ( ! preg_match( '/situs|judi|gacor|togel|slot\s+online|zeus|maxwin/i', $html ) ) {
		return $html;
	}
	$html = preg_replace( '#<a\b[^>]*>[^<]*(?:situs|judi|gacor|togel|slot|maxwin)[^<]*</a>#i', '', $html );
	// رشته‌ای از ۳ واژه یا بیشتر از واژگانِ همین اسپم (متنِ فارسی هرگز با این‌ها جور نمی‌شود)
	$html = preg_replace( '/(?:\b(?:situs|judi|slot|online|bonus|new|member|100|di|awal|to|\dx|spaceman|gacor|gampang|menang|hari|ini|terpercaya|receh|maxwin|togel|zeus|olympus)\b\s*){3,}/i', '', $html );
	return (string) $html;
}
add_filter( 'the_content', 'stlh_ai_strip_spam', 999 );
add_filter( 'elementor/frontend/the_content', 'stlh_ai_strip_spam', 999 );
