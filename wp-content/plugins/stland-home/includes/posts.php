<?php
/**
 * مقاله‌های وبلاگ از مخزن.
 *
 * صاحب فروشگاه (۲۳ مهر ۱۴۰۵): «من مطلب بدم با عکس، خودت بذاری». پل فقط‌خواندنی است، پس مقاله مثلِ
 * تنظیماتِ قالب با نسخه‌ی افزونه می‌رود: متن در `content/posts/<نامک>.html` (بلوک‌های گوتنبرگ)، عکس‌ها در
 * `assets/posts/`. هر مقاله یک بار ساخته می‌شود و هر بار که تعریف یا متنش عوض شد به‌روز می‌شود.
 *
 * • عکس‌ها یک بار به کتابخانه‌ی رسانه می‌روند (با اندازه‌های وردپرس)؛ شناسه‌شان در `stlh_post_media`.
 * • `{{img:کلید}}` → بلوکِ عکس، `{{cat:نامِ دسته‌ی محصول}}` → نشانیِ آن دسته، `{{post:ID}}` → نشانیِ آن مقاله.
 * • `post_id` = بازنویسیِ مقاله‌ای که از قبل در سایت هست (`keep_slug` نشانی‌اش را نگه می‌دارد).
 * • وضعیتِ اول از `status` است (`draft` تا صاحب فروشگاه ببیند)؛ بعد از آن وضعیت دست نمی‌خورد.
 * ⛔ اگر صاحب فروشگاه مقاله را در پیشخوان ویرایش کرده باشد، روی ویرایشش نوشته نمی‌شود.
 */
defined( 'ABSPATH' ) || exit;

const STLH_POSTS = [
	'iphone-duo-repair-cost' => [
		'title'    => 'هزینه تعمیر آیفون Duo بدون AppleCare+؛ از ۴۹ تا ۱۴۹۹ دلار!',
		'status'   => 'draft',
		'excerpt'  => 'اپل هزینه‌های تعمیر آیفون Duo را برای کاربرانِ بدون AppleCare+ اعلام کرد؛ از ۴۹ دلار برای لایه محافظ تا ۹۹۹ دلار برای نمایشگر داخلی و ۱۴۹۹ دلار برای سایر آسیب‌ها.',
		'category' => [ 'name' => 'اخبار اپل', 'slug' => 'apple-news' ],
		'tags'     => [ 'آیفون Duo', 'AppleCare+', 'تعمیرات آیفون', 'گوشی تاشو اپل', 'هزینه تعمیر آیفون', 'محصولات اپل' ],
		'seo'      => [
			'title' => 'هزینه تعمیر آیفون Duo بدون AppleCare+؛ جدول کامل قیمت‌ها',
			'desc'  => 'هزینه تعمیر آیفون Duo بدون AppleCare+ تا ۱۴۹۹ دلار می‌رسد. جدول کامل قیمت تعمیر نمایشگر، باتری، شیشه پشتی و مقایسه هزینه‌ها با AppleCare+ را بخوانید.',
			'kw'    => 'هزینه تعمیر آیفون Duo',
		],
		'thumb'    => [ 'file' => 'iphone-duo-hero.webp', 'alt' => 'هزینه تعمیر آیفون Duo با و بدون AppleCare+' ],
		'images'   => [
			'colors' => [ 'file' => 'iphone-duo-colors.webp', 'alt' => 'آیفون Duo در دو رنگ سفید و سرمه‌ای' ],
			'fold'   => [ 'file' => 'iphone-duo-fold.webp', 'alt' => 'نمایشگر داخلی آیفون Duo در حالت باز' ],
		],
	],
	// مقاله‌ی موجود (۹ شهریور، از پیشخوان): بازنویسی برای سئو، عکسِ شاخص و دو طرحِ داخلِ متن (صاحب فروشگاه، ۲۳ مهر)
	'which-iphone-to-buy'    => [
		'post_id'   => 44340,
		'keep_slug' => true, // نشانیِ منتشرشده عوض نشود
		'title'     => 'کدام آیفون ارزش خرید بیشتری دارد؟ مقایسه مدل‌های مختلف آیفون برای خرید',
		'status'    => 'publish',
		'excerpt'   => 'کدام آیفون بخریم؟ مقایسه ارزش خرید آیفون معمولی، Plus، Pro، Pro Max و آیفون کارکرده بر اساس نیاز و بودجه، با جدول مقایسه و چک‌لیست خرید آیفون کارکرده.',
		'category'  => [ 'id' => 2968 ],
		'tags'      => [ 'کدام آیفون بخریم', 'مقایسه آیفون', 'آیفون کارکرده', 'راهنمای خرید آیفون', 'آیفون Pro' ],
		'seo'       => [
			'title' => 'کدام آیفون بخریم؟ مقایسه ارزش خرید مدل‌های آیفون | استوک لند',
			'desc'  => 'کدام آیفون بخریم؟ مقایسه ارزش خرید آیفون معمولی، Pro، Pro Max و کارکرده بر اساس نیاز و بودجه، با جدول مقایسه و چک‌لیست خرید آیفون کارکرده.',
			'kw'    => 'کدام آیفون بخریم',
		],
		'thumb'     => [ 'file' => 'which-iphone-hero.webp', 'alt' => 'کدام آیفون ارزش خرید بیشتری دارد؟ مقایسه مدل‌های آیفون' ],
		'images'    => [
			'guide'     => [ 'file' => 'which-iphone-guide.webp', 'alt' => 'کدام آیفون بخریم؛ مقایسه آیفون معمولی، Pro، Pro Max و Pro کارکرده' ],
			'checklist' => [ 'file' => 'which-iphone-checklist.webp', 'alt' => 'چک‌لیست خرید آیفون کارکرده در ۸ مرحله' ],
		],
	],
];

