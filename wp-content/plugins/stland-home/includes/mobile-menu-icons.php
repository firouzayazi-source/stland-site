<?php
/**
 * منوی کشوییِ گوشی (`#off-canvas_menu`، منوی «modern»ِ قالبِ باکالا): ترتیب، آیکن‌ها و رنگ.
 *
 * صاحب فروشگاه (۲۴ مهر ۱۴۰۵): «آیکونِ لیست آک و کارکرده مرتبط بگذار؛ کلاً آیکون‌های این بخش نامتقارن و بی‌ربط‌اند».
 * صاحب فروشگاه (۷ آبان ۱۴۰۵): «گزینه‌ای برای بلاگ یا اخبار نداریم… ترتیبِ منوها را اوکی کن… لیست آک و کارکرده آیکونِ
 * متناسب ندارد… زیرمنوها هم آیکون… آیفون نو و کارکرده و لوازم جانبی فقط اسم‌اند… لوازم جانبی را باز می‌کنم یک صفحه‌ی کاملاً
 * مشکی باز می‌شود». پس:
 *   • منو روشن شد، هم‌رنگِ سربرگِ سفیدِ سایت (قالب با `darkmode`ِ باکالا آن را `bk-mm--dark` می‌کند؛ فقط متغیرهای رنگِ
 *     همین منو عوض می‌شوند، `darkmode` دست نمی‌خورد چون جاهای دیگر را هم تیره می‌کند).
 *   • هر موردِ سطحِ اول یک کاشیِ رنگیِ تخت با آیکنِ خطیِ سفید (از روی نشانی‌اش)؛ «لیست» برچسبِ قیمت، «مجله» روزنامه.
 *   • هر زیرمنو آیکن و رنگِ همان دسته را از مجموعه‌ی آیکنِ دسته‌های صفحه‌ی اصلی می‌گیرد (`stlh_cat_icon_key`) — دسته‌ی تازه
 *     خودکار آیکن دارد؛ «مشاهده‌ی همه» بالای هر زیرمنو، دکمه‌ی پرِ رنگیِ همان دسته.
 *   • ترتیب و موردِ «مجله و اخبار» یک بار در خودِ منوی وردپرس نوشته می‌شود (`stlh_menu_tidy`) — مثلِ افزودنِ «لیست» در
 *     `theme-settings.php`. ترتیب و عنوانِ قبلی در `stlh_menu_tidy_backup` می‌ماند؛ بعد از آن صاحب فروشگاه از «نمایش ← فهرست‌ها»
 *     هر چه بخواهد عوض می‌کند و این‌جا دیگر رویش نمی‌نویسد.
 * فقط CSS و منوی وردپرس؛ قالب دست نمی‌خورد.
 */
defined( 'ABSPATH' ) || exit;

const STLH_MENU_TIDY_REV = 1;

/** منوی جایگاهِ «mobile» (یا «main») */
function stlh_menu_main_id(): int {
	$loc = (array) get_nav_menu_locations();
	return (int) ( $loc['mobile'] ?? $loc['main'] ?? 0 );
}

function stlh_menu_blog_url(): string {
	$pid = (int) get_option( 'page_for_posts' );
	return $pid ? (string) get_permalink( $pid ) : home_url( '/blog/' );
}

/** [انتخاب‌گرِ پیوند، کلیدِ آیکن، رنگ] — سطحِ اول */
function stlh_mm_icon_rules(): array {
	$home = untrailingslashit( home_url() );
	$blog = untrailingslashit( stlh_menu_blog_url() );
	return [
		[ 'a.bk-mm-link[href="' . $home . '/"]', 'home', '#1f6fd6' ],
		[ 'button.bk-mm-link[data-bk-mm-open="m44319"]', 'phone', '#0f766e' ],
		[ 'button.bk-mm-link[data-bk-mm-open="m44320"]', 'earbuds', '#7c3aed' ],
		[ 'a.bk-mm-link[href*="/list/"]', 'pricetag', '#e08a00' ],
		[ 'a.bk-mm-link[href^="' . $blog . '"]', 'news', '#d63384' ],
		[ 'a.bk-mm-link[href*="/about/"]', 'info', '#475569' ],
		[ 'a.bk-mm-link[href*="/contact/"]', 'call', '#0e9f6e' ],
	];
}

