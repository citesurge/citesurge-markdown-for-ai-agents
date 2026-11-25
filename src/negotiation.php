<?php

namespace CiteSurge\Markdown;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function prefers_markdown( string $accept ): bool {
	$qualities = [ 'text/markdown' => -1, 'text/html' => -1, 'text/*' => -1, '*/*' => -1 ];
	foreach ( explode( ',', $accept ) as $preference ) {
		[ $media, $parameters ] = array_pad( explode( ';', $preference, 2 ), 2, '' );
		$media = strtolower( trim( $media ) );
		if ( ! array_key_exists( $media, $qualities ) ) {
			continue;
		}
		$value = preference_quality( $parameters );
		if ( null !== $value ) {
			$qualities[ $media ] = max( $qualities[ $media ], $value );
		}
	}

	$html = 0;
	foreach ( [ 'text/html', 'text/*', '*/*' ] as $range ) {
		if ( $qualities[ $range ] >= 0 ) {
			$html = $qualities[ $range ];
			break;
		}
	}

	return $qualities['text/markdown'] > $html;
}

function preference_quality( string $parameters ): ?int {
	$quality = null;
	foreach ( explode( ';', $parameters ) as $parameter ) {
		[ $name, $value ] = array_pad( explode( '=', $parameter, 2 ), 2, '' );
		if ( 'q' !== strtolower( trim( $name ) ) ) {
			continue;
		}
		$value = trim( $value );
		if ( ! preg_match( '/^(?:0(?:\.[0-9]{0,3})?|1(?:\.0{0,3})?)$/D', $value ) ) {
			return null;
		}
		$quality ??= (int) round( 1000 * (float) $value );
	}

	return $quality ?? 1000;
}
