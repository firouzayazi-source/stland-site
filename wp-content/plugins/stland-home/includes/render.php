<?php
defined( 'ABSPATH' ) || exit;

function stlh_woo(): bool {
	return function_exists( 'wc_get_products' );
}

/* ---------- بنر ---------- */
function stlh_banner(): string {
	$h1   = trim( (string) stlh_opt( 'h1' ) );
	$h1   = $h1 ? '<h1 class="st-sr-only">' . esc_html( $h1 ) . '</h1>' : '';
	$d_id = (int) stlh_opt( 'banner_desktop' );
	$m_id = (int) stlh_opt( 'banner_mobile' );
	$main = $d_id ?: $m_id;
	$src  = $main ? wp_get_attachment_image_src( $main, 'full' ) : false;

	if ( ! $src ) {
		return $h1;
	}

	// srcset: مرورگر گوشی نسخه‌ی کوچک‌ترِ همان عکس را می‌گیرد، نه ۱۹۲۰ پیکسلی را.
	// بنر اولین چیزی است که دیده می‌شود، پس وزنش مستقیم زمانِ باز شدن صفحه است.
	$srcset = static function ( int $id, string $fallback ): string {
		return wp_get_attachment_image_srcset( $id, 'full' ) ?: $fallback;
	};

	$source = '';
	if ( $d_id && $m_id && ( $ms = wp_get_attachment_image_src( $m_id, 'full' ) ) ) {
		$source = sprintf(
			'<source media="(max-width: 767px)" srcset="%s" sizes="100vw" width="%d" height="%d">',
			esc_attr( $srcset( $m_id, $ms[0] ) ), $ms[1], $ms[2]
		);
	}

	$pic = sprintf(
		'<picture>%s<img src="%s" srcset="%s" sizes="100vw" width="%d" height="%d" alt="%s" fetchpriority="high" decoding="async"></picture>',
		$source, esc_url( $src[0] ), esc_attr( $srcset( $main, $src[0] ) ), $src[1], $src[2], esc_attr( (string) stlh_opt( 'banner_alt' ) )
	);

	if ( $link = stlh_opt( 'banner_link' ) ) {
		$pic = '<a href="' . esc_url( $link ) . '">' . $pic . '</a>';
	}

	return '<div class="stland-full-width-banner">' . $h1 . $pic . '</div>';
}

/* ---------- نوار اعتماد ---------- */
function stlh_trust(): string {
	$items = '';
	foreach ( stlh_lines( (string) stlh_opt( 'trust' ) ) as $line ) {
		[ $icon, $title, $sub ] = array_pad( array_map( 'trim', explode( '|', $line ) ), 3, '' );
		if ( '' === $title ) {
			continue;
		}
		$items .= '<li class="st-trust-item"><span class="st-trust-icon">' . stlh_line_icon( $icon ) . '</span><span class="st-trust-text"><strong>' . esc_html( $title ) . '</strong>'
			. ( $sub ? '<span>' . esc_html( $sub ) . '</span>' : '' ) . '</span></li>';
	}
	return $items ? '<section class="st-trust-wrapper" aria-label="مزایای خرید از استوک لند"><ul class="st-trust-list">' . $items . '</ul></section>' : '';
}

/* ---------- هدر مشترک بخش‌ها ---------- */
function stlh_section_head( string $title, string $sub = '', string $url = '', string $btn = '', string $tag = 'h2' ): string {
	if ( '' === $title && '' === $url ) {
		return '';
	}
	$btn = $btn ?: stlh_label( 'more' );
	$html  = '<div class="st-sec-head"><div class="st-sec-title">';
	$html .= $title ? '<' . $tag . '>' . esc_html( $title ) . '</' . $tag . '>' : '';
	$html .= $sub ? '<p>' . esc_html( $sub ) . '</p>' : '';
	$html .= '</div>';
	$html .= $url ? '<a class="st-sec-more" href="' . esc_url( $url ) . '">' . esc_html( $btn ) . stlh_arrow() . '</a>' : '';
	return $html . '</div>';
}

/** دسته را با نامک، نام فارسی یا شناسه (ID) پیدا می‌کند */
function stlh_resolve_cat( string $ref ): WP_Term|false {
	$ref = trim( $ref );
	if ( '' === $ref ) {
		return false;
	}
	if ( ctype_digit( $ref ) ) {
		$t = get_term( (int) $ref, 'product_cat' );
		return ( $t instanceof WP_Term ) ? $t : false;
	}
	foreach ( [ sanitize_title( $ref ), sanitize_title( rawurldecode( $ref ) ) ] as $slug ) {
		if ( $t = get_term_by( 'slug', $slug, 'product_cat' ) ) {
			return $t;
		}
	}
	return get_term_by( 'name', $ref, 'product_cat' ) ?: false;
}

function stlh_term_url( string $slug ): string {
	$term = stlh_resolve_cat( $slug );
	if ( ! $term ) {
		return '';
	}
	$url = get_term_link( $term );
	return is_wp_error( $url ) ? '' : $url;
}

/* ---------- دسته‌بندی‌ها ---------- */

/**
 * «دسته‌بندی نشده»ِ ووکامرس که نباید کارت یا ردیف شود؟
 *
 * دسته‌ی پیش‌فرضِ ووکامرس را نمی‌شود پاک کرد؛ صاحب فروشگاه برای پاک کردنِ
 * «نسل ۶» «تلفن همراه» را پیش‌فرض کرد — و کلِ شاخه‌ی گوشی‌ها (آیفون نو،
 * کارکرده…) از صفحه‌ی اصلی رفت، چون اینجا پیش‌فرض یعنی «بی‌دسته». پس فقط
 * پیش‌فرضی کنار می‌رود که واقعاً سطلِ بی‌دسته است: زیردسته ندارد یا نامش
 * همان «دسته‌بندی نشده» است.
 */
