<?php
/**
 * php scripts/verify-bump.php old-manifest.json new-manifest.json
 *
 * WordPress only updates a plugin when its version goes UP. A plugin whose
 * files changed but whose Version did not would be published and silently
 * never reach the site. Fail loudly instead.
 */
[ , $oldPath, $newPath ] = $argv + [ null, '', '' ];
$old = json_decode( (string) @file_get_contents( $oldPath ), true )['plugins'] ?? [];
$new = json_decode( (string) file_get_contents( $newPath ), true )['plugins'] ?? [];

$fail = false;
foreach ( $new as $slug => $n ) {
	$o = $old[ $slug ] ?? null;
	if ( ! $o || ! isset( $o['hash'] ) ) {
		echo "$slug: new ({$n['version']})\n";
		continue;
	}
	if ( $o['hash'] === $n['hash'] ) {
		echo "$slug: unchanged ({$n['version']})\n";
		continue;
	}
	if ( version_compare( $n['version'], $o['version'], '>' ) ) {
		echo "$slug: {$o['version']} -> {$n['version']}\n";
		continue;
	}
	echo "::error title=Version not bumped::$slug changed but Version is still {$n['version']} (published: {$o['version']}). The site will never receive it. Bump the plugin header Version" . ( 'stland-home' === $slug ? ' and STLH_VER' : '' ) . ".\n";
	$fail = true;
}
exit( $fail ? 1 : 0 );
