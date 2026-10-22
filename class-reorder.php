<?php // phpcs:ignore WordPress.Files.FileName.InvalidClassFileName
/**
 * Reorder posts
 *
 * @package    WordPress
 * @subpackage Metronet Reorder Posts plugin
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Reorder posts
 * Adds drag and drop editor for reordering WordPress posts
 *
 * Based on work by Scott Basgaard and Ronald Huereca
 *
 * To use this class, simply instantiate it using an argument to set the post type as follows:
 * new MN_Reorder( array( 'post_type' => 'post', 'order'=> 'ASC' ) );
 *
 * @copyright Copyright (c), Metronet
 * @license http://www.gnu.org/licenses/gpl.html GPL
 * @author Ryan Hellyer <ryan@metronet.no>
 * @since 1.0
 */
final class MN_Reorder {

	/**
	 * @var $post_type
	 * @desc Post type to be reordered
	 * @access private
	 */
	private $post_type;

	/**
	 * @var $posts_per_page
	 * @desc How many posts to show
	 * @access private
	 */
	private $posts_per_page;

	/**
	 * @var $offset
	 * @desc How many posts to offset by
	 * @access private
	 */
	private $offset;

	/**
	 * @var $heading
	 * @desc Admin page heading
	 * @access private
	 */
	private $heading;

	/**
	 * @var $initial
	 * @desc HTML outputted at end of admin page
	 * @access private
	 */
	private $initial;

	/**
	 * @var $final
	 * @desc HTML outputted at end of admin page
	 * @access private
	 */
	private $final;

	/**
	 * @var $post_statush
	 * @desc The post status of posts to be reordered
	 * @access private
	 */
	private $post_status;

	/**
	 * @var $menu_label
	 * @desc Admin page menu label
	 * @access private
	 */
	private $menu_label;

	/**
	 * @var $order
	 * @desc ASC or DESC
	 * @access private
	 */
	private $order;

	/**
	 * @var $reorder_page
	 * @desc Where the reorder interface is being added
	 * @access private
	 */
	private $reorder_page = '';

	/**
	 * Get method for post status
	 *
	 * @author Ronald Huereca <ronald@gmail.com>
	 * @since Reorder 2.1.0
	 * @access public
	 * @returns string $post_status Post Status of Posts
	 */
	public function get_post_status() {
		return $this->post_status;
	}

	/**
	 * Get method for post order
	 *
	 * @author Ronald Huereca <ronald@gmail.com>
	 * @since Reorder 2.1.0
	 * @access public
	 * @returns string $order Order of posts (ASC or DESC)
	 */
	public function get_post_order() {
		return $this->order;
	}

	/**
	 * Get method for posts per page
	 *
	 * @author Ronald Huereca <ronald@gmail.com>
	 * @since Reorder 2.1.0
	 * @access public
	 * @returns int $posts_per_page How many posts to display
	 */
	public function get_posts_per_page() {
		return $this->posts_per_page;
	}

	/**
	 * Get method for post offset used in pagination
	 *
	 * @author Ronald Huereca <ronald@gmail.com>
	 * @since Reorder 2.1.0
	 * @access public
	 * @returns int $offset Offset of posts
	 */
	public function get_offset() {
		return $this->offset;
	}