function stlh_is_bucket( WP_Term $t, array $children ): bool {
	if ( in_array( rawurldecode( $t->slug ), [ 'uncategorized', 'دسته-بندی-نشده', 'بدون-دسته-بندی' ], true )
		|| in_array( $t->name, [ 'Uncategorized', 'دسته‌بندی نشده', 'بدون دسته‌بندی' ], true ) ) {
		return true;
	}
	return $t->term_id === (int) get_option( 'default_product_cat' ) && empty( $children[ $t->term_id ] );
}

/**
 * دسته‌های اصلیِ فروشگاه، به ترتیبِ خودِ ووکامرس.
 *
 * «یکی کردن با سایت» در حسابداری درختِ دسته‌ها را دوطرفه یکی می‌کند؛ پس
 * هر دسته‌ی اصلی که اینجا می‌آید همان دسته‌ی حسابداری است. دسته‌ای که
 * هیچ محصولی — نه خودش نه زیردسته‌هایش — ندارد، و «دسته‌بندی نشده»، نمی‌آیند.
 *
 * @return WP_Term[]
 */
function stlh_top_categories(): array {
	$all = get_terms( [ 'taxonomy' => 'product_cat', 'hide_empty' => false, 'menu_order' => 'ASC' ] );
	if ( is_wp_error( $all ) || ! $all ) {
		return [];
	}
	$children = [];
	foreach ( $all as $t ) {
		$children[ $t->parent ][] = $t;
	}
	$total = static function ( WP_Term $t ) use ( &$total, $children ): int {
		$n = (int) $t->count;
		foreach ( $children[ $t->term_id ] ?? [] as $c ) {
			$n += $total( $c );
		}
		return $n;
	};
	return array_values( array_filter( $children[0] ?? [], static fn( WP_Term $t ) => ! stlh_is_bucket( $t, $children ) && $total( $t ) > 0 ) );
}

/**
 * دسته‌های «برگ» برای کارت‌ها و ردیف‌ها، به ترتیبِ درخت.
 *
 * صاحب فروشگاه نمی‌خواهد سه کارتِ «تلفن همراه / لوازم جانبی / پرفروش‌ها»
 * ببیند؛ می‌خواهد «آیفون نو، آیفون کارکرده، ایرپاد، شارژر…». پس از هر
 * دسته‌ی اصلی پایین می‌رویم تا جایی که فرزندِ دارای محصول تمام شود:
 * تلفن همراه › آیفون › [آیفون نو، آیفون کارکرده]، لوازم جانبی › [ایرپاد، …].
 *
 * @return WP_Term[]
 */
function stlh_leaf_categories( bool $respect_hidden = true ): array {
	$all = get_terms( [ 'taxonomy' => 'product_cat', 'hide_empty' => false, 'menu_order' => 'ASC' ] );
	if ( is_wp_error( $all ) || ! $all ) {
		return [];
	}
	$children = [];
	foreach ( $all as $t ) {
		$children[ $t->parent ][] = $t;
	}
	$memo  = [];
	$total = static function ( WP_Term $t ) use ( &$total, &$memo, $children ): int {
		if ( ! isset( $memo[ $t->term_id ] ) ) {
			$n = (int) $t->count;
			foreach ( $children[ $t->term_id ] ?? [] as $c ) {
				$n += $total( $c );
			}
			$memo[ $t->term_id ] = $n;
		}
		return $memo[ $t->term_id ];
	};
	$hidden = $respect_hidden ? array_map( 'intval', (array) stlh_opt( 'hide_cats' ) ) : [];
	$out    = [];
	$walk   = static function ( array $terms ) use ( &$walk, &$out, $children, $total, $hidden ): void {
		foreach ( $terms as $t ) {
			if ( stlh_is_bucket( $t, $children ) || $total( $t ) < 1 || in_array( $t->term_id, $hidden, true ) ) {
				continue;
			}
			$full = array_filter( $children[ $t->term_id ] ?? [], static fn( WP_Term $c ) => $total( $c ) > 0 );
			if ( $full ) {
				$walk( $full );
			} else {
				$out[] = $t;
			}
		}
	};
	$walk( $children[0] ?? [] );
	return $out;
}

/** [دسته, کلیدِ آیکن] برای بخشِ دسته‌بندی‌ها */
function stlh_category_items(): array {
	if ( 'manual' !== stlh_opt( 'cat_source' ) ) {
		return array_map( static fn( WP_Term $t ) => [ $t, '' ], stlh_leaf_categories() );
	}
	$items = [];
	foreach ( stlh_lines( (string) stlh_opt( 'categories' ) ) as $line ) {
		[ $slug, $icon_key ] = array_pad( array_map( 'trim', explode( '|', $line ) ), 2, '' );
		if ( $term = stlh_resolve_cat( $slug ) ) {
			$items[] = [ $term, $icon_key ];
		}
	}
	return $items;
}

function stlh_categories(): string {
	$photo_mode = 'photo' === stlh_opt( 'cat_style' );
	$cards      = '';
	foreach ( stlh_category_items() as [ $term, $icon_key ] ) {
		$link = get_term_link( $term );
		if ( is_wp_error( $link ) ) {
			continue;
		}
		$media = '';
		if ( $photo_mode && ( $tid = (int) get_term_meta( $term->term_id, 'thumbnail_id', true ) ) ) {
			$media = wp_get_attachment_image( $tid, 'thumbnail', false, [ 'alt' => '', 'loading' => 'lazy', 'decoding' => 'async' ] );
		}
		$icon   = $media ?: stlh_cat_icon( $icon_key ?: stlh_cat_icon_key( rawurldecode( $term->slug ), $term->name ) );
		$cards .= sprintf(
			'<a class="st-category-card" href="%s"><span class="st-icon-box%s">%s</span><span class="st-cat-name">%s</span></a>',
			esc_url( $link ), $media ? ' has-img' : '', $icon, esc_html( stlh_cat_name( $term ) )
		);
	}
	if ( ! $cards ) {
		return '';
	}
	return '<section class="st-category-wrapper"><div class="st-category-container">'
		. '<div class="st-section-header"><h2>' . esc_html( (string) stlh_opt( 'cat_title' ) ) . '</h2><p>' . esc_html( (string) stlh_opt( 'cat_subtitle' ) ) . '</p></div>'
		. '<div class="st-category-grid">' . $cards . '</div></div></section>';
}

