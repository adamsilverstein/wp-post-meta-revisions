[![Build Status](https://travis-ci.org/adamsilverstein/wp-post-meta-revisions.svg?branch=master)](https://travis-ci.org/adamsilverstein/wp-post-meta-revisions)

=== WP-Post-Meta-Revisions ===
* Contributors: adamsilverstein, mattheu
* Requires at least: 4.1
* Tested up to: 4.9
* Stable tag: 1.0.0
* License: GPLv2 or later
* License URI: http://www.gnu.org/licenses/gpl-2.0.html

Allow selected post meta keys to be tracked in revisions.

== Description ==

## Important
**Version 2.0.0** of this plugin introduces a streamlined storage format for revisioned meta that is not backwards compatible with previous versions (1.0 and lower) of the plugin. Restoring revisions data from a previous version is not supported and arrays may not work as expected. If you need to be able to restore from previous versions, avoid upgrading or read the full issue to see how you can migrate your data: https://github.com/adamsilverstein/wp-post-meta-revisions/pull/56/.

This plugin implements a <i>post meta revisioning</i> feature as arrived at in https://core.trac.wordpress.org/ticket/20564.

The goal of releasing this code as a plugin is to allow as many people as possible to easily test the post meta revisioning feature, and also hopefully move towards inclusion of the feature into core, following the <a href="https://make.wordpress.org/core/features-as-plugins/">Features as Plugins</a> model.

Further development of the code for this plugin will continue on its <a href="https://github.com/adamsilverstein/wp-post-meta-revisions">GitHub repository</a>. Pull requests welcome!

To use this plugin, you must be running WordPress 4.1 or newer, two hooks were added in 4.1 that are required for this implementation.

To revision a post meta, you add its key via a filter:

<pre>
function add_meta_keys_to_revision( $keys ) {
	$keys[] = 'meta-key-to-revision';
	return $keys;
}
add_filter( 'wp_post_revision_meta_keys', 'add_meta_keys_to_revision' );
</pre>

Features:

* Allows for a whitelisted array of 'revisioned' meta keys (which can change at any time)
* A revision for the meta is stored on save (if the meta value has changed)
* A meta revision save (if changed) is also triggered during auto-saves
* Restoring a revision restores the revisioned meta field's values at that revision (including auto-saves)
* Supports storing of multiple values for a single key (and restoring them)
* Adds revisioned meta to the preview data via get_post_metadata
* Includes unit tests demonstrating feature
* Travis CI tests integrated with GitHub repository, props @mattheu
