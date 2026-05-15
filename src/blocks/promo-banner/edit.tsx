import { __ } from '@wordpress/i18n';
import {
	useBlockProps,
	RichText,
	InspectorControls,
} from '@wordpress/block-editor';
import { PanelBody, TextControl } from '@wordpress/components';

type Attrs = {
	headline: string;
	body: string;
	linkText: string;
	linkUrl: string;
};

export default function Edit( {
	attributes,
	setAttributes,
}: {
	attributes: Attrs;
	setAttributes: ( a: Partial< Attrs > ) => void;
} ) {
	const blockProps = useBlockProps( {
		className: 'starter-child-promo-banner',
	} );
	return (
		<>
			<InspectorControls>
				<PanelBody title={ __( 'Promo banner', 'starter-child' ) }>
					<TextControl
						label={ __( 'Link URL', 'starter-child' ) }
						value={ attributes.linkUrl }
						onChange={ ( v ) => setAttributes( { linkUrl: v } ) }
					/>
				</PanelBody>
			</InspectorControls>
			<aside { ...blockProps }>
				<RichText
					tagName="strong"
					value={ attributes.headline }
					onChange={ ( v ) => setAttributes( { headline: v } ) }
					placeholder={ __( 'Headline…', 'starter-child' ) }
				/>
				<RichText
					tagName="p"
					value={ attributes.body }
					onChange={ ( v ) => setAttributes( { body: v } ) }
					placeholder={ __( 'Body…', 'starter-child' ) }
				/>
				<RichText
					tagName="span"
					value={ attributes.linkText }
					onChange={ ( v ) => setAttributes( { linkText: v } ) }
					placeholder={ __( 'Link text…', 'starter-child' ) }
				/>
			</aside>
		</>
	);
}
