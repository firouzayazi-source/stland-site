<?php
defined( 'ABSPATH' ) || exit;

function stlh_defaults(): array {
	return [
		'h1'             => 'استوک لند | خرید آیفون نو و کارکرده و لوازم جانبی اپل در قم',
		'banner_desktop' => 0,
		'banner_mobile'  => 0,
		'banner_link'    => '',
		'banner_alt'     => 'استوک لند',
		'cat_title'      => 'دسته‌بندی‌های استوک لند',
		'cat_subtitle'   => 'لوازم جانبی و محصولات اپل با بالاترین کیفیت',
		'categories'     => "iphone-second-hand\niphone\nairpod\ncharger\naccessories\nlens-protector",
		'product_rows'   => "iphone|8|گوشی‌های نو استوک لند|آیفون نو با ضمانت اصالت کالا",
		'flash_mode'     => 'featured',
		'flash_product'  => 0,
		'flash_badge'    => '🔥 پیشنهاد ویژه استوک لند',
		'instagram'      => 'stock_land.ir',
		'telegram_bot'   => 'stock_land_bot',
		'phone'          => '09050323217',
		'address'        => 'قم، ۵۵ متری عمار یاسر، مجتمع تجاری بازار سلام، طبقه اول، واحد اف ۱۱',
		'faq'            => "شرایط خرید اقساطی گوشی‌های استوک لند به چه صورت است؟\nما شرایط خرید اقساطی ویژه‌ای را برای شما در نظر گرفته‌ایم. برای اطلاع از پیش‌پرداخت، تعداد قسط و مدارک لازم، لطفاً مستقیم با شماره ۰۹۰۵۰۳۲۳۲۱۷ تماس بگیرید.\n\n"
			. "آیا امکان خرید حضوری و مراجعه به فروشگاه وجود دارد؟\nبله حتماً! با کمال میل میزبان شما هستیم. آدرس مراجعه حضوری: قم، ۵۵ متری عمار یاسر، مجتمع تجاری بازار سلام، طبقه اول، واحد اف ۱۱\n\n"
			. "آیا گوشی‌های استوک دارای ضمانت و مهلت تست هستند؟\nبله، تمامی محصولات فروشگاه همراه با گارانتی اصالت کالا و مهلت تست مشخص ارائه می‌شوند تا با خیال راحت خرید خود را نهایی کنید.\n\n"
			. "چگونه می‌توانیم از سلامت سخت‌افزاری دستگاه مطمئن شویم؟\nدستگاه‌ها پیش از ارائه، توسط تیم فنی استوک لند تست کامل می‌شوند و پارت‌نامبر و وضعیت سلامت قطعات به صورت شفاف به مشتری اعلام می‌گردد.",
		'posts_count'    => 3,
		'takeover'       => '1',
		'accent'         => '#007bff',
		'cat_style'      => 'icon',
		'group_parent'   => 'accessories',
		'group_limit'    => 12,
		'group_title'    => 'لوازم جانبی استوک لند',
		'group_subtitle' => 'همه لوازم جانبی، به تفکیک دسته',
		'font_enable'    => '1',
		'font_sitewide'  => '',
		'trust'          => "shield|گارانتی اصالت|تضمین اصالت و سلامت کالا\nclock|مهلت تست|تست با خیال راحت پس از خرید\ncard|خرید اقساطی|شرایط ویژه پرداخت قسطی\nstore|خرید حضوری|قم، بازار سلام، واحد F11",
		'card_badges'    => "battery|باتری|٪\nregistry|ریجستری|",
		// صاحب فروشگاه: نوار اعتماد زیر بنر نه، درست پیش از سوالات متداول.
		// صاحب فروشگاه: دسته‌ها ← پیشنهاد ویژه ← آیفون نو ← کارکرده‌ها ← لوازم جانبی ← پرفروش‌ها
		'order'          => "banner\ncategories\nflash_deal\nrows\ngroup\nbest\nsocial\ntrust\nfaq\nposts",
		'best_cat'       => 'best-sellers',
		'recent_span'    => 5,
		// دسته‌هایی که صاحب فروشگاه با تیک از صفحه‌ی اصلی پنهان کرده (شناسه‌ی ترم)
		'hide_cats'      => [],
		// auto: همه‌ی دسته‌های اصلیِ ووکامرس (که با «یکی کردن با سایت» همان درختِ حسابداری است)
		'cat_source'     => 'auto',
		'rows_source'    => 'auto',
		// ردیف‌هایی که صاحب فروشگاه در تنظیمات چیده: [{cat: شناسه‌ی ترم, mode, title, limit}]؛ خالی = خودکار از درخت
		'lines'          => [],
		'city'           => 'قم',
	];
}

