( function ( wp ) {
	var el = wp.element.createElement;
	var addFilter = wp.hooks.addFilter;
	var createHigherOrderComponent = wp.compose.createHigherOrderComponent;
	var InspectorControls = wp.blockEditor.InspectorControls;
	var ToggleControl = wp.components.ToggleControl;
	var SelectControl = wp.components.SelectControl;
	var ToolsPanel = wp.components.__experimentalToolsPanel;
	var ToolsPanelItem = wp.components.__experimentalToolsPanelItem;
	var useSelect = wp.data.useSelect;
	var __ = wp.i18n.__;

	function fieldsFor( postType ) {
		var all = window.mnReorderPostFields || {};
		return all[ postType ] || [];
	}

	function parseMeta( orderBy ) {
		if ( ! orderBy ) {
			return null;
		}
		if ( orderBy.indexOf( 'mnmeta_num_' ) === 0 ) {
			return { kind: 'num', key: orderBy.slice( 'mnmeta_num_'.length ) };
		}
		if ( orderBy.indexOf( 'mnmeta_alpha_' ) === 0 ) {
			return { kind: 'alpha', key: orderBy.slice( 'mnmeta_alpha_'.length ) };
		}
		return null;
	}

	function tokenFor( field ) {
		var numeric = field.type === 'number' || field.type === 'integer';
		return ( numeric ? 'mnmeta_num_' : 'mnmeta_alpha_' ) + field.key;
	}

	var withCustomOrder = createHigherOrderComponent( function ( BlockEdit ) {
		return function ( props ) {
			var query = props.attributes.query || {};
			var postType = query.postType || 'post';
			var allowed = useSelect( function ( select ) {
				var variation = select( 'core/blocks' ).getActiveBlockVariation( 'core/query', props.attributes );
				return variation && variation.allowedControls ? variation.allowedControls : null;
			}, [ props.attributes ] );

			if ( props.name !== 'core/query' || query.inherit ) {
				return el( BlockEdit, props );
			}
			if ( allowed && allowed.indexOf( 'order' ) === -1 ) {
				return el( BlockEdit, props );
			}

			var fields = fieldsFor( postType );
			var meta = parseMeta( query.orderBy );
			var checked = query.orderBy === 'reorder';
			var selected = meta ? meta.key : '';

			function write( next ) {
				props.setAttributes( { query: Object.assign( {}, query, next ) } );
			}

			function setCustom( on ) {
				if ( on ) {
					write( { orderBy: 'reorder', order: 'asc' } );
					return;
				}
				if ( query.orderBy === 'reorder' ) {
					write( { orderBy: 'date', order: 'desc' } );
				}
			}

			function setField( key ) {
				if ( ! key ) {
					if ( meta ) {
						write( { orderBy: 'date', order: 'desc' } );
					}
					return;
				}
				var field = null;
				fields.forEach( function ( item ) {
					if ( item.key === key ) {
						field = item;
					}
				} );
				if ( ! field ) {
					return;
				}
				write( { orderBy: tokenFor( field ), order: 'asc' } );
			}

			function setDirection( order ) {
				write( { order: order } );
			}

			var fieldOptions = [ { label: __( 'None', 'metronet-reorder-posts' ), value: '' } ];
			fields.forEach( function ( field ) {
				fieldOptions.push( { label: field.label, value: field.key } );
			} );

			var numeric = meta && meta.kind === 'num';
			var directionOptions = numeric
				? [
					{ label: __( 'Lowest to highest', 'metronet-reorder-posts' ), value: 'asc' },
					{ label: __( 'Highest to lowest', 'metronet-reorder-posts' ), value: 'desc' }
				]
				: [
					{ label: __( 'A to Z', 'metronet-reorder-posts' ), value: 'asc' },
					{ label: __( 'Z to A', 'metronet-reorder-posts' ), value: 'desc' }
				];

			var fieldHelp = fields.length
				? __( 'Sort by a field saved on this content. Custom Order stays off while a field is chosen.', 'metronet-reorder-posts' )
				: __( 'This type has no fields to sort by.', 'metronet-reorder-posts' );

			return el(
				wp.element.Fragment,
				null,
				el( BlockEdit, props ),
				el(
					InspectorControls,
					null,
					el(
						ToolsPanel,
						{
							label: __( 'Reorder', 'metronet-reorder-posts' ),
							resetAll: function () {
								write( { orderBy: 'date', order: 'desc' } );
							},
							panelId: props.clientId
						},
						el(
							ToolsPanelItem,
							{
								label: __( 'Custom Order', 'metronet-reorder-posts' ),
								hasValue: function () {
									return checked;
								},
								onDeselect: function () {
									setCustom( false );
								},
								isShownByDefault: true,
								panelId: props.clientId
							},
							el( ToggleControl, {
								label: __( 'Custom Order', 'metronet-reorder-posts' ),
								help: __( 'Custom Order uses the order saved in Reorder Posts.', 'metronet-reorder-posts' ),
								checked: checked,
								onChange: setCustom
							} )
						),
						el(
							ToolsPanelItem,
							{
								label: __( 'Sort by field', 'metronet-reorder-posts' ),
								hasValue: function () {
									return !! meta;
								},
								onDeselect: function () {
									setField( '' );
								},
								isShownByDefault: true,
								panelId: props.clientId
							},
							el( SelectControl, {
								label: __( 'Sort by field', 'metronet-reorder-posts' ),
								value: checked ? '' : selected,
								options: fieldOptions,
								help: fieldHelp,
								disabled: checked || ! fields.length,
								onChange: setField
							} ),
							meta && ! checked
								? el( SelectControl, {
									label: __( 'Direction', 'metronet-reorder-posts' ),
									value: query.order === 'desc' ? 'desc' : 'asc',
									options: directionOptions,
									onChange: setDirection
								} )
								: null
						)
					)
				)
			);
		};
	}, 'withReorderPostsCustomOrder' );

	addFilter( 'editor.BlockEdit', 'metronet-reorder-posts/query-order', withCustomOrder );

	if ( window.mnReorderPostTemplateAttributes ) {
		wp.blocks.registerBlockBindingsSource( {
			name: 'metronet-reorder-posts/post-meta',
			label: __( 'Post Meta', 'metronet-reorder-posts' ),
			usesContext: [ 'postId', 'postType' ],
			getFieldsList: function ( _ref ) {
				var select = _ref.select;
				var context = _ref.context || {};
				var postType = context.postType;
				if ( ! postType && select ) {
					var clientId = select( 'core/block-editor' ).getSelectedBlockClientId();
					var parents = clientId ? select( 'core/block-editor' ).getBlockParents( clientId ) : [];
					var ids = parents.concat( clientId ? [ clientId ] : [] );
					for ( var i = ids.length - 1; i >= 0; i-- ) {
						var block = select( 'core/block-editor' ).getBlock( ids[ i ] );
						var query = block && block.attributes && block.attributes.query;
						if ( block && block.name === 'core/query' && query && query.postType ) {
							postType = query.postType;
							break;
						}
					}
				}
				if ( ! postType ) {
					return [];
				}
				var fields = ( window.mnReorderPostFields || {} )[ postType ] || [];
				return fields.map( function ( field ) {
					return {
						label: field.label,
						type: 'string',
						args: { key: field.key }
					};
				} );
			},
			getValues: function ( _ref ) {
				var select = _ref.select;
				var bindings = _ref.bindings;
				var context = _ref.context || {};
				var record = context.postId
					? select( 'core' ).getEntityRecord( 'postType', context.postType, context.postId )
					: null;
				var values = {};
				Object.keys( bindings ).forEach( function ( attribute ) {
					var key = bindings[ attribute ].args && bindings[ attribute ].args.key;
					var raw = record && record.meta ? record.meta[ key ] : '';
					values[ attribute ] = raw === undefined || raw === null ? '' : String( raw );
				} );
				return values;
			},
			canUserEditValue: function () {
				return false;
			}
		} );
	}
}( window.wp ) );