/* ---------- کارت محصول ---------- */
function stlh_price( WC_Product $p ): string {
	if ( ! $p->is_in_stock() ) {
		return '<span class="st-out">' . esc_html( stlh_label( 'out' ) ) . '</span>';
	}
	$html = $p->get_price_html();
	return $html ? wp_kses_post( $html ) : '<span class="st-call">' . esc_html( stlh_label( 'call' ) ) . '</span>';
}

function stlh_card( WC_Product $p ): string {
	$badges = '';
	foreach ( stlh_badges( $p ) as $b ) {
		$badges .= '<span class="st-chip">' . esc_html( $b ) . '</span>';
	}
	$sale = $p->is_in_stock() ? stlh_sale_label( $p ) : '';
	$sale = $sale ? '<span class="st-sale">' . esc_html( $sale ) . '</span>' : '';
	return sprintf(
		'<a class="st-pr-card" href="%s"><span class="st-pr-img">%s%s%s</span><span class="st-pr-name">%s</span><span class="st-pr-foot"><span class="st-pr-price">%s</span></span></a>',
		esc_url( $p->get_permalink() ),
		$badges ? '<span class="st-pr-badges">' . $badges . '</span>' : '',
		stlh_card_image( $p ),
		$sale,
		esc_html( $p->get_name() ),
		stlh_price( $p )
	);
}

/**
 * عکس کارت. محصولِ بی‌عکس به‌جای «صورتکِ غمگینِ» پیش‌فرضِ قالب یک
 * آیکنِ آرامِ همان دسته می‌گیرد — گوشیِ استوکِ تازه‌رسیده اغلب هنوز عکس ندارد.
 */
function stlh_card_image( WC_Product $p ): string {
	if ( $p->get_image_id() ) {
		return $p->get_image( 'woocommerce_thumbnail', [ 'loading' => 'lazy', 'decoding' => 'async' ] );
	}
	$terms = get_the_terms( $p->get_id(), 'product_cat' );
	$key   = ( $terms && ! is_wp_error( $terms ) ) ? stlh_cat_icon_key( rawurldecode( $terms[0]->slug ), $terms[0]->name ) : 'phone-new';
	return '<span class="st-pr-noimg">' . stlh_cat_icon( $key ) . '</span>';
}

/**
 * نشانِ تخفیف. «۱٪» روی گوشیِ دویست میلیونی بی‌معناست ولی «۳ میلیون»
 * معنا دارد؛ پس مبلغ، کوتاه. برای محصول متغیر همان درصد می‌ماند.
 */
function stlh_sale_label( WC_Product $p ): string {
	if ( ! $p->is_on_sale() ) {
		return '';
	}
	if ( $p->is_type( 'variable' ) ) {
		$off = stlh_sale_percent( $p );
		return $off ? stlh_fa( $off ) . '٪' : '';
	}
	$diff = (float) $p->get_regular_price() - (float) $p->get_sale_price();
	if ( $diff <= 0 ) {
		return '';
	}
	if ( 'IRR' === get_woocommerce_currency() ) {
		$diff /= 10; // همه‌جا به تومان خوانده شود
	}
	if ( $diff >= 1000000 ) {
		$n = round( $diff / 1000000, 1 );
		return stlh_fa( rtrim( rtrim( number_format( $n, 1, '.', '' ), '0' ), '.' ) ) . ' میلیون تخفیف';
	}
	if ( $diff >= 1000 ) {
		return stlh_fa( (int) round( $diff / 1000 ) ) . ' هزار تخفیف';
	}
	return stlh_fa( (int) $diff ) . ' تومان تخفیف';
}

/** $slugs: یک یا چند نامک دسته (OR) */
function stlh_query_products( array $slugs, int $limit ): array {
	$args = [
		'status'     => 'publish',
		'visibility' => 'catalog',
		'limit'      => max( 1, min( 100, $limit ) ),
		'orderby'    => 'date',
		'order'      => 'DESC',
	];
	$resolved = [];
	foreach ( $slugs as $ref ) {
		if ( $t = stlh_resolve_cat( (string) $ref ) ) {
			$resolved[] = $t->slug;
		}
	}
	if ( array_filter( array_map( 'trim', $slugs ) ) && ! $resolved ) {
		return []; // دسته واردشده وجود ندارد؛ کل فروشگاه نمایش داده نشود
	}
	if ( $resolved ) {
		$args['category'] = $resolved;
	}
	// از تنظیم خود ووکامرس پیروی می‌کند (تنظیمات ← محصولات ← موجودی)
	if ( 'yes' === get_option( 'woocommerce_hide_out_of_stock_items' ) ) {
		$args['stock_status'] = 'instock';
	}
	return wc_get_products( $args );
}

/* ---------- فیلتر مدل آیفون ---------- */
/** شماره نسل از نام محصول: iPhone 12 Pro → 12 ، آیفون ۱۳ → 13 ، X/XS/XR → 10 ، SE → 9 */
function stlh_model_number( string $name ): int {
	$name = strtr( $name, [ '۰' => '0', '۱' => '1', '۲' => '2', '۳' => '3', '۴' => '4', '۵' => '5', '۶' => '6', '۷' => '7', '۸' => '8', '۹' => '9' ] );
	if ( ! preg_match( '/(?:iphone|آیفون|ایفون)\s*(\d{1,2}|xs|xr|x|se)(?![a-z0-9])/iu', $name, $m )
		&& ! preg_match( '/^\s*(\d{1,2})(?=\s*(?:pro|plus|normal|mini|max|e\b|\s))/iu', $name, $m ) ) {
		return 0;
	}
	$v = strtolower( $m[1] );
	return match ( $v ) {
		'x', 'xs', 'xr' => 10,
		'se'            => 9,
		default         => (int) $v,
	};
}