function stlh_mm_icon_paths( string $key ): string {
	$p = [
		'home'     => '<path d="M3.5 10.5 12 3.5l8.5 7"/><path d="M5.5 9v11h13V9"/><path d="M10 20v-5.5h4V20"/>',
		'phone'    => '<rect x="6.5" y="2.5" width="11" height="19" rx="3"/><path d="M10.5 5h3M10 18.5h4"/>',
		'earbuds'  => '<path d="M8 4.5a3 3 0 0 0-3 3v1a3 3 0 0 0 3 3V19a1.5 1.5 0 0 0 3 0V7.5a3 3 0 0 0-3-3z"/><path d="M16 4.5a3 3 0 0 1 3 3v1a3 3 0 0 1-3 3V19a1.5 1.5 0 0 1-3 0V7.5a3 3 0 0 1 3-3z"/>',
		// برچسبِ قیمت با دو خطِ فهرست: «لیستِ قیمتِ روز»
		'pricetag' => '<path d="M3.5 12.2V4.5a1 1 0 0 1 1-1h7.7l8.3 8.3-8.7 8.7z"/><circle cx="8" cy="8" r="1.4"/><path d="M11.5 12.5l2.5 2.5M9.5 14.5l2.5 2.5"/>',
		// روزنامه: «مجله و اخبار»
		'news'     => '<path d="M4 5.5h12.5v13a1.5 1.5 0 0 0 1.5 1.5H6a2 2 0 0 1-2-2z"/><path d="M16.5 9H20v9.5a1.5 1.5 0 0 1-3 0"/><path d="M7 9h6.5M7 12.5h6.5M7 16h4"/>',
		'info'     => '<circle cx="12" cy="12" r="9"/><path d="M12 11v5.5M12 7.6v.01"/>',
		'call'     => '<path d="M5 4h4l2 5-2.5 1.5a11 11 0 0 0 5 5L15 13l5 2v4a2 2 0 0 1-2 2A16 16 0 0 1 3 6a2 2 0 0 1 2-2z"/>',
		'grid'     => '<rect x="4" y="4" width="7" height="7" rx="2"/><rect x="13" y="4" width="7" height="7" rx="2"/><rect x="4" y="13" width="7" height="7" rx="2"/><rect x="13" y="13" width="7" height="7" rx="2"/>',
	][ $key ] ?? ( function_exists( 'stlh_cat_icons' ) ? ( stlh_cat_icons()[ $key ] ?? '' ) : '' );
	return $p;
}

function stlh_mm_mask( string $key ): string {
	$svg = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="#000" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round">' . stlh_mm_icon_paths( $key ) . '</svg>';
	return 'url("data:image/svg+xml,' . rawurlencode( $svg ) . '")';
}

/**
 * زیرمنوها از خودِ منوی وردپرس: [نشانی، کلیدِ آیکن، رنگ، «مشاهده‌ی همه»؟]. «مشاهده‌ی همه» = پیوند به دسته‌ی پدرِ بقیه‌ی
 * فرزندانِ همان زیرمنو.
 */
function stlh_mm_sub_rules(): array {
	$menu = stlh_menu_main_id();
	if ( ! $menu || ! function_exists( 'stlh_cat_icon_key' ) ) {
		return [];
	}
	$items = (array) wp_get_nav_menu_items( $menu );
	$kids  = [];
	foreach ( $items as $it ) {
		if ( is_object( $it ) && (int) $it->menu_item_parent ) {
			$kids[ (int) $it->menu_item_parent ][] = $it;
		}
	}
	$out = [];
	foreach ( $kids as $list ) {
		$terms = [];
		foreach ( $list as $it ) {
			if ( 'taxonomy' === $it->type && 'product_cat' === $it->object ) {
				$t = get_term( (int) $it->object_id, 'product_cat' );
				if ( $t instanceof WP_Term ) {
					$terms[ (int) $it->ID ] = $t;
				}
			}
		}
		$parents = array_count_values( array_map( static fn( WP_Term $t ) => (int) $t->parent, $terms ) );
		arsort( $parents );
		$top = (int) array_key_first( $parents );
		foreach ( $terms as $t ) {
			$link = get_term_link( $t );
			if ( ! is_string( $link ) ) {
				continue;
			}
			$key   = stlh_cat_icon_key( rawurldecode( $t->slug ), $t->name );
			$all   = (int) $t->term_id === $top;
			$out[] = [ $link, $all ? 'grid' : $key, stlh_cat_palette( $key )[0], $all ];
		}
	}
	return $out;
}

