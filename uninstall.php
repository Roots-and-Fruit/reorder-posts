<?php
if ( ! defined( 'ABSPATH' ) && ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit();
}
delete_option( 'metronet-reorder-posts' );
delete_transient( 'mn_reorder_posts_updates' );