	/**
	 * Class constructor
	 *
	 * Sets definitions
	 * Adds methods to appropriate hooks
	 *
	 * @author Ryan Hellyer <ryan@metronet.no>
	 * @since Reorder 1.0
	 * @access public
	 * @param array $args    If not set, then uses $defaults instead
	 */
	public function __construct( $args = array() ) {

		// Get posts per page
		$user_id        = get_current_user_id();
		$posts_per_page = get_user_meta( $user_id, 'reorder_items_per_page', true );
		if ( ! is_numeric( $posts_per_page ) ) {
			$posts_per_page = 50;
		}
		$offset = $posts_per_page - 2;

		// Parse arguments
		$defaults = array(
			'post_type'      => 'post',                     // Setting the post type to be reordered
			'order'          => 'ASC',                      // Setting the order of the posts
			'heading'        => __( 'Reorder', 'metronet-reorder-posts' ), // Default text for heading
			'initial'        => '',                         // Initial text displayed before sorting code
			'final'          => '',                         // Initial text displayed before sorting code
			'post_status'    => 'publish',                  // Post status of posts to be reordered
			'menu_label'     => __( 'Reorder', 'metronet-reorder-posts' ), //Menu label for the post type
			'offset'         => $offset,
			'posts_per_page' => $posts_per_page,
		);
		$args     = wp_parse_args( $args, $defaults );

		// Set variables
		$this->post_type = $args['post_type'];
		$this->order     = $args['order'];

		$this->heading     = $args['heading'];
		$this->initial     = $args['initial'];
		$this->final       = $args['final'];
		$this->menu_label  = $args['menu_label'];
		$this->post_status = $args['post_status'];

		//Get offset and posts_per_page
		$this->posts_per_page = absint( $args['posts_per_page'] ); //todo - filterable?
		$this->offset         = absint( $args['offset'] ); //todo - filterable?
		if ( $this->offset > $this->posts_per_page ) {
			$this->offset = $this->posts_per_page;
		}

		// Add actions
		add_filter( 'set-screen-option', array( $this, 'add_screen_option_save' ), 10, 3 );
		add_action( 'wp_ajax_post_sort', array( $this, 'ajax_save_post_order' ) );
		add_action( 'admin_menu', array( $this, 'enable_post_sort' ), 10, 'page' );
		add_action( 'metronet_reorder_posts_interface_' . $this->post_type, array( $this, 'output_interface' ) );
	}
	/**
	 * Adjust the found posts for the offset
	 *
	 * @author Ronald Huereca <ronald@gmail.com>
	 * @since Reorder 2.1.0
	 * @access public
	 * @returns int $found_posts Number of posts
	 */
	public function adjust_offset_pagination( $found_posts, $query ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed
		//This sometimes will have a bug of showing an extra page, but it doesn't break anything, so leaving it for now.
		if ( $found_posts > $this->posts_per_page ) {
			$num_pages   = $found_posts / $this->offset;
			$found_posts = (string) round( $num_pages * $this->posts_per_page );
		}
		return $found_posts;
	}

	/**
	 * Saving the post oder for later use
	 *
	 * @author Ronald Huereca <ronalfy@gmail.com>
	 * @since Reorder 1.0
	 * @access public
	 * @global object $wpdb  The primary global database object used internally by WordPress
	 */
	public function ajax_save_post_order() {
		$return = $this->save_post_order( $_POST ); // phpcs:ignore WordPress.Security.NonceVerification.Missing -- verified inside save_post_order.
		if ( null === $return ) {
			die( '' );
		}
		die( wp_json_encode( $return ) );
	} //end ajax_save_post_order