function stlh_opt( string $key ): mixed {
	static $opts = null;
	if ( null === $opts ) {
		$opts = wp_parse_args( (array) get_option( STLH_OPT, [] ), stlh_defaults() );
		// ترتیبِ پیش‌فرضِ نسخه‌ی ۱.۵.۰ که ذخیره شده بود، یعنی کسی دستش نزده؛ پیش‌فرضِ تازه جایش.
		// ترتیب‌های پیش‌فرضِ نسخه‌های قبل که ذخیره شده‌اند یعنی کسی دستشان نزده؛ پیش‌فرضِ تازه جایشان.
		if ( in_array( $opts['order'], [ "banner\ntrust\ncategories\nflash_deal\nrows\ngroup\nsocial\nfaq\nposts", "banner\ncategories\nflash_deal\nrows\ngroup\nsocial\ntrust\nfaq\nposts" ], true ) ) {
			$opts['order'] = stlh_defaults()['order'];
		}
	}
	return $opts[ $key ] ?? null;
}

function stlh_assets(): void {
	wp_enqueue_style( 'stland-home-vars' );
	wp_enqueue_style( 'stland-home' );
	wp_enqueue_script( 'stland-home' );
}

function stlh_fa( string|int $s ): string {
	return strtr( (string) $s, [ '0' => '۰', '1' => '۱', '2' => '۲', '3' => '۳', '4' => '۴', '5' => '۵', '6' => '۶', '7' => '۷', '8' => '۸', '9' => '۹' ] );
}

/** خطوط غیرخالی یک textarea */
function stlh_lines( string $s ): array {
	return array_values( array_filter( array_map( 'trim', preg_split( '/\R/u', $s ) ) ) );
}

/** سوالات: هر بلوک با یک خط خالی جدا می‌شود؛ خط اول سوال، بقیه جواب */
function stlh_faq_items(): array {
	$items = [];
	foreach ( preg_split( '/\R\s*\R/u', trim( (string) stlh_opt( 'faq' ) ) ) as $block ) {
		$lines = stlh_lines( $block );
		if ( count( $lines ) < 2 ) {
			continue;
		}
		$items[] = [ 'q' => array_shift( $lines ), 'a' => implode( "\n", $lines ) ];
	}
	return $items;
}

function stlh_arrow(): string {
	return '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M15.41 7.41L14 6l-6 6 6 6 1.41-1.41L10.83 12z"/></svg>';
}

