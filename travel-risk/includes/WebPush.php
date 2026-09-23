<?php
/**
 * Web Push without libraries: VAPID (RFC 8292) and aes128gcm payload
 * encryption (RFC 8188 / RFC 8291), using PHP's OpenSSL extension.
 *
 * No WordPress dependency, so it can be tested on its own.
 */

namespace TravelRisk;

defined( 'ABSPATH' ) || defined( 'TRAVEL_RISK_TESTING' ) || exit;

class WebPush {

	/** DER prefix of a SubjectPublicKeyInfo for an uncompressed P-256 point. */
	const P256_SPKI_PREFIX = '3059301306072a8648ce3d020106082a8648ce3d030107034200';

	/** @return array{public:string, private:string} public = base64url raw point, private = PEM. */
	public static function generate_vapid_keys(): array {
		$key = openssl_pkey_new( array( 'curve_name' => 'prime256v1', 'private_key_type' => OPENSSL_KEYTYPE_EC ) );
		openssl_pkey_export( $key, $pem );
		return array( 'public' => self::b64url( self::raw_public( $key ) ), 'private' => $pem );
	}

	/**
	 * Builds the HTTP request for one push message.
	 *
	 * @param array  $sub     [endpoint, p256dh, auth] as sent by the browser (base64url keys).
	 * @param string $payload Plain text, usually JSON.
	 * @param array  $vapid   [public, private, subject] from generate_vapid_keys() plus mailto:/https: subject.
	 * @return array{url:string, headers:array<string,string>, body:string}
	 */
	public static function request( array $sub, string $payload, array $vapid, int $ttl = 86400 ): array {
		$body = self::encrypt( $payload, self::b64url_decode( $sub['p256dh'] ), self::b64url_decode( $sub['auth'] ) );
		$aud  = self::origin( $sub['endpoint'] );
		$jwt  = self::vapid_jwt( $aud, $vapid['subject'], $vapid['private'] );
		return array(
			'url'     => $sub['endpoint'],
			'headers' => array(
				'Content-Type'     => 'application/octet-stream',
				'Content-Encoding' => 'aes128gcm',
				'TTL'              => (string) $ttl,
				'Urgency'          => 'high',
				'Authorization'    => 'vapid t=' . $jwt . ', k=' . $vapid['public'],
			),
			'body'    => $body,
		);
	}

	/**
	 * RFC 8291 message encryption, single record.
	 *
	 * @param string $ua_public 65-byte uncompressed P-256 point of the browser (p256dh).
	 * @param string $auth      16-byte auth secret of the browser.
	 */
	public static function encrypt( string $payload, string $ua_public, string $auth ): string {
		if ( 65 !== strlen( $ua_public ) || "\x04" !== $ua_public[0] ) {
			throw new \InvalidArgumentException( 'invalid p256dh' );
		}
		$as_key    = openssl_pkey_new( array( 'curve_name' => 'prime256v1', 'private_key_type' => OPENSSL_KEYTYPE_EC ) );
		$as_public = self::raw_public( $as_key );
		$salt      = random_bytes( 16 );

		$peer   = openssl_pkey_get_public( self::pem_public( $ua_public ) );
		$shared = openssl_pkey_derive( $peer, $as_key );
		if ( false === $shared ) {
			throw new \RuntimeException( 'ecdh failed' );
		}

		$ikm   = hash_hkdf( 'sha256', $shared, 32, "WebPush: info\0" . $ua_public . $as_public, $auth );
		$cek   = hash_hkdf( 'sha256', $ikm, 16, "Content-Encoding: aes128gcm\0", $salt );
		$nonce = hash_hkdf( 'sha256', $ikm, 12, "Content-Encoding: nonce\0", $salt );

		$tag        = '';
		$ciphertext = openssl_encrypt( $payload . "\x02", 'aes-128-gcm', $cek, OPENSSL_RAW_DATA, $nonce, $tag, '', 16 );

		$record_size = 4096;
		return $salt . pack( 'N', $record_size ) . chr( 65 ) . $as_public . $ciphertext . $tag;
	}

	/** RFC 8292 JWT, ES256. */
	public static function vapid_jwt( string $audience, string $subject, string $private_pem, ?int $exp = null ): string {
		$header = self::b64url( json_encode( array( 'typ' => 'JWT', 'alg' => 'ES256' ) ) );
		$claims = self::b64url( json_encode( array( 'aud' => $audience, 'exp' => $exp ?? time() + 12 * 3600, 'sub' => $subject ), JSON_UNESCAPED_SLASHES ) );
		$input  = $header . '.' . $claims;
		openssl_sign( $input, $der, openssl_pkey_get_private( $private_pem ), OPENSSL_ALGO_SHA256 );
		return $input . '.' . self::b64url( self::der_to_raw( $der ) );
	}

	/** ECDSA signature from DER (SEQUENCE of two INTEGERs) to 64-byte r||s. */
	public static function der_to_raw( string $der ): string {
		$pos = 2 + ( ord( $der[1] ) & 0x80 ? ord( $der[1] ) & 0x7f : 0 );
		$out = '';
		for ( $i = 0; $i < 2; $i++ ) {
			$len  = ord( $der[ $pos + 1 ] );
			$int  = ltrim( substr( $der, $pos + 2, $len ), "\x00" );
			$out .= str_pad( $int, 32, "\x00", STR_PAD_LEFT );
			$pos += 2 + $len;
		}
		return $out;
	}

	public static function raw_public( $key ): string {
		$ec = openssl_pkey_get_details( $key )['ec'];
		return "\x04" . str_pad( $ec['x'], 32, "\x00", STR_PAD_LEFT ) . str_pad( $ec['y'], 32, "\x00", STR_PAD_LEFT );
	}

	public static function pem_public( string $raw ): string {
		$der = hex2bin( self::P256_SPKI_PREFIX ) . $raw;
		return "-----BEGIN PUBLIC KEY-----\n" . chunk_split( base64_encode( $der ), 64, "\n" ) . "-----END PUBLIC KEY-----\n";
	}

	public static function origin( string $url ): string {
		$p = parse_url( $url );
		return $p['scheme'] . '://' . $p['host'] . ( isset( $p['port'] ) ? ':' . $p['port'] : '' );
	}

	public static function b64url( string $bin ): string {
		return rtrim( strtr( base64_encode( $bin ), '+/', '-_' ), '=' );
	}

	public static function b64url_decode( string $s ): string {
		return (string) base64_decode( strtr( $s, '-_', '+/' ) . str_repeat( '=', ( 4 - strlen( $s ) % 4 ) % 4 ) );
	}
}

