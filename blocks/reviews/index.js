// blocks/reviews/index.js

( function( blocks, element, blockEditor, components ) {
    if ( ! blocks || ! element ) {
        return;
    }

    var el = element.createElement;
    var useBlockProps = blockEditor.useBlockProps;
    var InspectorControls = blockEditor.InspectorControls;
    var SelectControl = components.SelectControl;
    var ServerSideRender = wp.serverSideRender;
    var ToggleControl = components.ToggleControl; // Взимаме Toggle компонента
    var PanelBody = components.PanelBody;         // По-добре да ползваме променлива

    var locationOptions = window.grpData ? window.grpData.locations : [];

    blocks.registerBlockType( 'grp/reviews', {
        edit: function( props ) {
            var attributes = props.attributes;
            var setAttributes = props.setAttributes;

            return [
                el( InspectorControls, { key: 'inspector' },
                    el( PanelBody, { title: 'Layout Settings', initialOpen: true },
                        el( SelectControl, {
                            label: 'Display Style',
                            value: attributes.layout,
                            options: [
                                { label: 'Grid', value: 'grid' },
                                { label: 'List', value: 'list' },
                                { label: 'Slider', value: 'slider' },
                                { label: 'Badge', value: 'badge' },
                            ],
                            onChange: function( val ) { setAttributes( { layout: val } ); }
                        } ),
                        el( SelectControl, {
                            label: 'Select Location',
                            value: attributes.place_id,
                            options: locationOptions,
                            onChange: function( val ) { setAttributes( { place_id: val } ); }
                        } ),
                        el( ToggleControl, {
                            label: 'Enable SEO Schema',
                            help: attributes.schema ? 'Structured data is enabled.' : 'Structured data is disabled (recommended for secondary widgets).',
                            checked: attributes.schema,
                            onChange: function( val ) { setAttributes( { schema: val } ); }
                        } )
                    )
                ),
                el( 'div', useBlockProps( { key: 'visual' } ),
                    el( ServerSideRender, {
                        block: 'grp/reviews',
                        attributes: attributes,
                    } )
                )
            ];
        },
        save: function() {
            return null;
        },
    } );
} )(
    window.wp.blocks,
    window.wp.element,
    window.wp.blockEditor,
    window.wp.components
);