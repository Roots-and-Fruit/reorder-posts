<?php
/**
 * Plain-PHP oracle for the legacy ajax_save_post_order counting loop.
 *
 * Does not call plugin code. Given the ordered sibling IDs (excluding the moved
 * post when post_id > 0), start counter, moved post ID, and requested
 * menu_order, returns array<int,int> of ID => menu_order.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Compute the ID => menu_order map produced by the legacy loop.
 *
 * @param int[] $ordered_sibling_ids Sibling IDs in `menu_order ASC, post_title ASC` order, excluding $post_id when $post_id > 0.
 * @param int   $menu_order_start    Initial counter (`start` parameter).
 * @param int   $post_id             Moved post ID, or 0 for old-parent renumber pass.
 * @param int   $post_menu_order     Requested menu_order for the moved post.
 * @return array<int,int>
 */
function mn_reorder_test_oracle_map( array $ordered_sibling_ids, $menu_order_start, $post_id, $post_menu_order ) {
	$post_id         = absint( $post_id );
	$post_menu_order = absint( $post_menu_order );
	$start           = absint( $menu_order_start );
	$map             = array();

	if ( $post_id > 0 ) {
		$map[ $post_id ] = $post_menu_order;
	}

	foreach ( $ordered_sibling_ids as $sibling_id ) {
		$sibling_id = absint( $sibling_id );
		if ( $start === $post_menu_order && $post_id > 0 ) {
			++$start;
		}
		if ( $post_id !== $sibling_id ) {
			$map[ $sibling_id ] = $start;
		}
		++$start;
	}

	return $map;
}
