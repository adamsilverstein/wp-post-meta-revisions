<?php
/*
Plugin Name: WP-20564
Plugin URI: https://github.com/adamsilverstein/wp-20564
Description: Revisions Post Meta
Version: 0.3
Author: Adam Silverstein - code developed with others at https://core.trac.wordpress.org/ticket/20564
License: GPLv2 or later
*/

class WP_20564 {

	/**
	 * Set up the plugin actions
	 */
	public function __construct() {

		// Actions
		add_action( 'wp_restore_post_revision', array( $this, '_wp_restore_post_revision_meta'), 10, 2 );
		add_action( '_wp_creating_autosave', array( $this, '_wp_autosave_post_revisioned_meta_fields' ) );
		add_action( '_wp_put_post_revision', array( $this, '_wp_save_revisioned_meta_fields' ) );
		//Filters
		add_filter( 'get_post_metadata',     array( $this, '_wp_preview_meta_filter'), 10, 4 );
		add_filter( 'wp_save_post_revision_additional_check_for_changes', array( $this, '_wp_check_revisioned_meta_fields_have_changed' ), 10, 3 );

	}

	/**
	 * Autosave the revisioned meta fields
	 */
	private static function _wp_autosave_post_revisioned_meta_fields( $new_autosave ) {
		// Auto-save revisioned meta fields.
		foreach ( $this->_wp_post_revision_meta_keys() as $meta_key ) {
			if ( isset( $_POST[ $meta_key ] )
				&& '' !== $_POST[ $meta_key ]
				&& get_post_meta( $new_autosave['ID'], $meta_key, true ) != wp_unslash( $_POST[ $meta_key ] ) )
			{
				/*
				 * Use the underlying delete_metadata() and add_metadata() functions
				 * vs delete_post_meta() and add_post_meta() to make sure we're working
				 * with the actual revision meta.
				 */
				delete_metadata( 'post', $new_autosave['ID'], $meta_key );
				if ( ! empty( $_POST[ $meta_key ] ) ) {
					add_metadata( 'post', $new_autosave['ID'], $meta_key, $_POST[ $meta_key ] );
				}
			}
		}
	}

	/**
	 * Determine which post meta fields should be revisioned.
	 *
	 * @access private
	 * @since 4.1.0
	 *
	 * @return array An array of meta keys to be revisioned.
	 */
	function _wp_post_revision_meta_keys() {
		/**
		 * Filter the list of post meta keys to be revisioned.
		 *
		 * @since 4.1.0
		 *
		 * @param array $keys An array of default meta fields to be revisioned.
		 */
		return apply_filters( 'wp_post_revision_meta_keys', array() );
	}

	/**
	 * Check whether revisioned post meta fields have changed.
	 */
	function _wp_check_revisioned_meta_fields_have_changed( $post_has_changed, $last_revision, $post ) {
		foreach ( $this->_wp_post_revision_meta_keys() as $meta_key ) {
			if ( get_post_meta( $post->ID, $meta_key ) != get_post_meta( $last_revision->ID, $meta_key ) ) {
				$post_has_changed = true;
				break;
			}
		}
		return $post_has_changed;
	}

	/**
	 * Save the revisioned meta fields
	 */
	function _wp_save_revisioned_meta_fields( $revision_id ) {
		$revision = get_post( $revision_id );
		$post_id  = $revision->post_parent;
		// Save revisioned meta fields.
		foreach ( $this->_wp_post_revision_meta_keys() as $meta_key ) {
			$meta_value = get_post_meta( $post_id, $meta_key );

			// Don't save blank meta values
			if( '' !== $meta_value[0] ) {

				/*
				 * Use the underlying add_metadata() function vs add_post_meta()
				 * to ensure metadata is added to the revision post and not its parent.
				 */
				add_metadata( 'post', $revision_id, $meta_key, $meta_value );
			}
		}
		// Save the revisioned meta keys so we know which meta keys were revisioned
		add_metadata( 'post', $revision_id, '_wp_post_revision_meta_keys', $this->_wp_post_revision_meta_keys() );
	}

	/**
	 * Restore the revisioned meta values for a post
	 */
	function _wp_restore_post_revision_meta( $post_id, $revision_id ) {
		//var_dump( get_metadata( 'post', $revision_id, '_wp_post_revision_meta_keys' ) );
		// Restore revisioned meta fields; first get the keys for this revision
		$metas_revisioned =  wp_unslash( get_metadata( 'post', $revision_id, '_wp_post_revision_meta_keys' ) );
		if ( 0 !== sizeof( $metas_revisioned[0] ) ) {
			foreach ( $metas_revisioned[0] as $meta_key ) {
				// Clear any existing metas
				delete_post_meta( $post_id, $meta_key );
				// Get the stored meta, not stored === blank
				$meta_values = get_post_meta( $revision_id, $meta_key, true );
				if ( 0 !== sizeof( $meta_values ) && is_array( $meta_values ) ) {
					foreach( $meta_values as $meta_value ) {
						add_post_meta( $post_id, $meta_key, $meta_value );
					}
				}
			}
		}
	}
	 /**
	 * Filters post meta retrieval to get values from the actual autosave post,
	 * and not its parent.
	 *
	 * Filters revisioned meta keys only.
	 *
	 * @access private
	 * @since 4.1.0
	 *
	 * @param mixed  $value     Meta value to filter.
	 * @param int    $object_id Object ID.
	 * @param string $meta_key  Meta key to filter a value for.
	 * @param bool   $single    Whether to return a single value. Default false.
	 * @return mixed Original meta value if the meta key isn't revisioned, the object doesn't exist,
	 *               the post type is a revisionm or the post ID doesn't match the object ID.
	 *               Otherwise, the revisioned meta value is returned for the preview.
	 */
	function _wp_preview_meta_filter( $value, $object_id, $meta_key, $single ) {
		$post = get_post();
		if ( empty( $post )
			|| $post->ID != $object_id
			|| ! in_array( $meta_key, $this->_wp_post_revision_meta_keys() )
			|| 'revision' == $post->post_type )
		{
			return $value;
		}

		// Grab the autosave.
		$preview = wp_get_post_autosave( $post->ID );
		if ( ! is_object( $preview ) ) {
			return $value;
		}

		return get_post_meta( $preview->ID, $meta_key, $single );
	}
}

$wp_20564 = new WP_20564;