function stlh_mm_icons_css(): string {
	$m = '#off-canvas_menu';
	// ۱) روشن، هم‌رنگِ سربرگ — فقط متغیرهای همین منو
	$css = $m . '.bk-mm{--mm-bg:#fff;--mm-ink:#1f2430;--mm-text:#3d4456;--mm-muted:#7d8597;--mm-line:#eef0f4;--mm-soft:#f4f5f8;'
		. '--mm-accent-soft:color-mix(in srgb,var(--mm-accent) 11%,#fff);--mm-accent2-soft:color-mix(in srgb,var(--mm-accent2) 12%,#fff);--mm-shadow:0 24px 60px -12px rgb(15 18 25 / .35)}'
		. $m . ' .bk-mm-socials a{color:var(--mm-accent2)}'
		// ۲) کاشیِ سطحِ اول و زیرمنو: یک اندازه، تخت با رنگِ زنده، آیکنِ سفید
		. $m . ' .bk-mm-link .bk-mm-ico,' . $m . ' .bk-mm-panel:not(#bk-mm-root) .bk-mm-link[data-stlh-ico]::before{background:var(--g1,#1f6fd6)!important;color:#fff!important;box-shadow:none!important}'
		. $m . ' .bk-mm-ico i::before{color:#fff}'
		. $m . ' .bk-mm-panel:not(#bk-mm-root) .bk-mm-link::before{content:"";flex:none;width:38px;height:38px;border-radius:12px;background:var(--g1,#94a3b8);'
		. '}'
		. $m . ' .bk-mm-panel:not(#bk-mm-root) .bk-mm-link::after{content:"";position:absolute;right:18.5px;top:50%;transform:translateY(-50%);width:21px;height:21px;background:#fff;-webkit-mask:var(--ic) center/contain no-repeat;mask:var(--ic) center/contain no-repeat}'
		. $m . ' .bk-mm-panel:not(#bk-mm-root) .bk-mm-link{position:relative;min-height:56px}'
		. $m . ' .bk-mm-panel:not(#bk-mm-root) .bk-mm-list>li+li::before{right:60px}';
	foreach ( stlh_mm_icon_rules() as [ $sel, $key, $c1 ] ) {
		$ico  = $m . ' ' . $sel . ' .bk-mm-ico';
		$css .= $ico . '{--g1:' . $c1 . ';font-size:0!important;line-height:0!important;position:relative}'
			. $ico . '>*{display:none!important}'
			. $ico . '::after{content:"";width:21px;height:21px;background:#fff;-webkit-mask:' . stlh_mm_mask( $key ) . ' center/contain no-repeat;mask:' . stlh_mm_mask( $key ) . ' center/contain no-repeat}';
	}
	// ۳) زیرمنوها: آیکن و رنگِ دسته؛ «مشاهده‌ی همه» دکمه‌ی پرِ رنگی
	foreach ( stlh_mm_sub_rules() as [ $url, $key, $c1, $all ] ) {
		$a    = $m . ' .bk-mm-panel:not(#bk-mm-root) a.bk-mm-link[href="' . esc_attr( $url ) . '"]';
		$css .= $a . '{--g1:' . $c1 . ';--ic:' . stlh_mm_mask( $key ) . '}';
		if ( $all ) {
			$css .= $a . '{margin:2px 0 10px;background:' . $c1 . ';color:#fff;font-weight:800;box-shadow:0 10px 22px -14px ' . $c1 . '}'
				. $a . '::before{background:rgb(255 255 255 / .2)!important}'
				. $a . ':hover,' . $a . ':active{background:' . $c1 . ';color:#fff;filter:brightness(1.05)}';
		}
	}
	return $css;
}