/** «13-18» ، «-12» ، «13-» ، «12» → [min, max] */
function stlh_parse_range( string $r ): ?array {
	$r = trim( strtr( $r, [ '۰' => '0', '۱' => '1', '۲' => '2', '۳' => '3', '۴' => '4', '۵' => '5', '۶' => '6', '۷' => '7', '۸' => '8', '۹' => '9', '–' => '-', 'تا' => '-' ] ) );
	if ( '' === $r || ! preg_match( '/^\s*(\d*)\s*(-?)\s*(\d*)\s*$/', $r, $m ) ) {
		return null;
	}
	if ( '' === $m[2] ) {
		return '' === $m[1] ? null : [ (int) $m[1], (int) $m[1] ];
	}
	return [ '' === $m[1] ? 0 : (int) $m[1], '' === $m[3] ? 99 : (int) $m[3] ];
}

/** محصولات یک ردیف، با فیلتر اختیاری بازه مدل */
function stlh_row_items( array $slugs, int $limit, string $models = '' ): array {
	$range = stlh_parse_range( $models );
	if ( ! $range ) {
		return stlh_query_products( $slugs, $limit );
	}
	$limit = max( 1, min( 24, $limit ) );
	$out   = [];
	foreach ( stlh_query_products( $slugs, 100 ) as $p ) {
		$n = stlh_model_number( $p->get_name() );
		if ( $n && $n >= $range[0] && $n <= $range[1] ) {
			$out[] = $p;
			if ( count( $out ) >= $limit ) {
				break;
			}
		}
	}
	return $out;
}

/* ---------- ردیف محصولات ---------- */
function stlh_row_html( array $items, string $title, string $subtitle = '', string $url = '', string $extra = '' ): string {
	if ( ! $items ) {
		return '';
	}
	return '<section class="st-pr-wrapper"><div class="st-pr-container">'
		. stlh_section_head( $title, $subtitle, $url )
		. $extra
		. '<div class="st-pr-track"><div class="st-pr-row">' . implode( '', array_map( 'stlh_card', $items ) ) . '</div></div></div></section>';
}

function stlh_products( array $atts ): string {
	if ( ! stlh_woo() ) {
		return '';
	}
	$a     = shortcode_atts( [ 'category' => '', 'limit' => 8, 'title' => '', 'subtitle' => '', 'models' => '' ], $atts, 'stl_products' );
	$slugs = array_map( 'trim', explode( ',', (string) $a['category'] ) );
	$items = stlh_row_items( $slugs, (int) $a['limit'], (string) $a['models'] );
	$title = (string) $a['title'];
	if ( '' === $title && ( $t = stlh_resolve_cat( $slugs[0] ) ) ) {
		$title = stlh_cat_name( $t );
	}
	return stlh_row_html( $items, $title, (string) $a['subtitle'], stlh_term_url( $slugs[0] ) );
}

/* ---------- ردیف‌ها («خط»ها) ---------- */

/**
 * گونه‌های ردیف. صاحب فروشگاه ردیف‌ها را این‌طور می‌بیند: «آیفون آک، کارکرده
 * یک، کارکرده دو، لوازم جانبی». پس هر ردیف = یک دسته + یکی از این‌ها، و
 * در تنظیماتِ افزونه با کشیدن مرتب و با فهرستِ کشویی ساخته می‌شود.
 */
function stlh_line_modes(): array {
	return [
		'all'      => 'همه در یک ردیف (با زیرمجموعه‌ها)',
		'recent'   => 'فقط نسل‌های تازه (کارکرده ۱)',
		'older'    => 'فقط مدل‌های قدیمی‌تر (کارکرده ۲)',
		'children' => 'هر زیردسته یک ردیفِ جدا',
	];
}

/**
 * گوشی‌های یک دسته، دو دسته: نسل‌های تازه و قدیمی‌تر.
 *
 * صاحب فروشگاه: «آیفون کارکرده مثلاً ۵ نسل آخر اینجا بیاد که شلوغ نشه،
 * مابقی نسل‌ها زیرش.» «آخر» نسبت به **جدیدترین گوشیِ موجود** است، نه عددی
 * ثابت؛ آیفون ۱۸ که آمد، بازه خودش جابه‌جا می‌شود. نسل از نامِ محصول
 * خوانده می‌شود (`stlh_model_number`) — دسته‌ی «نسل ۱۴» لازم نیست. محصولی
 * که نسلش خوانده نشد به «قدیمی‌تر» می‌رود، نه اینکه گم شود.
 *
 * @return array{items: WC_Product[], recent: WC_Product[], older: WC_Product[], from: int, max: int, split: bool}
 */
function stlh_split_generations( WP_Term $t ): array {
	static $memo = [];
	if ( isset( $memo[ $t->term_id ] ) ) {
		return $memo[ $t->term_id ];
	}
	$items = stlh_query_products( [ (string) $t->term_id ], 100 );
	$gens  = array_map( static fn( WC_Product $p ) => stlh_model_number( $p->get_name() ), $items );
	$max   = $gens ? max( $gens ) : 0;
	$from  = $max - max( 1, (int) stlh_opt( 'recent_span' ) ?: 5 ) + 1;
	$recent = [];
	$older  = [];
	foreach ( $items as $i => $p ) {
		if ( $gens[ $i ] >= $from && $gens[ $i ] > 0 ) {
			$recent[] = $p;
		} else {
			$older[] = $p;
		}
	}
	// تازه‌ترها اول
	usort( $recent, static fn( $a, $b ) => stlh_model_number( $b->get_name() ) <=> stlh_model_number( $a->get_name() ) );
	return $memo[ $t->term_id ] = [
		'items'  => $items,
		'recent' => $recent,
		'older'  => $older,
		'from'   => $from,
		'max'    => $max,
		'split'  => $max > 0 && $recent && $older,
	];
}

