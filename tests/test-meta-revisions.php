<?php
/**
 * Unit test file.
 *
 * Test the meta revisioning features.
 *
 * @package WordPress\Plugins\WP_Post_Meta_Revisions
 * @link    https://github.com/adamsilverstein/wp-post-meta-revisions
 * @license http://creativecommons.org/licenses/GPL/2.0/ GNU General Public License, version 2 or higher
 */

/**
 * Tests for the "Post Meta Revisions" plugin.
 */
class MetaRevisionTests extends WP_UnitTestCase {

	/**
	 * Array of meta keys to revision.
	 *
	 * @var array
	 */
	protected $revisioned_keys;

	/**
	 * {@inheritDoc}
	 */
	protected function setUp() {
		parent::setUp();

		// Reset for current test.
		$this->revisioned_keys = array( 'meta_revision_test' );
	}

	/**
	 * Callback function to add the revisioned keys.
	 *
	 * @param array $keys The array of revisioned keys.
	 *
	 * @return array
	 */
	public function add_revisioned_keys( $keys ) {
		$keys[] = 'meta_revision_test';
		$keys[] = 'meta_multiples_test';
		return $keys;
	}

	/**
	 * Test the revisions system for storage of meta values with slashes.
	 *
	 * @param string $passed   The passed data for testing.
	 *
	 * @param string $expected The expected value after storing & retrieving.
	 *
	 * @group revision
	 * @group slashed
	 * @dataProvider slashed_data_provider
	 */
	public function test_revisions_stores_meta_values_with_slashes( $passed, $expected ) {
		// Set up a new post.
		$post_id          = $this->factory->post->create();
		$original_post_id = $post_id;

		// And update to store an initial revision.
		wp_update_post(
			array(
				'post_content' => 'some initial content',
				'ID'           => $post_id,
			)
		);
		add_filter( 'wp_post_revision_meta_keys', array( $this, 'add_revisioned_keys' ) );

		// Store a custom meta value, which is not revisioned by default.
		update_post_meta( $post_id, 'meta_revision_test', wp_slash( $passed ) );
		$this->assertEquals( $expected, get_post_meta( $post_id, 'meta_revision_test', true ) );

		// Update the post, storing a revision.
		wp_update_post(
			array(
				'post_content' => 'some more content',
				'ID'           => $post_id,
			)
		);

		// Overwrite.
		update_post_meta( $post_id, 'meta_revision_test', 'original' );
		// Update the post, storing a revision.
		wp_update_post(
			array(
				'post_content' => 'some more content again',
				'ID'           => $post_id,
			)
		);

		// Restore the previous revision.
		$revisions = (array) wp_get_post_revisions( $post_id );

		// Go back two to load the previous revision.
		array_shift( $revisions );
		$last_revision = array_shift( $revisions );

		// Restore!
		wp_restore_post_revision( $last_revision->ID );

		$this->assertEquals( $expected, get_post_meta( $post_id, 'meta_revision_test', true ) );
		remove_filter( 'wp_post_revision_meta_keys', array( $this, 'add_revisioned_keys' ) );
	}

	/**
	 * Provide data for the slashed data tests.
	 */
	public function slashed_data_provider() {
		return array(
			array(
				'some\text',
				'some\text',
			),
			array(
				'test some\ \\extra \\\slashed \\\\text ',
				'test some\ \\extra \\\slashed \\\\text ',
			),
			array(
				"This \'is\' an example \n of a \"quoted\" string",
				"This \'is\' an example \n of a \"quoted\" string",
			),
			array(
				'some unslashed text just to test! % & * ( ) #',
				'some unslashed text just to test! % & * ( ) #',
			),
		);
	}

