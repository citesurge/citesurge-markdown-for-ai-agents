<?php

define( 'ABSPATH', __DIR__ );

class WP_Post {}
function home_url(): string {
	return $GLOBALS['fixture_home'];
}
function get_permalink( WP_Post $post ): string {
	return $GLOBALS['fixture_permalink'];
}
function untrailingslashit( string $value ): string {
	return rtrim( $value, '/' );
}
function trailingslashit( string $value ): string {
	return untrailingslashit( $value ) . '/';
}

require __DIR__ . '/../src/routes.php';

use function CiteSurge\Markdown\alternate;

foreach ( [ 'https://example.test', 'https://example.test/blog' ] as $home ) {
	$GLOBALS['fixture_home'] = $home;
	$GLOBALS['fixture_permalink'] = $home . '/';
	if ( alternate( new WP_Post() ) !== $home . '/.md' ) {
		throw new RuntimeException( 'The static homepage alias must remain within the site root.' );
	}
	$GLOBALS['fixture_permalink'] = $home . '/article/';
	if ( alternate( new WP_Post() ) !== $home . '/article.md' ) {
		throw new RuntimeException( 'The post alias must stay within its permalink directory.' );
	}
}

echo "Root and subdirectory alias assertions passed.\n";
