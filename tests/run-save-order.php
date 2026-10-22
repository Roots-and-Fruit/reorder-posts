<?php
/**
 * Runner for Reorder Posts save order checks (Issue #42).
 *
 * Run via: studio wp eval-file wp-content/plugins/metronet-reorder-posts/tests/run-save-order.php
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit( 1 );
}

// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound, WordPress.WP.AlternativeFunctions.file_system_operations_fwrite

require_once __DIR__ . '/save-order-oracle.php';
$expected_fixtures = require __DIR__ . '/save-order-expected.php';

$test_post_type = 'mn_ro_test_cpt';
$created_ids    = array();
$created_users  = array();
$failures       = array();
$notes          = array();

register_post_type(
	$test_post_type,
	array(
		'public'       => false,
		'hierarchical' => true,
		'label'        => 'MN Reorder Test',
	)
);

$reorder = new MN_Reorder(
	array(
		'post_type'   => $test_post_type,
		'order'       => 'ASC',
		'heading'     => 'Test',
		'final'       => '',
		'initial'     => '',
		'menu_label'  => 'Test',
		'post_status' => 'publish',
	)
);

/**
 * Record an assertion result.
 *
 * @param bool   $condition Pass condition.
 * @param string $label     Assertion label.
 * @param mixed  $actual    Actual value on failure.
 * @param mixed  $expected  Expected value on failure.
 */
$assert_true = function ( $condition, $label, $actual = null, $expected = null ) use ( &$failures ) {
	if ( ! $condition ) {
		$detail = $label;
		if ( null !== $actual || null !== $expected ) {
			$detail .= ' | actual=' . wp_json_encode( $actual ) . ' expected=' . wp_json_encode( $expected );
		}
		$failures[] = $detail;
	}
};

/**
 * Delete all created posts and users for a clean slate.
 */
$cleanup_posts = function () use ( &$created_ids ) {
	foreach ( $created_ids as $id ) {
		wp_delete_post( $id, true );
	}
	$created_ids = array();
};

$cleanup_all = function () use ( $cleanup_posts, &$created_users ) {
	$cleanup_posts();
	if ( ! empty( $created_users ) ) {
		require_once ABSPATH . 'wp-admin/includes/user.php';
		foreach ( $created_users as $uid ) {
			wp_delete_user( $uid );
		}
		$created_users = array();
	}
};

