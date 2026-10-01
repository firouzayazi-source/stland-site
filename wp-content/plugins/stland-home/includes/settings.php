<?php
defined( 'ABSPATH' ) || exit;

add_action( 'admin_menu', function (): void {
	add_menu_page( 'صفحه اصلی استوک لند', 'صفحه اصلی', 'manage_options', 'stland-home', 'stlh_settings_page', 'dashicons-admin-home', 56 );
} );

add_action( 'admin_init', function (): void {
	register_setting( 'stlh', STLH_OPT, [
		'type'              => 'array',
		'sanitize_callback' => 'stlh_sanitize',
		'default'           => stlh_defaults(),
	] );
} );

add_action( 'admin_enqueue_scripts', function ( string $hook ): void {
	if ( 'toplevel_page_stland-home' !== $hook ) {
		return;
	}
	wp_enqueue_media();
	wp_enqueue_script( 'stlh-admin', STLH_URL . 'assets/js/admin.js', [ 'jquery', 'jquery-ui-sortable', 'jquery-touch-punch' ], STLH_VER, true );
} );

add_action( 'admin_post_stlh_font', function (): void {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( 'forbidden', 403 );
	}
	check_admin_referer( 'stlh_font' );
	$ok = stlh_download_font();
	wp_safe_redirect( add_query_arg( 'stlh_font', $ok ? 'ok' : 'fail', admin_url( 'admin.php?page=stland-home' ) ) );
	exit;
} );

function stlh_row_check( string $label, string $key, string $val, string $text, string $help = '' ): void {
	printf(
		'<tr><th scope="row">%s</th><td><label><input type="checkbox" name="%s" value="1" %s> %s</label>%s</td></tr>',
		esc_html( $label ), esc_attr( STLH_OPT . '[' . $key . ']' ), checked( $val, '1', false ), esc_html( $text ), stlh_help( $help )
	);
}

/** گزارش هر ردیف: دسته پیدا شد؟ چند محصول قابل نمایش دارد؟ */
function stlh_rows_report( string $rows ): string {
	if ( ! stlh_woo() ) {
		return '';
	}
	$out = '';
	foreach ( stlh_lines( $rows ) as $row ) {
		[ $cats, $limit, $title, , $models ] = array_pad( array_map( 'trim', explode( '|', $row ) ), 5, '' );
		$refs  = array_filter( array_map( 'trim', explode( ',', $cats ) ) );
		$count = count( stlh_row_items( $refs, (int) $limit ?: 8, $models ) );
		$out  .= '<li style="font-weight:600;margin-top:6px">ردیف «' . esc_html( $title ?: $cats ) . '»' . ( $models ? ' — مدل ' . esc_html( $models ) : '' ) . ': ' . stlh_fa( $count ) . ' محصول نمایش داده می‌شود</li>';
		foreach ( array_filter( array_map( 'trim', explode( ',', $cats ) ) ) as $ref ) {
			$t = stlh_resolve_cat( $ref );
			if ( ! $t ) {
				$out .= '<li style="color:#b32d2e">✖ دسته «' . esc_html( $ref ) . '» پیدا نشد — نامک را از جدول زیر کپی کنید.</li>';
				continue;
			}
			$visible = count( stlh_query_products( [ $t->slug ], 24 ) );
			$color   = $visible ? '#008a20' : '#b32d2e';
			$hint    = ( ! $visible && $t->count ) ? ' — محصولات این دسته منتشر نشده‌اند، «پنهان از فروشگاه» هستند یا ناموجودند و ووکامرس ناموجودها را مخفی می‌کند.' : ( $t->count ? '' : ' — هیچ محصولی در این دسته نیست.' );
			$out    .= '<li style="color:' . $color . '">' . ( $visible ? '✔' : '✖' ) . ' ' . esc_html( $t->name ) . ' — ' . stlh_fa( $visible ) . ' محصول قابل نمایش' . esc_html( $hint ) . '</li>';
		}
	}
	$terms = get_terms( [ 'taxonomy' => 'product_cat', 'hide_empty' => false ] );
	$table = '';
	if ( ! is_wp_error( $terms ) ) {
		foreach ( $terms as $t ) {
			$table .= '<tr><td>' . esc_html( $t->name ) . '</td><td><code style="user-select:all">' . esc_html( rawurldecode( $t->slug ) ) . '</code></td><td>' . stlh_fa( $t->count ) . '</td></tr>';
		}
	}
	return '<ul style="margin:0 0 10px">' . $out . '</ul>'
		. '<details><summary style="cursor:pointer">نامک همه دسته‌ها (برای کپی)</summary><table class="widefat striped" style="max-width:600px;margin-top:8px"><thead><tr><th>نام</th><th>نامک</th><th>تعداد</th></tr></thead><tbody>' . $table . '</tbody></table></details>';
}

