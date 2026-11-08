<?php
/**
 * منوی بالای سایت از درختِ زنده‌ی دسته‌ها (۱۸ آبان ۱۴۰۵).
 *
 * صاحب فروشگاه: «در Novo دسته‌بندی‌هایی داریم که در سایت نیست؛ مثلاً در لوازم جانبی کابل و گلس داریم، ولی منوی بالای لوازم
 * جانبی فقط شارژر و محافظ لنز دارد… همه‌چیز باید سینک باشد؛ همه‌ی دسته‌ها زیرمجموعه‌ی Novo‌اند.»
 *
 * ریشه: دسته‌ها در ووکامرس بودند («کابل»، «محافظ صفحه»، «قاب و گارد»)، ولی منوی جایگاهِ `main`/`mobile` یک «فهرستِ» ثابتِ
 * وردپرس است که یک بار با دست ساخته شده بود؛ دسته‌ی تازه هرگز خودش در آن نمی‌نشست. پس — بی دست زدن به قالب و بی نوشتن در منو —
 * مواردِ منو هنگامِ خواندن (`wp_get_nav_menu_items`) از درختِ زنده پر می‌شوند:
 *
 *   • زیرمنوی هر گروهِ دسته (مثلِ «لوازم جانبی»: «مشاهده‌ی همه» ← دسته‌ی «لوازم جانبی») = فرزندانِ زنده‌ی همان دسته، به ترتیبِ
 *     درخت (`menu_order` که حسابداری می‌نویسد)، با نامِ نمایشیِ `stlh_cat_name`. «مشاهده‌ی همه» و پیوندهای غیرِدسته سرِ جایشان.
 *   • فقط دسته‌ای که محصولِ قابلِ نمایش دارد (همان تعریفِ کارت‌های صفحه‌ی اصلی: خودش یا زیردسته‌هایش) — «دسته‌های خالی هم»
 *     در تنظیمات خلافش را می‌خواهد. دسته‌ی پنهان‌شده با تیکِ «درختِ دسته‌ها» (`hide_cats`) و «دسته‌بندی نشده» هرگز.
 *   • دسته‌ی اصلیِ دارای محصول که هیچ جای منو نیست (مثلاً دسته‌ی تازه‌ی سطحِ اولِ Novo) کنارِ گروه‌های دیگر سرِ منو می‌آید —
 *     با زیرمنو اگر فرزندِ دارای محصول دارد. «پرفروش‌ها» (`best_cat`) بخشِ صفحه‌ی اصلی است، نه منو.
 *
 * زیرمنو یک سطح است، چون منوی شیشه‌ای رایانه سطحِ سوم را نشان نمی‌دهد (`stlh_dm_css`)؛ زیردسته‌های عمیق‌تر در صفحه‌ی همان دسته‌اند.
 * کلیدِ «منوی بالا از درختِ دسته‌ها» (پیش‌فرض روشن) خاموشش می‌کند و همان فهرستِ ثابتِ وردپرس برمی‌گردد — چیزی از آن پاک نشده.
 * کهنه نشدن: درخت در کشِ نسل‌دار (`stlh_cached`) است؛ هر تغییرِ دسته/محصول نسل را بالا می‌برد و اگر منو واقعاً عوض شد (امضای
 * `stlh_live_menu_sig`) کلِ کشِ صفحه پاک می‌شود، چون منو روی همه‌ی صفحه‌هاست.
 */
defined( 'ABSPATH' ) || exit;

/** شناسه‌ی موردِ ساختگی: پایه + شناسه‌ی ترم — ثابت در هر بار ساختن، تا پنلِ گوشی (`m{ID}`) و CSS پایدار بمانند */
const STLH_LM_TERM = 900000000;
const STLH_LM_GROUP = 910000000;
const STLH_LM_ALL = 920000000;

function stlh_live_menu_on(): bool {
	return '1' === (string) stlh_opt( 'menu_live' );
}

/** منوهای جایگاهِ سرِ سایت (رایانه و گوشی) */
function stlh_live_menu_ids(): array {
	$loc = (array) get_nav_menu_locations();
	return array_values( array_unique( array_filter( [ (int) ( $loc['main'] ?? 0 ), (int) ( $loc['mobile'] ?? 0 ) ] ) ) );
}

