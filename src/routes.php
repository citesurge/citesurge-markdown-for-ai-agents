<?php

namespace CiteSurge\Markdown;

use WP_Post;

function register_hooks(): void {
	$routes = new Routes();
	add_filter( 'request', [ $routes, 'map_alias' ] );
	add_filter( 'redirect_canonical', [ $routes, 'canonical_redirect' ] );
	add_action( 'template_redirect', [ $routes, 'serve_post' ], 11 );
	add_filter( 'template_include', [ $routes, 'serve_index' ], PHP_INT_MAX );
	add_action( 'wp_head', [ $routes, 'advertise_alternate' ] );
}

function public_post( ?WP_Post $post ): bool {
	return $post && in_array( $post->post_type, [ 'page', 'post' ], true )
		&& 'publish' === $post->post_status && '' === $post->post_password;
}

function pretty_urls(): bool {
	return '' !== (string) get_option( 'permalink_structure' );
}

function request_path(): ?string {
	$path = wp_parse_url( wp_unslash( $_SERVER['REQUEST_URI'] ?? '' ), PHP_URL_PATH );
	$home = rtrim( (string) wp_parse_url( home_url(), PHP_URL_PATH ), '/' );
	if ( ! is_string( $path ) || ! str_starts_with( $path, $home . '/' ) || preg_match( '/[\x00-\x20\x7f]/', $path ) ) {
		return null;
	}

	return substr( $path, strlen( $home ) );
}

function safe_method(): bool {
	return in_array( wp_unslash( $_SERVER['REQUEST_METHOD'] ?? '' ), [ 'GET', 'HEAD' ], true );
}

function alternate( WP_Post $post ): string {
	$url = get_permalink( $post );
	return is_string( $url ) && ! str_contains( $url, '?' ) ? untrailingslashit( $url ) . '.md' : '';
}

function disable_page_cache(): void {
	if ( ! defined( 'DONOTCACHEPAGE' ) ) {
		define( 'DONOTCACHEPAGE', true );
	}
	do_action( 'litespeed_control_set_nocache', 'CiteSurge Markdown response' );
}

function response_header( string $name ): ?string {
	foreach ( headers_list() as $header ) {
		if ( 0 === stripos( $header, $name . ':' ) ) {
			return trim( substr( $header, strlen( $name ) + 1 ) );
		}
	}

	return null;
}

function emit_uncacheable( string $type ): void {
	header( 'Content-Type: ' . $type . '; charset=utf-8' );
	header( 'Cache-Control: private, no-store, no-cache, must-revalidate, max-age=0' );
	header( 'Vary: Accept', false );
	header_remove( 'Content-Length' );
	header_remove( 'ETag' );
}

final class Routes {
	private ?int $alias_id = null;
	private bool $gave_up = false;

	public function map_alias( array $query ): array {
		$path = request_path();
		if ( ! pretty_urls() || ! safe_method() || null === $path || ! str_ends_with( $path, '.md' ) ) {
			return $query;
		}

		$post_id = url_to_postid( home_url( substr( $path, 0, -3 ) ) );
		$post = $post_id ? get_post( $post_id ) : null;
		if ( ! public_post( $post ) ) {
			return $query;
		}

		$this->alias_id = $post->ID;
		return 'page' === $post->post_type ? [ 'page_id' => $post->ID ] : [ 'p' => $post->ID ];
	}

	public function canonical_redirect( $redirect ) {
		if ( null !== $this->alias_id || ( '/llms.txt' === request_path() && is_404() ) ) {
			return false;
		}

		return $redirect;
	}

	public function advertise_alternate(): void {
		$post = $this->eligible_post();
		$url = $post && pretty_urls() ? alternate( $post ) : '';
		if ( '' !== $url ) {
			printf( '<link rel="alternate" type="text/markdown" href="%s" />' . "\n", esc_url( $url ) );
		}
	}

	public function serve_post(): void {
		$post = $this->eligible_post();
		if ( ! $post || headers_sent() ) {
			return;
		}

		header( 'Vary: Accept', false );
		$url = pretty_urls() ? alternate( $post ) : '';
		if ( '' !== $url ) {
			header( 'Link: <' . esc_url_raw( $url ) . '>; rel="alternate"; type="text/markdown"', false );
		}
		$accept = sanitize_text_field( wp_unslash( $_SERVER['HTTP_ACCEPT'] ?? '' ) );
		if ( null === $this->alias_id && ! prefers_markdown( $accept ) ) {
			return;
		}

		disable_page_cache();
		ob_start( [ $this, 'convert_response' ], MAX_HTML_BYTES + 1 );
	}

	public function convert_response( string $html, int $phase ): string {
		if ( ! ( $phase & PHP_OUTPUT_HANDLER_FINAL ) ) {
			$this->gave_up = true;
			return $html;
		}

		$content_type = response_header( 'Content-Type' ) ?? 'text/html';
		[ $media, $parameters ] = array_pad( explode( ';', $content_type, 2 ), 2, '' );
		if ( $this->gave_up || headers_sent() || 200 !== http_response_code()
			|| 'text/html' !== strtolower( trim( $media ) ) || response_header( 'Location' ) ) {
			return $html;
		}
		preg_match( '/(?:^|;)\s*charset\s*=\s*["\']?([a-z0-9_-]+)/i', $parameters, $charset );
		$markdown = to_markdown( $html, $charset[1] ?? 'UTF-8' );
		if ( null === $markdown ) {
			return $html;
		}

		emit_uncacheable( 'text/markdown' );
		if ( null !== $this->alias_id ) {
			header( 'X-Robots-Tag: noindex', false );
		}

		return $markdown;
	}

	private function eligible_post(): ?WP_Post {
		if ( ! safe_method() || ! is_singular( [ 'post', 'page' ] ) || is_preview() || is_feed() || is_embed() ) {
			return null;
		}
		$post = get_queried_object();

		return $post instanceof WP_Post && public_post( $post ) ? $post : null;
	}

	public function serve_index( string $template ): string {
		if ( ! pretty_urls() || ! safe_method() || '/llms.txt' !== request_path() || ! is_404()
			|| headers_sent() || $template !== get_404_template() || is_file( ABSPATH . 'llms.txt' )
			|| ! apply_filters( 'citesurge_markdown_llms_enabled', true ) ) {
			return $template;
		}

		disable_page_cache();
		status_header( 200 );
		emit_uncacheable( 'text/plain' );
		if ( 'GET' === wp_unslash( $_SERVER['REQUEST_METHOD'] ?? '' ) ) {
			output_index();
		}

		exit;
	}
}

function output_index(): void {
	echo '# ' . esc_html( wp_strip_all_tags( get_bloginfo( 'name' ), true ) ) . "\n\n";
	echo "## Pages and posts\n\n";
	$page = 1;
	do {
		$posts = get_posts( [
			'post_type' => [ 'post', 'page' ],
			'post_status' => 'publish',
			'has_password' => false,
			'orderby' => 'ID',
			'order' => 'ASC',
			'posts_per_page' => 200,
			'paged' => $page++,
			'ignore_sticky_posts' => true,
		] );
		foreach ( $posts as $post ) {
			$url = alternate( $post );
			if ( '' !== $url ) {
				$title = str_replace( [ '[', ']' ], [ '\\[', '\\]' ], wp_strip_all_tags( get_the_title( $post ), true ) );
				printf( "- [%s](<%s>)\n", esc_html( $title ), esc_url_raw( $url ) );
			}
		}
	} while ( 200 === count( $posts ) );
}
