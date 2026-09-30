<?php
/**
 * Plugin Name:       StockLand Updater
 * Description:       افزونه‌های استوک لند را از گیت‌هاب به‌روز می‌کند — مثل آپدیت معمولی افزونه‌ها، بدون FTP.
 * Version:           1.0.1
 * Requires at least: 6.3
 * Requires PHP:      8.1
 * Text Domain:       stland-updater
 *
 * چرا این افزونه: هاست ایرانی اتصال FTP از بیرون ایران را رد می‌کند (531).
 * پس به‌جای اینکه گیت‌هاب به سایت بفرستد، سایت خودش از گیت‌هاب برمی‌دارد.
 * هر پوش روی main در Release «latest» یک manifest.json و zip هر افزونه
 * می‌گذارد؛ این افزونه manifest را می‌خواند و نسخه‌ی تازه‌تر را در همان
 * فهرست آپدیت‌های وردپرس نشان می‌دهد.
 *
 * ⛔ وردپرس فقط وقتی آپدیت می‌کند که نسخه بالاتر باشد. پس هر تغییری در
 *    یک افزونه یعنی بالا بردن Version آن — ورک‌فلو اگر فراموش شود قرمز
 *    می‌شود (scripts/verify-bump.php).
 */

defined( 'ABSPATH' ) || exit;

const STLU_REPO     = 'firouzayazi-source/stland-site';
// Overridable in wp-config.php only for testing against a local copy of the release.
defined( 'STLU_RELEASE' ) || define( 'STLU_RELEASE', 'https://github.com/' . STLU_REPO . '/releases/download/latest/' );
const STLU_OPT_AUTO = 'stlu_auto';
const STLU_CACHE    = 'stlu_manifest';

/**
 * manifest.json از Release — نیم ساعت در کش.
 *
 * @return array{sha:string,builtAt:string,plugins:array<string,array{version:string,zip:string}>}|null
 */
function stlu_manifest( bool $fresh = false ): ?array {
	if ( ! $fresh ) {
		$cached = get_site_transient( STLU_CACHE );
		if ( is_array( $cached ) ) {
			return $cached ?: null;
		}
	}
	$res  = wp_remote_get( STLU_RELEASE . 'manifest.json', [ 'timeout' => 15 ] );
	$code = is_wp_error( $res ) ? 0 : (int) wp_remote_retrieve_response_code( $res );
	$data = 200 === $code ? json_decode( (string) wp_remote_retrieve_body( $res ), true ) : null;
	$ok   = is_array( $data ) && isset( $data['plugins'] ) && is_array( $data['plugins'] );
	// شکست هم کش می‌شود (کوتاه‌تر) تا هر بار باز کردن پیشخوان منتظر گیت‌هاب نماند.
	set_site_transient( STLU_CACHE, $ok ? $data : [], $ok ? 30 * MINUTE_IN_SECONDS : 10 * MINUTE_IN_SECONDS );
	return $ok ? $data : null;
}

function stlu_file( string $slug ): string {
	return $slug . '/' . $slug . '.php';
}

function stlu_item( string $slug, array $info ): object {
	return (object) [
		'id'           => 'github.com/' . STLU_REPO . '/' . $slug,
		'slug'         => $slug,
		'plugin'       => stlu_file( $slug ),
		'new_version'  => (string) $info['version'],
		'url'          => 'https://github.com/' . STLU_REPO,
		'package'      => STLU_RELEASE . rawurlencode( (string) $info['zip'] ),
		'requires_php' => '8.1',
		'icons'        => [],
		'banners'      => [],
	];
}

/*
 * ─── آپدیت در فهرست معمولی وردپرس ───
 *
 * ⛔ روی «خواندنِ» فهرست سوار است، نه «نوشتنش». وردپرس فهرست را فقط وقتی
 *    می‌نویسد که api.wordpress.org جواب بدهد؛ از هاست ایرانی همیشه جواب
 *    نمی‌دهد و آن‌وقت آپدیتِ ما هم هیچ‌وقت دیده نمی‌شد. خواندن همیشه هست —
 *    صفحه‌ی افزونه‌ها، دکمه‌ی به‌روزرسانی و به‌روزرسانیِ خودکار همه از آن می‌خوانند.
 */