/**
 * ردیف‌هایی که صاحب فروشگاه نچیده — خودکار از درخت.
 *
 * هر برگِ دارای محصول یک ردیف، به ترتیبِ درخت؛ برگی که گوشی‌هایش از چند
 * نسل‌اند دو ردیف (تازه، قدیمی‌تر)؛ دسته‌ی «لوازم جانبی» یک ردیفِ
 * «هر زیردسته»؛ پرفروش‌ها بخشِ خودش را دارد و اینجا نمی‌آید.
 *
 * @return array<int, array{cat: int, mode: string, title: string, limit: int}>
 */
function stlh_auto_lines(): array {
	$group = stlh_resolve_cat( (string) stlh_opt( 'group_parent' ) );
	$under = $group ? array_map( 'intval', (array) get_term_children( $group->term_id, 'product_cat' ) ) : [];
	$best  = stlh_resolve_cat( (string) stlh_opt( 'best_cat' ) );
	$line  = static fn( int $cat, string $mode, int $limit = 8 ) => [ 'cat' => $cat, 'mode' => $mode, 'title' => '', 'sub' => '', 'limit' => $limit ];
	$out   = [];
	$added = false;
	foreach ( stlh_leaf_categories() as $t ) {
		if ( $best && $t->term_id === $best->term_id ) {
			continue;
		}
		if ( $group && ( $t->term_id === $group->term_id || in_array( $t->term_id, $under, true ) ) ) {
			if ( ! $added && $under ) {
				// صاحب فروشگاه: لوازم جانبی همه زیرِ هم، در یک کراسول — نه هر زیردسته یک ردیف
				$out[] = $line( $group->term_id, 'all', 16 );
				$added = true;
			} elseif ( ! $under ) {
				$out[] = $line( $t->term_id, 'all' );
			}
			continue;
		}
		if ( stlh_split_generations( $t )['split'] ) {
			$out[] = $line( $t->term_id, 'recent' );
			$out[] = $line( $t->term_id, 'older' );
		} else {
			$out[] = $line( $t->term_id, 'all' );
		}
	}
	return $out;
}

/** ردیف‌هایی که الان روی صفحه می‌آیند: چیده‌شده در تنظیمات، وگرنه خودکار */
function stlh_lines_effective(): array {
	$lines = array_values( array_filter( (array) stlh_opt( 'lines' ), 'is_array' ) );
	return $lines ?: stlh_auto_lines();
}

/**
 * یک ردیف — [عنوان، زیرعنوان، محصولات] یا برای «هر زیردسته» چند ردیف.
 *
 * @return array{0: WP_Term|null, 1: array<int, array{title: string, sub: string, items: WC_Product[], url: string}>}
 */
function stlh_line_blocks( array $line ): array {
	$t = get_term( (int) ( $line['cat'] ?? 0 ), 'product_cat' );
	if ( ! $t instanceof WP_Term ) {
		return [ null, [] ];
	}
	$limit = max( 1, min( 24, (int) ( $line['limit'] ?? 8 ) ?: 8 ) );
	$title = trim( (string) ( $line['title'] ?? '' ) );
	$sub   = trim( (string) ( $line['sub'] ?? '' ) );
	$url   = get_term_link( $t );
	$url   = is_wp_error( $url ) ? '' : $url;
	$mode  = (string) ( $line['mode'] ?? 'all' );

	if ( 'children' === $mode ) {
		$kids   = get_terms( [ 'taxonomy' => 'product_cat', 'parent' => $t->term_id, 'hide_empty' => true, 'menu_order' => 'ASC' ] );
		$blocks = [];
		foreach ( is_wp_error( $kids ) ? [] : $kids as $k ) {
			$items = stlh_query_products( [ (string) $k->term_id ], $limit );
			if ( $items ) {
				$kurl     = get_term_link( $k );
				$blocks[] = [ 'title' => stlh_cat_name( $k ), 'sub' => '', 'items' => $items, 'url' => is_wp_error( $kurl ) ? '' : $kurl ];
			}
		}
		return [ $t, $blocks ];
	}
	if ( 'recent' === $mode || 'older' === $mode ) {
		$g = stlh_split_generations( $t );
		if ( ! $g['split'] ) {
			// همه از یک نسل‌اند: «تازه» همه را نشان می‌دهد و «قدیمی‌تر» چیزی ندارد
			return [ $t, 'recent' === $mode && $g['items'] ? [ [ 'title' => $title ?: stlh_cat_name( $t ), 'sub' => '', 'items' => array_slice( $g['items'], 0, $limit ), 'url' => $url ] ] : [] ];
		}
		return 'recent' === $mode
			? [ $t, [ [ 'title' => $title ?: stlh_cat_name( $t ), 'sub' => $sub ?: stlh_label( 'recent_sub', [ '{از}' => stlh_fa( $g['from'] ), '{تا}' => stlh_fa( $g['max'] ) ] ), 'items' => array_slice( $g['recent'], 0, $limit ), 'url' => $url ] ] ]
			: [ $t, [ [ 'title' => $title ?: stlh_label( 'older_title', [ '{دسته}' => stlh_cat_name( $t ) ] ), 'sub' => $sub ?: stlh_label( 'older_sub', [ '{تا}' => stlh_fa( $g['from'] - 1 ) ] ), 'items' => array_slice( $g['older'], 0, $limit ), 'url' => $url ] ] ];
	}
	// محصولاتِ خودِ دسته و همه‌ی زیرمجموعه‌هایش، تازه‌ترها اول
	$items = stlh_query_products( [ (string) $t->term_id ], $limit );
	return [ $t, $items ? [ [ 'title' => $title ?: stlh_cat_name( $t ), 'sub' => $sub, 'items' => $items, 'url' => $url ] ] : [] ];
}

