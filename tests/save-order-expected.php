<?php
/**
 * Frozen hand-checked fixtures for Reorder Posts save order (Issue #42).
 *
 * Do not edit these expected maps to make a failing check pass.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

return array(
	'drop_e_to_1' => array(
		'initial'    => array(
			'Row A' => 0,
			'Row B' => 1,
			'Row C' => 2,
			'Row D' => 3,
			'Row E' => 4,
		),
		'moved'      => 'Row E',
		'menu_order' => 1,
		'expected'   => array(
			'Row A' => 0,
			'Row E' => 1,
			'Row B' => 2,
			'Row C' => 3,
			'Row D' => 4,
		),
	),
	'drop_a_to_4' => array(
		'initial'    => array(
			'Row A' => 0,
			'Row B' => 1,
			'Row C' => 2,
			'Row D' => 3,
			'Row E' => 4,
		),
		'moved'      => 'Row A',
		'menu_order' => 4,
		'expected'   => array(
			'Row B' => 0,
			'Row C' => 1,
			'Row D' => 2,
			'Row E' => 3,
			'Row A' => 4,
		),
	),
	'paged_offset_48' => array(
		'initial'    => array(
			'Row 00' => 0,
			'Row 01' => 1,
			'Row 02' => 2,
			'Row 03' => 3,
			'Row 04' => 4,
			'Row 05' => 5,
			'Row 06' => 6,
			'Row 07' => 7,
			'Row 08' => 8,
			'Row 09' => 9,
		),
		'moved'      => 'Row 09',
		'menu_order' => 48,
		'expected'   => array(
			'Row 00' => 0,
			'Row 01' => 1,
			'Row 02' => 2,
			'Row 03' => 3,
			'Row 04' => 4,
			'Row 05' => 5,
			'Row 06' => 6,
			'Row 07' => 7,
			'Row 08' => 8,
			'Row 09' => 48,
		),
	),
	'parent_change' => array(
		'top_initial'      => array(
			'Parent Alpha' => 0,
			'Moving Beta'  => 1,
			'Top Gamma'    => 2,
		),
		'children_initial' => array(
			'Child One' => 0,
			'Child Two' => 1,
		),
		'parent_key'       => 'Parent Alpha',
		'moved'            => 'Moving Beta',
		'new_menu_order'   => 1,
		'expected_new_parent_orders' => array(
			'Child One'   => 0,
			'Moving Beta' => 1,
			'Child Two'   => 2,
		),
		'expected_old_parent_orders' => array(
			'Parent Alpha' => 0,
			'Top Gamma'    => 1,
		),
	),
);
