/**
 * External dependencies
 */
import {
	Button,
	Modal,
	SelectControl,
	TextareaControl,
} from '@wordpress/components';
import { useDispatch } from '@wordpress/data';
import { useState } from '@wordpress/element';
import { __ } from '@wordpress/i18n';

/**
 * Internal dependencies
 */
import { STORE_NAME } from '../../data/store';
import type { Customer, LifecycleStatus } from '../../data/types';

interface Props {
	customer: Customer;
	onClose: () => void;
}

const OPTIONS: Array< { value: LifecycleStatus; label: string } > = [
	{ value: 'prospect', label: __( 'Prospect', 'woocommerce' ) },
	{ value: 'new', label: __( 'New', 'woocommerce' ) },
	{ value: 'active', label: __( 'Active', 'woocommerce' ) },
	{ value: 'at-risk', label: __( 'At risk', 'woocommerce' ) },
	{ value: 'dormant', label: __( 'Dormant', 'woocommerce' ) },
];

export function LifecycleOverrideModal( { customer, onClose }: Props ) {
	const [ status, setStatus ] = useState< LifecycleStatus >(
		customer.lifecycle_status === 'merged'
			? 'active'
			: customer.lifecycle_status
	);
	const [ reason, setReason ] = useState( '' );
	const [ submitting, setSubmitting ] = useState( false );

	const { setLifecycle, clearLifecycleOverride } = useDispatch( STORE_NAME );

	const handleSave = async () => {
		setSubmitting( true );
		try {
			await setLifecycle( customer.id, status, reason || undefined );
			onClose();
		} finally {
			setSubmitting( false );
		}
	};

	const handleClear = async () => {
		setSubmitting( true );
		try {
			await clearLifecycleOverride( customer.id );
			onClose();
		} finally {
			setSubmitting( false );
		}
	};

	return (
		<Modal
			title={ __( 'Override lifecycle status', 'woocommerce' ) }
			onRequestClose={ onClose }
			className="wc-customer-view__modal"
		>
			<SelectControl
				label={ __( 'Status', 'woocommerce' ) }
				value={ status }
				options={ OPTIONS }
				onChange={ ( value ) => setStatus( value as LifecycleStatus ) }
			/>
			<TextareaControl
				label={ __( 'Reason (optional)', 'woocommerce' ) }
				value={ reason }
				onChange={ setReason }
				rows={ 3 }
			/>
			<div className="wc-customer-view__modal-actions">
				<Button
					variant="primary"
					onClick={ handleSave }
					disabled={ submitting }
				>
					{ __( 'Save', 'woocommerce' ) }
				</Button>
				{ customer.lifecycle_overridden && (
					<Button
						variant="secondary"
						onClick={ handleClear }
						disabled={ submitting }
					>
						{ __( 'Clear override', 'woocommerce' ) }
					</Button>
				) }
				<Button variant="tertiary" onClick={ onClose }>
					{ __( 'Cancel', 'woocommerce' ) }
				</Button>
			</div>
		</Modal>
	);
}