function stlh_sanitize( mixed $in ): array {
	$in  = is_array( $in ) ? $in : [];
	$out = [];
	foreach ( stlh_defaults() as $k => $def ) {
		$v         = $in[ $k ] ?? $def;
		$out[ $k ] = match ( true ) {
			in_array( $k, [ 'banner_desktop', 'banner_mobile', 'flash_product', 'posts_count', 'group_limit', 'recent_span' ], true ) => absint( $v ),
			'banner_link' === $k                                                                         => esc_url_raw( (string) $v ),
			in_array( $k, [ 'categories', 'product_rows', 'faq', 'address', 'trust', 'card_badges' ], true ) => sanitize_textarea_field( (string) $v ),
			'order' === $k                                                                               => implode( "\n", array_values( array_intersect( array_unique( stlh_lines( sanitize_textarea_field( (string) $v ) ) ), array_keys( stlh_sections() ) ) ) ),
			in_array( $k, [ 'font_enable', 'font_sitewide', 'takeover' ], true )                                     => empty( $in[ $k ] ) ? '' : '1',
			'cat_style' === $k                                                                           => 'photo' === $v ? 'photo' : 'icon',
			in_array( $k, [ 'cat_source', 'rows_source' ], true )                                        => 'manual' === $v ? 'manual' : 'auto',
			'hide_cats' === $k                                                                           => array_values( array_filter( array_map( 'absint', (array) $v ) ) ),
			'accent' === $k                                                                              => sanitize_hex_color( (string) $v ) ?: '#007bff',
			'flash_mode' === $k                                                                          => in_array( $v, [ 'featured', 'manual', 'off' ], true ) ? $v : 'featured',
			default                                                                                      => sanitize_text_field( (string) $v ),
		};
	}
	if ( '' === $out['order'] ) {
		$out['order'] = stlh_defaults()['order']; // خالی یعنی اشتباه، نه «هیچ بخشی»
	}
	$out['posts_count'] = min( 12, $out['posts_count'] );
	$out['group_limit'] = max( 1, min( 24, $out['group_limit'] ) );
	$out['recent_span'] = max( 1, min( 20, $out['recent_span'] ?: 5 ) );
	return $out;
}

function stlh_help( string $help ): string {
	return $help ? '<p class="description">' . esc_html( $help ) . '</p>' : '';
}

function stlh_row_text( string $label, string $key, string $val, string $help = '', string $type = 'text' ): void {
	printf(
		'<tr><th scope="row">%s</th><td><input type="%s" class="regular-text" name="%s" value="%s" dir="auto">%s</td></tr>',
		esc_html( $label ), esc_attr( $type ), esc_attr( STLH_OPT . '[' . $key . ']' ), esc_attr( $val ), stlh_help( $help )
	);
}

function stlh_row_radio( string $label, string $key, string $val, array $choices, string $help = '' ): void {
	$html = '';
	foreach ( $choices as $v => $text ) {
		$html .= sprintf( '<label style="display:block;margin-bottom:4px"><input type="radio" name="%s" value="%s" %s> %s</label>', esc_attr( STLH_OPT . '[' . $key . ']' ), esc_attr( $v ), checked( $val, $v, false ), esc_html( $text ) );
	}
	printf( '<tr><th scope="row">%s</th><td>%s%s</td></tr>', esc_html( $label ), $html, stlh_help( $help ) );
}