/**
 * منوی بالای سایت در رایانه (`.bk_menu`، «منوی شیشه‌ای»ِ باکالا) — صاحب فروشگاه با دو عکس (۷ آبان): «لیست» و «مجله»
 * آیکن ندارند و زیرمنو «صفحه‌ی بزرگِ کلاً مشکی» است. زیرمنوهای ما سطحِ سوم ندارند، پس به‌جای پرده‌ی تمام‌عرضِ تیره، یک
 * کارتِ روشنِ کوچک زیرِ همان مورد با همان کاشی‌های رنگیِ منوی گوشی؛ آیکنِ «لیست» و «مجله» هم‌اندازه‌ی آیکن‌های قالب.
 */
function stlh_dm_css(): string {
	$nav  = 'html body .main-menu-div .bk_menu>.bakala>ul>li';
	// کلاسِ تکراری برای اینکه از قاعده‌ی پنج‌کلاسیِ قالب (.bk_menu.bk_vertical_menu.level …) جلو بزند
	$drop = $nav . '>.bakala-tab-menu-items.bakala-tab-menu-items.bakala-tab-menu-items';
	$open = $nav . ':hover>.bakala-tab-menu-items.bakala-tab-menu-items.bakala-tab-menu-items,'
		. $nav . ':focus-within>.bakala-tab-menu-items.bakala-tab-menu-items.bakala-tab-menu-items';
	// بسته: حالتِ پنهانِ قالب دست نمی‌خورد، فقط پهنای ۱۳۲۰ پیکسلی کنار می‌رود
	$css  = $nav . ':has(>.bakala-tab-menu-items){position:relative!important}'
		. $drop . '{left:auto!important;right:0!important;width:290px!important;min-width:0!important;max-width:none!important;background:#fff!important;backdrop-filter:none!important}'
		. $open . '{height:auto!important;max-height:none!important;min-height:0!important;opacity:1!important;display:block!important;overflow:visible!important;padding:10px!important;'
		. 'border:1px solid #eef0f4!important;border-radius:18px!important;box-shadow:0 24px 50px -16px rgb(15 18 25 / .35)!important}'
		. $drop . '::before,' . $drop . '::after{display:none!important}'
		. $drop . '>ul,' . $drop . '>ul.level{position:static!important;width:100%!important;float:none!important;height:auto!important;min-height:0!important;max-height:none!important;overflow:visible!important;border:0!important;padding:0!important;margin:0!important;background:transparent!important;box-shadow:none!important}'
		. $drop . '>ul>li{width:100%!important;height:auto!important;float:none!important;margin:0!important;padding:0!important;background:transparent!important;border:0!important;border-radius:14px!important}'
		. $drop . '>ul>li:hover{background:#f4f5f8!important}'
		. $drop . '>ul>li>:not(.title2){display:none!important}'
		. $drop . ' .title2{width:100%!important;height:auto!important;padding:0!important;margin:0!important;background:transparent!important;border:0!important}'
		. $drop . ' .title2 a{position:relative;display:flex!important;align-items:center;gap:12px;width:100%!important;min-height:52px;padding:7px 8px!important;color:#1f2430!important;background:transparent!important;font-size:14px!important;font-weight:700!important;line-height:1.5!important;border-radius:14px;white-space:nowrap}'
		. $drop . ' .title2 a::before{content:""!important;flex:none;width:36px;height:36px;border-radius:11px;background:var(--g1,#94a3b8)!important;margin:0!important;font-size:0!important}'
		. $drop . ' .title2 a::after{content:"";position:absolute;right:15.5px;top:50%;transform:translateY(-50%);width:20px;height:20px;background:#fff;-webkit-mask:var(--ic) center/contain no-repeat;mask:var(--ic) center/contain no-repeat}';
	foreach ( stlh_mm_sub_rules() as [ $url, $key, $c1, $all ] ) {
		$a    = $drop . ' .title2 a[href="' . esc_attr( $url ) . '"]';
		$css .= $a . '{--g1:' . $c1 . ';--ic:' . stlh_mm_mask( $key ) . '}';
		if ( $all ) {
			$css .= $a . '{background:' . $c1 . '!important;color:#fff!important;margin-bottom:6px}' . $a . '::before{background:rgb(255 255 255 / .2)!important}';
		}
	}
	// آیکنِ «لیست» و «مجله» در نوارِ منو، کنارِ آیکن‌های قالب
	$blog = untrailingslashit( stlh_menu_blog_url() );
	foreach ( [ 'a[href*="/list/"]' => 'pricetag', 'a[href^="' . $blog . '"]' => 'news' ] as $sel => $key ) {
		$css .= $nav . '>.title>' . $sel . '::before{content:"";display:inline-block;width:17px;height:17px;margin-inline-end:6px;vertical-align:-3px;background:currentColor;'
			. '-webkit-mask:' . stlh_mm_mask( $key ) . ' center/contain no-repeat;mask:' . stlh_mm_mask( $key ) . ' center/contain no-repeat}';
	}
	return $css;
}

