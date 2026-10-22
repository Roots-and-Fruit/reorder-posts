<?php
/**
 * Connect the Query Loop block to the order saved by Reorder Posts.
 *
 * @package ReorderPosts
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Adds Custom Order to the Query Loop block.
 */
final class MN_Reorder_Blocks {

	/**
	 * Register hooks.
	 */
	public static function init() {
		add_action( 'init', array( __CLASS__, 'register_post_meta_source' ) );
		add_action( 'enqueue_block_editor_assets', array( __CLASS__, 'enqueue_editor' ) );
		add_action( 'rest_api_init', array( __CLASS__, 'register_rest_order' ) );
		add_filter( 'query_loop_block_query_vars', array( __CLASS__, 'map_query_vars' ), 10, 2 );
	}

	/**
	 * Editor script that adds Custom Order to the Query Loop Order by menu.
	 */
	public static function enqueue_editor() {
		wp_enqueue_script(
			'metronet-reorder-posts-block-order',
			REORDER_URL . '/scripts/block-order.js',
			array( 'wp-blocks', 'wp-element', 'wp-compose', 'wp-hooks', 'wp-block-editor', 'wp-components', 'wp-i18n', 'wp-data' ),
			filemtime( REORDER_DIR . '/scripts/block-order.js' ),
			true
		);

		$fields = array();
		foreach ( get_post_types( array( 'show_in_rest' => true ), 'names' ) as $post_type ) {
			$fields[ $post_type ] = self::sortable_fields( 'post', $post_type );
		}
		wp_add_inline_script(
			'metronet-reorder-posts-block-order',
			'window.mnReorderPostFields = ' . wp_json_encode( $fields ) . '; window.mnReorderPostTemplateAttributes = ' . wp_json_encode( self::post_template_attributes_enabled() ) . ';',
			'before'
		);
	}

	/**
	 * Whether the Post Template Attributes setting is on.
	 *
	 * @return bool
	 */
	public static function post_template_attributes_enabled() {
		$options = get_option( 'metronet-reorder-posts', array() );
		return is_array( $options ) && isset( $options['post_template_attributes'] ) && 'on' === $options['post_template_attributes'];
	}

	/**
	 * Front-end source for a Paragraph bound to a public custom field.
	 */
	public static function register_post_meta_source() {
		if ( ! function_exists( 'register_block_bindings_source' ) ) {
			return;
		}
		register_block_bindings_source(
			'metronet-reorder-posts/post-meta',
			array(
				'label'              => _x( 'Post Meta', 'block bindings source', 'metronet-reorder-posts' ),
				'get_value_callback' => array( __CLASS__, 'get_post_meta_binding_value' ),
				'uses_context'       => array( 'postId', 'postType' ),
			)
		);
	}

	/**
	 * @param array    $source_args    Binding args, including the meta key.
	 * @param WP_Block $block_instance Block being rendered.
	 * @return string|null
	 */
	public static function get_post_meta_binding_value( $source_args, $block_instance ) {
		if ( empty( $source_args['key'] ) || empty( $block_instance->context['postId'] ) ) {
			return null;
		}
		$key = (string) $source_args['key'];
		if ( is_protected_meta( $key, 'post' ) ) {
			return null;
		}
		$post_id   = (int) $block_instance->context['postId'];
		$post_type = isset( $block_instance->context['postType'] ) ? (string) $block_instance->context['postType'] : (string) get_post_type( $post_id );
		$keys      = get_registered_meta_keys( 'post', $post_type );
		if ( empty( $keys[ $key ]['show_in_rest'] ) || empty( $keys[ $key ]['single'] ) ) {
			return null;
		}
		$value = get_post_meta( $post_id, $key, true );
		if ( is_array( $value ) || is_object( $value ) ) {
			return null;
		}
		return (string) $value;
	}

	/**
	 * Allow orderby=reorder on post REST routes so the editor preview can load.
	 */
	public static function register_rest_order() {
		$post_types = get_post_types( array( 'show_in_rest' => true ), 'names' );
		foreach ( $post_types as $post_type ) {
			add_filter(
				"rest_{$post_type}_collection_params",
				static function ( $params ) use ( $post_type ) {
					return MN_Reorder_Blocks::allow_rest_orderby( $params, $post_type );
				}
			);
			add_filter(
				"rest_{$post_type}_query",
				static function ( $args, $request ) use ( $post_type ) {
					return MN_Reorder_Blocks::map_rest_query( $args, $request, $post_type );
				},
				10,
				2
			);
		}
	}

	/**
	 * Add the custom order value to the REST orderby list.
	 *
	 * @param array $params Collection parameters.
	 * @return array
	 */
	/**
	 * Registered single fields the editor is allowed to sort by.
	 *
	 * @param string $object_type post or term.
	 * @param string $subtype     Post type or taxonomy.
	 * @return array<int, array<string, string>>
	 */
	public static function sortable_fields( $object_type, $subtype ) {
		$keys   = get_registered_meta_keys( $object_type, $subtype );
		$fields = array();
		if ( ! is_array( $keys ) ) {
			return $fields;
		}
		foreach ( $keys as $key => $args ) {
			if ( ! is_string( $key ) || '' === $key || '_' === $key[0] || ! is_array( $args ) ) {
				continue;
			}
			if ( empty( $args['show_in_rest'] ) || empty( $args['single'] ) ) {
				continue;
			}
			$type = isset( $args['type'] ) ? $args['type'] : 'string';
			if ( ! in_array( $type, array( 'string', 'number', 'integer' ), true ) ) {
				continue;
			}
			$fields[] = array(
				'key'   => $key,
				'type'  => $type,
				'label' => ucfirst( str_replace( array( '-', '_' ), ' ', $key ) ),
			);
		}
		return $fields;
	}

