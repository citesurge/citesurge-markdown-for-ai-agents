<?php

namespace CiteSurge\Markdown;

use DOMDocument;
use DOMElement;
use DOMNode;
use DOMXPath;
use League\HTMLToMarkdown\HtmlConverter;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

const MAX_HTML_BYTES = 6 * 1024 * 1024;
const MAX_RENDER_UNITS = 64 * 1024 * 1024;

function to_markdown( string $html, string $charset ): ?string {
	if ( '' === $html || strlen( $html ) > MAX_HTML_BYTES ) {
		return null;
	}

	try {
		$utf8 = 'UTF-8' === strtoupper( $charset ) ? $html : @iconv( $charset, 'UTF-8', $html );
		if ( false === $utf8 || ! preg_match( '//u', $utf8 ) ) {
			return null;
		}
		$document = parse_document( $utf8 );
		if ( ! $document || ! within_render_budget( $document ) ) {
			return null;
		}
		return render_document( $document );
	} catch ( \Throwable $error ) {
		return null;
	}
}

function parse_document( string $html ): ?DOMDocument {
	$document = new DOMDocument( '1.0', 'UTF-8' );
	$previous = libxml_use_internal_errors( true );
	libxml_clear_errors();
	try {
		$loaded = $document->loadHTML( '<?xml encoding="UTF-8">' . $html, LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING );
		foreach ( libxml_get_errors() as $error ) {
			if ( LIBXML_ERR_FATAL === $error->level ) {
				return null;
			}
		}

		return $loaded ? $document : null;
	} finally {
		libxml_clear_errors();
		libxml_use_internal_errors( $previous );
	}
}

function within_render_budget( DOMNode $root ): bool {
	$pending = [ [ $root, 1, 0 ] ];
	$units = 0;
	while ( $pending ) {
		[ $node, $depth, $structure ] = array_pop( $pending );
		$structure += (int) in_array( $node->nodeName, [ 'blockquote', 'ul', 'ol' ], true );
		$size = XML_TEXT_NODE === $node->nodeType ? strlen( $node->nodeValue ) + 1 : 1;
		$units += $size * $depth * ( 1 + $structure );
		if ( $structure > 16 || $units > MAX_RENDER_UNITS ) {
			return false;
		}
		foreach ( $node->childNodes as $child ) {
			$pending[] = [ $child, $depth + 1, $structure ];
		}
	}

	return true;
}

function metadata( DOMXPath $xpath ): string {
	$fields = [];
	foreach ( [ 'title', 'description', 'image' ] as $key ) {
		$queries = [
			'/html/head/meta[@name="' . $key . '"]/@content',
			'/html/head/meta[@property="og:' . $key . '"]/@content',
			'/html/head/meta[@name="twitter:' . $key . '"]/@content',
		];
		if ( 'title' === $key ) {
			array_unshift( $queries, '/html/head/title' );
		}
		foreach ( $queries as $query ) {
			$value = (string) $xpath->evaluate( 'string(' . $query . ')' );
			$value = trim( preg_replace( '/[\p{Cc}\s]+/u', ' ', $value ) ?? '' );
			if ( '' !== $value ) {
				$fields[] = $key . ': ' . json_encode( $value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR );
				break;
			}
		}
	}

	return $fields ? "---\n" . implode( "\n", $fields ) . "\n---" : '';
}

function structured_data( DOMXPath $xpath ): array {
	$blocks = [];
	foreach ( $xpath->query( '//script[@type]' ) as $element ) {
		if ( 'application/ld+json' !== strtolower( trim( $element->getAttribute( 'type' ) ) ) ) {
			continue;
		}
		$value = trim( $element->textContent );
		if ( '' === $value ) {
			continue;
		}
		preg_match_all( '/`{3,}/', $value, $runs );
		$max = array_reduce( $runs[0], static fn( int $length, string $run ): int => max( $length, strlen( $run ) + 1 ), 3 );
		$fence = str_repeat( "`", $max );
		$blocks[] = $fence . "json\n" . $value . "\n" . $fence;
	}

	return $blocks;
}

function render_document( DOMDocument $document ): string {
	$xpath = new DOMXPath( $document );
	$frontmatter = metadata( $xpath );
	$blocks = structured_data( $xpath );
	$remove = '//nav|//script|//style|//noscript|//template|//svg|//iframe|//head'
		. '|//header[not(ancestor::main or ancestor::article or ancestor::section)]'
		. '|//footer[not(ancestor::main or ancestor::article or ancestor::section)]';
	foreach ( $xpath->query( $remove ) as $node ) {
		$node->parentNode?->removeChild( $node );
	}

	$body = $document->getElementsByTagName( 'body' )->item( 0 );
	$converter = new HtmlConverter( [ 'strip_tags' => true, 'header_style' => 'atx' ] );
	$markdown = $body instanceof DOMElement ? trim( $converter->convert( $document->saveHTML( $body ) ) ) : '';
	$sections = array_filter( [ $frontmatter, $markdown, ...$blocks ], static fn( string $part ): bool => '' !== $part );

	return $sections ? implode( "\n\n", $sections ) . "\n" : '';
}