add_action( 'wp_head', static function (): void {
	if ( is_admin() ) {
		return;
	}
	echo '<style id="stlh-mm-icons">' . stlh_mm_icons_css() . stlh_dm_css() . "</style>\n"; // phpcs:ignore WordPress.Security.EscapeOutput -- CSSِ ساخته‌ی خودمان
}, 30 );

/*
 * ─── ترتیب و «مجله و اخبار» (یک بار) ───
 * سطحِ اول: خانه، آیفون، لوازم جانبی، لیست آک و کارکرده، مجله و اخبار، درباره ما، تماس با ما (هر موردِ دیگر بعد از این‌ها).
 * زیرمنو: «مشاهده‌ی همه» اول — پیوندی که به دسته‌ی پدرِ بقیه‌ی فرزندان می‌رود؛ اگر نیست ساخته می‌شود (آیفون)، و اگر هست
 * (لوازم جانبی) عنوانش «مشاهده‌ی همه» می‌شود.
 */
add_action( 'init', static function (): void {
	$prev = (array) get_option( 'stlh_menu_tidy', [] );
	if ( (int) ( $prev['rev'] ?? 0 ) >= STLH_MENU_TIDY_REV || ! taxonomy_exists( 'product_cat' ) ) {
		return;
	}
	$menu = stlh_menu_main_id();
	if ( ! $menu || ! add_option( 'stlh_menu_tidy_lock', time(), '', false ) ) {
		return;
	}
	$items  = array_values( array_filter( (array) wp_get_nav_menu_items( $menu ), 'is_object' ) );
	$backup = [];
	foreach ( $items as $it ) {
		$backup[ (int) $it->ID ] = [ (int) $it->menu_order, (string) get_post_field( 'post_title', (int) $it->ID ) ];
	}
	$log  = [ 'rev' => STLH_MENU_TIDY_REV, 'at' => gmdate( 'c' ), 'menu' => $menu ];
	$top  = array_filter( $items, static fn( $it ) => ! (int) $it->menu_item_parent );
	$kids = [];
	foreach ( $items as $it ) {
		if ( (int) $it->menu_item_parent ) {
			$kids[ (int) $it->menu_item_parent ][] = $it;
		}
	}
	$home = untrailingslashit( home_url() );
	$blog = stlh_menu_blog_url();
	$find = static function ( callable $test ) use ( $top ) {
		foreach ( $top as $it ) {
			if ( $test( $it ) ) {
				return $it;
			}
		}
		return null;
	};
	$list_pid = defined( 'STLH_APP_LIST_PAGE' ) ? (int) get_option( STLH_APP_LIST_PAGE ) : 0;
	$blog_it  = $find( static fn( $it ) => untrailingslashit( $it->url ) === untrailingslashit( $blog ) );
	if ( ! $blog_it ) {
		$pid = (int) get_option( 'page_for_posts' );
		$r   = wp_update_nav_menu_item( $menu, 0, $pid ? [
			'menu-item-title' => 'مجله و اخبار', 'menu-item-object' => 'page', 'menu-item-object-id' => $pid,
			'menu-item-type' => 'post_type', 'menu-item-status' => 'publish',
		] : [
			'menu-item-title' => 'مجله و اخبار', 'menu-item-url' => $blog, 'menu-item-type' => 'custom', 'menu-item-status' => 'publish',
		] );
		$log['blog'] = is_wp_error( $r ) ? $r->get_error_message() : 'added ' . $r;
		$blog_it     = is_wp_error( $r ) ? null : (object) [ 'ID' => (int) $r ];
	} else {
		$log['blog'] = 'exists ' . $blog_it->ID;
	}
	$want = [
		$find( static fn( $it ) => untrailingslashit( $it->url ) === $home ),
		$find( static fn( $it ) => isset( $kids[ (int) $it->ID ] ) && str_contains( $it->title, 'آیفون' ) ),
		$find( static fn( $it ) => isset( $kids[ (int) $it->ID ] ) && str_contains( $it->title, 'لوازم' ) ),
		$find( static fn( $it ) => $list_pid && (int) $it->object_id === $list_pid ),
		$blog_it,
		$find( static fn( $it ) => str_contains( (string) $it->url, '/about/' ) ),
		$find( static fn( $it ) => str_contains( (string) $it->url, '/contact/' ) ),
	];
	$order = [];
	foreach ( $want as $it ) {
		if ( $it && ! in_array( (int) $it->ID, $order, true ) ) {
			$order[] = (int) $it->ID;
		}
	}
	foreach ( $top as $it ) {
		if ( ! in_array( (int) $it->ID, $order, true ) ) {
			$order[] = (int) $it->ID;
		}
	}
	// زیرمنوها: «مشاهده‌ی همه» اول
	$n    = 0;
	$seq  = [];
	foreach ( $order as $id ) {
		$seq[ $id ] = ++$n;
		$list       = $kids[ $id ] ?? [];
		if ( ! $list ) {
			continue;
		}
		$terms = [];
		foreach ( $list as $it ) {
			if ( 'taxonomy' === $it->type && 'product_cat' === $it->object && ( $t = get_term( (int) $it->object_id, 'product_cat' ) ) instanceof WP_Term ) {
				$terms[ (int) $it->ID ] = $t;
			}
		}
		$parents = array_count_values( array_map( static fn( WP_Term $t ) => (int) $t->parent, $terms ) );
		arsort( $parents );
		$pterm = (int) array_key_first( $parents );
		$all   = null;
		foreach ( $terms as $iid => $t ) {
			if ( (int) $t->term_id === $pterm ) {
				$all = $iid;
			}
		}
		if ( ! $all && $pterm ) {
			$r   = wp_update_nav_menu_item( $menu, 0, [
				'menu-item-title' => 'مشاهده‌ی همه', 'menu-item-object' => 'product_cat', 'menu-item-object-id' => $pterm,
				'menu-item-type' => 'taxonomy', 'menu-item-parent-id' => $id, 'menu-item-status' => 'publish',
			] );
			$all = is_wp_error( $r ) ? null : (int) $r;
			$log['all'][ $id ] = is_wp_error( $r ) ? $r->get_error_message() : 'added ' . $r;
		} elseif ( $all ) {
			wp_update_post( [ 'ID' => $all, 'post_title' => 'مشاهده‌ی همه' ] );
			$log['all'][ $id ] = 'renamed ' . $all;
		}
		if ( $all ) {
			$seq[ $all ] = ++$n;
		}
		foreach ( $list as $it ) {
			if ( (int) $it->ID !== $all ) {
				$seq[ (int) $it->ID ] = ++$n;
			}
		}
	}
	foreach ( $seq as $id => $pos ) {
		wp_update_post( [ 'ID' => $id, 'menu_order' => $pos ] );
	}
	$log['order'] = $seq;
	update_option( 'stlh_menu_tidy_backup', $backup, false );
	update_option( 'stlh_menu_tidy', $log, false );
	delete_option( 'stlh_menu_tidy_lock' );
	do_action( 'litespeed_purge_all' );
}, 45 );