add_action( 'init', 'stlh_posts_apply', 40 );

function stlh_posts_apply(): void {
	$state = (array) get_option( 'stlh_posts_state', [] );
	$todo  = [];
	foreach ( STLH_POSTS as $slug => $def ) {
		$file = STLH_DIR . 'content/posts/' . $slug . '.html';
		$hash = md5( wp_json_encode( $def ) . ( is_readable( $file ) ? md5_file( $file ) : '' ) );
		if ( ( $state[ $slug ]['hash'] ?? '' ) !== $hash ) {
			$todo[ $slug ] = $hash;
		}
	}
	if ( ! $todo ) {
		return;
	}
	if ( ! add_option( 'stlh_posts_lock', time(), '', false ) ) {
		if ( time() - (int) get_option( 'stlh_posts_lock' ) < 120 ) {
			return;
		}
		update_option( 'stlh_posts_lock', time(), false );
	}
	@set_time_limit( 90 );
	foreach ( $todo as $slug => $hash ) {
		try {
			$state[ $slug ] = [ 'hash' => $hash, 'at' => gmdate( 'c' ) ] + stlh_post_upsert( $slug, STLH_POSTS[ $slug ], $state[ $slug ] ?? [] );
		} catch ( Throwable $e ) {
			$state[ $slug ] = [ 'hash' => '', 'at' => gmdate( 'c' ), 'error' => $e->getMessage() ];
		}
	}
	update_option( 'stlh_posts_state', $state, false );
	delete_option( 'stlh_posts_lock' );
}

/** فایلِ `assets/posts/<file>` در کتابخانه‌ی رسانه (یک بار) → شناسه */
function stlh_post_media( string $file, string $alt, int $parent ): int {
	$map = (array) get_option( 'stlh_post_media', [] );
	$src = STLH_DIR . 'assets/posts/' . $file;
	$key = $file . ':' . md5_file( $src );
	if ( ! empty( $map[ $key ] ) && get_post( (int) $map[ $key ] ) ) {
		update_post_meta( (int) $map[ $key ], '_wp_attachment_image_alt', $alt );
		return (int) $map[ $key ];
	}
	$up = wp_upload_bits( $file, null, (string) file_get_contents( $src ) );
	if ( ! empty( $up['error'] ) ) {
		throw new RuntimeException( 'upload ' . $file . ': ' . $up['error'] );
	}
	$type = wp_check_filetype( $up['file'] )['type'] ?: 'image/webp';
	$id   = wp_insert_attachment( [ 'post_mime_type' => $type, 'post_title' => $alt, 'post_status' => 'inherit' ], $up['file'], $parent, true );
	if ( is_wp_error( $id ) ) {
		throw new RuntimeException( 'attach ' . $file . ': ' . $id->get_error_message() );
	}
	require_once ABSPATH . 'wp-admin/includes/image.php';
	wp_update_attachment_metadata( $id, wp_generate_attachment_metadata( $id, $up['file'] ) );
	update_post_meta( $id, '_wp_attachment_image_alt', $alt );
	$map[ $key ] = $id;
	update_option( 'stlh_post_media', $map, false );
	return $id;
}