try {
	$admins = get_users(
		array(
			'role'   => 'administrator',
			'number' => 1,
			'fields' => 'ID',
		)
	);
	$assert_true( ! empty( $admins ), 'Administrator user exists' );
	$admin_id = (int) $admins[0];
	wp_set_current_user( $admin_id );
	$nonce = wp_create_nonce( 'sortnonce' );

	$update_count  = 0;
	$count_updates = function ( $query ) use ( &$update_count ) {
		global $wpdb;
		if ( preg_match( '/^\s*UPDATE\s+`?' . preg_quote( $wpdb->posts, '/' ) . '`?\b/i', $query ) ) {
			++$update_count;
		}
		return $query;
	};
	add_filter( 'query', $count_updates );

	// 1. Hand-checked fixtures: drop_e_to_1, drop_a_to_4, paged_offset_48.
	foreach ( array( 'drop_e_to_1', 'drop_a_to_4', 'paged_offset_48' ) as $case_key ) {
		$cleanup_posts();
		$case      = $expected_fixtures[ $case_key ];
		$title_ids = array();
		foreach ( $case['initial'] as $title => $order ) {
			$pid = wp_insert_post(
				array(
					'post_type'   => $test_post_type,
					'post_status' => 'publish',
					'post_title'  => $title,
					'menu_order'  => $order,
					'post_parent' => 0,
				)
			);
			$created_ids[]       = $pid;
			$title_ids[ $title ] = $pid;
		}

		$moved_id     = $title_ids[ $case['moved'] ];
		$update_count = 0;
		$response     = $reorder->save_post_order(
			array(
				'post_parent' => 0,
				'start'       => 0,
				'nonce'       => $nonce,
				'post_id'     => $moved_id,
				'menu_order'  => $case['menu_order'],
				'excluded'    => array(),
				'post_type'   => $test_post_type,
			)
		);

		$assert_true( is_array( $response ), $case_key . ': response is array' );
		$assert_true( false === $response['more_posts'], $case_key . ': more_posts is false', $response['more_posts'], false );
		$assert_true( 1 === $update_count, $case_key . ': exactly 1 UPDATE query executed', $update_count, 1 );

		$actual_by_title = array();
		foreach ( $case['expected'] as $title => $expected_order ) {
			$post                      = get_post( $title_ids[ $title ] );
			$actual_by_title[ $title ] = (int) $post->menu_order;
		}
		$assert_true(
			$actual_by_title === $case['expected'],
			$case_key . ': stored menu_order matches frozen expected map',
			$actual_by_title,
			$case['expected']
		);
	}

	// 2. Parent change hand-checked fixture.
	$cleanup_posts();
	$pcase     = $expected_fixtures['parent_change'];
	$title_ids = array();
	foreach ( $pcase['top_initial'] as $title => $order ) {
		$pid = wp_insert_post(
			array(
				'post_type'   => $test_post_type,
				'post_status' => 'publish',
				'post_title'  => $title,
				'menu_order'  => $order,
				'post_parent' => 0,
			)
		);
		$created_ids[]       = $pid;
		$title_ids[ $title ] = $pid;
	}
	$new_parent_id = $title_ids[ $pcase['parent_key'] ];
	foreach ( $pcase['children_initial'] as $title => $order ) {
		$pid = wp_insert_post(
			array(
				'post_type'   => $test_post_type,
				'post_status' => 'publish',
				'post_title'  => $title,
				'menu_order'  => $order,
				'post_parent' => $new_parent_id,
			)
		);
		$created_ids[]       = $pid;
		$title_ids[ $title ] = $pid;
	}

	$moved_id = $title_ids[ $pcase['moved'] ];
	// First call: new parent.
	$update_count = 0;
	$resp_new     = $reorder->save_post_order(
		array(
			'post_parent' => $new_parent_id,
			'start'       => 0,
			'nonce'       => $nonce,
			'post_id'     => $moved_id,
			'menu_order'  => $pcase['new_menu_order'],
			'excluded'    => array(),
			'post_type'   => $test_post_type,
		)
	);
	$assert_true( 1 === $update_count, 'parent_change: new parent executed 1 UPDATE', $update_count, 1 );
	// Second call: old parent (post_id = 0).
	$update_count = 0;
	$resp_old     = $reorder->save_post_order(
		array(
			'post_parent' => 0,
			'start'       => 0,
			'nonce'       => $nonce,
			'post_id'     => 0,
			'menu_order'  => 0,
			'excluded'    => array(),
			'post_type'   => $test_post_type,
		)
	);
	$assert_true( 1 === $update_count, 'parent_change: old parent executed 1 UPDATE', $update_count, 1 );

	$assert_true( is_array( $resp_new ) && is_array( $resp_old ), 'parent_change: both responses are arrays' );
	$moved_post = get_post( $moved_id );
	$assert_true(
		(int) $moved_post->post_parent === $new_parent_id,
		'parent_change: moved post_parent updated',
		(int) $moved_post->post_parent,
		$new_parent_id
	);

	$actual_new_parent = array();
	foreach ( $pcase['expected_new_parent_orders'] as $title => $exp ) {
		$p                            = get_post( $title_ids[ $title ] );
		$actual_new_parent[ $title ] = (int) $p->menu_order;
		$assert_true(
			(int) $p->post_parent === $new_parent_id,
			'parent_change: child parent matches for ' . $title,
			(int) $p->post_parent,
			$new_parent_id
		);
	}
	$assert_true(
		$actual_new_parent === $pcase['expected_new_parent_orders'],
		'parent_change: new parent menu_order matches expected',
		$actual_new_parent,
		$pcase['expected_new_parent_orders']
	);

	$actual_old_parent = array();
	foreach ( $pcase['expected_old_parent_orders'] as $title => $exp ) {
		$p                            = get_post( $title_ids[ $title ] );
		$actual_old_parent[ $title ] = (int) $p->menu_order;
		$assert_true( 0 === (int) $p->post_parent, 'parent_change: old parent stayed 0 for ' . $title );
	}
	$assert_true(
		$actual_old_parent === $pcase['expected_old_parent_orders'],
		'parent_change: old parent menu_order matches expected',
		$actual_old_parent,
		$pcase['expected_old_parent_orders']
	);
	$notes['parent_change_first_run_matched'] = ( $actual_new_parent === $pcase['expected_new_parent_orders'] && $actual_old_parent === $pcase['expected_old_parent_orders'] );

	// 3. 51-post, 52-post, and 120-post live saves compared to oracle, each in 1 call and 1 UPDATE.
	foreach ( array( 51, 52, 120 ) as $count ) {
		$cleanup_posts();
		$ids_in_order = array();
		for ( $i = 0; $i < $count; $i++ ) {
			$pid = wp_insert_post(
				array(
					'post_type'   => $test_post_type,
					'post_status' => 'publish',
					'post_title'  => sprintf( 'Batch Post %03d', $i ),
					'menu_order'  => $i,
					'post_parent' => 0,
				)
			);
			$created_ids[]  = $pid;
			$ids_in_order[] = $pid;
		}

		$moved_id        = $ids_in_order[ $count - 1 ];
		$target_order    = 7;
		$siblings_oracle = array_values( array_diff( $ids_in_order, array( $moved_id ) ) );
		$oracle_map      = mn_reorder_test_oracle_map( $siblings_oracle, 0, $moved_id, $target_order );

		$req = array(
			'post_parent' => 0,
			'start'       => 0,
			'nonce'       => $nonce,
			'post_id'     => $moved_id,
			'menu_order'  => $target_order,
			'excluded'    => array(),
			'post_type'   => $test_post_type,
		);

		$update_count = 0;
		$resp         = $reorder->save_post_order( $req );
		$assert_true( is_array( $resp ), "batch_{$count}: response is array" );
		$assert_true( false === $resp['more_posts'], "batch_{$count}: more_posts is false on single call", $resp['more_posts'], false );
		$assert_true( 1 === $update_count, "batch_{$count}: executed exactly 1 UPDATE", $update_count, 1 );

		$notes[ "posts_{$count}_update_count" ] = $update_count;

		$actual_map = array();
		foreach ( $ids_in_order as $pid ) {
			$actual_map[ $pid ] = (int) get_post( $pid )->menu_order;
		}
		ksort( $actual_map );
		$expected_sorted = $oracle_map;
		ksort( $expected_sorted );

		$assert_true(
			$actual_map === $expected_sorted,
			"batch_{$count}: stored menu_order matches oracle",
			$actual_map,
			$expected_sorted
		);

		if ( 120 === $count ) {
			// Run the exact same save a second time: must leave the same map and use 1 UPDATE.
			$update_count = 0;
			$resp_second  = $reorder->save_post_order( $req );
			$assert_true( is_array( $resp_second ) && false === $resp_second['more_posts'], 'batch_120_repeat: more_posts false' );
			$assert_true( 1 === $update_count, 'batch_120_repeat: executed 1 UPDATE on repeat save', $update_count, 1 );

			$actual_map_repeat = array();
			foreach ( $ids_in_order as $pid ) {
				$actual_map_repeat[ $pid ] = (int) get_post( $pid )->menu_order;
			}
			ksort( $actual_map_repeat );
			$assert_true(
				$actual_map_repeat === $expected_sorted,
				'batch_120_repeat: stored menu_order unchanged on second save',
				$actual_map_repeat,
				$expected_sorted
			);
			$notes['posts_120_second_run_identical'] = ( $actual_map_repeat === $actual_map );
			$notes['posts_120_case_ids']             = count( $actual_map );
		}

		if ( 51 === $count || 52 === $count ) {
			$replayed_batch_maps[ $count ] = array(
				'ids_in_order' => $ids_in_order,
				'moved_id'     => $moved_id,
				'target_order' => $target_order,
				'siblings'     => $siblings_oracle,
				'replayed_map' => $actual_map,
			);
		}
	}

	// 4. Phase 2 reflection checks on MN_Reorder::build_menu_order_map.
	$ref_method = new ReflectionMethod( 'MN_Reorder', 'build_menu_order_map' );
	$ref_method->setAccessible( true );

	foreach ( array( 'drop_e_to_1', 'drop_a_to_4', 'paged_offset_48' ) as $case_key ) {
		$case            = $expected_fixtures[ $case_key ];
		$synthetic_ids   = array();
		$title_by_syn_id = array();
		$syn_id          = 1000;
		foreach ( $case['initial'] as $title => $order ) {
			++$syn_id;
			$synthetic_ids[ $title ]    = $syn_id;
			$title_by_syn_id[ $syn_id ] = $title;
		}
		$moved_syn_id = $synthetic_ids[ $case['moved'] ];
		$sibling_ids  = array();
		foreach ( $case['initial'] as $title => $order ) {
			if ( $title !== $case['moved'] ) {
				$sibling_ids[] = $synthetic_ids[ $title ];
			}
		}
		$computed = $ref_method->invoke( null, $sibling_ids, 0, $moved_syn_id, $case['menu_order'] );
		$by_title = array();
		foreach ( $case['expected'] as $title => $exp_order ) {
			$id                 = $synthetic_ids[ $title ];
			$by_title[ $title ] = isset( $computed[ $id ] ) ? $computed[ $id ] : null;
		}
		$assert_true(
			$by_title === $case['expected'],
			'phase2_' . $case_key . ': build_menu_order_map matches frozen expected map',
			$by_title,
			$case['expected']
		);
	}

	// Parent change via build_menu_order_map (new parent with post_id > 0, old parent with post_id = 0).
	$p2_new_map = $ref_method->invoke( null, array( 2001, 2002 ), 0, 2099, 1 );
	$assert_true(
		array(
			2099 => 1,
			2001 => 0,
			2002 => 2,
		) === $p2_new_map,
		'phase2_parent_change_new: build_menu_order_map matches new parent expected',
		$p2_new_map
	);
	$p2_old_map = $ref_method->invoke( null, array( 2010, 2011 ), 0, 0, 0 );
	$assert_true(
		array(
			2010 => 0,
			2011 => 1,
		) === $p2_old_map,
		'phase2_parent_change_old: build_menu_order_map matches old parent post_id=0 expected',
		$p2_old_map
	);

	// Compare 51 and 52 one-shot map against Phase 1 replayed batch result and oracle.
	foreach ( array( 51, 52 ) as $count ) {
		$snap     = $replayed_batch_maps[ $count ];
		$one_shot = $ref_method->invoke( null, $snap['siblings'], 0, $snap['moved_id'], $snap['target_order'] );
		ksort( $one_shot );
		$assert_true(
			$one_shot === $snap['replayed_map'],
			"phase2_{$count}: one-shot build_menu_order_map matches Phase 1 replayed batches",
			$one_shot,
			$snap['replayed_map']
		);
	}

	// 120-post ID list compared to oracle in one shot, and idempotency when called twice.
	$ids_120 = range( 5001, 5120 );
	$moved_120 = 5085;
	$siblings_120 = array_values( array_diff( $ids_120, array( $moved_120 ) ) );
	$oracle_120   = mn_reorder_test_oracle_map( $siblings_120, 0, $moved_120, 23 );
	$map_120_a    = $ref_method->invoke( null, $siblings_120, 0, $moved_120, 23 );
	$map_120_b    = $ref_method->invoke( null, $siblings_120, 0, $moved_120, 23 );
	$assert_true(
		$map_120_a === $oracle_120,
		'phase2_120: build_menu_order_map matches oracle for 120 IDs',
		$map_120_a,
		$oracle_120
	);
	$assert_true(
		$map_120_a === $map_120_b,
		'phase2_120_repeat: calling build_menu_order_map twice returns identical map'
	);
	$notes['phase2_120_matched_oracle'] = ( $map_120_a === $oracle_120 );

	// 5. Security gates: bad nonce and subscriber.
	$cleanup_posts();
	$sec_a = wp_insert_post(
		array(
			'post_type'   => $test_post_type,
			'post_status' => 'publish',
			'post_title'  => 'Sec A',
			'menu_order'  => 0,
			'post_parent' => 0,
		)
	);
	$sec_b = wp_insert_post(
		array(
			'post_type'   => $test_post_type,
			'post_status' => 'publish',
			'post_title'  => 'Sec B',
			'menu_order'  => 1,
			'post_parent' => 0,
		)
	);
	$created_ids[] = $sec_a;
	$created_ids[] = $sec_b;

	$bad_nonce_resp = $reorder->save_post_order(
		array(
			'post_parent' => 0,
			'start'       => 0,
			'nonce'       => 'invalid_nonce_value',
			'post_id'     => $sec_b,
			'menu_order'  => 0,
			'excluded'    => array(),
			'post_type'   => $test_post_type,
		)
	);
	$assert_true( null === $bad_nonce_resp, 'security: bad nonce returns null', $bad_nonce_resp, null );
	$assert_true( 0 === (int) get_post( $sec_a )->menu_order && 1 === (int) get_post( $sec_b )->menu_order, 'security: bad nonce leaves menu_order untouched' );

	$sub_id = wp_insert_user(
		array(
			'user_login' => 'mn_ro_sub_' . wp_generate_password( 6, false ),
			'user_pass'  => wp_generate_password( 16 ),
			'role'       => 'subscriber',
		)
	);
	$created_users[] = $sub_id;
	wp_set_current_user( $sub_id );
	$sub_nonce = wp_create_nonce( 'sortnonce' );

	$sub_resp = $reorder->save_post_order(
		array(
			'post_parent' => 0,
			'start'       => 0,
			'nonce'       => $sub_nonce,
			'post_id'     => $sec_b,
			'menu_order'  => 0,
			'excluded'    => array(),
			'post_type'   => $test_post_type,
		)
	);
	$assert_true( null === $sub_resp, 'security: subscriber returns null', $sub_resp, null );
	$assert_true( 0 === (int) get_post( $sec_a )->menu_order && 1 === (int) get_post( $sec_b )->menu_order, 'security: subscriber leaves menu_order untouched' );

	wp_set_current_user( $admin_id );
} finally {
	if ( isset( $count_updates ) ) {
		remove_filter( 'query', $count_updates );
	}
	$cleanup_all();
}

$remaining = get_posts(
	array(
		'post_type'      => $test_post_type,
		'post_status'    => 'any',
		'posts_per_page' => -1,
		'fields'         => 'ids',
	)
);
if ( ! empty( $remaining ) ) {
	$failures[] = 'Cleanup left behind test posts: ' . wp_json_encode( $remaining );
}

echo 'NOTES: ' . wp_json_encode( $notes ) . "\n";

if ( ! empty( $failures ) ) {
	fwrite( STDERR, "FAILURES:\n - " . implode( "\n - ", $failures ) . "\n" );
	exit( 1 );
}

echo "PASS: all save-order checks succeeded.\n";
exit( 0 );