/**
 * دکمه‌های کوچکِ زیردسته‌ها بالای ردیف (ایرپاد، شارژر…) — همه‌ی لوازم جانبی
 * در یک کراسول می‌آیند و مشتری با یک لمس به همان زیردسته می‌رود.
 */
function stlh_subcat_chips( WP_Term $t ): string {
	$kids = get_terms( [ 'taxonomy' => 'product_cat', 'parent' => $t->term_id, 'hide_empty' => true, 'menu_order' => 'ASC' ] );
	if ( is_wp_error( $kids ) || count( $kids ) < 2 ) {
		return '';
	}
	$html = '';
	foreach ( $kids as $k ) {
		$url = get_term_link( $k );
		if ( ! is_wp_error( $url ) ) {
			$html .= '<a class="st-subcat" href="' . esc_url( $url ) . '">' . esc_html( stlh_cat_name( $k ) ) . '</a>';
		}
	}
	return $html ? '<nav class="st-subcats" aria-label="' . esc_attr( $t->name ) . '">' . $html . '</nav>' : '';
}

function stlh_line_html( array $line ): string {
	[ $t, $blocks ] = stlh_line_blocks( $line );
	if ( ! $t || ! $blocks ) {
		return '';
	}
	if ( 'children' !== ( $line['mode'] ?? '' ) ) {
		$b = $blocks[0];
		return stlh_row_html( $b['items'], $b['title'], $b['sub'], $b['url'], 'all' === ( $line['mode'] ?? 'all' ) ? stlh_subcat_chips( $t ) : '' );
	}
	$html = '';
	foreach ( $blocks as $b ) {
		$html .= '<div class="st-grp-block">'
			. stlh_section_head( $b['title'], '', $b['url'], stlh_label( 'more_list' ), 'h3' )
			. '<div class="st-pr-track"><div class="st-pr-row st-grp-row">' . implode( '', array_map( 'stlh_card', $b['items'] ) ) . '</div></div></div>';
	}
	$url   = get_term_link( $t );
	$title = trim( (string) ( $line['title'] ?? '' ) ) ?: stlh_cat_name( $t );
	return '<section class="st-pr-wrapper st-grp-wrapper"><div class="st-pr-container">'
		. stlh_section_head( $title, '', is_wp_error( $url ) ? '' : $url )
		. $html . '</div></section>';
}

/* ---------- پرفروش‌ها ---------- */
function stlh_best(): string {
	if ( ! stlh_woo() || ! ( $t = stlh_resolve_cat( (string) stlh_opt( 'best_cat' ) ) ) ) {
		return '';
	}
	$url = get_term_link( $t );
	return stlh_row_html( stlh_query_products( [ (string) $t->term_id ], 12 ), stlh_cat_name( $t ), '', is_wp_error( $url ) ? '' : $url );
}

/* ---------- گروه لوازم جانبی (هر زیردسته یک ردیف) ---------- */
function stlh_group(): string {
	if ( ! stlh_woo() ) {
		return '';
	}
	$parent = stlh_resolve_cat( (string) stlh_opt( 'group_parent' ) );
	if ( ! $parent ) {
		return '';
	}
	// همین دسته در «ردیف‌ها» هست؟ دو بار نیاید
	if ( 'manual' !== stlh_opt( 'rows_source' ) ) {
		foreach ( stlh_lines_effective() as $l ) {
			if ( (int) ( $l['cat'] ?? 0 ) === $parent->term_id ) {
				return '';
			}
		}
	}
	$children = get_terms( [ 'taxonomy' => 'product_cat', 'parent' => $parent->term_id, 'hide_empty' => true, 'menu_order' => 'ASC' ] );
	if ( is_wp_error( $children ) || ! $children ) {
		return ''; // بدون زیردسته نمایش داده نمی‌شود (جلوگیری از تکرار با ردیف‌ها)
	}
	$terms = $children;
	$limit    = (int) stlh_opt( 'group_limit' ) ?: 12;

	$blocks = '';
	foreach ( $terms as $term ) {
		$items = stlh_query_products( [ $term->slug ], $limit );
		if ( ! $items ) {
			continue;
		}
		$url     = get_term_link( $term );
		$blocks .= '<div class="st-grp-block">'
			. stlh_section_head( stlh_cat_name( $term ), '', is_wp_error( $url ) ? '' : $url, stlh_label( 'more_list' ), 'h3' )
			. '<div class="st-pr-track"><div class="st-pr-row st-grp-row">' . implode( '', array_map( 'stlh_card', $items ) ) . '</div></div></div>';
	}
	if ( ! $blocks ) {
		return '';
	}
	$parent_url = get_term_link( $parent );
	return '<section class="st-pr-wrapper st-grp-wrapper"><div class="st-pr-container">'
		. stlh_section_head( (string) stlh_opt( 'group_title' ), (string) stlh_opt( 'group_subtitle' ), is_wp_error( $parent_url ) ? '' : $parent_url )
		. $blocks . '</div></section>';
}