	/**
	 * Test the revisions system for storage of meta values.
	 *
	 * @group revision
	 */
	public function test_revisions_stores_meta_values() {
		/*
		 * Set Up.
		 */

		// Set up a new post.
		$post_id          = $this->factory->post->create();
		$original_post_id = $post_id;

		// And update to store an initial revision.
		wp_update_post(
			array(
				'post_content' => 'some initial content',
				'ID'           => $post_id,
			)
		);

		// One revision so far.
		$revisions = wp_get_post_revisions( $post_id );
		$this->assertCount( 1, $revisions );

		/*
		 * First set up a meta value.
		 */

		// Store a custom meta value, which is not revisioned by default.
		update_post_meta( $post_id, 'meta_revision_test', 'original' );

		// Update the post, storing a revision.
		wp_update_post(
			array(
				'post_content' => 'some more content',
				'ID'           => $post_id,
			)
		);

		$revisions = wp_get_post_revisions( $post_id );
		$this->assertCount( 2, $revisions );

		// Next, store some updated meta values for the same key.
		update_post_meta( $post_id, 'meta_revision_test', 'update1' );

		// Save the post, changing content to force a revision.
		wp_update_post(
			array(
				'post_content' => 'some updated content',
				'ID'           => $post_id,
			)
		);

		$revisions = wp_get_post_revisions( $post_id );
		$this->assertCount( 3, $revisions );

		/*
		 * Now restore the original revision.
		 */

		// Restore the previous revision.
		$revisions = (array) wp_get_post_revisions( $post_id );

		// Go back two to load the previous revision.
		array_shift( $revisions );
		$last_revision = array_shift( $revisions );

		// Restore!
		wp_restore_post_revision( $last_revision->ID );

		wp_update_post( array( 'ID' => $post_id ) );
		$revisions = wp_get_post_revisions( $post_id );
		$this->assertCount( 4, $revisions );

		/*
		 * Check the meta values to verify they are NOT revisioned - they are not revisioned by default.
		 */

		// Custom post meta should NOT be restored, orignal value should not be restored, value still 'update1'.
		$this->assertEquals( 'update1', get_post_meta( $post_id, 'meta_revision_test', true ) );

		update_post_meta( $post_id, 'meta_revision_test', 'update2' );

		/*
		 * Test the revisioning of custom meta when enabled by the wp_post_revision_meta_keys filter.
		 */

		// Add the custom field to be revised via the wp_post_revision_meta_keys filter.
		add_filter( 'wp_post_revision_meta_keys', array( $this, 'add_revisioned_keys' ) );

		// Save the post, changing content to force a revision.
		wp_update_post(
			array(
				'post_content' => 'more updated content',
				'ID'           => $post_id,
			)
		);

		$revisions = array_values( wp_get_post_revisions( $post_id ) );
		$this->assertCount( 5, $revisions );
		$this->assertEquals( 'update2', get_post_meta( $revisions[0]->ID, 'meta_revision_test', true ) );

		// Store custom meta values, which should now be revisioned.
		update_post_meta( $post_id, 'meta_revision_test', 'update3' );

		/*
		 * Save the post again, custom meta should now be revisioned.
		 *
		 * Note that a revision is saved even though there is no change
		 * in post content, because the revisioned post_meta has changed.
		 */
		wp_update_post(
			array(
				'ID' => $post_id,
			)
		);

		// This revision contains the existing post meta ('update3').
		$revisions = wp_get_post_revisions( $post_id );
		$this->assertCount( 6, $revisions );

		// Verify that previous post meta is set.
		$this->assertEquals( 'update3', get_post_meta( $post_id, 'meta_revision_test', true ) );

		// Restore the previous revision.
		$revisions = wp_get_post_revisions( $post_id );

		// Go back two to load the previous revision.
		array_shift( $revisions );
		$last_revision = array_shift( $revisions );
		wp_restore_post_revision( $last_revision->ID );

		/*
		 * Verify that previous post meta is restored.
		 */
		$this->assertEquals( 'update2', get_post_meta( $post_id, 'meta_revision_test', true ) );

		// Try storing a blank meta.
		update_post_meta( $post_id, 'meta_revision_test', '' );
		wp_update_post(
			array(
				'ID' => $post_id,
			)
		);

		update_post_meta( $post_id, 'meta_revision_test', 'update 4' );
		wp_update_post(
			array(
				'ID' => $post_id,
			)
		);

		// Restore the previous revision.
		$revisions = wp_get_post_revisions( $post_id );
		array_shift( $revisions );
		$last_revision = array_shift( $revisions );
		wp_restore_post_revision( $last_revision->ID );

		/*
		 * Verify that previous blank post meta is restored.
		 */
		$this->assertEquals( '', get_post_meta( $post_id, 'meta_revision_test', true ) );

		/*
		 * Test not tracking a key - remove the key from the revisioned meta.
		 */
		remove_all_filters( 'wp_post_revision_meta_keys' );

		// Meta should no longer be revisioned.
		update_post_meta( $post_id, 'meta_revision_test', 'update 5' );
		wp_update_post(
			array(
				'ID'           => $post_id,
				'post_content' => 'changed content',
			)
		);
		update_post_meta( $post_id, 'meta_revision_test', 'update 6' );
		wp_update_post(
			array(
				'ID'           => $post_id,
				'post_content' => 'go updated content',
			)
		);

		// Restore the previous revision.
		$revisions = wp_get_post_revisions( $post_id );
		array_shift( $revisions );
		$last_revision = array_shift( $revisions );
		wp_restore_post_revision( $last_revision->ID );

		/*
		 * Verify that previous post meta is NOT restored.
		 */
		$this->assertEquals( 'update 6', get_post_meta( $post_id, 'meta_revision_test', true ) );

		// Add the custom field to be revised via the wp_post_revision_meta_keys filter.
		add_filter( 'wp_post_revision_meta_keys', array( $this, 'add_revisioned_keys' ) );

		/*
		 * Test the revisioning of multiple meta keys.
		 */

		// Add three values for meta.
		update_post_meta( $post_id, 'meta_revision_test', 'update 7' );
		add_post_meta( $post_id, 'meta_revision_test', 'update 7 number 2' );
		add_post_meta( $post_id, 'meta_revision_test', 'update 7 number 3' );
		wp_update_post( array( 'ID' => $post_id ) );

		// Update all three values.
		update_post_meta( $post_id, 'meta_revision_test', 'update 8', 'update 7' );
		update_post_meta( $post_id, 'meta_revision_test', 'update 8 number 2', 'update 7 number 2' );
		update_post_meta( $post_id, 'meta_revision_test', 'update 8 number 3', 'update 7 number 3' );

		// Restore the previous revision.
		$revisions     = wp_get_post_revisions( $post_id );
		$last_revision = array_shift( $revisions );
		wp_restore_post_revision( $last_revision->ID );

		/*
		 * Verify that multiple metas stored correctly.
		 */
		$this->assertEquals( array( 'update 7', 'update 7 number 2', 'update 7 number 3' ), get_post_meta( $post_id, 'meta_revision_test' ) );

		/*
		 * Test the revisioning of a multidimensional array.
		 */
		$test_array = array(
			'a' => array(
				'1',
				'2',
				'3',
			),
			'b' => 'ok',
			'c' => array(
				'multi' => array(
					'a',
					'b',
					'c',
				),
				'not'   => 'ok',
			),
		);

		// Clear any old value.
		delete_post_meta( $post_id, 'meta_revision_test' );

		// Set the test meta to the array.
		update_post_meta( $post_id, 'meta_revision_test', $test_array );

		// Update to save.
		wp_update_post( array( 'ID' => $post_id ) );

		// Set the test meta blank.
		update_post_meta( $post_id, 'meta_revision_test', '' );

		// Restore the previous revision.
		$revisions     = wp_get_post_revisions( $post_id );
		$last_revision = array_shift( $revisions );
		wp_restore_post_revision( $last_revision->ID );

		/*
		 * Verify  multidimensional array stored correctly.
		 */
		$stored_array = get_post_meta( $post_id, 'meta_revision_test' );
		$this->assertEquals( $test_array, $stored_array[0] );
		/*

		 * Test multiple revisions on the same key.
		 */

		// Set the test meta to the array.
		add_post_meta( $post_id, 'meta_multiples_test', 'test1' );
		add_post_meta( $post_id, 'meta_multiples_test', 'test2' );
		add_post_meta( $post_id, 'meta_multiples_test', 'test3' );

		// Update to save.
		wp_update_post( array( 'ID' => $post_id ) );

		$stored_array = get_post_meta( $post_id, 'meta_multiples_test' );
		$expect       = array( 'test1', 'test2', 'test3' );

		$this->assertEquals( $expect, $stored_array );

		// Restore the previous revision.
		$revisions     = wp_get_post_revisions( $post_id );
		$last_revision = array_shift( $revisions );
		wp_restore_post_revision( $last_revision->ID );

		$stored_array = get_post_meta( $post_id, 'meta_multiples_test' );
		$expect       = array( 'test1', 'test2', 'test3' );

		$this->assertEquals( $expect, $stored_array );

		// Cleanup!
		wp_delete_post( $original_post_id );
	}

