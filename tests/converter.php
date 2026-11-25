<?php

require __DIR__ . '/../vendor/autoload.php';
require __DIR__ . '/../src/converter.php';

use function CiteSurge\Markdown\to_markdown;

$html = '<!doctype html><html><head><title>Example</title>'
	. '<meta property="og:description" content="Other"><meta name="description" content="Better">'
	. '<meta property="og:image" content="https://example.test/img.jpg">'
	. '<script type="application/ld+json">{"name":"Example"}</script></head>'
	. '<body><header>Global header</header><nav>Menu</nav><main><article>'
	. '<header><h1>Article title</h1></header><p>Public page.</p>'
	. '<footer>Section notes</footer></article></main><footer>Global footer</footer></body></html>';

$markdown = to_markdown( $html, 'UTF-8' );
foreach ( [ 'title: "Example"', 'description: "Better"', '# Article title', 'Public page.', 'Section notes', '{"name":"Example"}' ] as $text ) {
	if ( null === $markdown || ! str_contains( $markdown, $text ) ) {
		throw new RuntimeException( 'Missing Markdown: ' . $text );
	}
}
foreach ( [ 'Global header', 'Global footer', 'Menu', 'Other' ] as $text ) {
	if ( str_contains( $markdown, $text ) ) {
		throw new RuntimeException( 'Unexpected Markdown: ' . $text );
	}
}
$nested = str_repeat( '<blockquote>', 17 ) . 'Content' . str_repeat( '</blockquote>', 17 );
if ( null !== to_markdown( $nested, 'UTF-8' ) || null !== to_markdown( "<p>\xFF</p>", 'UTF-8' ) ||
	null !== to_markdown( str_repeat( 'a', 6 * 1024 * 1024 + 1 ), 'UTF-8' ) ) {
	throw new RuntimeException( 'An over-budget or invalid document was converted.' );
}
if ( ! str_contains( to_markdown( "<p>Caf\xE9</p>", 'ISO-8859-1' ) ?? '', 'Café' ) ) {
	throw new RuntimeException( 'Declared encoding was not honored.' );
}

echo "Converter assertions passed.\n";