	/**
	 * Process one post_sort request and return the response payload, or null when rejected.
	 *
	 * @param array $request Request values (defaults to $_POST from ajax_save_post_order).
	 * @return array|null
	 */
	public function save_post_order( array $request ) {
		global $wpdb;

		if ( ! current_user_can( 'edit_pages' ) ) {
			return null;
		}
		$nonce = isset( $request['nonce'] ) ? sanitize_text_field( wp_unslash( $request['nonce'] ) ) : '';
		// Verify nonce value, for security purposes
		if ( ! wp_verify_nonce( $nonce, 'sortnonce' ) ) {
			return null;
		}

		//Get Ajax Vars
		$post_parent     = isset( $request['post_parent'] ) ? absint( $request['post_parent'] ) : 0;
		$post_id         = isset( $request['post_id'] ) ? absint( $request['post_id'] ) : 0;
		$post_menu_order = isset( $request['menu_order'] ) ? absint( $request['menu_order'] ) : 0;
		$post_type       = isset( $request['post_type'] ) ? sanitize_text_field( wp_unslash( $request['post_type'] ) ) : false;

		if ( ! $post_type ) {
			return null;
		}

		//Performance
		remove_action( 'pre_post_update', 'wp_save_post_revision' );

		//Build Initial Return
		$return                = array();
		$return['more_posts']  = false;
		$return['action']      = 'post_sort';
		$return['post_parent'] = $post_parent;
		$return['nonce']       = $nonce;
		$return['post_id']     = $post_id;
		$return['menu_order']  = $post_menu_order;
		$return['post_type']   = $post_type;

		//Build Query
		$query_args = array(
			'post_type'              => $post_type,
			'orderby'                => 'menu_order title',
			'order'                  => $this->order,
			'posts_per_page'         => -1,
			'fields'                 => 'ids',
			'no_found_rows'          => true,
			'suppress_filters'       => true,
			'ignore_sticky_posts'    => true,
			'post_status'            => $this->post_status,
			'post_parent'            => $post_parent,
			'post__not_in'           => $post_id > 0 ? array( $post_id ) : array(),
			'update_post_term_cache' => false,
			'update_post_meta_cache' => false,
		);
		$posts      = new WP_Query( $query_args );
		$map        = self::build_menu_order_map( $posts->posts, 0, $post_id, $post_menu_order );

		if ( ! empty( $map ) ) {
			$when_clauses = array();
			$when_params  = array();
			$id_holders   = array();
			$ids          = array();

			foreach ( $map as $row_id => $order_val ) {
				$when_clauses[] = 'WHEN %d THEN %d';
				$when_params[]  = $row_id;
				$when_params[]  = $order_val;
				$id_holders[]   = '%d';
				$ids[]          = $row_id;
			}

			$when_sql = implode( ' ', $when_clauses );
			$in_sql   = implode( ', ', $id_holders );

			if ( $post_id > 0 ) {
				$params = array_merge( $when_params, array( $post_id, $post_parent ), $ids );
				// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber, PluginCheck.Security.DirectDB.UnescapedDBParameter
				$wpdb->query(
					$wpdb->prepare(
						"UPDATE {$wpdb->posts} SET menu_order = CASE ID {$when_sql} END, post_parent = CASE WHEN ID = %d THEN %d ELSE post_parent END WHERE ID IN ({$in_sql})",
						$params
					)
				);
				// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber, PluginCheck.Security.DirectDB.UnescapedDBParameter
			} else {
				$params = array_merge( $when_params, $ids );
				// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare, PluginCheck.Security.DirectDB.UnescapedDBParameter
				$wpdb->query(
					$wpdb->prepare(
						"UPDATE {$wpdb->posts} SET menu_order = CASE ID {$when_sql} END WHERE ID IN ({$in_sql})",
						$params
					)
				);
				// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare, PluginCheck.Security.DirectDB.UnescapedDBParameter
			}

			foreach ( $ids as $row_id ) {
				clean_post_cache(
					(object) array(
						'ID'        => $row_id,
						'post_type' => $post_type,
					)
				);
			}
		}

		return $return;
	}

	/**
	 * Build the ID => menu_order assignment map for one parent save.
	 *
	 * @param int[] $ordered_sibling_ids Ordered sibling IDs (excluding $post_id when $post_id > 0).
	 * @param int   $menu_order_start    Starting menu_order counter.
	 * @param int   $post_id             Moved post ID, or 0 for an old-parent renumber pass.
	 * @param int   $post_menu_order     Requested menu_order for the moved post.
	 * @return array<int,int>
	 */
	private static function build_menu_order_map( array $ordered_sibling_ids, $menu_order_start, $post_id, $post_menu_order ) {
		$post_id         = absint( $post_id );
		$post_menu_order = absint( $post_menu_order );
		$start           = absint( $menu_order_start );
		$map             = array();

		if ( $post_id > 0 ) {
			$map[ $post_id ] = $post_menu_order;
		}

		foreach ( $ordered_sibling_ids as $sibling_id ) {
			$sibling_id = absint( $sibling_id );
			if ( 0 === $sibling_id || $sibling_id === $post_id ) {
				continue;
			}
			if ( $start === $post_menu_order && $post_id > 0 ) {
				++$start;
			}
			$map[ $sibling_id ] = $start;
			++$start;
		}

		return $map;
	}

	/**
	 * Print styles to admin page
	 *
	 * @author Ryan Hellyer <ryan@metronet.no>
	 * @since Reorder 1.0
	 * @access public
	 * @global string $pagenow Used internally by WordPress to designate what the current page is in the admin panel
	 */
	public function print_styles() {
		MN_Reorder_Admin::get_instance()->enqueue_screen_assets();
		wp_enqueue_style( 'reorderpages_style', REORDER_URL . '/css/admin.css', array( 'metronet-reorder-posts-settings' ), '20261022.3' );
	}

