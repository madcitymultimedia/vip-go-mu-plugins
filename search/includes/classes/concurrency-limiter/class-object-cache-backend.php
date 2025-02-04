<?php

namespace Automattic\VIP\Search\ConcurrencyLimiter;

use function Automattic\VIP\Logstash\log2logstash;

require_once __DIR__ . '/backendinterface.php';

class Object_Cache_Backend implements BackendInterface {
	const KEY_NAME   = 'vip_search_concurrent_requests_count';
	const GROUP_NAME = 'vip_search';

	/** @var int */
	private $limit;

	/** @var int */
	private $increments = 0;

	public function __destruct() {
		if ( $this->increments > 0 ) {
			wp_cache_decr( self::KEY_NAME, $this->increments, self::GROUP_NAME );
		}
	}

	public function initialize( int $limit, int $ttl ): void {
		$this->limit = $limit;

		$found = false;
		$value = wp_cache_get( self::KEY_NAME, self::GROUP_NAME, false, $found );
		if ( ! $found || ! is_int( $value ) ) {
			// phpcs:ignore WordPressVIPMinimum.Performance.LowExpiryCacheTime.CacheTimeUndetermined
			wp_cache_set( self::KEY_NAME, 0, self::GROUP_NAME, $ttl );
		}
	}

	public static function is_supported(): bool {
		return true;
	}

	public function inc_value(): bool {
		$value = wp_cache_incr( self::KEY_NAME, 1, self::GROUP_NAME );
		if ( false !== $value ) {
			++$this->increments;

			if ( $value > $this->limit ) {
				log2logstash( [
					'severity' => 'warning',
					'feature'  => 'search_concurrency_limiter',
					'message'  => 'Reached concurrency limit',
					'extra'    => [
						'counter' => $value,
					],
				] );
			}
	
			return $value <= $this->limit;
		}

		log2logstash( [
			'severity' => 'warning',
			'feature'  => 'search_concurrency_limiter',
			'message'  => 'Failed to increment the counter',
		] );

		return false;
	}

	public function dec_value(): void {
		if ( $this->increments > 0 ) {
			$result = wp_cache_decr( self::KEY_NAME, 1, self::GROUP_NAME );
			if ( false !== $result ) {
				--$this->increments;
			} else {
				log2logstash( [
					'severity' => 'warning',
					'feature'  => 'search_concurrency_limiter',
					'message'  => 'Failed to decrement the counter',
				] );
			}
		}
	}
}