/**
 * ترتیبِ بخش‌ها با کشیدن و رها کردن، و روشن/خاموشِ هر بخش با تیک.
 * صاحب فروشگاه: «نامک‌ها نوشتاری نباشند… بتوانم دراگ‌اند‌دراپ کنم… که خطای
 * املایی نداشته باشم.» مقدارِ واقعی همان `order` است (یک کلید در هر خط) که
 * اسکریپت از روی فهرست می‌سازد.
 */
function stlh_row_sections( string $order ): void {
	$on    = stlh_lines( $order );
	$all   = stlh_sections();
	$keys  = array_merge( array_values( array_intersect( $on, array_keys( $all ) ) ), array_values( array_diff( array_keys( $all ), $on ) ) );
	$items = '';
	foreach ( $keys as $k ) {
		$items .= sprintf(
			'<li class="stlh-sec" data-key="%1$s"><span class="stlh-grip" aria-hidden="true">☰</span><label><input type="checkbox" %2$s> %3$s</label></li>',
			esc_attr( $k ), checked( in_array( $k, $on, true ), true, false ), esc_html( $all[ $k ] )
		);
	}
	printf(
		'<tr><th scope="row">ترتیب بخش‌ها</th><td><ul class="stlh-sections" data-target="%s">%s</ul><input type="hidden" name="%s" value="%s">%s</td></tr>',
		esc_attr( STLH_OPT . '-order' ), $items, esc_attr( STLH_OPT . '[order]' ), esc_attr( $order ),
		stlh_help( 'بخش‌ها را با کشیدن جابه‌جا کنید؛ تیک را بردارید تا آن بخش در صفحه نیاید. بعد «ذخیره تغییرات».' )
	);
}

/** همه‌ی دسته‌های ووکامرس به ترتیبِ درخت (menu_order)، با عمق */
function stlh_term_rows(): array {
	$all = get_terms( [ 'taxonomy' => 'product_cat', 'hide_empty' => false, 'menu_order' => 'ASC' ] );
	if ( is_wp_error( $all ) ) {
		return [];
	}
	$kids = [];
	foreach ( $all as $t ) {
		$kids[ $t->parent ][] = $t;
	}
	$out  = [];
	$walk = static function ( int $parent, int $depth ) use ( &$walk, &$out, $kids ): void {
		foreach ( $kids[ $parent ] ?? [] as $t ) {
			$out[] = [ $t, $depth, ! empty( $kids[ $t->term_id ] ) ];
			$walk( $t->term_id, $depth + 1 );
		}
	};
	$walk( 0, 0 );
	return $out;
}

/** انتخابِ دسته از فهرست، به‌جای تایپِ نامک */
function stlh_row_cat_select( string $label, string $key, string $val, string $help = '' ): void {
	$cur  = stlh_resolve_cat( $val );
	$opts = '<option value="">— هیچ (بخش پنهان) —</option>';
	foreach ( stlh_term_rows() as [ $t, $depth ] ) {
		$slug  = rawurldecode( $t->slug );
		$opts .= sprintf(
			'<option value="%s" %s>%s%s (%s)</option>',
			esc_attr( $slug ), selected( $cur && $cur->term_id === $t->term_id, true, false ),
			str_repeat( '— ', $depth ), esc_html( $t->name ), esc_html( stlh_fa( (int) $t->count ) )
		);
	}
	printf(
		'<tr><th scope="row">%s</th><td><select name="%s">%s</select>%s</td></tr>',
		esc_html( $label ), esc_attr( STLH_OPT . '[' . $key . ']' ), $opts, stlh_help( $help )
	);
}

/**
 * درختِ دسته‌ها همان‌طور که صفحه‌ی اصلی می‌بیند — با تیکِ «پنهان».
 * ترتیب و نام از حسابداری می‌آید («دسته‌بندی‌ها» ← کشیدن)؛ اینجا فقط دیده و
 * اگر لازم شد پنهان می‌شود.
 */
