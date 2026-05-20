/**
 * External dependencies
 */
import { Button, Modal, TextControl } from '@wordpress/components';
import { useDispatch, useSelect } from '@wordpress/data';
import { useEffect, useState } from '@wordpress/element';
import { __ } from '@wordpress/i18n';

/**
 * Internal dependencies
 */
import { STORE_NAME } from '../../data/store';
import type { CustomerTag } from '../../data/types';

interface Props {
	customerId: number;
	onClose: () => void;
}

export function TagPicker( { customerId, onClose }: Props ) {
	const [ slug, setSlug ] = useState( '' );
	const [ name, setName ] = useState( '' );
	const [ color, setColor ] = useState( '' );
	const [ submitting, setSubmitting ] = useState( false );

	const allTags = useSelect(
		( select ) => select( STORE_NAME ).getAllTags() as CustomerTag[],
		[]
	);
	const currentTags = useSelect(
		( select ) =>
			select( STORE_NAME ).getTags( customerId ) as CustomerTag[],
		[ customerId ]
	);
	const { attachTag } = useDispatch( STORE_NAME );

	useEffect( () => {
		// Resolver auto-triggers, but in case it doesn't, no-op.
	}, [] );

	const attachedIds = new Set( currentTags.map( ( t ) => t.tag_id ) );
	const available = allTags.filter( ( t ) => ! attachedIds.has( t.tag_id ) );

	const handleAttachExisting = async ( tag: CustomerTag ) => {
		setSubmitting( true );
		try {
			await attachTag( customerId, { slug: tag.slug } );
			onClose();
		} finally {
			setSubmitting( false );
		}
	};

	const handleCreate = async ( e: React.FormEvent ) => {
		e.preventDefault();
		if ( ! slug.trim() ) {
			return;
		}
		setSubmitting( true );
		try {
			await attachTag( customerId, {
				slug: slug.trim(),
				name: name.trim() || undefined,
				color: color.trim() || undefined,
			} );
			onClose();
		} finally {
			setSubmitting( false );
		}
	};

	return (
		<Modal
			title={ __( 'Add tag', 'woocommerce' ) }
			onRequestClose={ onClose }
			className="wc-customer-view__modal"
		>
			{ available.length > 0 && (
				<div>
					<h3>{ __( 'Existing tags', 'woocommerce' ) }</h3>
					<ul className="wc-customer-view__tag-picker-list">
						{ available.map( ( tag ) => (
							<li key={ tag.tag_id }>
								<Button
									variant="secondary"
									size="small"
									onClick={ () =>
										handleAttachExisting( tag )
									}
									disabled={ submitting }
								>
									{ tag.name }
								</Button>
							</li>
						) ) }
					</ul>
				</div>
			) }
			<form onSubmit={ handleCreate }>
				<h3>{ __( 'Create new tag', 'woocommerce' ) }</h3>
				<TextControl
					label={ __( 'Slug', 'woocommerce' ) }
					value={ slug }
					onChange={ setSlug }
					required
				/>
				<TextControl
					label={ __( 'Name', 'woocommerce' ) }
					value={ name }
					onChange={ setName }
				/>
				<TextControl
					label={ __( 'Color (hex, e.g. #ff0000)', 'woocommerce' ) }
					value={ color }
					onChange={ setColor }
				/>
				<Button
					type="submit"
					variant="primary"
					disabled={ submitting || ! slug.trim() }
				>
					{ __( 'Create and attach', 'woocommerce' ) }
				</Button>
				<Button variant="tertiary" onClick={ onClose }>
					{ __( 'Cancel', 'woocommerce' ) }
				</Button>
			</form>
		</Modal>
	);
}
