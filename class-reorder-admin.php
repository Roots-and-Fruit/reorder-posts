<?php
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
 * This class is self-instantiated and should not be used directly
 *
 * @license http://www.gnu.org/licenses/gpl.html GPL
 * @author Ronald Huereca
 * @since 2.0.0
 */
class MN_Reorder_Admin {
	/**
	 * @var $instance
	 * @desc Instance of the admin class
	 * @access private
	 */
	private static $instance = null;

	/**
	 * @var $options
	 * @desc WordPress options for access within this class
	 * @access private
	 */
	private $options = false;

	/**
	 * Class constructor
	 *
	 * Sets definitions
	 * Adds methods to appropriate hooks
	 *
	 * @author Ronald Huereca
	 * @since Reorder 1.1
	 * @access private
	 */
	private function __construct() {
		//Filter to hide the admin panel options
		if ( ! apply_filters( 'metronet_reorder_post_allow_admin', true ) ) {
			return; //Use this filter if you want to disable the admin panel settings for this plugin, including disabling the menu order overrides
		}

		//Initialize actions
		/* Dev note:  If you know what you're doing code-wise, add the menu_order in get_posts, pre_get_posts, or WP_Query, disable these filters, and/or disable the admin settings (see above). */
		add_filter( 'posts_orderby', array( $this, 'modify_menu_order_sql' ), 30, 2 );
		add_action( 'pre_get_posts', array( $this, 'modify_menu_order_pre' ), 30, 1 );

		//Admin Settings
		add_action( 'admin_menu', array( $this, 'add_admin_menu' ) );
		add_action( 'admin_init', array( $this, 'init_admin_settings' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_settings_styles' ) );

		//Plugin settings
		add_filter( 'plugin_action_links_' . REORDER_BASENAME, array( $this, 'add_settings_link' ) );
	}

	//Singleton
	public static function get_instance() {
		if ( null == self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	} //end get_instance

	/**
	 * Initialize options page
	 *
	 * Create plugin options page and callback
	 *
	 * @since 2.0.0
	 * @access public
	 *
	 * @see init
	 *
	 */
	public function add_admin_menu() {
		add_options_page( _x( 'Reorder Posts', 'Plugin Name - Settings Page Title', 'metronet-reorder-posts' ), _x( 'Reorder Posts', 'Plugin Name - Menu Item', 'metronet-reorder-posts' ), 'manage_options', 'metronet-reorder-posts', array( $this, 'options_page' ) );
	}

	/**
	 * Add query output
	 *
	 * Add query output
	 *
	 * @since 1.0.0
	 * @access public
	 *
	 * @see init_admin_settings
	 *
	 * @param array $args {
			@type string $desc Description for the setting.

	}
	 */
	public function add_settings_reorder_query( $args = array() ) {
		//Get options/defaults
		$settings       = $this->get_plugin_options();
		$settings_query = isset( $settings['show_query'] ) ? $settings['show_query'] : 'on';

		printf( '<p><input type="radio" name="metronet-reorder-posts[show_query]" value="on" id="reorder_show_query_yes" %s />&nbsp;<label for="reorder_show_query_yes">%s</label></p>', checked( 'on', $settings_query, false ), esc_html__( 'Yes', 'metronet-reorder-posts' ) );
		printf( '<p><input type="radio" name="metronet-reorder-posts[show_query]" value="off" id="reorder_show_query_no" %s />&nbsp;<label for="reorder_show_query_no">%s</label></p>', checked( 'off', $settings_query, false ), esc_html__( 'No', 'metronet-reorder-posts' ) );
	}

	/**
	 * Optional control so a Paragraph inside a Post Template can show a custom field.
	 */
	public function add_settings_field_post_template_attributes() {
		$options = $this->get_plugin_options();
		$enabled = isset( $options['post_template_attributes'] ) && 'on' === $options['post_template_attributes'];
		printf(
			'<p><label for="reorder_post_template_attributes"><input type="checkbox" name="metronet-reorder-posts[post_template_attributes]" id="reorder_post_template_attributes" value="on" %s /> %s</label></p>',
			checked( $enabled, true, false ),
			esc_html__( 'Enable Attributes on Post Template blocks. A Paragraph inside the template can then show a custom field.', 'metronet-reorder-posts' )
		);
	}

	/**
	 * Add post types to be overridden using menu_order
	 *
	 * Output options for menu_order
	 *
	 * @since 1.0.0
	 * @access public
	 *
	 * @see init_admin_settings
	 *
	 * @param array $args {
			@type string $desc Description for the setting.

	}
	 */
	public function add_settings_field_menu_order( $args = array() ) {
		echo '<p>' . esc_html__( 'Choose a default order for each post type. Menu Order applies that direction to front-end lists. None leaves each query to decide its own order.', 'metronet-reorder-posts' ) . '</p>';
		//Get options/defaults
		$settings            = $this->get_plugin_options();
		$settings_menu_order = isset( $settings['menu_order'] ) ? $settings['menu_order'] : array();
		$post_types          = get_post_types( array(), 'object' );
		$settings_post_types = isset( $settings['post_types'] ) ? $settings['post_types'] : array();

		//Foreach loop to show de awesome post types
		foreach ( $post_types as $post_type_name => $post_type ) {

			//Determine whether to show this post type (show_ui = true)
			$show_ui = (bool) isset( $post_type->show_ui ) ? $post_type->show_ui : false;
			if ( ! $show_ui || 'attachment' === $post_type_name ) {
				continue;
			}

			//Get post type labels for checkbox
			$post_type_value = isset( $settings_post_types[ $post_type_name ] ) ? $settings_post_types[ $post_type_name ] : 'off';
			if ( 'off' === $post_type_value ) {
				continue;
			}
			$post_type_label = isset( $post_type->label ) ? $post_type->label : $post_type_name;
			printf( '<div id="metronet-reorder-posts-%s">', esc_attr( $post_type_name ) );
			printf( '<h3>%s</h3>', esc_html( $post_type_label ) );

			//Get menu order arguments
			$menu_orderby = isset( $settings_menu_order[ $post_type_name ]['orderby'] ) ? $settings_menu_order[ $post_type_name ]['orderby'] : 'none';

			//Output Menu Order Arguments
			printf( '<p>%s</p>', esc_html__( 'Sort by:', 'metronet-reorder-posts' ) );
			printf( '<select name="metronet-reorder-posts[menu_order][%1$s][orderby]">', esc_attr( $post_type_name ) );
			printf( '<option value="none" %s>%s</option>', selected( 'none', $menu_orderby, false ), esc_html__( 'None', 'metronet-reorder-posts' ) );
			printf( '<option value="menu_order" %s>%s</option>', selected( 'menu_order', $menu_orderby, false ), esc_html__( 'Menu Order', 'metronet-reorder-posts' ) );
			echo '</select>';

			//Get order arguments
			$menu_order = isset( $settings_menu_order[ $post_type_name ]['order'] ) ? $settings_menu_order[ $post_type_name ]['order'] : 'DESC'; //DESC is WP_Query default
			//Output Menu Order Arguments
			printf( '<p>%s</p>', esc_html__( 'Sort Order:', 'metronet-reorder-posts' ) );
			printf( '<select name="metronet-reorder-posts[menu_order][%1$s][order]">', esc_attr( $post_type_name ) );
			printf( '<option value="ASC" %s>%s</option>', selected( 'ASC', $menu_order, false ), esc_html__( 'ASC', 'metronet-reorder-posts' ) );
			printf( '<option value="DESC" %s>%s</option>', selected( 'DESC', $menu_order, false ), esc_html__( 'DESC', 'metronet-reorder-posts' ) );
			echo '</select>';

			//Output closing div
			echo '</div>';
		}
	}

	/**
	 * Add post types to be reordered using the plugin
	 *
	 * Output checkboxes for post types
	 *
	 * @since 1.0.0
	 * @access public
	 *
	 * @see init_admin_settings
	 *
	 * @param array $args {
			@type string $desc Description for the setting.

	}
	 */
	public function add_settings_field_post_types( $args = array() ) {
		//Get options/defaults
		$settings            = $this->get_plugin_options();
		$settings_post_types = isset( $settings['post_types'] ) ? $settings['post_types'] : array();
		$post_types          = get_post_types( array(), 'object' );

		//Foreach loop to show de awesome post types
		foreach ( $post_types as $post_type_name => $post_type ) {

			$post_type_object = get_post_type_object( $post_type_name );
			//Determine whether to show this post type (show_ui = true)
			$show_ui = (bool) isset( $post_type->show_ui ) ? $post_type->show_ui : false;
			if ( ! $show_ui || 'attachment' === $post_type_name ) {
				continue;
			}

			//Get post type labels for checkbox
			$post_type_value  = isset( $settings_post_types[ $post_type_name ] ) ? $settings_post_types[ $post_type_name ] : 'off';
			$post_type_label  = isset( $post_type->label ) ? $post_type->label : $post_type_name;
			$post_type_action = '';
			if ( 'on' === $post_type_value ) {

				// Get edit path for post type.
				$edit_path = 'edit.php?post_type=' . $post_type_name;
				if ( 'post' === $post_type_name ) {
					$edit_path = 'edit.php';
				}
				$reorder_path_name = 'reorder-' . $post_type_name;
				if ( 'post' === $post_type_name ) {
					$reorder_path_name = 'reorder-posts';
				}
				$edit_path        = add_query_arg(
					array(
						'page' => $reorder_path_name,
					),
					admin_url( $edit_path )
				);
				if ( ! $post_type_object->show_in_menu || false === $post_type_object->show_in_menu || false === $post_type_object->public ) {
					$edit_path = add_query_arg(
						array(
							'page' => $reorder_path_name,
						),
						admin_url( '/' )
					);
				}
				$post_type_action = ' &mdash; <a href="' . esc_url_raw( $edit_path ) . '" class="button-link">' . esc_html__( 'Reorder', 'metronet-reorder-posts' ) . '</a>';
			}

			//Output post type option
			echo '<div class="mn-rf-type">';
			printf( '<input type="hidden" name="metronet-reorder-posts[post_types][%1$s]" value="off" />', esc_attr( $post_type_name ) );
			$this->render_toggle(
				array(
					'name'    => 'metronet-reorder-posts[post_types][' . $post_type_name . ']',
					'value'   => 'on',
					'checked' => 'on' === $post_type_value,
					'id'      => 'post_type_' . $post_type_name,
					'note'    => $post_type_label,
				)
			);
			if ( $post_type_action ) {
				echo wp_kses_post( $post_type_action );
			}
			echo '</div>';
		}
	}

	/**
	 * Add a settings link to the plugin's options.
	 *
	 * Add a settings link on the WordPress plugin's page.
	 *
	 * @since 2.0.0
	 * @access public
	 *
	 * @see init
	 *
	 * @param array $links Array of plugin options
	 * @return array $links Array of plugin options
	 */
	public function add_settings_link( $links ) {
		$settings_link = sprintf( '<a href="%s">%s</a>', esc_url( admin_url( 'options-general.php?page=metronet-reorder-posts' ) ), _x( 'Settings', 'Plugin settings link on the plugins page', 'metronet-reorder-posts' ) );
			array_unshift( $links, $settings_link );
			return $links;
	}

	/**
	 * Initialize and return plugin options.
	 *
	 * Return an array of plugin options.
	 *
	 * @since 1.0.0
	 * @access public
	 *
	 * @see init
	 *
	 * @return array Plugin options
	 */
	public function get_plugin_options() {
		if ( false === $this->options ) {
			$settings = get_option( 'metronet-reorder-posts' );
		} else {
			$settings = $this->options;
		}

		if ( false === $settings || ! is_array( $settings ) ) {
			$defaults = array(
				'post_types' => array(
					'post' => 'on',
					'page' => 'on',
				),
				'menu_order' => array(),
				'show_query' => 'on',
			);
			update_option( 'metronet-reorder-posts', $defaults );
			return $defaults;
		}
		$this->options = $settings;
		return $settings;
	}

	/**
	 * Initialize options
	 *
	 * Initialize page settings, fields, and sections and their callbacks
	 *
	 * @since 2.0.0
	 * @access public
	 *
	 * @see init
	 *
	 */
	public function init_admin_settings() {
		register_setting( 'metronet-reorder-posts', 'metronet-reorder-posts', array( $this, 'sanitization' ) );

		add_settings_section( 'mn-reorder-post-types', _x( 'Settings', 'plugin settings heading', 'metronet-reorder-posts' ), '__return_empty_string', 'metronet-reorder-posts' );

		add_settings_section( 'mn-reorder-query', _x( 'Reorder Posts', 'plugin settings heading', 'metronet-reorder-posts' ), '__return_empty_string', 'metronet-reorder-posts' );

		add_settings_field( 'mn-post-types', __( 'Post Types', 'metronet-reorder-posts' ), array( $this, 'add_settings_field_post_types' ), 'metronet-reorder-posts', 'mn-reorder-post-types', array( 'desc' => __( 'Select the post types you would like this plugin enabled for.', 'metronet-reorder-posts' ) ) );

		add_settings_field( 'mn-post-template-attributes', __( 'Attributes', 'metronet-reorder-posts' ), array( $this, 'add_settings_field_post_template_attributes' ), 'metronet-reorder-posts', 'mn-reorder-post-types' );

		add_settings_field( 'mn-menu-order', __( 'Defaults', 'metronet-reorder-posts' ), array( $this, 'add_settings_field_menu_order' ), 'metronet-reorder-posts', 'mn-reorder-post-types', array( 'desc' => __( 'Select the menu order for the post types.', 'metronet-reorder-posts' ) ) );

		add_settings_field( 'mn-reorder-query', __( 'Show Posts Query', 'metronet-reorder-posts' ), array( $this, 'add_settings_reorder_query' ), 'metronet-reorder-posts', 'mn-reorder-query', array( 'desc' => __( 'Show a helpful sample query for Reorder posts.', 'metronet-reorder-posts' ) ) );
	}

	//Used for get_posts calls
	public function modify_menu_order_pre( $query ) {
		if ( $query->is_main_query() || is_admin() || is_feed() ) {
			return;
		}

		if ( ! apply_filters( 'metronet_reorder_allow_menu_order', true ) ) {
			return; //Return false to disable this completely
		}

		//Get main plugin options
		$plugin_options = $this->get_plugin_options();
		if ( ! isset( $plugin_options['menu_order'] ) || ! is_array( $plugin_options['menu_order'] ) || empty( $plugin_options['menu_order'] ) ) {
			return;
		}
		$menu_order = $plugin_options['menu_order'];

		//Get main post type
		$main_query = $query->query;
		$post_type  = false;
		if ( isset( $main_query['post_type'] ) ) {
			//Typically set on post type archives or custom WP_Query or get_posts instances
			$post_type = $main_query['post_type'];
		}
		if ( ! $post_type || is_array( $post_type ) ) {
			return;
		}

		//See if suppress filters is on (using get_posts) (if it's not, use modify_menu_order_sql for that)
		if ( ! isset( $main_query['suppress_filters'] ) ) {
			return;
		}

		if ( ! apply_filters( 'metronet_reorder_allow_menu_order_' . $post_type, true ) ) {
			return; //Return false to disable this for each individual post type as opposed to using a global filter above
		}

		//See if we're modifying the menu_order
		$menu_order_orderby = isset( $menu_order[ $post_type ]['orderby'] ) ? $menu_order[ $post_type ]['orderby'] : 'none';
		$menu_order_order   = isset( $menu_order[ $post_type ]['order'] ) ? $menu_order[ $post_type ]['order'] : 'DESC';

		//Return if post type is not be ordered
		if ( $menu_order_orderby === 'none' ) {
			return;
		}

		//Return of $menu_order_order is invalid
		if ( $menu_order_order !== 'ASC' && $menu_order_order !== 'DESC' ) {
			return;
		}

		//Overwrite the orderby clause
		$query->set( 'orderby', 'menu_order' );
		$query->set( 'order', $menu_order_order );
	}

	//Used on homepage archives, post type archives, or custom WP_Query calls
	public function modify_menu_order_sql( $sql_orderby, $query ) {
		if ( is_admin() || is_404() || is_feed() ) {
			return $sql_orderby;
		}

		if ( ! apply_filters( 'metronet_reorder_allow_menu_order', true ) ) {
			return $sql_orderby; //Return false to disable this completely
		}

		//Get main plugin options
		$plugin_options = $this->get_plugin_options();
		if ( ! isset( $plugin_options['menu_order'] ) || ! is_array( $plugin_options['menu_order'] ) || empty( $plugin_options['menu_order'] ) ) {
			return $sql_orderby;
		}

		$menu_order = $plugin_options['menu_order'];

		$main_query = $query->query;

		//Get main post type - Try to get post_type first, if not, see if we're on the main page showing blog posts
		$post_type = false;
		if ( isset( $main_query['post_type'] ) ) {
			//Typically set on post type archives or custom WP_Query or get_posts instances
			$post_type = $main_query['post_type'];
		} elseif ( is_home() ) {
			$post_type = 'post';
		}
		if ( ! $post_type || is_array( $post_type ) ) {
			return $sql_orderby;
		}

		if ( ! apply_filters( 'metronet_reorder_allow_menu_order_' . $post_type, true ) ) {
			return $sql_orderby; //Return false to disable this for each individual post type as opposed to using a global filter above
		}

		//See if we're modifying the menu_order
		$menu_order_orderby = isset( $menu_order[ $post_type ]['orderby'] ) ? $menu_order[ $post_type ]['orderby'] : 'none';
		$menu_order_order   = isset( $menu_order[ $post_type ]['order'] ) ? $menu_order[ $post_type ]['order'] : 'DESC';

		//Return if post type is not be ordered
		if ( $menu_order_orderby === 'none' ) {
			return $sql_orderby;
		}

		//Return of $menu_order_order is invalid
		if ( $menu_order_order !== 'ASC' && $menu_order_order !== 'DESC' ) {
			return $sql_orderby;
		}

		//Overwrite the orderby clause
		global $wpdb;
		$sql_orderby = sprintf( '%s.menu_order %s', $wpdb->posts, $menu_order_order ); //for devs: no sanitization required as tablename is from $wpdb and $menu_order_order is set to match only ASC or DESC.

		//Return
		return $sql_orderby;
	}

	/**
	 * Output options page HTML.
	 *
	 * Output option page HTML and fields/sections.
	 *
	 * @since 2.0.0
	 * @access public
	 *
	 * @see add_admin_menu
	 *
	 */
	/**
	 * Settings screen styles.
	 *
	 * @param string $hook_suffix Current admin page.
	 */
	public function enqueue_settings_styles( $hook_suffix ) {
		if ( 'settings_page_metronet-reorder-posts' !== $hook_suffix ) {
			return;
		}
		$this->enqueue_screen_assets();
	}

	/**
	 * Styles and script shared by Settings and the reorder screens.
	 */
	public function enqueue_screen_assets() {
		wp_enqueue_style( 'metronet-reorder-posts-settings', REORDER_URL . '/css/settings.css', array(), '2.6.2.17' );
		wp_enqueue_script( 'metronet-reorder-posts-settings', REORDER_URL . '/scripts/settings.js', array(), '2.6.2.5', true );
	}

	/**
	 * On and off labels for a switch.
	 *
	 * @return array{enabled:string,disabled:string}
	 */
	private function toggle_labels() {
		return array(
			/* translators: Visible state of an on/off switch. */
			'enabled'  => _x( 'Enabled', 'toggle state', 'metronet-reorder-posts' ),
			/* translators: Visible state of an on/off switch. */
			'disabled' => _x( 'Disabled', 'toggle state', 'metronet-reorder-posts' ),
		);
	}

	/**
	 * A checkbox drawn as a switch, with Enabled or Disabled beside it.
	 *
	 * @param array $args name, value, checked, id, note.
	 */
	private function render_toggle( $args ) {
		$id      = $args['id'];
		$note_id = $id . '-note';
		$state   = $id . '-state';
		$labels  = $this->toggle_labels();
		$checked = ! empty( $args['checked'] );

		echo '<label class="mn-rf-toggle">';
		printf(
			'<input class="mn-rf-toggle__input" type="checkbox" name="%1$s" id="%2$s" value="%3$s" aria-labelledby="%4$s %5$s" %6$s />',
			esc_attr( $args['name'] ),
			esc_attr( $id ),
			esc_attr( $args['value'] ),
			esc_attr( $note_id ),
			esc_attr( $state ),
			checked( $checked, true, false )
		);
		echo '<span class="mn-rf-toggle__track" aria-hidden="true"></span>';
		printf(
			'<span class="mn-rf-toggle__state" id="%1$s" data-enabled="%2$s" data-disabled="%3$s">%4$s</span>',
			esc_attr( $state ),
			esc_attr( $labels['enabled'] ),
			esc_attr( $labels['disabled'] ),
			esc_html( $checked ? $labels['enabled'] : $labels['disabled'] )
		);
		printf( '<span class="mn-rf-toggle__note" id="%1$s">%2$s</span>', esc_attr( $note_id ), esc_html( $args['note'] ) );
		echo '</label>';
	}

	/**
	 * Slanted right edge of a folder tab.
	 */
	public function tab_shoulder() {
		return '<svg class="rf-tabs__shoulder" viewBox="0 0 48 40" width="48" height="40" preserveAspectRatio="none" aria-hidden="true" focusable="false"><path class="rf-tabs__shoulder-face" d="M0 0H20Q26 0 30 8L46 40H0Z" /><path class="rf-tabs__shoulder-edge" d="M0 .5H20Q26 .5 30 8.5L46 40" /></svg>';
	}

	/**
	 * Sticky bar and the about card opened from the logo.
	 */
	/**
	 * Small arrow that marks a link opening in a new tab.
	 */
	private function external_icon() {
		return '<svg class="mn-rf-external__icon" viewBox="0 0 12 12" width="12" height="12" aria-hidden="true" focusable="false"><path fill="none" stroke="currentColor" stroke-width="1.4" stroke-linecap="round" stroke-linejoin="round" d="M4.2 2.2H2.4A1.2 1.2 0 0 0 1.2 3.4v6.2A1.2 1.2 0 0 0 2.4 10.8h6.2a1.2 1.2 0 0 0 1.2-1.2V7.8M6.4 1.2h4.4V5.6M10.6 1.4 5.2 6.8"/></svg>';
	}

	/**
	 * Footer or about link that leaves wp-admin.
	 *
	 * @param string $url   Destination.
	 * @param string $label Visible text.
	 * @param string $class Optional class names.
	 */
	private function external_link( $url, $label, $class = '' ) {
		$class_attr = '' !== $class ? ' class="' . esc_attr( $class ) . '"' : '';
		echo '<a' . $class_attr . ' href="' . esc_url( $url ) . '" target="_blank" rel="noopener noreferrer">';
		echo esc_html( $label );
		echo $this->external_icon(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Static SVG.
		echo '<span class="screen-reader-text">' . esc_html__( '(opens in a new tab)', 'metronet-reorder-posts' ) . '</span>';
		echo '</a>';
	}

	/**
	 * Newspaper mark for an update item.
	 */
	private function newspaper_icon() {
		return '<span class="mn-reorder-updates__tile" aria-hidden="true"><svg class="mn-reorder-updates__icon" viewBox="0 0 24 24" width="20" height="20" focusable="false"><path fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round" d="M4.5 5.5h12v13a1.5 1.5 0 0 0 1.5 1.5 1.5 1.5 0 0 0 1.5-1.5V9.5h-3M4.5 5.5v13a1.5 1.5 0 0 0 1.5 1.5h12M7.5 9.5h6M7.5 13h6M7.5 16.5h4"/></svg></span>';
	}

	/**
	 * Public URL and transient key for the remote updates feed.
	 */
	const UPDATES_URL       = 'https://updates.rootsandfruit.com/reorder-posts.json';
	const UPDATES_TRANSIENT = 'mn_reorder_posts_updates';

	/**
	 * Up to three updates beside the settings form and each reorder screen.
	 *
	 * The list is fetched from updates.rootsandfruit.com, cached in a transient,
	 * and falls back to the local file shipped with the plugin. If neither has
	 * valid items, nothing prints and the screen still loads.
	 */
	public function render_updates() {
		$bundle = $this->update_items();
		if ( empty( $bundle['items'] ) ) {
			return;
		}

		echo '<aside class="mn-reorder-updates" aria-labelledby="mn-reorder-updates-heading" data-source="' . esc_attr( $bundle['source'] ) . '" data-feed-url="' . esc_url( self::UPDATES_URL ) . '">';
		echo '<h2 id="mn-reorder-updates-heading" class="mn-reorder-updates__heading">' . esc_html__( 'Reorder All the Things Updates', 'metronet-reorder-posts' ) . '</h2>';
		echo '<ol class="mn-reorder-updates__feed">';
		foreach ( $bundle['items'] as $item ) {
			echo '<li class="mn-reorder-updates__item">';
			echo $this->newspaper_icon(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Static SVG.
			echo '<div class="mn-reorder-updates__copy">';
			$this->external_link( $item['url'], $item['title'], 'mn-reorder-updates__link' );
			printf(
				'<time class="mn-reorder-updates__date" datetime="%1$s">%2$s</time>',
				esc_attr( $item['date'] ),
				esc_html( $item['display_date'] )
			);
			echo '<p class="mn-reorder-updates__sentence">' . esc_html( $item['sentence'] ) . '</p>';
			echo '</div></li>';
		}
		echo '</ol></aside>';
	}

	/**
	 * Three newest items from the remote updates feed or the local fallback file.
	 *
	 * @return array{source:string,items:array<int, array{title:string,url:string,date:string,display_date:string,sentence:string}>}
	 */
	private function update_items() {
		$data   = null;
		$source = 'remote';
		$cached = get_transient( self::UPDATES_TRANSIENT );

		if ( is_array( $cached ) && isset( $cached['items'] ) && is_array( $cached['items'] ) ) {
			$data   = $cached;
			$source = isset( $cached['source'] ) && is_string( $cached['source'] ) ? $cached['source'] : 'remote';
		} else {
			$response = wp_remote_get(
				self::UPDATES_URL,
				array(
					'timeout'             => 2,
					'redirection'         => 0,
					'reject_unsafe_urls'  => true,
					'limit_response_size' => 65536,
					'user-agent'          => 'WordPress/Reorder-Posts',
				)
			);

			if ( ! is_wp_error( $response ) && 200 === wp_remote_retrieve_response_code( $response ) ) {
				$decoded = json_decode( wp_remote_retrieve_body( $response ), true );
				if ( is_array( $decoded ) && ! empty( $decoded['items'] ) && is_array( $decoded['items'] ) ) {
					$data = array(
						'source' => 'remote',
						'items'  => $decoded['items'],
					);
					set_transient( self::UPDATES_TRANSIENT, $data, 12 * HOUR_IN_SECONDS );
				}
			}

			if ( null === $data ) {
				$path = REORDER_DIR . '/data/updates.json';
				if ( is_readable( $path ) ) {
					$local = wp_json_file_decode( $path, array( 'associative' => true ) );
					if ( is_array( $local ) && ! empty( $local['items'] ) && is_array( $local['items'] ) ) {
						$data   = array(
							'source' => 'local',
							'items'  => $local['items'],
						);
						$source = 'local';
					}
				}
				set_transient(
					self::UPDATES_TRANSIENT,
					null !== $data ? $data : array(
						'source' => 'none',
						'items'  => array(),
					),
					HOUR_IN_SECONDS
				);
			}
		}

		if ( ! is_array( $data ) || empty( $data['items'] ) || ! is_array( $data['items'] ) ) {
			return array(
				'source' => 'none',
				'items'  => array(),
			);
		}

		$items = array();
		foreach ( $data['items'] as $item ) {
			if ( ! is_array( $item ) ) {
				continue;
			}

			$title    = isset( $item['title'] ) ? trim( wp_strip_all_tags( (string) $item['title'] ) ) : '';
			$url      = isset( $item['url'] ) ? esc_url_raw( (string) $item['url'] ) : '';
			$date     = isset( $item['date'] ) ? (string) $item['date'] : '';
			$sentence = isset( $item['sentence'] ) ? trim( wp_strip_all_tags( (string) $item['sentence'] ) ) : '';
			if ( '' === $title || '' === $url || '' === $sentence || ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $date ) ) {
				continue;
			}

			$timestamp = strtotime( $date . ' 12:00:00 UTC' );
			if ( false === $timestamp ) {
				continue;
			}

			$items[] = array(
				'title'        => $title,
				'url'          => $url,
				'date'         => $date,
				'display_date' => date_i18n( get_option( 'date_format' ), $timestamp ),
				'sentence'     => $sentence,
				'timestamp'    => $timestamp,
			);
		}

		usort(
			$items,
			static function ( $a, $b ) {
				return $b['timestamp'] <=> $a['timestamp'];
			}
		);

		$items = array_slice( $items, 0, 3 );
		foreach ( $items as $index => $item ) {
			unset( $items[ $index ]['timestamp'] );
		}

		return array(
			'source' => $source,
			'items'  => $items,
		);
	}

	public function render_footer() {
		echo '<footer class="mn-rf-footer">';
		$this->external_link( 'https://mattc.link/reorder-posts-rating', __( 'Like Reorder Posts? Give us a 5-★ Rating Here', 'metronet-reorder-posts' ), 'mn-rf-footer__rate' );
		echo '<nav class="mn-rf-footer__links" aria-label="' . esc_attr__( 'Plugin links', 'metronet-reorder-posts' ) . '">';
		$this->external_link( 'https://mattc.link/reorder-posts-docs', __( 'Docs', 'metronet-reorder-posts' ) );
		$this->external_link( 'https://mattc.link/reorder-posts-feedback', __( 'Feedback', 'metronet-reorder-posts' ) );
		$this->external_link( 'https://mattc.link/reorder-posts-support', __( 'Support', 'metronet-reorder-posts' ) );
		echo '</nav>';
		echo '<button type="button" class="mn-rf-footer__brand" aria-expanded="false" aria-controls="mn-rf-about">';
		echo '<img src="' . esc_url( REORDER_URL . '/images/rf-logo.svg' ) . '" alt="' . esc_attr__( 'Roots and Fruit', 'metronet-reorder-posts' ) . '" width="120" height="53" />';
		echo '</button>';
		echo '</footer>';

		echo '<aside id="mn-rf-about" class="mn-rf-about" hidden>';
		echo '<p class="mn-rf-about__hello"><strong>' . esc_html__( 'Hi, I’m Matt!', 'metronet-reorder-posts' ) . '</strong>';
		echo '<span>' . esc_html__( 'But most folks call me Cromwell.', 'metronet-reorder-posts' ) . '</span></p>';
		echo '<p>' . esc_html__( 'Roots & Fruit is my digital product practice for solopreneurs and product teams: sustainable growth focused on CX and Marketing driven ROI.', 'metronet-reorder-posts' ) . '</p>';
		echo '<h2 class="mn-rf-about__heading">' . esc_html__( 'About Reorder Posts', 'metronet-reorder-posts' ) . '</h2>';
		echo '<p>' . esc_html__( 'Reorder Posts is a simple plugin for putting posts, pages, and custom post types in the order you want. It has three Core User Commitments:', 'metronet-reorder-posts' ) . '</p>';
		echo '<ol class="mn-rf-about__list">';
		echo '<li>' . esc_html__( 'Drag and drop saves immediately.', 'metronet-reorder-posts' ) . '</li>';
		echo '<li>' . esc_html__( 'Order is stored in WordPress menu order.', 'metronet-reorder-posts' ) . '</li>';
		echo '<li>' . esc_html__( 'Front-end auto-sorting stays off until you turn it on.', 'metronet-reorder-posts' ) . '</li>';
		echo '</ol>';
		echo '<p>' . esc_html__( 'I’m always available to chat.', 'metronet-reorder-posts' ) . ' ';
		$this->external_link( 'https://mattc.link/reorder-posts-feedback', __( 'Here’s my comment form.', 'metronet-reorder-posts' ) );
		echo '</p>';
		echo '<div class="mn-rf-about__person">';
		echo '<img class="mn-rf-about__photo" src="' . esc_url( REORDER_URL . '/images/matt-cromwell.jpg' ) . '" alt="" width="64" height="64" />';
		echo '<p class="mn-rf-about__id"><strong>' . esc_html__( 'Matt Cromwell', 'metronet-reorder-posts' ) . '</strong>';
		echo '<span>' . esc_html__( 'Founder and CGO at Roots and Fruit', 'metronet-reorder-posts' ) . '</span></p>';
		echo '</div></aside>';
	}

	/**
	 * Settings screen.
	 *
	 * One tab per registered section, including sections added by add-ons.
	 * Every section stays in the form so a save on one tab still submits the others.
	 */
	public function options_page() {
		global $wp_settings_sections, $wp_settings_fields;

		$page     = 'metronet-reorder-posts';
		$sections = isset( $wp_settings_sections[ $page ] ) ? $wp_settings_sections[ $page ] : array();
		$tab      = isset( $_GET['tab'] ) ? sanitize_key( wp_unslash( $_GET['tab'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$ids      = array_keys( $sections );
		if ( ! in_array( $tab, $ids, true ) ) {
			$tab = $ids ? $ids[0] : '';
		}
		$title = _x( 'Reorder Posts', 'Plugin Name - Settings Page Title', 'metronet-reorder-posts' );
		?>
		<div class="wrap mn-rf-settings">
			<header class="mn-rf-settings__header">
				<h1 class="mn-rf-settings__title"><?php echo esc_html( $title ); ?></h1>
				<p class="mn-rf-settings__lede"><?php esc_html_e( 'Quickly reorder posts, pages, and custom post types using an intuitive drag-and-drop interface. Reordering supports auto-sorting and nested pages.', 'metronet-reorder-posts' ); ?></p>
			</header>
			<hr class="wp-header-end" />
			<div class="mn-rf-settings__body">
				<div class="mn-reorder-stage">
					<div class="mn-reorder-main">
						<form action="<?php echo esc_url( admin_url( 'options.php' ) ); ?>" method="post">
							<?php settings_fields( $page ); ?>
							<?php if ( $sections ) : ?>
								<div class="rf-tabs">
									<nav class="rf-tabs__list" aria-label="<?php echo esc_attr( $title ); ?>">
										<?php foreach ( $sections as $id => $section ) : ?>
											<?php
											$url    = add_query_arg(
												array(
													'page' => $page,
													'tab'  => $id,
												),
												admin_url( 'options-general.php' )
											);
											$label  = ! empty( $section['title'] ) ? $section['title'] : $id;
											$active = $tab === $id;
											?>
											<a href="<?php echo esc_url( $url ); ?>" class="rf-tabs__tab<?php echo $active ? ' is-current' : ''; ?>"<?php echo $active ? ' aria-current="page"' : ''; ?>>
												<span class="rf-tabs__dot" aria-hidden="true"></span>
												<?php echo esc_html( $label ); ?>
												<?php echo $this->tab_shoulder(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Static SVG markup. ?>
											</a>
										<?php endforeach; ?>
									</nav>
									<div class="rf-tabs__panel">
										<?php foreach ( $sections as $id => $section ) : ?>
											<div class="mn-rf-settings__panel"<?php echo $tab === $id ? '' : ' hidden'; ?>>
												<?php if ( ! empty( $section['title'] ) ) : ?>
													<h2><?php echo esc_html( $section['title'] ); ?></h2>
												<?php endif; ?>
												<?php
												if ( ! empty( $section['callback'] ) ) {
													call_user_func( $section['callback'], $section );
												}
												if ( ! empty( $wp_settings_fields[ $page ][ $id ] ) ) {
													echo '<table class="form-table" role="presentation">';
													do_settings_fields( $page, $id );
													echo '</table>';
												}
												?>
											</div>
										<?php endforeach; ?>
										<?php submit_button(); ?>
									</div>
								</div>
							<?php endif; ?>
						</form>
					</div>
					<?php $this->render_updates(); ?>
				</div>
			</div>
			<?php $this->render_footer(); ?>
		</div>
		<?php
	}

	/**
	 * Sanitize options before they are saved.
	 *
	 * Sanitize and prepare error messages when saving options.
	 *
	 * @since 2.0.0
	 * @access public
	 *
	 * @see init_admin_settings
	 *
	 * @param array $input {
			@type array $post_types
			@type array $menu_order
	}
	 * @return array Sanitized array of options
	 */
	public function sanitization( $input = array() ) {
		if ( ! is_array( $input ) ) {
			$input = array();
		}
		$input['post_template_attributes'] = ( isset( $input['post_template_attributes'] ) && 'on' === $input['post_template_attributes'] ) ? 'on' : 'off';

		//Get post type options
		$post_types = $input['post_types'] ?? array();
		if ( ! empty( $post_types ) ) {
			foreach ( $post_types as $post_type_name => &$value ) {
				if ( 'on' !== $value ) {
					$value = 'off';
				}
			}
			$input['post_types'] = $post_types;
		}

		//Get menu order options
		$menu_order = $input['menu_order'];
		if ( ! empty( $menu_order ) ) {
			foreach ( $post_types as $post_type_name => &$values ) {
				$orderby = isset( $menu_order[ $post_type_name ]['orderby'] ) ? $menu_order[ $post_type_name ]['orderby'] : 'none';
				if ( 'menu_order' !== $orderby ) {
					$menu_order[ $post_type_name ]['orderby'] = 'none';
				}

				$order = isset( $menu_order[ $post_type_name ]['order'] ) ? $menu_order[ $post_type_name ]['order'] : 'DESC';
				if ( 'ASC' !== $order ) {
					$menu_order[ $post_type_name ]['order'] = 'DESC';
				}
			}
			$input['menu_order'] = $menu_order;

		}
		return $input;
	}
} //end class Reorder_Admin

add_action( 'init', 'mn_reorder_admin_instantiate', 15 );
function mn_reorder_admin_instantiate() {
	MN_Reorder_Admin::get_instance();
}
