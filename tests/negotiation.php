<?php

define( 'ABSPATH', __DIR__ );

require __DIR__ . '/../src/negotiation.php';

use function CiteSurge\Markdown\prefers_markdown;

$cases = [
	'' => false,
	'*/*' => false,
	'text/*' => false,
	'text/*,text/html;q=0.5' => false,
	'text/html;q=invalid,*/*;q=1,text/markdown;q=0.8' => false,
	'text/markdown;q=1;q=invalid,text/html;q=0.5' => false,
	'text/markdown;q=0.8;q=0.6,text/html;q=0.7' => true,
	'text/html' => false,
	'text/markdown' => true,
	'TEXT/MARKDOWN; Q=0.8,text/html;q=0.3' => true,
	'text/html;q=0.8,text/markdown;q=0.8' => false,
	'text/markdown;q=0,*/*;q=1' => false,
	'text/markdown;q=0.9,text/*;q=0.4,*/*;q=1' => true,
	'text/markdown;q=0.4,text/html;q=0.8' => false,
	'text/markdown;q=2,text/html;q=0' => false,
	'text/markdown;q=0.0000' => false,
];

foreach ( $cases as $accept => $expected ) {
	if ( prefers_markdown( $accept ) !== $expected ) {
		throw new RuntimeException( 'Unexpected representation for Accept: ' . $accept );
	}
}

echo 'Negotiation: ' . count( $cases ) . " assertions passed.\n";