	/**
	 * Read a mnmeta_num_{key} or mnmeta_alpha_{key} order value.
	 *
	 * @param string $orderby Requested orderby.
	 * @return array<string, string>|null
	 */
	public static function parse_meta_orderby( $orderby ) {
		$kind = '';
		$key  = '';
		if ( 0 === strpos( $orderby, 'mnmeta_num_' ) ) {
			$kind = 'num';
			$key  = substr( $orderby, strlen( 'mnmeta_num_' ) );
		} elseif ( 0 === strpos( $orderby, 'mnmeta_alpha_' ) ) {
			$kind = 'alpha';
			$key  = substr( $orderby, strlen( 'mnmeta_alpha_' ) );
		}
		if ( '' === $kind || '' === $key || sanitize_key( $key ) !== $key ) {
			return null;
		}
		return array(
			'kind' => $kind,
			'key'  => $key,
		);
	}

	/**
	 * Whether this post type registered that field for this sort.
	 *
	 * @param string $post_type Post type.
	 * @param string $key       Meta key.
	 * @param string $kind      num or alpha.
	 * @return bool
	 */
	public static function allows_meta_sort( $post_type, $key, $kind ) {
		foreach ( self::sortable_fields( 'post', $post_type ) as $field ) {
			$field_kind = ( 'number' === $field['type'] || 'integer' === $field['type'] ) ? 'num' : 'alpha';
			if ( $field['key'] === $key && $field_kind === $kind ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * @param array  $query Query vars.
	 * @param string $key   Meta key.
	 * @param string $kind  num or alpha.
	 * @param string $order ASC or DESC.
	 * @return array
	 */
	public static function with_meta_sort( $query, $key, $kind, $order ) {
		$query['orderby']    = ( 'num' === $kind ) ? 'meta_value_num' : 'meta_value';
		$query['order']      = $order;
		$query['meta_key']   = $key;
		$query['meta_query'] = array(
			'relation' => 'OR',
			array(
				'key'     => $key,
				'compare' => 'NOT EXISTS',
			),
			array(
				'key'     => $key,
				'compare' => 'EXISTS',
			),
		);
		return $query;
	}

	/**
	 * Add Custom Order and the registered field sorts to the REST orderby list.
	 *
	 * @param array  $params    Collection parameters.
	 * @param string $post_type Post type.
	 * @return array
	 */
	public static function allow_rest_orderby( $params, $post_type ) {
		if ( ! isset( $params['orderby']['enum'] ) || ! is_array( $params['orderby']['enum'] ) ) {
			return $params;
		}
		$extra = array( 'reorder' );
		foreach ( self::sortable_fields( 'post', $post_type ) as $field ) {
			$prefix  = ( 'number' === $field['type'] || 'integer' === $field['type'] ) ? 'mnmeta_num_' : 'mnmeta_alpha_';
			$extra[] = $prefix . $field['key'];
		}
		foreach ( $extra as $value ) {
			if ( ! in_array( $value, $params['orderby']['enum'], true ) ) {
				$params['orderby']['enum'][] = $value;
			}
		}
		return $params;
	}

	/**
	 * Turn a Custom Order request into menu_order ascending.
	 *
	 * @param array           $args    Query args.
	 * @param WP_REST_Request $request Request.
	 * @return array
	 */
	/**
	 * @param array           $args      Query args.
	 * @param WP_REST_Request $request   Request.
	 * @param string          $post_type Post type for this route.
	 * @return array
	 */
	public static function map_rest_query( $args, $request, $post_type ) {
		$orderby = isset( $request['orderby'] ) ? (string) $request['orderby'] : '';
		if ( 'reorder' === $orderby ) {
			$args['orderby'] = 'menu_order';
			$args['order']   = 'ASC';
			return $args;
		}
		$parsed = self::parse_meta_orderby( $orderby );
		if ( ! $parsed || ! self::allows_meta_sort( $post_type, $parsed['key'], $parsed['kind'] ) ) {
			return $args;
		}
		$order = ( isset( $args['order'] ) && 'DESC' === strtoupper( (string) $args['order'] ) ) ? 'DESC' : 'ASC';
		return self::with_meta_sort( $args, $parsed['key'], $parsed['kind'], $order );
	}

	/**
	 * Turn Custom Order into menu_order ascending on the public page.
	 *
	 * @param array $query Query vars for the Query Loop block.
	 * @return array
	 */
	/**
	 * @param array         $query Query vars.
	 * @param WP_Block|null $block Block instance.
	 * @return array
	 */
	public static function map_query_vars( $query, $block = null ) {
		$orderby = isset( $query['orderby'] ) ? (string) $query['orderby'] : '';
		if ( 'reorder' === $orderby ) {
			$query['orderby'] = 'menu_order';
			$query['order']   = 'ASC';
			return $query;
		}
		$parsed = self::parse_meta_orderby( $orderby );
		if ( ! $parsed ) {
			return $query;
		}
		$post_type = 'post';
		if ( $block instanceof WP_Block && isset( $block->context['query']['postType'] ) ) {
			$post_type = (string) $block->context['query']['postType'];
		} elseif ( isset( $query['post_type'] ) && is_string( $query['post_type'] ) ) {
			$post_type = $query['post_type'];
		}
		if ( ! self::allows_meta_sort( $post_type, $parsed['key'], $parsed['kind'] ) ) {
			return $query;
		}
		$order = ( isset( $query['order'] ) && 'DESC' === strtoupper( (string) $query['order'] ) ) ? 'DESC' : 'ASC';
		return self::with_meta_sort( $query, $parsed['key'], $parsed['kind'], $order );
	}
}

MN_Reorder_Blocks::init();
