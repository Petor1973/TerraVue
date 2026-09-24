<?php
/**
 * The governments' own maps of their advice (UK, NL), served from this site.
 *
 * The browser never contacts gov.uk or nederlandwereldwijd.nl (no IP addresses to third
 * parties): the server downloads a map once into uploads/travel-risk-maps/ and the app shows
 * that copy. Only https URLs on Sources::MAP_HOSTS, only raster images or PDF (no SVG, which
 * could carry scripts), at most MAX_BYTES. A new URL (map updated) replaces the old file.
 */

namespace TravelRisk;

defined( 'ABSPATH' ) || exit;

class Maps {

	const DIR       = 'travel-risk-maps';
	const MAX_BYTES = 8388608; // 8 MB
	const TYPES     = array( 'image/png' => 'png', 'image/jpeg' => 'jpg', 'image/gif' => 'gif', 'image/webp' => 'webp', 'application/pdf' => 'pdf' );

	/**
	 * @param array{url:string,type:string} $map From the advice.
	 * @return array{url:string,type:string}|\WP_Error Local URL.
	 */
	public static function local( array $map, string $source, string $iso ) {
		$url  = (string) ( $map['url'] ?? '' );
		$host = strtolower( (string) wp_parse_url( $url, PHP_URL_HOST ) );
		$ok   = 'https' === wp_parse_url( $url, PHP_URL_SCHEME ) && array_filter( Sources::MAP_HOSTS, fn( $h ) => $host === $h || str_ends_with( $host, '.' . $h ) );
		if ( ! $ok ) {
			return new \WP_Error( 'no_map', 'no_map', array( 'status' => 404 ) );
		}
		$uploads = wp_upload_dir();
		if ( ! empty( $uploads['error'] ) ) {
			return new \WP_Error( 'map_storage', 'map_storage', array( 'status' => 500 ) );
		}
		$dir    = trailingslashit( $uploads['basedir'] ) . self::DIR;
		$prefix = sanitize_key( $source ) . '-' . strtolower( preg_replace( '/[^A-Za-z]/', '', $iso ) ) . '-' . substr( md5( $url ), 0, 12 );
		foreach ( self::TYPES as $ext ) {
			if ( file_exists( "$dir/$prefix.$ext" ) ) {
				return self::result( $uploads, "$prefix.$ext" );
			}
		}

		$res = wp_safe_remote_get( $url, array(
			'timeout'             => 20,
			'limit_response_size' => self::MAX_BYTES,
			'user-agent'          => 'TravelRisk/' . VERSION . '; ' . home_url( '/' ),
		) );
		if ( is_wp_error( $res ) ) {
			return new \WP_Error( 'unreachable', 'unreachable', array( 'status' => 502 ) );
		}
		$code = (int) wp_remote_retrieve_response_code( $res );
		$body = (string) wp_remote_retrieve_body( $res );
		$type = strtolower( trim( explode( ';', (string) wp_remote_retrieve_header( $res, 'content-type' ) )[0] ) );
		$ext  = self::TYPES[ $type ] ?? null;
		$real = 'pdf' === $ext ? str_starts_with( $body, '%PDF' ) : ( $ext && false !== @getimagesizefromstring( $body ) ); // phpcs:ignore WordPress.PHP.NoSilencedErrors
		if ( 200 !== $code || ! $ext || ! $real ) {
			return new \WP_Error( 'no_map', 'no_map', array( 'status' => 404 ) );
		}

		wp_mkdir_p( $dir );
		foreach ( (array) glob( $dir . '/' . sanitize_key( $source ) . '-' . strtolower( $iso ) . '-*' ) as $old ) {
			wp_delete_file( $old ); // an older version of this map
		}
		file_put_contents( "$dir/$prefix.$ext", $body ); // phpcs:ignore WordPress.WP.AlternativeFunctions
		return self::result( $uploads, "$prefix.$ext" );
	}

	private static function result( array $uploads, string $file ): array {
		return array(
			'url'  => trailingslashit( $uploads['baseurl'] ) . self::DIR . '/' . $file,
			'type' => str_ends_with( $file, '.pdf' ) ? 'pdf' : 'image',
		);
	}

	/** Removes all stored maps (uninstall). */
	public static function purge(): void {
		$dir = trailingslashit( wp_upload_dir()['basedir'] ) . self::DIR;
		foreach ( (array) glob( $dir . '/*' ) as $file ) {
			wp_delete_file( $file );
		}
		@rmdir( $dir ); // phpcs:ignore WordPress.PHP.NoSilencedErrors
	}
}