	/**
	 * Print scripts to admin page
	 *
	 * @author Ryan Hellyer <ryan@metronet.no>
	 * @since Reorder 1.0
	 * @access public
	 * @global string $pagenow Used internally by WordPress to designate what the current page is in the admin panel
	 */
	public function print_scripts() {
		wp_enqueue_script( 'jquery-ui-touch-punch', REORDER_URL . '/scripts/jquery.ui.touch-punch.js', array( 'jquery-ui-sortable' ), '0.2.3', true );
		wp_register_script( 'reorder_nested', REORDER_URL . '/scripts/jquery.mjs.nestedSortable.js', array( 'jquery-ui-touch-punch' ), '2.0.1', true );

		wp_enqueue_script( 'reorder_posts', REORDER_URL . '/scripts/sort.js', array( 'reorder_nested' ), '20261022.4', true );
		wp_localize_script(
			'reorder_posts',
			'reorder_posts',
			array(
				'action'       => 'post_sort',
				'expand'       => esc_js( __( 'Expand', 'metronet-reorder-posts' ) ),
				'collapse'     => esc_js( __( 'Collapse', 'metronet-reorder-posts' ) ),
				'sortnonce'    => wp_create_nonce( 'sortnonce' ),
				'hierarchical' => is_post_type_hierarchical( $this->post_type ) ? 'true' : 'false',
			)
		);
	}

	/**
	 * Add submenu
	 *
	 * @author Ryan Hellyer <ryan@metronet.no>
	 * @since Reorder 1.0
	 * @access public
	 */
	public function enable_post_sort() {
		$post_type = $this->post_type;
		if ( 'post' !== $post_type ) {
			$menu_location  = apply_filters( 'metronet_reorder_menu_location_' . $post_type, 'edit.php?post_type=' . $post_type, $post_type );
			$post_type_args = get_post_type_object( $post_type );
			// IF show_ui is false, add it to a hidden parent menu.
			if ( ! $post_type_args->show_ui || false === $post_type_args->show_in_menu || false === $post_type_args->public ) {
				$menu_location = '';
				$hook          = add_submenu_page(
					'',
					apply_filters( 'metronet_reorder_menu_label_' . $post_type, esc_html__( 'Reorder', 'metronet-reorder-posts' ) . ' ' . esc_html( $post_type_args->label ), $post_type ),
					'',
					'edit_pages',
					'reorder-' . $post_type,
					array( $this, 'sort_posts' )
				);
			} else {
				$hook = add_submenu_page(
					$menu_location,
					$this->heading,
					apply_filters( 'metronet_reorder_menu_label_' . $post_type, $this->menu_label, $post_type ),
					'edit_pages',
					'reorder-' . $post_type,
					array( $this, 'sort_posts' )
				);
			}
			$this->reorder_page = add_query_arg( array( 'page' => 'reorder-' . $post_type ), admin_url( $menu_location ) );
		} else {
			$hook               = add_posts_page(
				$this->heading,                     // Page title (unneeded since specified directly)
				apply_filters( 'metronet_reorder_menu_label_' . $post_type, $this->menu_label ),                  // Menu title
				'edit_pages',                       // Capability
				'reorder-posts',                    // Menu slug
				array( $this, 'sort_posts' )        // Callback function
			);
			$this->reorder_page = add_query_arg( array( 'page' => 'reorder-posts' ), admin_url( 'edit.php' ) );
		}
		add_action( "load-$hook", array( $this, 'add_screen_option' ) );
		do_action( 'metronet_reorder_posts_add_menu_' . $post_type, $hook ); //Allow other plugin authors to add scripts/styles to our menu items
		do_action( 'metronet_reorder_menu_url_' . $post_type, $this->reorder_page );
		add_action( 'admin_print_styles-' . $hook, array( $this, 'print_styles' ) );
		add_action( 'admin_print_scripts-' . $hook, array( $this, 'print_scripts' ) );
	}

	/**
	 * Add screen option for setting items per page
	 *
	 * @author Ronald Huereca
	 * @since 2.3.0
	 * @access public
	 */
	public function add_screen_option() {
		$args = array(
			'label'   => __( 'Items per Page', 'metronet_reorder_posts' ),
			'default' => 50,
			'option'  => 'reorder_items_per_page',
		);

		add_screen_option( 'per_page', $args );
	}