/* ---------- پیشنهاد ویژه ---------- */
function stlh_flash_deal(): string {
	$mode = stlh_opt( 'flash_mode' );
	if ( 'off' === $mode || ! stlh_woo() ) {
		return '';
	}
	// ⛔ گوشی تک‌عددی است و زود فروش می‌رود؛ «پیشنهاد ویژه»ی ناموجود
	//    بدترین چیزی است که می‌شود وسط صفحه اصلی نشان داد. پس محصولِ دستیِ
	//    ناموجود کنار می‌رود و جایش آخرین محصولِ «ویژه»ی موجود می‌آید؛ اگر
	//    هیچ‌کدام نبود، کل بخش پنهان می‌شود.
	$ok = static fn( $p ): bool => $p instanceof WC_Product && 'publish' === $p->get_status() && $p->is_in_stock() && $p->is_visible();
	$p  = null;
	if ( 'manual' === $mode && ( $id = (int) stlh_opt( 'flash_product' ) ) ) {
		$p = wc_get_product( $id );
	}
	if ( ! $ok( $p ) ) {
		$found = wc_get_products( [ 'status' => 'publish', 'featured' => true, 'stock_status' => 'instock', 'limit' => 5, 'orderby' => 'modified', 'order' => 'DESC' ] );
		$p     = current( array_filter( $found, $ok ) ) ?: null;
	}
	if ( ! $p ) {
		return '';
	}

	$desc  = $p->get_short_description() ?: $p->get_description();
	$desc  = wp_trim_words( wp_strip_all_tags( $desc ), 40 );
	$cats  = get_the_terms( $p->get_id(), 'product_cat' );
	$label = ( $cats && ! is_wp_error( $cats ) ) ? $cats[0]->name : '';
	$stock = 'موجود در فروشگاه';

	ob_start();
	?>
	<section class="st-flash-deal-wrapper"><div class="st-flash-deal-container">
		<div class="st-fd-content">
			<div class="st-fd-badge-box">
				<span class="st-fd-badge"><?php echo esc_html( (string) stlh_opt( 'flash_badge' ) ); ?></span>
				<span class="st-fd-timer-badge is-in"><?php echo esc_html( $stock ); ?></span>
			</div>
			<?php $fbadges = stlh_badges( $p, 4 ); if ( $fbadges ) : ?>
				<div class="st-fd-specs"><?php foreach ( $fbadges as $b ) : ?><span class="st-chip"><?php echo esc_html( $b ); ?></span><?php endforeach; ?></div>
			<?php endif; ?>
			<h2><?php echo esc_html( $p->get_name() ); ?></h2>
			<?php if ( $desc ) : ?><p><?php echo esc_html( $desc ); ?></p><?php endif; ?>
			<div class="st-fd-price-box"><div class="st-fd-new-price"><?php echo stlh_price( $p ); ?></div></div>
			<a class="st-fd-btn" href="<?php echo esc_url( $p->get_permalink() ); ?>">مشاهده و خرید محصول<?php echo stlh_arrow(); ?></a>
		</div>
		<div class="st-fd-image-container">
			<?php if ( $label ) : ?><span class="st-fd-off-badge"><?php echo esc_html( $label ); ?></span><?php endif; ?>
			<div class="st-fd-img-frame"><?php echo $p->get_image( 'woocommerce_single', [ 'loading' => 'lazy', 'decoding' => 'async' ] ); ?></div>
		</div>
	</div></section>
	<?php
	return (string) ob_get_clean();
}

/* ---------- شبکه‌های اجتماعی ---------- */
function stlh_social(): string {
	$ig  = ltrim( (string) stlh_opt( 'instagram' ), '@' );
	$bot = ltrim( (string) stlh_opt( 'telegram_bot' ), '@' );
	if ( ! $ig && ! $bot ) {
		return '';
	}
	ob_start();
	?>
	<section class="st-social-bot-wrapper"><div class="st-social-bot-container">
		<h2 class="st-sr-only">استوک لند در شبکه‌های اجتماعی</h2>
		<div class="st-social-bot-grid">
			<?php if ( $ig ) : ?>
			<a class="st-sb-card st-sb-instagram" href="<?php echo esc_url( 'https://instagram.com/' . $ig ); ?>" target="_blank" rel="noopener noreferrer">
				<div>
					<div class="st-sb-top"><span class="st-sb-icon-box"><?php echo stlh_brand_icon( 'instagram' ); ?></span><span class="st-sb-badge">شبکه اجتماعی</span></div>
					<div class="st-sb-content"><h3>اینستاگرام استوک لند</h3>
						<p>ویدیوهای آنباکس، تست‌های واقعی گوشی‌های استوک، معرفی مدل‌های جدید آیفون و تخفیف‌های روزانه را در پیج ما دنبال کنید.</p></div>
				</div>
				<span class="st-sb-action-link"><span>مشاهده پیج (<?php echo esc_html( $ig ); ?>)</span><?php echo stlh_arrow(); ?></span>
			</a>
			<?php endif; ?>
			<?php if ( $bot ) : ?>
			<a class="st-sb-card st-sb-telegram" href="<?php echo esc_url( 'https://t.me/' . $bot ); ?>" target="_blank" rel="noopener noreferrer">
				<div>
					<div class="st-sb-top"><span class="st-sb-icon-box"><?php echo stlh_brand_icon( 'telegram' ); ?></span><span class="st-sb-badge">دستیار هوشمند</span></div>
					<div class="st-sb-content"><h3>ربات اختصاصی تلگرام</h3>
						<p>تمامی خدمات تخصصی اپل را سریع و آنلاین در ربات ما دریافت کنید:</p></div>
					<div class="st-sb-features"><span class="st-sb-chip">ساخت و خدمات اپل‌آیدی</span><span class="st-sb-chip">تحلیل و تست گوشی</span><span class="st-sb-chip">کارشناسی قیمت</span></div>
				</div>
				<span class="st-sb-action-link"><span>ورود به ربات (@<?php echo esc_html( $bot ); ?>)</span><?php echo stlh_arrow(); ?></span>
			</a>
			<?php endif; ?>
		</div>
	</div></section>
	<?php
	return (string) ob_get_clean();
}