	/**
	 * Verify that only existing meta is revisioned.
	 */
	public function only_existing_meta_is_revisioned() {
		$this->revisioned_keys[] = 'foo';
		$this->revisioned_keys[] = 'bar';

		add_filter( 'wp_post_revision_meta_keys', array( $this, 'add_revisioned_keys' ) );

		// Set up a new post.
		$post_id = $this->factory->post->create(
			array(
				'post_content' => 'initial content',
			)
		);

		// Revision v1.
		wp_update_post(
			array(
				'ID'           => $post_id,
				'post_content' => 'updated content v1',
			)
		);

		$this->assertPostNotHasMetaKey( $post_id, 'foo' );
		$this->assertPostNotHasMetaKey( $post_id, 'bar' );

		$revisions = wp_get_post_revisions( $post_id );
		$revision  = array_shift( $revisions );
		$this->assertEmpty( get_metadata( 'post', $revision->ID ) );

		// Revision v2.
		wp_update_post(
			array(
				'ID'           => $post_id,
				'post_content' => 'updated content v2',
				'meta_input'   => array(
					'foo' => 'foo v2',
				),
			)
		);

		$this->assertPostHasMetaKey( $post_id, 'foo' );
		$this->assertPostNotHasMetaKey( $post_id, 'bar' );
		$this->assertPostNotHasMetaKey( $post_id, 'meta_revision_test' );

		$revisions = wp_get_post_revisions( $post_id );
		$revision  = array_shift( $revisions );
		$this->assertPostHasMetaKey( $revision->ID, 'foo' );
		$this->assertPostNotHasMetaKey( $revision->ID, 'bar' );
		$this->assertPostNotHasMetaKey( $revision->ID, 'meta_revision_test' );
	}