	/**
	 * Saves the screen options setting
	 *
	 * @author Ronald Huereca
	 * @since 2.3.0
	 * @access public
	 */
	public function add_screen_option_save( $status, $option, $value ) {
		if ( 'reorder_items_per_page' === $option ) {
			return $value;
		}

		return $status;
	}

	/**
	 * Output the main Reorder Interface
	 *
	 * @author Ryan Hellyer <ryan@metronet.no> and Ronald Huereca <ronalfy@gmail.com>
	 * @since Reorder 2.1.0
	 * @access public
	 * @global string $post_type
	 */
	public function output_interface() {
		$post_count_obj = wp_count_posts( $this->post_type );
		$post_count     = isset( $post_count_obj->{$this->post_status} ) ? absint( $post_count_obj->{$this->post_status} ) : absint( $post_count_obj['publish'] );
		if ( $post_count >= 1000 ) {
			printf(
				'<div class="error"><p>%s</p></div>',
				esc_html(
					sprintf(
						/* translators: %s: formatted post count */
						__( 'There are over %s posts found.  We do not recommend you sort these posts for performance reasons.', 'metronet_reorder_posts' ),
						number_format( $post_count )
					)
				)
			);
		}
		?>
		<div id="reorder-error"></div>
		<div><img src="<?php echo esc_url( admin_url( 'images/loading.gif' ) ); ?>" id="loading-animation" /></div>
		<?php echo esc_html( $this->initial ); ?>
		<?php
		//Output non hierarchical posts
		$page = isset( $_GET['paged'] ) ? absint( $_GET['paged'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( 0 === $page || 1 === $page ) {
			$offset = 0;
		} elseif ( $page > 1 ) {
			$offset = $this->offset * ( $page - 1 );
		}
		printf( '<input type="hidden" id="reorder-offset" value="%s" />', absint( $offset ) );
		add_filter( 'found_posts', array( $this, 'adjust_offset_pagination' ), 10, 2 );
		$post_query = new WP_Query(
			array(
				'post_type'      => $this->post_type,
				'posts_per_page' => $this->posts_per_page,
				'orderby'        => 'menu_order title',
				'order'          => $this->order,
				'post_status'    => $this->post_status,
				'post_parent'    => 0,
				'offset'         => $offset,
			)
		);
		remove_filter( 'found_posts', array( $this, 'adjust_offset_pagination' ), 10, 2 );
		if ( $post_query->have_posts() ) {
			$children_by_parent = array();
			if ( is_post_type_hierarchical( $this->post_type ) ) {
				$frontier = wp_list_pluck( $post_query->posts, 'ID' );
				while ( ! empty( $frontier ) ) {
					$level_map = $this->get_children_by_parent( $frontier );
					$frontier  = array();
					foreach ( $level_map as $parent_id => $level_children ) {
						$children_by_parent[ $parent_id ] = $level_children;
						foreach ( $level_children as $child_post ) {
							$frontier[] = absint( $child_post->ID );
						}
					}
				}
			}
			echo '<div class="mn-reorder-list">';
			echo '<ul id="post-list">';
			while ( $post_query->have_posts() ) {
				global $post;
				$post_query->the_post();
				$this->output_row( $post, $children_by_parent );
			}
			echo '</ul><!-- #post-list -->';

			//Show pagination links
			if ( $post_query->max_num_pages > 1 ) {
				echo '<div id="reorder-pagination">';
				$current_url     = add_query_arg( array( 'paged' => '%#%' ) );
				$pagination_args = array(
					'base'    => $current_url,
					'total'   => $post_query->max_num_pages,
					'current' => ( 0 === $page ) ? 1 : $page,
				);
				echo wp_kses_post( paginate_links( $pagination_args ) );
				echo '</div>';
			}
			echo '</div>';
		} else {
			printf( '<h3>%s</h3>', esc_html__( 'There is nothing to sort at this time', 'metronet-reorder-posts' ) );
		}
		echo esc_html( $this->final );
		$options = get_option( 'metronet-reorder-posts' );

		if ( ! isset( $options['show_query'] ) || 'on' === $options['show_query'] ) :
			printf( '<h3>%s</h3>', esc_html__( 'Reorder Posts Query', 'metronet-reorder-posts' ) );
			printf( '<p>%s</p>', esc_html__( 'You will need custom code to reorder posts.  Here are some example query arguments for getting your content.', 'metronet-reorder-posts' ) );
			$query = "
\$query = array(
    'orderby' => 'menu_order',
    'order' => 'ASC',
    'post_status' => 'publish',
	'post_type' => '{$this->post_type}',
	'posts_per_page' => {$this->posts_per_page}
);
\$posts = get_posts( \$query );
if( ! empty( \$posts ) ) {
     echo '<ul>';
     foreach( \$posts as \$post ) {
          printf( '<li><a href=\"%s\">%s</a></li>', esc_url( get_permalink( \$post->ID ) ), esc_html( \$post->post_title ) );
    }
    echo '</ul>';
}
";
			printf( '<blockquote><pre><code>%s</code></pre></blockquote>', esc_html( $query ) );
		endif;
	}

	/**
	 * Grip that starts a drag. The title stays selectable as text.
	 */
	private function drag_handle() {
		echo '<span class="mn-reorder-handle" aria-hidden="true"><span class="mn-reorder-handle__dots"></span></span>';
	}

	/**
	 * Leaf arrow from the design system, inline after the title.
	 * Points right when closed and rotates down when the children are open.
	 */
	private function expand_control() {
		echo '<button type="button" class="mn-reorder-expand" aria-expanded="false">';
		echo '<svg class="mn-reorder-expand__arrow" viewBox="0 0 36 32" width="16" height="16" aria-hidden="true" focusable="false">';
		echo '<path fill="#c5d4b8" d="M6 5L30 16L6 16Z"/>';
		echo '<path fill="#9aaf86" d="M6 16L30 16L6 27Z"/>';
		echo '<path fill="none" stroke="#40352a" stroke-width="2.6" stroke-linejoin="round" stroke-linecap="round" d="M6 5L30 16L6 27Z"/>';
		echo '</svg>';
		echo '<span class="screen-reader-text">' . esc_html__( 'Expand', 'metronet-reorder-posts' ) . '</span>';
		echo '</button>';
	}

	/**
	 * Load children for one tree level and group at most 100 children per parent ID.
	 *
	 * @param int[] $parent_ids Parent post IDs on the current tree level.
	 * @return array<int,WP_Post[]>
	 */
	private function get_children_by_parent( array $parent_ids ) {
		if ( ! is_post_type_hierarchical( $this->post_type ) ) {
			return array();
		}

		$parent_ids = array_values( array_unique( array_filter( array_map( 'absint', $parent_ids ) ) ) );
		if ( empty( $parent_ids ) ) {
			return array();
		}

		$query = new WP_Query(
			array(
				'post_type'              => $this->post_type,
				'post_status'            => $this->post_status,
				'posts_per_page'         => -1,
				'post_parent__in'        => $parent_ids,
				'orderby'                => 'menu_order',
				'order'                  => $this->order,
				'no_found_rows'          => true,
				'update_post_meta_cache' => false,
				'update_post_term_cache' => false,
			)
		);

		$by_parent = array();
		foreach ( $query->posts as $child ) {
			$parent_id = absint( $child->post_parent );
			if ( ! isset( $by_parent[ $parent_id ] ) ) {
				$by_parent[ $parent_id ] = array();
			}
			if ( count( $by_parent[ $parent_id ] ) < 100 ) {
				$by_parent[ $parent_id ][] = $child;
			}
		}

		return $by_parent;
	}

	/**
	 * Output a single row and any prefetched children.
	 *
	 * @param WP_Post                $post_item          Post object to render.
	 * @param array<int,WP_Post[]>   $children_by_parent Map of parent ID to child posts.
	 */
	private function output_row( $post_item, array $children_by_parent = array() ) {
		global $post;
		$post = $post_item; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
		setup_postdata( $post );
		$children = isset( $children_by_parent[ $post->ID ] ) ? $children_by_parent[ $post->ID ] : array();
		?>
		<li id="list_<?php the_id(); ?>" data-id="<?php the_id(); ?>" data-menu-order="<?php echo absint( $post->menu_order ); ?>" data-parent="<?php echo absint( $post->post_parent ); ?>" data-post-type="<?php echo esc_attr( $post->post_type ); ?>">
			<div class="row">
				<div class="row-content<?php echo is_post_type_hierarchical( $post->post_type ) ? '' : ' non-hierarchical'; ?>">
					<?php $this->drag_handle(); ?>
					<span class="mn-reorder-label">
						<?php the_title(); ?><?php echo ( defined( 'REORDER_DEBUG' ) && true === (bool) REORDER_DEBUG ) ? ' - Menu Order:' . absint( $post->menu_order ) : ''; ?>
						<?php
						if ( ! empty( $children ) ) {
							$this->expand_control();
						}
						?>
					</span>
				</div><!-- .row-content -->
			</div><!-- .row -->
			<?php

			if ( ! empty( $children ) ) {
				echo '<ul class="children">';
				foreach ( $children as $child_post ) {
					$this->output_row( $child_post, $children_by_parent );
				}
				echo '</ul>';
			}
			?>
		</li>
		<?php
	} //end output_row

	/**
	 * Initial HTML output
	 *
	 * @author Ryan Hellyer <ryan@metronet.no> and Ronald Huereca <ronalfy@gmail.com>
	 * @since Reorder 2.1.0
	 * @access public
	 * @global string $post_type
	 */
	public function sort_posts() {
		// Dev note - Settings API not used here because there are no options to save.
		$tabs = array(
			array(
				'url'    => $this->reorder_page,
				'label'  => $this->heading,
				'get'    => 'main',
				'action' => 'metronet_reorder_posts_interface_' . $this->post_type,
			),
		);
		$tabs = apply_filters( 'metronet_reorder_posts_tabs_' . $this->post_type, (array) $tabs );

		$active_tab = isset( $_GET['tab'] ) ? sanitize_key( wp_unslash( $_GET['tab'] ) ) : 'main'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$do_action  = '';
		$matched    = false;
		foreach ( $tabs as $tab ) {
			$tab_get = isset( $tab['get'] ) ? (string) $tab['get'] : '';
			if ( $active_tab === $tab_get ) {
				$do_action = isset( $tab['action'] ) ? $tab['action'] : '';
				$matched   = true;
				break;
			}
		}
		if ( ! $matched && ! empty( $tabs[0]['action'] ) ) {
			$do_action  = $tabs[0]['action'];
			$active_tab = isset( $tabs[0]['get'] ) ? (string) $tabs[0]['get'] : 'main';
		}

		$show_tabs = count( $tabs ) > 1;
		$admin     = MN_Reorder_Admin::get_instance();
		?>
		<div class="wrap mn-rf-settings">
			<header class="mn-rf-settings__header">
				<h1 class="mn-rf-settings__title"><?php echo esc_html( $this->heading ); ?></h1>
			</header>
			<hr class="wp-header-end" />
			<div class="mn-rf-settings__body">
				<div class="mn-reorder-stage">
					<div class="mn-reorder-main">
						<?php if ( $show_tabs ) : ?>
							<div class="rf-tabs">
								<nav class="rf-tabs__list" aria-label="<?php echo esc_attr( $this->heading ); ?>">
									<?php foreach ( $tabs as $tab ) : ?>
										<?php
										$tab_get   = isset( $tab['get'] ) ? (string) $tab['get'] : '';
										$tab_url   = isset( $tab['url'] ) ? $tab['url'] : '';
										$tab_label = isset( $tab['label'] ) ? $tab['label'] : '';
										$current   = $active_tab === $tab_get;
										?>
										<a href="<?php echo esc_url( $tab_url ); ?>" class="rf-tabs__tab<?php echo $current ? ' is-current' : ''; ?>"<?php echo $current ? ' aria-current="page"' : ''; ?>>
											<span class="rf-tabs__dot" aria-hidden="true"></span>
											<?php echo esc_html( $tab_label ); ?>
											<?php echo $admin->tab_shoulder(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Static SVG markup. ?>
										</a>
									<?php endforeach; ?>
								</nav>
								<div class="rf-tabs__panel">
									<?php
									if ( $do_action ) {
										do_action( $do_action );
									}
									?>
								</div>
							</div>
						<?php else : ?>
							<div class="rf-tabs__panel">
								<?php
								if ( $do_action ) {
									do_action( $do_action );
								}
								?>
							</div>
						<?php endif; ?>
					</div>
					<?php $admin->render_updates(); ?>
				</div>
			</div>
			<?php $admin->render_footer(); ?>
		</div>
		<?php
	} //end sort_posts
}