add_filter( 'site_transient_update_plugins', function ( $transient ) {
	$manifest = stlu_manifest();
	if ( ! $manifest ) {
		return $transient;
	}
	if ( ! is_object( $transient ) ) {
		$transient = (object) [ 'last_checked' => time(), 'checked' => [], 'response' => [], 'no_update' => [], 'translations' => [] ];
	}
	if ( ! function_exists( 'get_plugins' ) ) {
		require_once ABSPATH . 'wp-admin/includes/plugin.php';
	}
	$installed = get_plugins();
	foreach ( $manifest['plugins'] as $slug => $info ) {
		$file = stlu_file( (string) $slug );
		if ( ! isset( $installed[ $file ] ) ) {
			continue;
		}
		$item = stlu_item( (string) $slug, $info );
		unset( $transient->response[ $file ], $transient->no_update[ $file ] );
		if ( version_compare( $item->new_version, (string) $installed[ $file ]['Version'], '>' ) ) {
			$transient->response[ $file ] = $item;
		} else {
			$transient->no_update[ $file ] = $item;
		}
	}
	return $transient;
} );

// «مشاهده‌ی جزئیات» — بدون این، وردپرس از wordpress.org می‌پرسد و خطا می‌دهد.
add_filter( 'plugins_api', function ( $result, $action, $args ) {
	if ( 'plugin_information' !== $action || empty( $args->slug ) ) {
		return $result;
	}
	$manifest = stlu_manifest();
	if ( ! $manifest || ! isset( $manifest['plugins'][ $args->slug ] ) ) {
		return $result;
	}
	$info = $manifest['plugins'][ $args->slug ];
	return (object) [
		'name'          => $args->slug,
		'slug'          => $args->slug,
		'version'       => $info['version'],
		'author'        => 'StockLand',
		'homepage'      => 'https://github.com/' . STLU_REPO,
		'download_link' => STLU_RELEASE . rawurlencode( (string) $info['zip'] ),
		'requires_php'  => '8.1',
		'sections'      => [ 'description' => 'منتشرشده از کامیت ' . esc_html( substr( (string) $manifest['sha'], 0, 7 ) ) ],
	];
}, 10, 3 );

// به‌روزرسانی خودکار، اگر روشن باشد
add_filter( 'auto_update_plugin', function ( $update, $item ) {
	if ( '1' !== get_option( STLU_OPT_AUTO ) || empty( $item->slug ) ) {
		return $update;
	}
	$manifest = stlu_manifest();
	return ( $manifest && isset( $manifest['plugins'][ $item->slug ] ) ) ? true : $update;
}, 10, 2 );

// بعد از آپدیتِ افزونه‌های ما کش صفحه‌ها پاک شود، وگرنه مشتری نسخه‌ی قبلی را می‌بیند.
add_action( 'upgrader_process_complete', function ( $upgrader, array $extra ): void {
	if ( ( $extra['type'] ?? '' ) !== 'plugin' ) {
		return;
	}
	$ours = array_map( 'stlu_file', array_keys( ( stlu_manifest() ?? [ 'plugins' => [] ] )['plugins'] ) );
	if ( ! array_intersect( (array) ( $extra['plugins'] ?? [] ), $ours ) ) {
		return;
	}
	stlu_purge_caches();
}, 10, 2 );

function stlu_purge_caches(): void {
	wp_cache_flush();
	do_action( 'litespeed_purge_all' );
	if ( function_exists( 'rocket_clean_domain' ) ) {
		rocket_clean_domain();
	}
	if ( function_exists( 'w3tc_flush_all' ) ) {
		w3tc_flush_all();
	}
	if ( function_exists( 'wp_cache_clear_cache' ) ) {
		wp_cache_clear_cache();
	}
}

// ─── صفحه‌ی تنظیمات ───
add_action( 'admin_menu', function (): void {
	add_options_page( 'به‌روزرسانی استوک لند', 'به‌روزرسانی استوک لند', 'update_plugins', 'stland-updater', 'stlu_page' );
} );

add_filter( 'plugin_action_links_' . plugin_basename( __FILE__ ), function ( array $links ): array {
	array_unshift( $links, '<a href="' . esc_url( admin_url( 'options-general.php?page=stland-updater' ) ) . '">به‌روزرسانی</a>' );
	return $links;
} );