	/**
	 * Verify that blank strings are revisioned correctly.
	 */
	public function blank_meta_is_revisioned() {
		$this->revisioned_keys[] = 'foo';

		add_filter( 'wp_post_revision_meta_keys', array( $this, 'add_revisioned_keys' ) );

		// Set up a new post.
		$post_id = $this->factory->post->create(
			array(
				'post_content' => 'initial content',
				'meta_input'   => array(
					'foo' => 'foo',
				),
			)
		);

		// Set the test meta to an empty string.
		update_post_meta( $post_id, 'foo', '' );

		// Update to save.
		wp_update_post( array( 'ID' => $post_id ) );

		$stored_array = get_post_meta( $post_id, 'meta_multiples_test' );
		$expect       = array( 'test1', 'test2', 'test3' );

		$this->assertEquals( $expect, $stored_array );

		// Restore the previous revision.
		$revisions     = wp_get_post_revisions( $post_id );
		$last_revision = array_shift( $revisions );
		wp_restore_post_revision( $last_revision->ID );
		$stored_data = get_post_meta( $post_id, 'foo' );
		$this->assertEquals( '', $stored_data[0] );

		$stored_array = get_post_meta( $post_id, 'meta_multiples_test' );
		$expect       = array( 'test1', 'test2', 'test3' );

	/**
	 * Assert the a post has a meta key.
	 *
	 * @param int    $post_id        The ID of the post to check.
	 * @param string $meta_key The meta key to check for.
	 */
	protected function assertPostHasMetaKey( $post_id, $meta_key ) {
		$this->assertEquals( $expect, $stored_array );
	}

	/**
	 * Assert that post does not have a meta key.
	 *
	 * @param int    $post_id        The ID of the post to check.
	 * @param string $meta_key The meta key to check for.
	 */
	protected function assertPostNotHasMetaKey( $post_id, $meta_key ) {
		$this->assertArrayNotHasKey( $meta_key, get_metadata( 'post', $post_id ) );
	}
}
