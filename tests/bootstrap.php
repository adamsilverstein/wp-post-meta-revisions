<?php
/**
 * Set up the environment for running the unit tests.
 *
 * @package WordPress\Plugins\WP_Post_Meta_Revisions
 * @link    https://github.com/adamsilverstein/wp-post-meta-revisions
 * @license http://creativecommons.org/licenses/GPL/2.0/ GNU General Public License, version 2 or higher
 */

$_tests_dir = getenv( 'WP_TESTS_DIR' );

if ( ! $_tests_dir ) {
	$_tests_dir = '/tmp/wordpress-tests-lib';
}

require_once $_tests_dir . '/includes/functions.php';

function _manually_load_plugin() {
	require dirname( __FILE__ ) . '/../wp-post-meta-revisions.php';
}
tests_add_filter( 'muplugins_loaded', '_manually_load_plugin' );

require $_tests_dir . '/includes/bootstrap.php';