/**
 * درختِ زنده: [terms => [id => [n: نام, u: نشانی, p: والد, s: نمایش؟]], kids => [والد => [id…]]] به ترتیبِ درخت.
 * `s` = محصولِ قابلِ نمایش دارد (یا «خالی‌ها هم» روشن است)، «دسته‌بندی نشده» نیست و پنهان نشده.
 */
function stlh_live_cat_tree_build(): array {
	$all = get_terms( [ 'taxonomy' => 'product_cat', 'hide_empty' => false, 'menu_order' => 'ASC' ] );
	if ( is_wp_error( $all ) || ! $all ) {
		return [ 'terms' => [], 'kids' => [] ];
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
	$hidden = array_map( 'intval', (array) stlh_opt( 'hide_cats' ) );
	$empty  = '1' === (string) stlh_opt( 'menu_live_empty' );
	$terms  = [];
	$kids   = [];
	foreach ( $all as $t ) {
		$url = get_term_link( $t );
		$terms[ $t->term_id ] = [
			'n' => stlh_cat_name( $t ),
			'u' => is_string( $url ) ? $url : '',
			'p' => (int) $t->parent,
			's' => is_string( $url ) && ! stlh_is_bucket( $t, $children ) && ! in_array( $t->term_id, $hidden, true ) && ( $empty || $total( $t ) > 0 ),
		];
		$kids[ (int) $t->parent ][] = $t->term_id;
	}
	return [ 'terms' => $terms, 'kids' => $kids ];
}

function stlh_live_cat_tree(): array {
	static $tree = null;
	if ( null === $tree ) {
		$tree = json_decode( stlh_cached( 'live-menu-tree', static fn() => (string) wp_json_encode( stlh_live_cat_tree_build() ) ), true );
		$tree = is_array( $tree ) ? $tree : [ 'terms' => [], 'kids' => [] ];
	}
	return $tree;
}

/** دسته‌ی $id خودِ $anc است یا زیرِ آن */
function stlh_live_under( array $terms, int $id, int $anc ): bool {
	for ( $i = 0; $id && $i < 12; $i++ ) {
		if ( $id === $anc ) {
			return true;
		}
		$id = (int) ( $terms[ $id ]['p'] ?? 0 );
	}
	return false;
}

/** یک موردِ منو، از روی الگوی یکی از مواردِ واقعی (تا هر ویژگی‌ای که قالب می‌خواند باشد) */
function stlh_live_item( object $tpl, int $id, int $parent, string $title, string $url, int $term = 0 ): object {
	$it                   = clone $tpl;
	$it->ID               = $id;
	$it->db_id            = $id;
	$it->menu_item_parent = (string) $parent;
	$it->post_parent      = 0;
	$it->title            = $title;
	$it->post_title       = $title;
	$it->url              = $url;
	$it->target           = '';
	$it->attr_title       = '';
	$it->description      = '';
	$it->xfn              = '';
	$it->classes          = [];
	$it->post_name        = (string) $id;
	if ( $term ) {
		$it->type       = 'taxonomy';
		$it->object     = 'product_cat';
		$it->object_id  = (string) $term;
		$it->type_label = 'دسته';
	} else {
		$it->type       = 'custom';
		$it->object     = 'custom';
		$it->object_id  = (string) $id;
		$it->type_label = 'پیوند دلخواه';
	}
	$it->stlh_live = true;
	return $it;
}

/**
 * فهرستِ کاملِ مواردِ یک منو (به ترتیبِ `menu_order`) → همان، با زیرمنوهای دسته از درختِ زنده.
 * خالص روی ورودی‌اش — آزمونِ `tests/visual/live-menu.php` مستقیم صدایش می‌زند.
 */
function stlh_live_menu_apply( array $items, array $tree ): array {
	$items = array_values( array_filter( $items, 'is_object' ) );
	$terms = (array) ( $tree['terms'] ?? [] );
	$tkids = (array) ( $tree['kids'] ?? [] );
	if ( ! $items || ! $terms ) {
		return $items;
	}
	$is_cat = static fn( object $it ): bool => 'taxonomy' === ( $it->type ?? '' ) && 'product_cat' === ( $it->object ?? '' ) && isset( $terms[ (int) $it->object_id ] );
	$byParent = [];
	foreach ( $items as $it ) {
		$byParent[ (int) $it->menu_item_parent ][] = $it;
	}
	$tpl     = $items[0];
	$covered = [];
	$drop    = [];
	$extra   = []; // شناسه‌ی پدر => مواردِ زنده‌ی جایگزین، به ترتیب
	$groups  = []; // سرگروه‌های دسته در سطحِ اول (برای جای گروهِ تازه)

	foreach ( $byParent[0] ?? [] as $top ) {
		$kids = $byParent[ (int) $top->ID ] ?? [];
		$cats = array_values( array_filter( $kids, $is_cat ) );
		if ( $is_cat( $top ) ) {
			$covered[ (int) $top->object_id ] = true;
			$groups[]                         = (int) $top->ID;
		}
		if ( ! $cats ) {
			continue;
		}
		// دسته‌ی لنگر: موردی که بقیه زیرِ آن‌اند («مشاهده‌ی همه»)، وگرنه پدرِ مشترکِ بیشترشان
		$anchor = 0;
		$all_it = null;
		foreach ( $cats as $c ) {
			$ok = true;
			foreach ( $cats as $o ) {
				$ok = $ok && stlh_live_under( $terms, (int) $o->object_id, (int) $c->object_id );
			}
			if ( $ok ) {
				$anchor = (int) $c->object_id;
				$all_it = $c;
				break;
			}
		}
		if ( ! $anchor ) {
			$parents = array_count_values( array_map( static fn( $c ) => (int) $terms[ (int) $c->object_id ]['p'], $cats ) );
			arsort( $parents );
			$anchor = (int) array_key_first( $parents );
		}
		if ( ! $anchor ) {
			foreach ( $cats as $c ) {
				$covered[ (int) $c->object_id ] = true;
			}
			continue;
		}
		$groups[]           = (int) $top->ID;
		$covered[ $anchor ] = true;
		$static             = [];
		foreach ( $cats as $c ) {
			if ( $c !== $all_it && (int) $terms[ (int) $c->object_id ]['p'] === $anchor ) {
				$static[ (int) $c->object_id ] = $c;
			}
		}
		$live = [];
		foreach ( $tkids[ $anchor ] ?? [] as $tid ) {
			if ( empty( $terms[ $tid ]['s'] ) ) {
				continue;
			}
			$covered[ $tid ] = true;
			if ( isset( $static[ $tid ] ) ) {
				// موردِ واقعیِ همین دسته می‌ماند (شناسه و آیکنش)، فقط نامِ امروز را می‌گیرد
				$it        = clone $static[ $tid ];
				$it->title = $terms[ $tid ]['n'];
				$live[]    = $it;
				unset( $static[ $tid ] );
			} else {
				$live[] = stlh_live_item( $tpl, STLH_LM_TERM + $tid, (int) $top->ID, $terms[ $tid ]['n'], $terms[ $tid ]['u'], $tid );
			}
		}
		// موردِ ثابتِ دسته‌ای که دیگر نیست یا خالی است — با زیرمنوی خودش — می‌رود
		foreach ( $static as $c ) {
			$drop[ (int) $c->ID ] = true;
		}
		$extra[ (int) $top->ID ] = $live;
	}

	// زیرمجموعه‌ی مواردِ رفته هم برود
	for ( $changed = true; $changed; ) {
		$changed = false;
		foreach ( $items as $it ) {
			if ( ! isset( $drop[ (int) $it->ID ] ) && isset( $drop[ (int) $it->menu_item_parent ] ) ) {
				$drop[ (int) $it->ID ] = true;
				$changed               = true;
			}
		}
	}

	// دسته‌های اصلیِ دارای محصول که هیچ جای منو نیستند
	$best   = function_exists( 'stlh_resolve_cat' ) ? stlh_resolve_cat( (string) stlh_opt( 'best_cat' ) ) : false;
	$best   = $best ? (int) $best->term_id : 0;
	$has    = static function ( int $id ) use ( &$has, $tkids, $covered ): bool {
		if ( isset( $covered[ $id ] ) ) {
			return true;
		}
		foreach ( $tkids[ $id ] ?? [] as $k ) {
			if ( $has( (int) $k ) ) {
				return true;
			}
		}
		return false;
	};
	$orphan = [];
	$walk   = static function ( int $parent ) use ( &$walk, &$orphan, $tkids, $terms, $covered, $has, $best ): void {
		foreach ( $tkids[ $parent ] ?? [] as $tid ) {
			if ( empty( $terms[ $tid ]['s'] ) || $tid === $best || isset( $covered[ $tid ] ) ) {
				continue;
			}
			if ( $has( (int) $tid ) ) {
				$walk( (int) $tid );
			} else {
				$orphan[] = (int) $tid;
			}
		}
	};
	$walk( 0 );
	$new_tops = [];
	foreach ( $orphan as $tid ) {
		$shown = array_values( array_filter( $tkids[ $tid ] ?? [], static fn( $k ) => ! empty( $terms[ $k ]['s'] ) ) );
		if ( ! $shown ) {
			$new_tops[] = [ stlh_live_item( $tpl, STLH_LM_TERM + $tid, 0, $terms[ $tid ]['n'], $terms[ $tid ]['u'], $tid ), [] ];
			continue;
		}
		$gid  = STLH_LM_GROUP + $tid;
		$subs = [ stlh_live_item( $tpl, STLH_LM_ALL + $tid, $gid, stlh_label( 'menu_all' ), $terms[ $tid ]['u'], $tid ) ];
		foreach ( $shown as $k ) {
			$subs[] = stlh_live_item( $tpl, STLH_LM_TERM + $k, $gid, $terms[ $k ]['n'], $terms[ $k ]['u'], (int) $k );
		}
		$new_tops[] = [ stlh_live_item( $tpl, $gid, 0, $terms[ $tid ]['n'], '#' ), $subs ];
	}
	$after = $groups ? end( $groups ) : 0;

	// چیدنِ دوباره: هر سرِ منو، زیرمنوی ثابتش (با زنده‌ها به جای دسته‌های ثابت) و گروه‌های تازه بعد از آخرین گروهِ دسته
	$out  = [];
	$emit = static function ( object $it ) use ( &$out, &$emit, $byParent, $drop, $extra ): void {
		if ( isset( $drop[ (int) $it->ID ] ) ) {
			return;
		}
		$out[] = $it;
		$id    = (int) $it->ID;
		$live  = $extra[ $id ] ?? null;
		$put   = static function () use ( &$out, &$emit, $live, $byParent ): void {
			foreach ( $live as $l ) {
				$out[] = $l;
				foreach ( $byParent[ (int) $l->ID ] ?? [] as $g ) {
					$emit( $g ); // زیرمنوی موردِ واقعیِ ماندگار
				}
			}
		};
		$reused = $live ? array_map( static fn( $x ) => (int) $x->ID, $live ) : [];
		$placed = null === $live;
		foreach ( $byParent[ $id ] ?? [] as $k ) {
			// جای نخستین دسته‌ی ثابت (رفته یا ماندگار) = جای همه‌ی زنده‌ها، به ترتیبِ درخت
			if ( null !== $live && ( isset( $drop[ (int) $k->ID ] ) || in_array( (int) $k->ID, $reused, true ) ) ) {
				if ( ! $placed ) {
					$put();
					$placed = true;
				}
				continue;
			}
			$emit( $k );
		}
		if ( ! $placed ) {
			$put();
		}
	};
	$tops = $byParent[0] ?? [];
	if ( ! $after && $new_tops ) {
		$after = (int) $tops[ min( 1, count( $tops ) - 1 ) ]->ID; // بی گروهِ دسته: بعد از نخستین مورد (خانه)
	}
	foreach ( $tops as $top ) {
		$emit( $top );
		if ( (int) $top->ID === $after ) {
			foreach ( $new_tops as [ $g, $subs ] ) {
				$out[] = $g;
				array_push( $out, ...$subs );
			}
			$new_tops = [];
		}
	}
	foreach ( $new_tops as [ $g, $subs ] ) {
		$out[] = $g;
		array_push( $out, ...$subs );
	}
	// ترتیبِ تازه؛ `wp_nav_menu` موارد را با `menu_order` کلید می‌کند، پس یکتا و پشتِ سرِ هم
	$n = 0;
	foreach ( $out as $i => $it ) {
		$it             = clone $it;
		$it->menu_order = ++$n;
		$out[ $i ]      = $it;
	}
	return $out;
}

/** خواندنِ منو فقط در صفحه‌های عمومی و بعد از راه‌اندازیِ کامل (کارهای `init` که منو را می‌نویسند، دست‌نخورده می‌خوانند) */
function stlh_live_menu_active(): bool {
	return stlh_live_menu_on() && did_action( 'wp_loaded' ) && ! is_admin() && ! wp_doing_cron()
		&& ! ( defined( 'REST_REQUEST' ) && REST_REQUEST ) && ! is_customize_preview() && taxonomy_exists( 'product_cat' );
}

add_filter( 'wp_get_nav_menu_items', static function ( $items, $menu, $args ) {
	static $busy = false, $full = [];
	if ( $busy || ! is_array( $items ) || ! is_object( $menu ) || ! stlh_live_menu_active() ) {
		return $items;
	}
	$mid = (int) ( $menu->term_id ?? 0 );
	if ( ! in_array( $mid, stlh_live_menu_ids(), true ) ) {
		return $items;
	}
	if ( ! isset( $full[ $mid ] ) ) {
		$busy = true;
		try {
			$all          = (array) wp_get_nav_menu_items( $mid );
			$full[ $mid ] = stlh_live_menu_apply( $all, stlh_live_cat_tree() );
		} finally {
			$busy = false;
		}
	}
	// قالب (منوی شیشه‌ای) فرزندانِ هر مورد را با `meta_key=_menu_item_menu_item_parent` جدا می‌پرسد
	if ( '_menu_item_menu_item_parent' === ( $args['meta_key'] ?? '' ) && isset( $args['meta_value'] ) ) {
		$want = (string) (int) $args['meta_value'];
		return array_values( array_filter( $full[ $mid ], static fn( $it ) => (string) (int) $it->menu_item_parent === $want ) );
	}
	return $full[ $mid ];
}, 20, 3 );

/** امضای منوی زنده — فقط آنچه روی منو دیده می‌شود */
function stlh_live_menu_signature(): string {
	$t = stlh_live_cat_tree_build();
	$shown = [];
	foreach ( $t['kids'] as $p => $ids ) {
		foreach ( $ids as $id ) {
			if ( ! empty( $t['terms'][ $id ]['s'] ) ) {
				$shown[] = [ $p, $id, $t['terms'][ $id ]['n'] ];
			}
		}
	}
	return md5( (string) wp_json_encode( [ $shown, stlh_live_menu_on() ] ) );
}

/*
 * منو روی همه‌ی صفحه‌هاست ولی پاک کردنِ نسل فقط صفحه‌ی اصلی و فهرست‌ها را تازه می‌کند. پس در درخواستی که دسته یا محصول عوض شد،
 * آخرِ کار یک بار امضای منو سنجیده می‌شود و فقط اگر منو واقعاً فرق کرد (دسته‌ی تازه، تغییرِ نام یا ترتیب، دسته‌ای که پر یا خالی شد)
 * کلِ کشِ صفحه پاک می‌شود. شمارِ محصولِ دسته را ووکامرس در متای `product_count_product_cat` می‌نویسد.
 */
$stlh_lm_dirty = static function (): void {
	$GLOBALS['stlh_lm_dirty'] = true;
};
add_action( 'stlh_cache_flushed', $stlh_lm_dirty );
foreach ( [ 'added_term_meta', 'updated_term_meta' ] as $stlh_h ) {
	add_action( $stlh_h, static function ( $mid, $term_id, string $key ) use ( $stlh_lm_dirty ): void {
		if ( 'product_count_product_cat' === $key ) {
			$stlh_lm_dirty();
		}
	}, 10, 3 );
}
unset( $stlh_h, $stlh_lm_dirty );

add_action( 'shutdown', static function (): void {
	if ( empty( $GLOBALS['stlh_lm_dirty'] ) || ! taxonomy_exists( 'product_cat' ) ) {
		return;
	}
	$sig = stlh_live_menu_signature();
	if ( get_option( 'stlh_live_menu_sig' ) === $sig ) {
		return;
	}
	update_option( 'stlh_live_menu_sig', $sig, false );
	if ( ! did_action( 'stlh_cache_flushed' ) ) {
		stlh_cache_flush(); // شمارِ دسته عوض شد ولی نسل نه — درختِ کش‌شده هم تازه شود
	}
	do_action( 'litespeed_purge_all' );
}, 5 );