/* ---------- سوالات متداول ---------- */
function stlh_faq(): string {
	$items = stlh_faq_items();
	if ( ! $items ) {
		return '';
	}
	$phone   = preg_replace( '/\D/', '', (string) stlh_opt( 'phone' ) );
	$address = trim( (string) stlh_opt( 'address' ) );
	$uid     = wp_unique_id( 'st-faq-' );
	$schema  = [ '@context' => 'https://schema.org', '@type' => 'FAQPage', 'mainEntity' => [] ];

	ob_start();
	?>
	<section class="st-faq-light-wrapper"><div class="st-faq-light-container"><div class="st-faq-light-grid">
		<aside class="st-faq-light-sidebar">
			<span class="st-faq-light-badge"><?php echo stlh_line_icon( 'info' ); ?>پشتیبانی و راهنمایی</span>
			<h2>سوالات متداول کاربران</h2>
			<p>پاسخ پرسش‌های رایج درباره خرید اقساطی، مراجعه حضوری و شرایط ضمانت محصولات استوک لند.</p>
			<div class="st-faq-light-contacts">
				<?php if ( $phone ) : ?>
				<a class="st-faq-light-contact-card" href="tel:<?php echo esc_attr( $phone ); ?>">
					<span class="st-faq-light-c-icon"><?php echo stlh_line_icon( 'phone' ); ?></span>
					<span class="st-faq-light-c-text"><span class="st-faq-light-c-label">تماس برای شرایط اقساط</span><strong><?php echo esc_html( stlh_fa( $phone ) ); ?></strong></span>
				</a>
				<?php endif; ?>
				<?php if ( $address ) : ?>
				<div class="st-faq-light-contact-card">
					<span class="st-faq-light-c-icon"><?php echo stlh_line_icon( 'pin' ); ?></span>
					<span class="st-faq-light-c-text"><span class="st-faq-light-c-label">آدرس فروشگاه حضوری</span><strong><?php echo esc_html( $address ); ?></strong></span>
				</div>
				<?php endif; ?>
			</div>
		</aside>
		<div class="st-faq-light-list">
			<?php foreach ( $items as $i => $it ) :
				$aid                    = $uid . '-' . $i;
				$schema['mainEntity'][] = [ '@type' => 'Question', 'name' => $it['q'], 'acceptedAnswer' => [ '@type' => 'Answer', 'text' => $it['a'] ] ];
				?>
			<div class="st-faq-light-item">
				<button class="st-faq-light-question" type="button" aria-expanded="false" aria-controls="<?php echo esc_attr( $aid ); ?>">
					<span class="st-faq-light-q-text"><?php echo esc_html( $it['q'] ); ?></span>
					<span class="st-faq-light-icon" aria-hidden="true">+</span>
				</button>
				<div class="st-faq-light-answer" id="<?php echo esc_attr( $aid ); ?>" role="region">
					<div class="st-faq-light-answer-inner"><?php echo nl2br( esc_html( $it['a'] ) ); ?></div>
				</div>
			</div>
			<?php endforeach; ?>
		</div>
	</div></div></section>
	<script type="application/ld+json"><?php echo wp_json_encode( $schema, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ); ?></script>
	<?php
	return (string) ob_get_clean();
}

/* ---------- مقالات ---------- */
function stlh_reading_time( int $post_id ): string {
	$words = count( preg_split( '/\s+/u', trim( wp_strip_all_tags( (string) get_post_field( 'post_content', $post_id ) ) ), -1, PREG_SPLIT_NO_EMPTY ) );
	return stlh_fa( max( 1, (int) ceil( $words / 200 ) ) ) . ' دقیقه مطالعه';
}

function stlh_posts(): string {
	$n = (int) stlh_opt( 'posts_count' );
	if ( $n < 1 ) {
		return '';
	}
	$q = new WP_Query( [ 'post_type' => 'post', 'post_status' => 'publish', 'posts_per_page' => $n, 'ignore_sticky_posts' => true, 'no_found_rows' => true ] );
	if ( ! $q->have_posts() ) {
		return '';
	}
	$blog  = (int) get_option( 'page_for_posts' );
	$blog  = $blog ? get_permalink( $blog ) : home_url( '/blog/' );
	$clock = '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M11.99 2C6.47 2 2 6.48 2 12s4.47 10 9.99 10C17.52 22 22 17.52 22 12S17.52 2 11.99 2zM12 20c-4.42 0-8-3.58-8-8s3.58-8 8-8 8 3.58 8 8-3.58 8-8 8zm.5-13H11v6l5.25 3.15.75-1.23-4.5-2.67z"/></svg>';

	$cards = '';
	foreach ( $q->posts as $post ) {
		$cat    = get_the_category( $post->ID );
		$badge  = $cat ? '<span class="st-article-badge">' . esc_html( $cat[0]->name ) . '</span>' : '';
		$thumb  = get_the_post_thumbnail( $post, 'medium_large', [ 'loading' => 'lazy', 'decoding' => 'async' ] );
		$cards .= sprintf(
			'<a class="st-article-card" href="%s"><div class="st-article-img-box">%s%s</div><h3>%s</h3><div class="st-article-footer"><span class="st-article-meta">%s%s</span><span class="st-article-action-btn" aria-hidden="true">←</span></div></a>',
			esc_url( get_permalink( $post ) ), $badge, $thumb, esc_html( get_the_title( $post ) ), $clock, esc_html( stlh_reading_time( $post->ID ) )
		);
	}

	return '<section class="st-articles-wrapper"><div class="st-articles-container">'
		. '<div class="st-articles-header"><div class="st-articles-title-box"><h2>مجله و مقالات استوک لند</h2><p>راهنمای خرید آیفون، بررسی اصالت و مقایسه مدل‌های مختلف اپل</p></div>'
		. '<a class="st-articles-more-btn" href="' . esc_url( $blog ) . '">' . esc_html( stlh_label( 'posts_more' ) ) . stlh_arrow() . '</a></div>'
		. '<div class="st-articles-grid">' . $cards . '</div></div></section>';
}
