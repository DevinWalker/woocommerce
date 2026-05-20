/**
 * External dependencies
 */
import { __, _x } from '@wordpress/i18n';

/**
 * Internal dependencies
 */
import type { LifecycleStatus } from '../data/types';

const LABELS: Record< LifecycleStatus, string > = {
	prospect: _x( 'Prospect', 'customer lifecycle', 'woocommerce' ),
	new: _x( 'New', 'customer lifecycle', 'woocommerce' ),
	active: _x( 'Active', 'customer lifecycle', 'woocommerce' ),
	'at-risk': _x( 'At risk', 'customer lifecycle', 'woocommerce' ),
	dormant: _x( 'Dormant', 'customer lifecycle', 'woocommerce' ),
	merged: _x( 'Merged', 'customer lifecycle', 'woocommerce' ),
};

interface Props {
	status: LifecycleStatus;
	overridden?: boolean;
	onClick?: () => void;
}

export function LifecycleBadge( { status, overridden, onClick }: Props ) {
	const className = `wc-lifecycle wc-lifecycle--${ status }${
		overridden ? ' wc-lifecycle--overridden' : ''
	}`;
	const label = LABELS[ status ] ?? status;
	const title = overridden
		? __( 'Lifecycle status (manually overridden)', 'woocommerce' )
		: __( 'Lifecycle status', 'woocommerce' );

	if ( onClick ) {
		return (
			<button
				type="button"
				className={ `${ className } wc-lifecycle--button` }
				onClick={ onClick }
				aria-label={ title }
			>
				{ label }
			</button>
		);
	}

	return (
		<span className={ className } role="status" aria-label={ title }>
			{ label }
		</span>
	);
}
