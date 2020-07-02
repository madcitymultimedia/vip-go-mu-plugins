<?php

namespace Automattic\VIP\Search\Commands;

use \WP_CLI;
use \WP_CLI\Utils;

/**
 * Commands to view and manage index versions
 *
 * @package Automattic\VIP\Search
 */
class VersionCommand extends \WPCOM_VIP_CLI_Command {
	private const SUCCESS_ICON = "\u{2705}"; // unicode check mark
	private const FAILURE_ICON = "\u{274C}"; // unicode cross mark

	/**
	 * Register a new index version
	 *
	 * ## OPTIONS
	 * 
	 * <type>
	 * : The index type (the slug of the Indexable, such as 'post', 'user', etc)
	 *
	 * ## EXAMPLES
	 *     wp vip-search index-versions add post
	 *
	 * @subcommand add
	 */
	public function add( $args, $assoc_args ) {
		$search = \Automattic\VIP\Search\Search::instance();

		$indexable = \ElasticPress\Indexables::factory()->get( $assoc_args['type'] );

		$result = $search->versioning->add_version( $indexable );

		if ( is_wp_error( $result ) ) {
			return WP_CLI::error( $result->get_error_message() );
		}

		if ( false === $result ) {
			return WP_CLI::error( 'Failed to register the new index version' );
		}
	
		$versions = $search->versioning->get_versions( $indexable );

		if ( ! count( $versions ) ) {
			return WP_CLI::error( 'No versions found after registering new version' );
		}

		// New version will be last on the list

		$new_version = end( $versions );

		WP_CLI::success( sprintf( 'Registered new index version %d. The new index has not yet been created', $new_version ) );
	}
}
