<?php
/**
 * Cache helper functions for Cloudflare API data.
 *
 * All API results are stored as JSON files in the /cache/ directory.
 * Pages read from the cache; a "Refresh" button triggers an AJAX request
 * that re-fetches from the Cloudflare API and updates the cache.
 */

// Direct access protection
defined( 'CLOUDFLARE_API_KEY' ) || exit( 'No direct script access allowed' );

define( 'PW_CACHE_DIR', __DIR__ . '/cache' );

/**
 * Returns the absolute path for a given cache key.
 *
 * @param string $key Cache key (alphanumeric, underscores, dashes only).
 * @return string
 */
function pw_cache_path( $key ) {
	$safe_key = preg_replace( '/[^a-z0-9_-]/', '_', strtolower( $key ) );
	return PW_CACHE_DIR . '/' . $safe_key . '.json';
}

/**
 * Read cached data for a given key.
 *
 * @param string $key Cache key.
 * @return mixed|false Cached data, or false if no cache exists.
 */
function pw_cache_get( $key ) {
	$path = pw_cache_path( $key );
	if ( ! file_exists( $path ) ) {
		return false;
	}
	$contents = file_get_contents( $path );
	if ( ! $contents ) {
		return false;
	}
	$cached = json_decode( $contents, true );
	if ( ! isset( $cached['data'] ) ) {
		return false;
	}
	return $cached['data'];
}

/**
 * Write data to cache.
 *
 * @param string $key  Cache key.
 * @param mixed  $data Data to cache (must be JSON-serializable).
 * @return bool True on success.
 */
function pw_cache_set( $key, $data ) {
	if ( ! is_dir( PW_CACHE_DIR ) ) {
		mkdir( PW_CACHE_DIR, 0755, true );
	}
	$contents = json_encode(
		array(
			'timestamp' => time(),
			'data'      => $data,
		)
	);
	return file_put_contents( pw_cache_path( $key ), $contents ) !== false;
}

/**
 * Get the Unix timestamp of a cache entry.
 *
 * @param string $key Cache key.
 * @return int|false Timestamp, or false if no cache.
 */
function pw_cache_timestamp( $key ) {
	$path = pw_cache_path( $key );
	if ( ! file_exists( $path ) ) {
		return false;
	}
	$contents = file_get_contents( $path );
	if ( ! $contents ) {
		return false;
	}
	$cached = json_decode( $contents, true );
	return isset( $cached['timestamp'] ) ? (int) $cached['timestamp'] : false;
}

/**
 * Return a human-readable age string for a cache entry.
 *
 * @param string $key Cache key.
 * @return string|false Age label like "5 mins ago", or false if no cache.
 */
function pw_cache_age_label( $key ) {
	$ts = pw_cache_timestamp( $key );
	if ( ! $ts ) {
		return false;
	}
	$age = time() - $ts;
	if ( $age < 60 ) {
		return 'just now';
	} elseif ( $age < 3600 ) {
		$m = (int) round( $age / 60 );
		return $m . ' min' . ( 1 !== $m ? 's' : '' ) . ' ago';
	} elseif ( $age < 86400 ) {
		$h = (int) round( $age / 3600 );
		return $h . ' hour' . ( 1 !== $h ? 's' : '' ) . ' ago';
	} else {
		$d = (int) round( $age / 86400 );
		return $d . ' day' . ( 1 !== $d ? 's' : '' ) . ' ago';
	}
}