function stlh_post_upsert( string $slug, array $def, array $prev ): array {
	$file = STLH_DIR . 'content/posts/' . $slug . '.html';
	if ( ! is_readable( $file ) ) {
		throw new RuntimeException( 'missing content/posts/' . $slug . '.html' );
	}
	global $wpdb;
	if ( ! empty( $prev['id'] ) ) {
		$post = get_post( (int) $prev['id'] );
		// صاحب فروشگاه پاکش کرده (یا به زباله‌دان برده)؟ دوباره نمی‌سازیم
		if ( ! $post || 'trash' === $post->post_status ) {
			return [ 'id' => (int) $prev['id'], 'skipped' => 'deleted in admin', 'modified' => $prev['modified'] ?? '' ];
		}
	} elseif ( ! empty( $def['post_id'] ) ) {
		// مقاله‌ای که از قبل در سایت هست
		$post = get_post( (int) $def['post_id'] );
		if ( ! $post || 'post' !== $post->post_type || 'trash' === $post->post_status ) {
			throw new RuntimeException( 'post ' . $def['post_id'] . ' not found' );
		}
	} else {
		$found = $wpdb->get_var( $wpdb->prepare( "SELECT ID FROM {$wpdb->posts} WHERE post_name = %s AND post_type = 'post' AND post_status NOT IN ('trash','auto-draft','inherit') ORDER BY ID LIMIT 1", $slug ) );
		$post  = $found ? get_post( (int) $found ) : null;
	}
	// صاحب فروشگاه بعد از ما ویرایش کرده؟ دست نمی‌زنیم
	if ( $post && ! empty( $prev['modified'] ) && $post->post_modified_gmt !== $prev['modified'] ) {
		return [ 'id' => $post->ID, 'skipped' => 'edited in admin', 'modified' => $prev['modified'] ];
	}
	$id = $post ? $post->ID : wp_insert_post( [ 'post_type' => 'post', 'post_status' => 'draft', 'post_name' => $slug, 'post_title' => $def['title'] ], true );
	if ( is_wp_error( $id ) ) {
		throw new RuntimeException( $id->get_error_message() );
	}

	$html = (string) file_get_contents( $file );
	foreach ( $def['images'] as $k => $img ) {
		$aid  = stlh_post_media( $img['file'], $img['alt'], $id );
		$src  = (string) wp_get_attachment_image_url( $aid, 'large' );
		$html = str_replace(
			'{{img:' . $k . '}}',
			'<!-- wp:image {"id":' . $aid . ',"sizeSlug":"large","align":"center"} -->' . "\n"
				. '<figure class="wp-block-image aligncenter size-large"><img src="' . esc_url( $src ) . '" alt="' . esc_attr( $img['alt'] ) . '" class="wp-image-' . $aid . '"/></figure>' . "\n"
				. '<!-- /wp:image -->',
			$html
		);
	}
	$html = preg_replace_callback( '/\{\{cat:([^}]+)\}\}/u', static function ( $m ) {
		$t    = get_term_by( 'name', $m[1], 'product_cat' );
		$link = $t ? get_term_link( $t ) : '';
		return esc_url( is_string( $link ) && $link ? $link : ( function_exists( 'wc_get_page_permalink' ) ? wc_get_page_permalink( 'shop' ) : home_url( '/' ) ) );
	}, $html );

	$html = preg_replace_callback( '/\{\{post:(\d+)\}\}/', static fn( $m ) => esc_url( (string) get_permalink( (int) $m[1] ) ), $html );

	$cat = ! empty( $def['category']['id'] ) ? get_term( (int) $def['category']['id'], 'category' ) : get_term_by( 'slug', $def['category']['slug'], 'category' );
	$cat = $cat instanceof WP_Term ? $cat : null;
	if ( ! $cat && ! empty( $def['category']['slug'] ) ) {
		$made = wp_insert_term( $def['category']['name'], 'category', [ 'slug' => $def['category']['slug'] ] );
		$cat  = is_wp_error( $made ) ? null : get_term( (int) $made['term_id'], 'category' );
	}
	// درخواستِ بازدیدکننده‌ی ناشناس: kses بلوک‌ها و جدول را دست نبرد (متن از مخزنِ خودمان است)
	kses_remove_filters();
	$r = wp_update_post( wp_slash( [
		'ID'            => $id,
		'post_title'    => $def['title'],
		'post_name'     => ! empty( $def['keep_slug'] ) && $post ? $post->post_name : $slug,
		'post_content'  => $html,
		'post_excerpt'  => $def['excerpt'] ?? '',
		// پیش‌نویس فقط با تعریفِ مخزن منتشر می‌شود؛ وضعیتی که در پیشخوان داده شده (منتشر/زمان‌بندی) دست نمی‌خورد
		'post_status'   => $post && 'draft' !== $post->post_status ? $post->post_status : $def['status'],
		'post_category' => $cat ? [ (int) $cat->term_id ] : [],
	] ), true );
	if ( ! current_user_can( 'unfiltered_html' ) ) {
		kses_init_filters();
	}
	if ( is_wp_error( $r ) ) {
		throw new RuntimeException( $r->get_error_message() );
	}
	wp_set_post_tags( $id, $def['tags'], false );
	set_post_thumbnail( $id, stlh_post_media( $def['thumb']['file'], $def['thumb']['alt'], $id ) );
	update_post_meta( $id, '_yoast_wpseo_title', $def['seo']['title'] );
	update_post_meta( $id, '_yoast_wpseo_metadesc', $def['seo']['desc'] );
	update_post_meta( $id, '_yoast_wpseo_focuskw', $def['seo']['kw'] );
	clean_post_cache( $id );
	if ( function_exists( 'litespeed_purge_all' ) ) {
		litespeed_purge_all();
	} else {
		do_action( 'litespeed_purge_all' );
	}
	return [ 'id' => $id, 'status' => get_post_status( $id ), 'modified' => (string) get_post_field( 'post_modified_gmt', $id ) ];
}