function stlu_action_url( string $do, array $extra = [] ): string {
	return wp_nonce_url( add_query_arg( [ 'action' => 'stlu', 'do' => $do ] + $extra, admin_url( 'admin-post.php' ) ), 'stlu_' . $do );
}

function stlu_back( string $msg, bool $ok = true ): never {
	set_transient( 'stlu_notice_' . get_current_user_id(), [ $msg, $ok ], 60 );
	wp_safe_redirect( admin_url( 'options-general.php?page=stland-updater' ) );
	exit;
}

/** آزمایش اتصال: هم manifest و هم دانلود zip (که به سرور دیگری ریدایرکت می‌شود) */
function stlu_probe(): array {
	$out   = [];
	$start = microtime( true );
	$res   = wp_remote_get( STLU_RELEASE . 'manifest.json', [ 'timeout' => 15 ] );
	$out[] = [ 'manifest.json', is_wp_error( $res ) ? $res->get_error_message() : (string) wp_remote_retrieve_response_code( $res ), round( microtime( true ) - $start, 1 ) ];
	$data  = is_wp_error( $res ) ? null : json_decode( (string) wp_remote_retrieve_body( $res ), true );
	$zip   = is_array( $data ) && ! empty( $data['plugins'] ) ? (string) reset( $data['plugins'] )['zip'] : '';
	if ( '' !== $zip ) {
		$start = microtime( true );
		$res   = wp_remote_get( STLU_RELEASE . rawurlencode( $zip ), [ 'timeout' => 30, 'limit_response_size' => 1024 * 1024 ] );
		$out[] = [ $zip, is_wp_error( $res ) ? $res->get_error_message() : (string) wp_remote_retrieve_response_code( $res ), round( microtime( true ) - $start, 1 ) ];
	}
	return $out;
}

add_action( 'admin_post_stlu', function (): void {
	$do = sanitize_key( (string) ( $_GET['do'] ?? '' ) );
	if ( ! current_user_can( 'update_plugins' ) ) {
		wp_die( 'اجازه ندارید.' );
	}
	check_admin_referer( 'stlu_' . $do );

	switch ( $do ) {
		case 'check':
			stlu_manifest( true );
			stlu_back( stlu_manifest() ? 'فهرست از گیت‌هاب خوانده شد.' : 'گیت‌هاب جواب نداد — «آزمایش اتصال» را بزنید.', (bool) stlu_manifest() );

		case 'test':
			set_transient( 'stlu_probe_' . get_current_user_id(), stlu_probe(), 300 );
			stlu_back( 'آزمایش انجام شد — نتیجه پایین صفحه است.' );

		case 'auto':
			update_option( STLU_OPT_AUTO, '1' === get_option( STLU_OPT_AUTO ) ? '' : '1' );
			stlu_back( '1' === get_option( STLU_OPT_AUTO ) ? 'به‌روزرسانی خودکار روشن شد.' : 'به‌روزرسانی خودکار خاموش شد.' );

		case 'install':
			$slug     = sanitize_key( (string) ( $_GET['slug'] ?? '' ) );
			$manifest = stlu_manifest( true );
			if ( ! $manifest || ! isset( $manifest['plugins'][ $slug ] ) ) {
				stlu_back( 'این افزونه در فهرست گیت‌هاب نیست.', false );
			}
			require_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
			$upgrader = new Plugin_Upgrader( new Automatic_Upgrader_Skin() );
			$result   = $upgrader->install( stlu_item( $slug, $manifest['plugins'][ $slug ] )->package );
			if ( true !== $result ) {
				$msg = is_wp_error( $result ) ? $result->get_error_message() : implode( ' ', (array) $upgrader->skin->get_upgrade_messages() );
				stlu_back( 'نصب نشد: ' . $msg, false );
			}
			stlu_back( 'نصب شد. از صفحه‌ی افزونه‌ها فعالش کنید.' );
	}
	stlu_back( 'کار ناشناخته.', false );
} );