/** مجموعه آیکن خطی دسته‌بندی‌ها (هم‌خانواده با آیکن‌های نوار اعتماد) */
function stlh_cat_icons(): array {
	return [
		'phone-new'  => '<rect x="5.5" y="2.5" width="11" height="19" rx="3"/><path d="M9.5 5h3"/><path d="M20 3v3.5M18.25 4.75h3.5"/>',
		'phone-used' => '<rect x="6.5" y="2.5" width="11" height="19" rx="3"/><path d="M10.5 5h3"/><path d="M14.6 12.4a2.6 2.6 0 1 1-.8-2.2"/><path d="M14.2 8.7v1.8h-1.8"/>',
		'earbuds'    => '<path d="M8 4.5a3 3 0 0 0-3 3v1a3 3 0 0 0 3 3V19a1.5 1.5 0 0 0 3 0V7.5a3 3 0 0 0-3-3z"/><path d="M16 4.5a3 3 0 0 1 3 3v1a3 3 0 0 1-3 3V19a1.5 1.5 0 0 1-3 0V7.5a3 3 0 0 1 3-3z"/>',
		'charger'    => '<rect x="6" y="8" width="12" height="13" rx="3"/><path d="M9.5 8V3.5M14.5 8V3.5"/><path d="M12.6 11l-2 3.4h3l-2 3.4"/>',
		'cable'      => '<path d="M7 3v4M11 3v4"/><rect x="5.5" y="7" width="7" height="5" rx="1.5"/><path d="M9 12v3a4 4 0 0 0 4 4h2a3 3 0 0 0 3-3V9"/><rect x="16" y="4" width="4" height="5" rx="1"/>',
		'camera'     => '<rect x="3.5" y="3.5" width="17" height="17" rx="5"/><circle cx="9" cy="9" r="2.6"/><circle cx="9" cy="15.5" r="2.6"/><circle cx="15.5" cy="12.2" r="2.6"/><path d="M16 7h.01"/>',
		'watch'      => '<rect x="6.5" y="6" width="11" height="12" rx="3"/><path d="M9 6l.7-3h4.6l.7 3M9 18l.7 3h4.6l.7-3"/><path d="M12 9.5V12l1.5 1"/>',
		'headphones' => '<path d="M4 15v-3a8 8 0 0 1 16 0v3"/><rect x="3.5" y="14" width="4" height="6.5" rx="1.5"/><rect x="16.5" y="14" width="4" height="6.5" rx="1.5"/>',
		'powerbank'  => '<rect x="6" y="3" width="12" height="18" rx="3"/><path d="M10 6.5h4"/><path d="M12.6 10l-2 3.2h3l-2 3.3"/>',
		'speaker'    => '<rect x="5.5" y="3" width="13" height="18" rx="3"/><circle cx="12" cy="14" r="3.2"/><path d="M12 7h.01"/>',
		'case'       => '<rect x="6" y="2.5" width="12" height="19" rx="3.2"/><rect x="8.5" y="5" width="4.2" height="4.2" rx="1.3"/><path d="M10.6 7.1h.01"/>',
		'tablet'     => '<rect x="4" y="3" width="16" height="18" rx="2.5"/><path d="M11 18h2"/>',
		'laptop'     => '<rect x="5" y="5" width="14" height="10" rx="1.5"/><path d="M3 18.5h18"/>',
		'tag'        => '<path d="M3.5 12.5V4.5a1 1 0 0 1 1-1h8l8 8-9 9z"/><circle cx="8" cy="8" r="1.5"/>',
	];
}

/** نامک (و نام) دسته ← کلید آیکن پیش‌فرض */
function stlh_cat_icon_key( string $slug, string $name = '' ): string {
	$map = [
		'iphone'             => 'phone-new',
		'iphone-second-hand' => 'phone-used',
		'airpod'             => 'earbuds',
		'charger'            => 'charger',
		'accessories'        => 'cable',
		'lens-protector'     => 'camera',
	];
	if ( isset( $map[ $slug ] ) ) {
		return $map[ $slug ];
	}
	// نامک دسته‌هایی که حسابداری می‌سازد ممکن است فارسی باشد؛ پس از روی نام هم.
	foreach ( [ 'کارکرده' => 'phone-used', 'استوک' => 'phone-used', 'ایرپاد' => 'earbuds', 'ایربادز' => 'earbuds', 'هندزفری' => 'earbuds', 'شارژر' => 'charger', 'آداپتور' => 'charger', 'کابل' => 'cable', 'قاب' => 'case', 'کاور' => 'case', 'ساعت' => 'watch', 'واچ' => 'watch', 'هدفون' => 'headphones', 'پاوربانک' => 'powerbank', 'اسپیکر' => 'speaker', 'آیپد' => 'tablet', 'تبلت' => 'tablet', 'مک‌بوک' => 'laptop', 'مکبوک' => 'laptop', 'لنز' => 'camera', 'آیفون' => 'phone-new', 'گوشی' => 'phone-new' ] as $needle => $key ) {
		if ( '' !== $name && str_contains( $name, $needle ) ) {
			return $key;
		}
	}
	foreach ( [ 'watch' => 'watch', 'head' => 'headphones', 'power' => 'powerbank', 'speak' => 'speaker', 'case' => 'case', 'cover' => 'case', 'ipad' => 'tablet', 'mac' => 'laptop', 'cable' => 'cable', 'lens' => 'camera', 'airpod' => 'earbuds', 'charg' => 'charger' ] as $needle => $key ) {
		if ( str_contains( $slug, $needle ) ) {
			return $key;
		}
	}
	return 'tag';
}

