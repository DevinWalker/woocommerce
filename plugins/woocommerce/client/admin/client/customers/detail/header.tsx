/**
 * External dependencies
 */
import { Button, Flex, FlexItem } from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import { useState } from '@wordpress/element';

/**
 * Internal dependencies
 */
import type { Customer } from '../data/types';
import { LifecycleBadge } from './lifecycle-badge';
import { TagChips } from './tag-chips';
import { TagPicker } from './modals/tag-picker';
import { LifecycleOverrideModal } from './modals/lifecycle-override';
import { MergeModal } from './modals/merge-modal';

const initialsOf = ( customer: Customer ): string => {
	const first = customer.first_name?.trim().charAt( 0 ) ?? '';
	const last = customer.last_name?.trim().charAt( 0 ) ?? '';
	const combined = `${ first }${ last }`.toUpperCase();
	if ( combined ) {
		return combined;
	}
	return customer.email?.trim().charAt( 0 ).toUpperCase() || '?';
};

export function Header( { customer }: { customer: Customer } ) {
	const [ showTagPicker, setShowTagPicker ] = useState( false );
	const [ showLifecycleModal, setShowLifecycleModal ] = useState( false );
	const [ showMergeModal, setShowMergeModal ] = useState( false );

	const displayName =
		`${ customer.first_name } ${ customer.last_name }`.trim() ||
		customer.email;

	return (
		<header className="wc-customer-view__header">
			<Flex align="center" gap={ 4 }>
				<FlexItem>
					<div
						className="wc-customer-view__avatar"
						aria-hidden="true"
					>
						{ initialsOf( customer ) }
					</div>
				</FlexItem>
				<FlexItem isBlock>
					<h1 className="wc-customer-view__name">{ displayName }</h1>
					<p className="wc-customer-view__email">
						{ customer.email }
					</p>
					<Flex
						align="center"
						gap={ 2 }
						justify="flex-start"
						className="wc-customer-view__badges"
					>
						<LifecycleBadge
							status={ customer.lifecycle_status }
							overridden={ customer.lifecycle_overridden }
							onClick={ () => setShowLifecycleModal( true ) }
						/>
						{ customer.is_registered ? (
							<span className="wc-customer-view__pill wc-customer-view__pill--registered">
								{ __( 'Registered', 'woocommerce' ) }
							</span>
						) : (
							<span className="wc-customer-view__pill wc-customer-view__pill--guest">
								{ __( 'Guest', 'woocommerce' ) }
							</span>
						) }
						<TagChips tags={ customer.tags } />
						<Button
							variant="tertiary"
							size="small"
							onClick={ () => setShowTagPicker( true ) }
						>
							{ __( 'Add tag', 'woocommerce' ) }
						</Button>
					</Flex>
				</FlexItem>
				<FlexItem>
					<Flex gap={ 2 } justify="flex-end">
						<Button
							variant="secondary"
							href={ `mailto:${ customer.email }` }
						>
							{ __( 'Email', 'woocommerce' ) }
						</Button>
						{ customer.is_registered && customer.user_id > 0 && (
							<Button
								variant="secondary"
								href={ `user-edit.php?user_id=${ customer.user_id }` }
							>
								{ __( 'WP profile', 'woocommerce' ) }
							</Button>
						) }
						<Button
							variant="tertiary"
							onClick={ () => setShowMergeModal( true ) }
						>
							{ __( 'Merge', 'woocommerce' ) }
						</Button>
					</Flex>
				</FlexItem>
			</Flex>
			{ showTagPicker && (
				<TagPicker
					customerId={ customer.id }
					onClose={ () => setShowTagPicker( false ) }
				/>
			) }
			{ showLifecycleModal && (
				<LifecycleOverrideModal
					customer={ customer }
					onClose={ () => setShowLifecycleModal( false ) }
				/>
			) }
			{ showMergeModal && (
				<MergeModal
					customer={ customer }
					onClose={ () => setShowMergeModal( false ) }
				/>
			) }
		</header>
	);
}