function stlu_page(): void {
	if ( ! function_exists( 'get_plugins' ) ) {
		require_once ABSPATH . 'wp-admin/includes/plugin.php';
	}
	$uid      = get_current_user_id();
	$notice   = get_transient( 'stlu_notice_' . $uid );
	$probe    = get_transient( 'stlu_probe_' . $uid );
	$manifest = stlu_manifest();
	$plugins  = get_plugins();
	$auto     = '1' === get_option( STLU_OPT_AUTO );
	delete_transient( 'stlu_notice_' . $uid );
	?>
	<div class="wrap" dir="rtl">
		<h1>به‌روزرسانی استوک لند</h1>
		<?php if ( $notice ) : ?>
			<div class="notice notice-<?php echo $notice[1] ? 'success' : 'error'; ?>"><p><?php echo esc_html( $notice[0] ); ?></p></div>
		<?php endif; ?>

		<p>
			افزونه‌های استوک لند از
			<a href="<?php echo esc_url( 'https://github.com/' . STLU_REPO ); ?>" target="_blank" rel="noopener">گیت‌هاب</a>
			خوانده می‌شوند. نسخه‌ی تازه در صفحه‌ی «افزونه‌ها» و «به‌روزرسانی‌ها» مثل بقیه دیده می‌شود.
			<?php if ( $manifest ) : ?>
				<br>آخرین انتشار: کامیت <code><?php echo esc_html( substr( (string) $manifest['sha'], 0, 7 ) ); ?></code>
			<?php endif; ?>
		</p>

		<p style="display:flex;flex-wrap:wrap;gap:8px">
			<a class="button button-primary" href="<?php echo esc_url( stlu_action_url( 'check' ) ); ?>">بررسی همین حالا</a>
			<a class="button" href="<?php echo esc_url( stlu_action_url( 'test' ) ); ?>">آزمایش اتصال به گیت‌هاب</a>
			<a class="button" href="<?php echo esc_url( stlu_action_url( 'auto' ) ); ?>">
				به‌روزرسانی خودکار: <?php echo $auto ? 'روشن — خاموش کن' : 'خاموش — روشن کن'; ?>
			</a>
		</p>

		<?php if ( ! $manifest ) : ?>
			<div class="notice notice-warning inline"><p>فهرست از گیت‌هاب خوانده نشد. «آزمایش اتصال» را بزنید.</p></div>
		<?php else : ?>
			<table class="widefat striped" style="max-width:640px">
				<thead><tr><th>افزونه</th><th>نصب‌شده</th><th>گیت‌هاب</th><th></th></tr></thead>
				<tbody>
				<?php foreach ( $manifest['plugins'] as $slug => $info ) :
					$file      = stlu_file( (string) $slug );
					$installed = $plugins[ $file ]['Version'] ?? null;
					?>
					<tr>
						<td><?php echo esc_html( $plugins[ $file ]['Name'] ?? $slug ); ?></td>
						<td><?php echo esc_html( $installed ?? '—' ); ?></td>
						<td><?php echo esc_html( (string) $info['version'] ); ?></td>
						<td>
							<?php if ( null === $installed ) : ?>
								<a class="button button-small" href="<?php echo esc_url( stlu_action_url( 'install', [ 'slug' => $slug ] ) ); ?>">نصب</a>
							<?php elseif ( version_compare( (string) $info['version'], $installed, '>' ) ) : ?>
								<a class="button button-small button-primary" href="<?php echo esc_url( wp_nonce_url( self_admin_url( 'update.php?action=upgrade-plugin&plugin=' . rawurlencode( $file ) ), 'upgrade-plugin_' . $file ) ); ?>">به‌روزرسانی</a>
							<?php else : ?>
								به‌روز است
							<?php endif; ?>
						</td>
					</tr>
				<?php endforeach; ?>
				</tbody>
			</table>
		<?php endif; ?>

		<?php if ( $probe ) : ?>
			<h2>نتیجه‌ی آزمایش اتصال</h2>
			<table class="widefat striped" style="max-width:640px">
				<thead><tr><th>فایل</th><th>پاسخ</th><th>ثانیه</th></tr></thead>
				<tbody>
				<?php foreach ( $probe as [ $name, $status, $secs ] ) : ?>
					<tr>
						<td><code><?php echo esc_html( $name ); ?></code></td>
						<td><?php echo '200' === $status ? '✅ ۲۰۰' : '❌ ' . esc_html( $status ); ?></td>
						<td><?php echo esc_html( (string) $secs ); ?></td>
					</tr>
				<?php endforeach; ?>
				</tbody>
			</table>
			<p>هر دو ردیف باید ✅ باشند. اگر ❌ است، هاست اجازه‌ی اتصال به گیت‌هاب را نمی‌دهد.</p>
		<?php endif; ?>
	</div>
	<?php
}
