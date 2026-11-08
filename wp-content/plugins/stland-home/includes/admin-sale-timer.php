<?php
/**
 * پیشخوان ← محصولات ← «تایمرِ تخفیف»: گذاشتن یا برداشتنِ تایمر برای همه‌ی کالاهای تخفیف‌دار با هم (یا تیک‌خورده‌ها).
 * مدت از فهرست انتخاب می‌شود (۱ روز، ۴۸ ساعت، ۳ روز، یک هفته…) یا عددِ دلخواه — چیزی تایپ نمی‌شود که بشود انتخابش کرد.
 * فقط تاریخِ پایانِ تخفیف (`date_on_sale_to`) نوشته می‌شود؛ قیمت دست نمی‌خورد. توضیح در `sale-timer.php`.
 */
defined( 'ABSPATH' ) || exit;

const STLH_TIMER_PRESETS = [ 24 => '۱ روز (۲۴ ساعت)', 48 => '۲ روز (۴۸ ساعت)', 72 => '۳ روز', 120 => '۵ روز', 168 => 'یک هفته', 336 => 'دو هفته' ];

/** همه‌ی محصولاتِ اصلیِ در تخفیف (ساده یا متغیر) */
function stlh_timer_sale_products(): array {
	$out = [];
	foreach ( wc_get_product_ids_on_sale() as $id ) {
		$p = wc_get_product( $id );
		if ( ! $p ) {
			continue;
		}
		$main         = $p->get_parent_id() ?: $p->get_id();
		$out[ $main ] = $main === $p->get_id() ? $p : wc_get_product( $main );
	}
	return array_filter( $out );
}

/** تاریخِ پایان را روی محصول (و همه‌ی گونه‌های در تخفیفِ محصولِ متغیر) می‌گذارد؛ ۰ = برداشتنِ تایمر */
function stlh_timer_apply( WC_Product $p, int $end ): void {
	$targets = $p->is_type( 'variable' ) ? array_filter( array_map( 'wc_get_product', $p->get_children() ) ) : [ $p ];
	foreach ( $targets as $t ) {
		if ( ! $t->is_on_sale( 'edit' ) ) {
			continue;
		}
		$t->set_date_on_sale_to( $end ?: null );
		if ( $end ) {
			$from = $t->get_date_on_sale_from( 'edit' );
			if ( $from && $from->getTimestamp() > time() ) {
				$t->set_date_on_sale_from( null ); // تخفیفی که هنوز شروع نشده با تایمر از همین حالا شروع می‌شود
			}
		}
		$t->save();
	}
	delete_transient( 'stlh_sale_timers' );
}

/**
 * تایمر روی صفحه‌ی اصلی، همه‌ی دسته‌ها و صفحه‌ی هر محصول است؛ ذخیره‌ی محصول در درخواستِ زمان‌بندی‌شده صفحه‌ی کش‌شده‌ی LiteSpeed را پاک
 * نکرد (۱۷ آبان: ۲۷ کالا تایمر گرفتند ولی صفحه‌ی محصول از کش بی تایمر می‌آمد). پس بعد از هر تغییرِ گروهی کلِ کشِ صفحه پاک می‌شود.
 */
function stlh_timer_purge(): void {
	if ( function_exists( 'stlh_cache_flush' ) ) {
		stlh_cache_flush();
	}
	do_action( 'litespeed_purge_all' );
	if ( function_exists( 'stlh_pc_purge' ) ) {
		stlh_pc_purge();
	}
}

/** «۱ روز و ۳ ساعت مانده» */
function stlh_timer_left( int $end ): string {
	$s = $end - time();
	if ( $s <= 0 ) {
		return 'تمام شد';
	}
	$d = intdiv( $s, DAY_IN_SECONDS );
	$h = intdiv( $s % DAY_IN_SECONDS, HOUR_IN_SECONDS );
	$m = intdiv( $s % HOUR_IN_SECONDS, MINUTE_IN_SECONDS );
	$bits = array_filter( [ $d ? $d . ' روز' : '', $h ? $h . ' ساعت' : '', ( ! $d && $m ) ? $m . ' دقیقه' : '' ] );
	return stlh_fa( implode( ' و ', $bits ) ?: 'کمتر از یک دقیقه' ) . ' مانده';
}

add_action( 'admin_menu', static function (): void {
	add_submenu_page( 'edit.php?post_type=product', 'تایمرِ تخفیف', 'تایمرِ تخفیف', 'manage_woocommerce', 'stlh-sale-timer', 'stlh_timer_page' );
} );