function stlh_cat_icon( string $key ): string {
	$icons = stlh_cat_icons();
	return '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' . ( $icons[ $key ] ?? $icons['tag'] ) . '</svg>';
}

/** درصد تخفیف (محصول ساده یا بیشترین تخفیف متغیر) */
function stlh_sale_percent( WC_Product $p ): int {
	if ( ! $p->is_on_sale() ) {
		return 0;
	}
	$max = 0;
	$ids = $p->is_type( 'variable' ) ? $p->get_visible_children() : [ $p->get_id() ];
	foreach ( $ids as $id ) {
		$v   = $id === $p->get_id() ? $p : wc_get_product( $id );
		$reg = $v ? (float) $v->get_regular_price() : 0;
		$sal = $v ? (float) $v->get_sale_price() : 0;
		if ( $reg > 0 && $sal > 0 && $sal < $reg ) {
			$max = max( $max, (int) round( ( $reg - $sal ) / $reg * 100 ) );
		}
	}
	return $max;
}

/** آیکن‌های خطی (stroke) — نوار اعتماد، تماس و نشان‌ها */
function stlh_line_icon( string $key ): string {
	$icons = [
		'shield' => '<path d="M12 3l7 3v5c0 4.5-3 8.3-7 9.5C8 19.3 5 15.5 5 11V6l7-3z"/><path d="M9 12l2 2 4-4"/>',
		'clock'  => '<circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/>',
		'card'   => '<rect x="3" y="5" width="18" height="14" rx="2.5"/><path d="M3 10h18M7 15h4"/>',
		'store'  => '<path d="M4 10v10h16V10"/><path d="M3.5 10L5.5 4h13l2 6"/><path d="M3.5 10a2.8 2.8 0 0 0 5.7 0 2.8 2.8 0 0 0 5.6 0 2.8 2.8 0 0 0 5.7 0"/><path d="M10 20v-5h4v5"/>',
		'truck'  => '<path d="M3 6h11v10H3zM14 9h4l3 3v4h-7z"/><circle cx="7" cy="17.5" r="1.8"/><circle cx="17.5" cy="17.5" r="1.8"/>',
		'check'  => '<circle cx="12" cy="12" r="9"/><path d="M8 12l3 3 5-6"/>',
		'phone'  => '<path d="M5 4h4l2 5-2.5 1.5a11 11 0 0 0 5 5L15 13l5 2v4a2 2 0 0 1-2 2A16 16 0 0 1 3 6a2 2 0 0 1 2-2z"/>',
		'pin'    => '<path d="M12 21s-7-6.2-7-11a7 7 0 0 1 14 0c0 4.8-7 11-7 11z"/><circle cx="12" cy="10" r="2.5"/>',
		'info'   => '<circle cx="12" cy="12" r="9"/><path d="M12 11v5M12 8h.01"/>',
	];
	$body = $icons[ $key ] ?? $icons['check'];
	return '<svg class="st-line-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' . $body . '</svg>';
}

/** لوگوی برند (fill) */
function stlh_brand_icon( string $brand ): string {
	if ( 'instagram' === $brand ) {
		return '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><rect x="3" y="3" width="18" height="18" rx="5"/><circle cx="12" cy="12" r="4.2"/><circle cx="17.4" cy="6.6" r="1.2" fill="currentColor" stroke="none"/></svg>';
	}
	return '<svg viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M21.9 4.6l-3.2 15c-.24 1.06-.87 1.32-1.76.82l-4.87-3.59-2.35 2.26c-.26.26-.48.48-.98.48l.35-4.96 9.03-8.16c.39-.35-.09-.54-.61-.2L6.35 13.3 1.54 11.8c-1.04-.33-1.06-1.04.22-1.54L20.57 3c.87-.32 1.63.2 1.33 1.6z"/></svg>';
}

