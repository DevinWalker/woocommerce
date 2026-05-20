/**
 * External dependencies
 */
import { __ } from '@wordpress/i18n';
import { Flex, FlexItem } from '@wordpress/components';

/**
 * Internal dependencies
 */
import type { Customer } from '../data/types';

const daysSince = ( iso: string | null ): number | null => {
	if ( ! iso ) {
		return null;
	}
	const then = Date.parse( iso );
	if ( ! Number.isFinite( then ) ) {
		return null;
	}
	return Math.max( 0, Math.floor( ( Date.now() - then ) / 86_400_000 ) );
};

const formatCurrency = ( value: string | number ): string => {
	const numeric =
		typeof value === 'number' ? value : Number.parseFloat( value );
	if ( ! Number.isFinite( numeric ) ) {
		return '—';
	}
	return numeric.toLocaleString( undefined, {
		minimumFractionDigits: 2,
		maximumFractionDigits: 2,
	} );
};

interface StatProps {
	label: string;
	value: string | number;
}

const Stat = ( { label, value }: StatProps ) => (
	<FlexItem className="wc-customer-view__stat">
		<span className="wc-customer-view__stat-label">{ label }</span>
		<span className="wc-customer-view__stat-value">{ value }</span>
	</FlexItem>
);

export function StatsStrip( { customer }: { customer: Customer } ) {
	const days = daysSince( customer.date_last_order );

	return (
		<section
			className="wc-customer-view__stats"
			aria-label={ __( 'Customer stats', 'woocommerce' ) }
		>
			<Flex justify="flex-start" gap={ 4 } align="stretch" wrap>
				<Stat
					label={ __( 'Lifetime value', 'woocommerce' ) }
					value={ formatCurrency( customer.total_spend ) }
				/>
				<Stat
					label={ __( 'Orders', 'woocommerce' ) }
					value={ customer.orders_count }
				/>
				<Stat
					label={ __( 'Average order value', 'woocommerce' ) }
					value={ formatCurrency( customer.avg_order_value ) }
				/>
				<Stat
					label={ __( 'Days since last order', 'woocommerce' ) }
					value={ days === null ? '—' : days }
				/>
				<Stat
					label={ __( 'Notes', 'woocommerce' ) }
					value={ customer.notes_count }
				/>
			</Flex>
		</section>
	);
}