add_action( 'admin_post_stlh_sale_display', static function (): void {
	if ( ! current_user_can( 'manage_woocommerce' ) ) {
		wp_die( 'دسترسی ندارید.' );
	}
	check_admin_referer( 'stlh_sale_display' );
	update_option( 'stlh_sale_display', 'amount' === ( $_POST['mode'] ?? '' ) ? 'amount' : 'percent', false );
	stlh_timer_purge();
	wp_safe_redirect( add_query_arg( [ 'page' => 'stlh-sale-timer', 'shown' => 1 ], admin_url( 'edit.php?post_type=product' ) ) );
	exit;
} );

add_action( 'admin_post_stlh_sale_timer', static function (): void {
	if ( ! current_user_can( 'manage_woocommerce' ) ) {
		wp_die( 'دسترسی ندارید.' );
	}
	check_admin_referer( 'stlh_sale_timer' );
	$ids   = array_map( 'intval', (array) ( $_POST['ids'] ?? [] ) );
	$clear = isset( $_POST['clear'] );
	$hours = (int) ( $_POST['preset'] ?? 48 );
	if ( 0 === $hours ) { // دلخواه
		$n     = max( 1, min( 999, (int) ( $_POST['custom_n'] ?? 1 ) ) );
		$hours = 'd' === ( $_POST['custom_u'] ?? 'h' ) ? $n * 24 : $n;
	}
	$hours = max( 1, min( 24 * 90, $hours ) );
	$end   = $clear ? 0 : time() + $hours * HOUR_IN_SECONDS;
	$done  = 0;
	foreach ( $ids as $id ) {
		$p = wc_get_product( $id );
		if ( $p ) {
			stlh_timer_apply( $p, $end );
			++$done;
		}
	}
	stlh_timer_purge();
	wp_safe_redirect( add_query_arg( [ 'page' => 'stlh-sale-timer', 'done' => $done, 'cleared' => (int) $clear ], admin_url( 'edit.php?post_type=product' ) ) );
	exit;
} );

