/**
 * External dependencies
 */
import apiFetch from '@wordpress/api-fetch';
import { Button, Modal, TextControl } from '@wordpress/components';
import { useDispatch } from '@wordpress/data';
import { useState } from '@wordpress/element';
import { addQueryArgs } from '@wordpress/url';
import { __ } from '@wordpress/i18n';

/**
 * Internal dependencies
 */
import { REST_NAMESPACE } from '../../data/constants';
import { STORE_NAME } from '../../data/store';
import type { Customer } from '../../data/types';

interface Props {
	customer: Customer;
	onClose: () => void;
}

interface SearchResult {
	id: number;
	email: string;
	first_name: string;
	last_name: string;
}

export function MergeModal( { customer, onClose }: Props ) {
	const [ search, setSearch ] = useState( '' );
	const [ results, setResults ] = useState< SearchResult[] >( [] );
	const [ target, setTarget ] = useState< SearchResult | null >( null );
	const [ searching, setSearching ] = useState( false );
	const [ merging, setMerging ] = useState( false );

	const { mergeCustomer } = useDispatch( STORE_NAME );

	const runSearch = async ( value: string ) => {
		setSearch( value );
		setTarget( null );
		if ( value.trim().length < 2 ) {
			setResults( [] );
			return;
		}
		setSearching( true );
		try {
			const rows: SearchResult[] = await apiFetch( {
				path: addQueryArgs( `${ REST_NAMESPACE }`, {
					search: value.trim(),
					per_page: 10,
				} ),
			} );
			setResults(
				( Array.isArray( rows ) ? rows : [] ).filter(
					( r ) => r.id !== customer.id
				)
			);
		} catch {
			setResults( [] );
		} finally {
			setSearching( false );
		}
	};

	const handleMerge = async () => {
		if ( ! target ) {
			return;
		}
		setMerging( true );
		try {
			await mergeCustomer( customer.id, target.id );
			window.location.assign(
				`admin.php?page=wc-admin&path=/customers/${ target.id }`
			);
		} finally {
			setMerging( false );
		}
	};

	return (
		<Modal
			title={ __( 'Merge customer', 'woocommerce' ) }
			onRequestClose={ onClose }
			className="wc-customer-view__modal"
		>
			<TextControl
				label={ __(
					'Search for target customer (by email or name)',
					'woocommerce'
				) }
				value={ search }
				onChange={ runSearch }
				placeholder={ __( 'Type to search…', 'woocommerce' ) }
			/>
			{ searching && <p>{ __( 'Searching…', 'woocommerce' ) }</p> }
			{ ! searching && results.length > 0 && (
				<ul className="wc-customer-view__merge-results">
					{ results.map( ( r ) => (
						<li key={ r.id }>
							<Button
								variant={
									target?.id === r.id
										? 'primary'
										: 'secondary'
								}
								onClick={ () => setTarget( r ) }
							>
								{ r.first_name } { r.last_name } – { r.email }
							</Button>
						</li>
					) ) }
				</ul>
			) }
			{ target && (
				<p>
					{ __(
						'This will move all data from the current customer into:',
						'woocommerce'
					) }{ ' ' }
					<strong>{ target.email }</strong>
				</p>
			) }
			<div className="wc-customer-view__modal-actions">
				<Button
					variant="primary"
					isDestructive
					disabled={ ! target || merging }
					onClick={ handleMerge }
				>
					{ __( 'Confirm merge', 'woocommerce' ) }
				</Button>
				<Button variant="tertiary" onClick={ onClose }>
					{ __( 'Cancel', 'woocommerce' ) }
				</Button>
			</div>
		</Modal>
	);
}