/** نشان‌های کارت از ویژگی (attribute) یا متای محصول */
function stlh_badges( WC_Product $p, int $max = 2 ): array {
	$out = [];
	foreach ( stlh_lines( (string) stlh_opt( 'card_badges' ) ) as $line ) {
		[ $key, $label, $suffix ] = array_pad( array_map( 'trim', explode( '|', $line ) ), 3, '' );
		if ( '' === $key ) {
			continue;
		}
		$val = trim( wp_strip_all_tags( (string) $p->get_attribute( $key ) ) );
		if ( '' === $val ) {
			$meta = $p->get_meta( $key );
			$val  = is_scalar( $meta ) ? trim( wp_strip_all_tags( (string) $meta ) ) : '';
		}
		if ( '' === $val ) {
			continue;
		}
		$out[] = trim( $label . ' ' . stlh_fa( $val ) . $suffix );
		if ( count( $out ) >= $max ) {
			break;
		}
	}
	return $out;
}

/* ---------- فونت وزیرمتن (سلف‌هاست در uploads) ---------- */
function stlh_font_path(): array {
	$u = wp_upload_dir( null, false );
	return [
		trailingslashit( $u['basedir'] ) . 'stland-home/fonts/Vazirmatn-wght.woff2',
		trailingslashit( $u['baseurl'] ) . 'stland-home/fonts/Vazirmatn-wght.woff2',
	];
}

function stlh_font_ready(): bool {
	return is_readable( stlh_font_path()[0] );
}

function stlh_font_url(): string {
	[ $path, $url ] = stlh_font_path();
	return add_query_arg( 'v', (string) filemtime( $path ), $url );
}

function stlh_font_active(): bool {
	return '1' === stlh_opt( 'font_enable' ) && stlh_font_ready();
}

function stlh_download_font(): bool {
	[ $path ] = stlh_font_path();
	if ( ! wp_mkdir_p( dirname( $path ) ) ) {
		return false;
	}
	$urls = [
		'https://cdn.jsdelivr.net/gh/rastikerdar/vazirmatn@v33.003/fonts/webfonts/Vazirmatn%5Bwght%5D.woff2',
		'https://raw.githubusercontent.com/rastikerdar/vazirmatn/v33.003/fonts/webfonts/Vazirmatn%5Bwght%5D.woff2',
		'https://raw.githubusercontent.com/rastikerdar/vazirmatn/master/fonts/webfonts/Vazirmatn%5Bwght%5D.woff2',
	];
	foreach ( $urls as $url ) {
		$res = wp_remote_get( $url, [ 'timeout' => 30 ] );
		if ( is_wp_error( $res ) || 200 !== (int) wp_remote_retrieve_response_code( $res ) ) {
			continue;
		}
		$body = (string) wp_remote_retrieve_body( $res );
		if ( strlen( $body ) < 10000 || 'wOF2' !== substr( $body, 0, 4 ) ) {
			continue;
		}
		return false !== file_put_contents( $path, $body );
	}
	return false;
}

/** متغیرهای CSS: رنگ تأکیدی و فونت */
function stlh_inline_css(): string {
	$accent = sanitize_hex_color( (string) stlh_opt( 'accent' ) ) ?: '#007bff';
	$css    = ':root{--st-accent:' . $accent . ';}';
	if ( stlh_font_active() ) {
		$css .= "@font-face{font-family:'Vazirmatn';src:url('" . esc_url_raw( stlh_font_url() ) . "') format('woff2');font-weight:100 900;font-style:normal;font-display:swap}";
		$css .= ":root{--st-font:'Vazirmatn',Tahoma,sans-serif;}";
		if ( '1' === stlh_opt( 'font_sitewide' ) ) {
			$css .= 'body,button,input,select,textarea{font-family:var(--st-font);}';
		}
	}
	return $css;
}