function stlh_timer_page(): void {
	$items = stlh_timer_sale_products();
	?>
	<div class="wrap stlh-tmw">
		<style>
			.stlh-tmw{max-width:980px}.stlh-tmw h1{font-weight:800}
			.stlh-tmw .card{max-width:none;border-radius:14px;padding:18px 20px;margin:16px 0;border:1px solid #e6e6ea;box-shadow:none}
			.stlh-tmw .bar{display:flex;flex-wrap:wrap;gap:10px;align-items:center}
			.stlh-tmw .bar select,.stlh-tmw .bar input[type=number]{min-height:36px;border-radius:8px}
			.stlh-tmw .go{background:linear-gradient(120deg,#ff3d5a,#ff8a3d)!important;border:0!important;color:#fff!important;border-radius:10px!important;padding:2px 18px!important;min-height:38px;font-weight:700}
			.stlh-tmw table{border-radius:12px;overflow:hidden}.stlh-tmw td,.stlh-tmw th{vertical-align:middle}
			.stlh-tmw .on{display:inline-block;background:#fff1ee;color:#d6323f;border-radius:999px;padding:2px 10px;font-weight:700}
			.stlh-tmw .off{color:#8a8a92}.stlh-tmw del{color:#9a9aa2}.stlh-tmw .hint{color:#646970;margin:6px 0 0}
		</style>
		<h1>تایمرِ تخفیف</h1>
		<p class="hint">روی کالاهای تخفیف‌دار شمارشِ معکوس «تا پایانِ تخفیف» نشان داده می‌شود و وقتی تمام شد، قیمت خودکار به قیمتِ اصلی برمی‌گردد.
			برای یک کالای تنها، کادرِ «زمان‌بندی» در ویرایشِ همان محصول هم کار می‌کند؛ Novo هم می‌تواند همین تاریخ را بفرستد.</p>
		<?php if ( isset( $_GET['done'] ) ) : ?>
			<div class="notice notice-success"><p><?php echo esc_html( stlh_fa( (int) $_GET['done'] ) . ( ! empty( $_GET['cleared'] ) ? ' کالا بی‌تایمر شد.' : ' کالا تایمر گرفت.' ) ); ?></p></div>
		<?php endif; ?>
		<?php if ( isset( $_GET['shown'] ) ) : ?><div class="notice notice-success"><p>نمایشِ تخفیف ذخیره شد.</p></div><?php endif; ?>
		<form class="card" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<?php wp_nonce_field( 'stlh_sale_display' ); ?>
			<input type="hidden" name="action" value="stlh_sale_display">
			<div class="bar">
				<strong>روی کارت‌ها، کنارِ قیمت نشان بده:</strong>
				<?php $mode = get_option( 'stlh_sale_display', 'percent' ); ?>
				<label><input type="radio" name="mode" value="percent" <?php checked( 'amount' !== $mode ); ?>> درصدِ تخفیف (مثلاً «۶٪»)</label>
				<label><input type="radio" name="mode" value="amount" <?php checked( 'amount' === $mode ); ?>> مبلغِ تخفیف (مثلاً «۱۰٫۱ میلیون»)</label>
				<button class="button" type="submit">ذخیره</button>
			</div>
		</form>
		<?php if ( ! $items ) : ?>
			<div class="card"><p>الان هیچ کالایی در تخفیف نیست. اول در Novo یا ویرایشِ محصول قیمتِ ویژه بگذارید.</p></div>
		<?php else : ?>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<?php wp_nonce_field( 'stlh_sale_timer' ); ?>
			<input type="hidden" name="action" value="stlh_sale_timer">
			<div class="card">
				<div class="bar">
					<strong>مدتِ تایمر از همین حالا:</strong>
					<select name="preset" id="stlh-tm-preset">
						<?php foreach ( STLH_TIMER_PRESETS as $h => $label ) : ?>
							<option value="<?php echo (int) $h; ?>" <?php selected( 48, $h ); ?>><?php echo esc_html( $label ); ?></option>
						<?php endforeach; ?>
						<option value="0">دلخواه…</option>
					</select>
					<span id="stlh-tm-custom" hidden>
						<input type="number" name="custom_n" min="1" max="999" value="12" style="width:80px">
						<select name="custom_u"><option value="h">ساعت</option><option value="d">روز</option></select>
					</span>
					<button class="button go" type="submit">گذاشتنِ تایمر برای تیک‌خورده‌ها</button>
					<button class="button" type="submit" name="clear" value="1" onclick="return confirm('تایمرِ کالاهای تیک‌خورده برداشته شود؟ تخفیفشان بی‌پایان می‌ماند.')">برداشتنِ تایمر</button>
				</div>
			</div>
			<table class="widefat striped">
				<thead><tr><th style="width:32px"><input type="checkbox" checked onclick="document.querySelectorAll('.stlh-tm-id').forEach(c=>c.checked=this.checked)"></th><th>کالا</th><th>قیمت</th><th>تایمر</th></tr></thead>
				<tbody>
				<?php foreach ( $items as $id => $p ) : $end = stlh_sale_end( $p ); ?>
					<tr>
						<td><input class="stlh-tm-id" type="checkbox" name="ids[]" value="<?php echo (int) $id; ?>" checked></td>
						<td><a href="<?php echo esc_url( get_edit_post_link( $id ) ); ?>"><?php echo esc_html( $p->get_name() ); ?></a><?php echo $p->is_in_stock() ? '' : ' <span class="off">(ناموجود)</span>'; ?></td>
						<td><?php echo wp_kses_post( $p->get_price_html() ); ?></td>
						<td><?php echo $end ? '<span class="on">⏱ ' . esc_html( stlh_timer_left( $end ) ) . '</span>' : '<span class="off">بی‌تایمر</span>'; ?></td>
					</tr>
				<?php endforeach; ?>
				</tbody>
			</table>
		</form>
		<script>document.getElementById('stlh-tm-preset').addEventListener('change',function(){document.getElementById('stlh-tm-custom').hidden=this.value!=='0'})</script>
		<?php endif; ?>
	</div>
	<?php
}

/*
 * یک بار: ۴۸ ساعت برای همه‌ی کالاهای تخفیف‌دارِ امروز (صاحب فروشگاه، ۱۷ آبان ۱۴۰۵: «برای همه‌شون یه تایمر ۴۸ ساعته»).
 * فقط کالایی که هنوز تایمر ندارد؛ فهرست و زمان در `stlh_sale_timer_seed` می‌ماند. `add_option` قفل است تا دو درخواستِ هم‌زمان دو بار ننویسند.
 */
add_action( 'init', static function (): void {
	// فقط در درخواستِ زمان‌بندی‌شده یا پیشخوان — نه وسطِ بازدیدِ یک مشتری (۲۷ ذخیره‌ی محصول چند ثانیه طول می‌کشد)
	if ( ! ( wp_doing_cron() || ( is_admin() && ! wp_doing_ajax() ) ) || false !== get_option( 'stlh_sale_timer_seed', false ) || ! function_exists( 'wc_get_product_ids_on_sale' ) ) {
		return;
	}
	if ( ! add_option( 'stlh_sale_timer_seed', [ 'at' => gmdate( 'c' ), 'state' => 'running' ], '', false ) ) {
		return;
	}
	$end  = time() + 48 * HOUR_IN_SECONDS;
	$done = [];
	foreach ( stlh_timer_sale_products() as $id => $p ) {
		if ( ! stlh_sale_end( $p ) ) {
			stlh_timer_apply( $p, $end );
			$done[] = $id;
		}
	}
	update_option( 'stlh_sale_timer_seed', [ 'at' => gmdate( 'c' ), 'end' => gmdate( 'c', $end ), 'ids' => $done ], false );
	stlh_timer_purge();
}, 60 );
