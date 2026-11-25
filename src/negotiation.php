<?php

namespace CiteSurge\Markdown;

function prefers_markdown( string $accept ): bool {
	$html = quality( $accept, 'text/html' );
	$markdown = quality( $accept, 'text/markdown' );

	return $markdown > 0 && $markdown > $html;
}

function quality( string $accept, string $type ): int {
	$quality = 0;
	$specificity = -1;
	foreach ( explode( ',', $accept ) as $preference ) {
		[ $media, $parameters ] = array_pad( explode( ';', $preference, 2 ), 2, '' );
		$weight = match ( strtolower( trim( $media ) ) ) {
			$type => 2,
			'text/*' => 1,
			'*/*' => 0,
			default => -1,
		};
		if ( $weight < 0 || $weight < $specificity ) {
			continue;
		}
		$value = preference_quality( $parameters );
		$quality = $weight > $specificity ? $value : max( $quality, $value );
		$specificity = $weight;
	}

	return $quality;
}

function preference_quality( string $parameters ): int {
	foreach ( explode( ';', $parameters ) as $parameter ) {
		[ $name, $value ] = array_pad( explode( '=', $parameter, 2 ), 2, '' );
		if ( 'q' !== strtolower( trim( $name ) ) ) {
			continue;
		}
		$value = trim( $value );
		if ( ! preg_match( '/^(?:0(?:\.[0-9]{0,3})?|1(?:\.0{0,3})?)$/D', $value ) ) {
			return 0;
		}

		return (int) round( 1000 * (float) $value );
	}

	return 1000;
}