function stlh_row_tree( array $hidden ): void {
	$default = (int) get_option( 'default_product_cat' );
	$leaves  = array_map( static fn( WP_Term $t ) => $t->term_id, stlh_leaf_categories( false ) );
	$rows    = '';
	foreach ( stlh_term_rows() as [ $t, $depth, $has_kids ] ) {
		$is_leaf = in_array( $t->term_id, $leaves, true );
		$mark    = $t->term_id === $default ? ' <em style="color:#777">(پیش‌فرضِ ووکامرس — نمایش داده نمی‌شود)</em>' : '';
		$box     = '';
		if ( $is_leaf || in_array( $t->term_id, $hidden, true ) ) {
			$box = sprintf(
				' <label style="margin-inline-start:8px;color:#b32d2e"><input type="checkbox" name="%s[]" value="%d" %s> پنهان در صفحه اصلی</label>',
				esc_attr( STLH_OPT . '[hide_cats]' ), $t->term_id, checked( in_array( $t->term_id, $hidden, true ), true, false )
			);
		}
		$rows .= sprintf(
			'<li style="padding-inline-start:%.1frem;margin:2px 0">%s <b%s>%s</b> <span style="color:#777">%s محصول</span>%s%s</li>',
			$depth * 1.4, $has_kids ? '📂' : '🏷️', $is_leaf ? '' : ' style="font-weight:800"', esc_html( $t->name ),
			esc_html( stlh_fa( (int) $t->count ) ), $mark, $box
		);
	}
	printf(
		'<tr><th scope="row">درختِ دسته‌ها</th><td><ul style="margin:0">%s</ul>%s</td></tr>',
		$rows,
		stlh_help( 'کارت‌ها و ردیف‌های صفحه‌ی اصلی خودکار از «برگ»های این درخت ساخته می‌شوند، به همین ترتیب. نام، جایگاه و ترتیب را در حسابداری (کالاها ← دسته‌بندی‌ها) عوض کنید؛ همان لحظه اینجا هم عوض می‌شود.' )
	);
}

function stlh_row_textarea( string $label, string $key, string $val, string $help = '', int $rows = 6 ): void {
	printf(
		'<tr><th scope="row">%s</th><td><textarea class="large-text" rows="%d" name="%s" dir="auto">%s</textarea>%s</td></tr>',
		esc_html( $label ), $rows, esc_attr( STLH_OPT . '[' . $key . ']' ), esc_textarea( $val ), stlh_help( $help )
	);
}

function stlh_row_media( string $label, string $key, int $id, string $help = '' ): void {
	$img = $id ? wp_get_attachment_image( $id, 'medium', false, [ 'style' => 'max-width:320px;height:auto;border-radius:8px' ] ) : '';
	printf(
		'<tr><th scope="row">%s</th><td><div class="stlh-media"><input type="hidden" name="%s" value="%d"><div class="stlh-prev">%s</div><p><button type="button" class="button stlh-pick">انتخاب تصویر</button> <button type="button" class="button-link-delete stlh-clear">حذف</button></p>%s</div></td></tr>',
		esc_html( $label ), esc_attr( STLH_OPT . '[' . $key . ']' ), $id, $img, stlh_help( $help )
	);
}

function stlh_settings_page(): void {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}
	$o        = wp_parse_args( (array) get_option( STLH_OPT, [] ), stlh_defaults() );
	$products = function_exists( 'wc_get_products' )
		? wc_get_products( [ 'status' => 'publish', 'limit' => 300, 'orderby' => 'date', 'order' => 'DESC' ] )
		: [];
	?>
	<div class="wrap">
		<style>
			.stlh-sections{margin:0;max-width:420px}
			.stlh-sec{display:flex;align-items:center;gap:10px;background:#fff;border:1px solid #dcdcde;border-radius:8px;padding:10px 12px;margin:0 0 6px;}
			.stlh-sec .stlh-grip{cursor:grab;font-size:18px;color:#787c82;touch-action:none;padding:0 4px}
			.stlh-sec.ui-sortable-helper{box-shadow:0 6px 18px rgba(0,0,0,.12)}
			.stlh-sec label{flex:1}
		</style>
		<h1>صفحه اصلی استوک لند</h1>
		<p>کل صفحه با شورت‌کد <code>[stl_home]</code> نمایش داده می‌شود (در ویجت «کد کوتاه» المنتور). هر بخش جدا هم شورت‌کد دارد:
			<code>[stl_banner]</code> <code>[stl_categories]</code> <code>[stl_products category="iphone" limit="6" title="آیفون‌ها"]</code>
			<code>[stl_flash_deal]</code> <code>[stl_social]</code> <code>[stl_faq]</code> <code>[stl_posts]</code></p>

		<?php
		$font_msg = sanitize_key( $_GET['stlh_font'] ?? '' );
		if ( 'ok' === $font_msg ) {
			echo '<div class="notice notice-success is-dismissible"><p>فونت وزیرمتن دانلود و فعال شد.</p></div>';
		} elseif ( 'fail' === $font_msg ) {
			[ $fp ] = stlh_font_path();
			echo '<div class="notice notice-error"><p>دانلود فونت از سرور ممکن نشد (احتمالاً دسترسی خارجی هاست بسته است). فایل <code>Vazirmatn[wght].woff2</code> را از گیت‌هاب rastikerdar/vazirmatn بگیرید و با نام <code>Vazirmatn-wght.woff2</code> در این مسیر آپلود کنید:<br><code>' . esc_html( $fp ) . '</code></p></div>';
		}
		?>

		<h2>ظاهر</h2>
		<table class="form-table" role="presentation">
			<tr><th scope="row">فونت وزیرمتن</th><td>
				<?php if ( stlh_font_ready() ) : ?>
					<span style="color:#008a20">✔ فایل فونت روی هاست موجود است.</span>
				<?php else : ?>
					<span style="color:#b32d2e">فایل فونت هنوز روی هاست نیست.</span>
				<?php endif; ?>
				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="display:inline-block;margin-inline-start:10px">
					<input type="hidden" name="action" value="stlh_font">
					<?php wp_nonce_field( 'stlh_font' ); ?>
					<button class="button"><?php echo stlh_font_ready() ? 'دانلود دوباره' : 'دانلود و نصب فونت'; ?></button>
				</form>
			</td></tr>
		</table>

		<form method="post" action="options.php">
			<?php settings_fields( 'stlh' ); ?>

			<table class="form-table" role="presentation">
				<tr><th scope="row">رنگ تأکیدی</th><td>
					<input type="color" name="<?php echo esc_attr( STLH_OPT ); ?>[accent]" value="<?php echo esc_attr( (string) $o['accent'] ); ?>">
					<p class="description">فقط یک رنگ برای لینک‌ها، آیکن‌ها و حالت‌های فعال. بقیه صفحه مشکی و خنثی است.</p>
				</td></tr>
				<?php
				stlh_row_check( 'نمایش خودکار در صفحه اصلی', 'takeover', (string) $o['takeover'], 'صفحه اصلی سایت را این افزونه می‌سازد', 'روشن: هدر و فوتر از قالب، محتوای وسط از این افزونه؛ نیازی به المنتور نیست. خاموش: فقط جایی که شورت‌کد [stl_home] گذاشته شود.' );
				stlh_row_check( 'استفاده از وزیرمتن', 'font_enable', (string) $o['font_enable'], 'در بخش‌های صفحه اصلی' );
				stlh_row_sections( (string) $o['order'] );
				stlh_row_check( 'وزیرمتن در کل سایت', 'font_sitewide', (string) $o['font_sitewide'], 'فونت همه صفحات سایت هم وزیرمتن شود', 'پیش‌فرض خاموش؛ قبل از روشن کردن، صفحات محصول و سبد خرید را چک کنید.' );
				?>
			</table>

			<h2>بنر و عنوان صفحه</h2>
			<table class="form-table" role="presentation">
				<?php
				stlh_row_media( 'بنر دسکتاپ', 'banner_desktop', (int) $o['banner_desktop'], 'پیشنهاد: 1920×600 با فرمت WebP' );
				stlh_row_media( 'بنر موبایل', 'banner_mobile', (int) $o['banner_mobile'], 'اختیاری. پیشنهاد: 800×800 یا 800×1000. اگر خالی باشد بنر دسکتاپ نمایش داده می‌شود.' );
				stlh_row_text( 'لینک بنر', 'banner_link', (string) $o['banner_link'], 'اختیاری', 'url' );
				stlh_row_text( 'متن جایگزین بنر (alt)', 'banner_alt', (string) $o['banner_alt'] );
				stlh_row_text( 'عنوان H1 صفحه', 'h1', (string) $o['h1'], 'برای سئو؛ دیده نمی‌شود. اگر قالب خودش H1 دارد خالی بگذارید.' );
				?>
			</table>

			<h2>نوار اعتماد</h2>
			<table class="form-table" role="presentation">
				<?php stlh_row_textarea( 'آیتم‌ها', 'trust', (string) $o['trust'], 'هر خط: آیکن|عنوان|توضیح — آیکن‌ها: shield, clock, card, store, truck, check. خالی = مخفی', 5 ); ?>
			</table>

			<h2>دسته‌بندی‌ها</h2>
			<table class="form-table" role="presentation">
				<?php
				stlh_row_text( 'عنوان', 'cat_title', (string) $o['cat_title'] );
				stlh_row_text( 'زیرعنوان', 'cat_subtitle', (string) $o['cat_subtitle'] );
				stlh_row_tree( array_map( 'intval', (array) $o['hide_cats'] ) );
				?>
				<tr><th scope="row">نمایش</th><td>
					<label><input type="radio" name="<?php echo esc_attr( STLH_OPT ); ?>[cat_style]" value="icon" <?php checked( $o['cat_style'], 'icon' ); ?>> آیکن</label>&nbsp;&nbsp;
					<label><input type="radio" name="<?php echo esc_attr( STLH_OPT ); ?>[cat_style]" value="photo" <?php checked( $o['cat_style'], 'photo' ); ?>> عکس بندانگشتی دسته (اگر نداشت، آیکن)</label>
				</td></tr>
			</table>

			<h2>ردیف‌های محصول</h2>
			<table class="form-table" role="presentation">
				<?php
				stlh_row_text( 'نسل‌های تازه در ردیفِ اول', 'recent_span', (string) $o['recent_span'], 'وقتی گوشی‌های یک دسته از چند نسل‌اند، ردیفِ اول فقط این تعداد نسلِ آخر را نشان می‌دهد (نسبت به جدیدترین گوشیِ موجود) و بقیه در ردیفِ «مدل‌های قدیمی‌تر» می‌آیند.', 'number' );
				stlh_row_cat_select( 'دسته‌ی پرفروش‌ها', 'best_cat', (string) $o['best_cat'], 'بخشِ «پرفروش‌ها» محصولاتِ همین دسته را نشان می‌دهد. در حسابداری، از پنلِ انتشارِ هر کالا این دسته را کنارِ دسته‌ی اصلی‌اش بزنید.' );
				stlh_row_textarea( 'نشان‌های کارت', 'card_badges', (string) $o['card_badges'], 'هر خط: کلید ویژگی یا متای محصول|برچسب|پسوند — مثال: battery|باتری|٪ . حداکثر ۲ نشان روی هر کارت؛ اگر محصول آن مقدار را نداشته باشد نمایش داده نمی‌شود.', 3 );
				?>
			</table>

			<h2>بخش لوازم جانبی (هر زیردسته یک ردیف)</h2>
			<table class="form-table" role="presentation">
				<?php
				stlh_row_cat_select( 'دسته‌ی مادر', 'group_parent', (string) $o['group_parent'], 'زیردسته‌های این دسته (دارای محصول) هر کدام یک ردیف می‌شوند، به همان ترتیبِ درخت.' );
				stlh_row_text( 'تعداد محصول هر ردیف', 'group_limit', (string) $o['group_limit'], '', 'number' );
				stlh_row_text( 'عنوان', 'group_title', (string) $o['group_title'] );
				stlh_row_text( 'زیرعنوان', 'group_subtitle', (string) $o['group_subtitle'] );
				?>
			</table>

			<h2>پیشنهاد ویژه</h2>
			<table class="form-table" role="presentation">
				<tr><th scope="row">حالت</th><td>
					<label><input type="radio" name="<?php echo esc_attr( STLH_OPT ); ?>[flash_mode]" value="featured" <?php checked( $o['flash_mode'], 'featured' ); ?>> خودکار: آخرین محصول «ویژه» (ستاره در ووکامرس)</label><br>
					<label><input type="radio" name="<?php echo esc_attr( STLH_OPT ); ?>[flash_mode]" value="manual" <?php checked( $o['flash_mode'], 'manual' ); ?>> دستی: محصول انتخاب‌شده</label><br>
					<label><input type="radio" name="<?php echo esc_attr( STLH_OPT ); ?>[flash_mode]" value="off" <?php checked( $o['flash_mode'], 'off' ); ?>> خاموش</label>
				</td></tr>
				<tr><th scope="row">محصول (حالت دستی)</th><td>
					<select name="<?php echo esc_attr( STLH_OPT ); ?>[flash_product]" style="max-width:100%">
						<option value="0">—</option>
						<?php foreach ( $products as $p ) : ?>
							<option value="<?php echo (int) $p->get_id(); ?>" <?php selected( (int) $o['flash_product'], $p->get_id() ); ?>><?php echo esc_html( $p->get_name() ); ?></option>
						<?php endforeach; ?>
					</select>
				</td></tr>
				<?php stlh_row_text( 'متن نشان', 'flash_badge', (string) $o['flash_badge'] ); ?>
			</table>

			<h2>شبکه‌های اجتماعی و تماس</h2>
			<table class="form-table" role="presentation">
				<?php
				stlh_row_text( 'آیدی اینستاگرام', 'instagram', (string) $o['instagram'], 'بدون @' );
				stlh_row_text( 'آیدی ربات تلگرام', 'telegram_bot', (string) $o['telegram_bot'], 'بدون @' );
				stlh_row_text( 'شماره تماس', 'phone', (string) $o['phone'], 'با ارقام انگلیسی' );
				stlh_row_textarea( 'آدرس فروشگاه', 'address', (string) $o['address'], '', 2 );
				stlh_row_text( 'شهر', 'city', (string) $o['city'], 'برای گوگل (جستجوی محلی و نقشه). آدرس، تلفن و شهر به‌صورت «فروشگاه موبایل» به گوگل معرفی می‌شوند.' );
				?>
			</table>

			<h2>سوالات متداول</h2>
			<table class="form-table" role="presentation">
				<?php stlh_row_textarea( 'سوال‌ها', 'faq', (string) $o['faq'], 'خط اول: سوال، خطوط بعد: جواب. بین سوال‌ها یک خط خالی بگذارید.', 14 ); ?>
			</table>

			<h2>مقالات</h2>
			<table class="form-table" role="presentation">
				<?php stlh_row_text( 'تعداد مقالات', 'posts_count', (string) $o['posts_count'], '۰ یعنی مخفی', 'number' ); ?>
			</table>

			<details style="margin:18px 0">
				<summary style="cursor:pointer;font-weight:600">پیشرفته: فهرستِ دستیِ دسته‌ها و ردیف‌ها (معمولاً لازم نیست)</summary>
				<p class="description">پیش‌فرض «خودکار» است: همه‌چیز از درختِ دسته‌ها و ترتیبِ حسابداری می‌آید. فقط اگر صفحه‌ای با ترکیبِ خاص می‌خواهید، این‌ها را روی «فهرست» بگذارید.</p>
				<table class="form-table" role="presentation">
					<?php
					stlh_row_radio( 'کارت‌های دسته', 'cat_source', (string) $o['cat_source'], [ 'auto' => 'خودکار از درخت (پیشنهادی)', 'manual' => 'فقط فهرستِ زیر' ] );
					stlh_row_textarea( 'نامک دسته‌ها', 'categories', (string) $o['categories'], 'هر خط: نامک یا نامک|آیکن.', 4 );
					stlh_row_radio( 'ردیف‌های محصول', 'rows_source', (string) $o['rows_source'], [ 'auto' => 'خودکار از درخت (پیشنهادی)', 'manual' => 'فقط ردیف‌های زیر' ] );
					stlh_row_textarea( 'ردیف‌ها', 'product_rows', (string) $o['product_rows'], 'هر خط: دسته|تعداد|عنوان|زیرعنوان|بازه مدل', 4 );
					echo '<tr><th scope="row">وضعیت ردیف‌ها</th><td>' . stlh_rows_report( (string) $o['product_rows'] ) . '</td></tr>';
					?>
				</table>
			</details>

			<?php submit_button( 'ذخیره تغییرات' ); ?>
		</form>
	</div>
	<?php
}
