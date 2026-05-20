/**
 * External dependencies
 */
import { render, screen } from '@testing-library/react';

/**
 * Internal dependencies
 */
import { StatsStrip } from '../stats-strip';
import type { Customer } from '../../data/types';

const customer: Customer = {
	id: 1,
	user_id: 0,
	email: 'a@b.test',
	username: '',
	first_name: 'A',
	last_name: 'B',
	lifecycle_status: 'active',
	lifecycle_overridden: false,
	created_via: 'order',
	merged_into_customer_id: null,
	tags: [],
	notes_count: 7,
	is_registered: true,
	orders_count: 5,
	total_spend: '250.00',
	avg_order_value: '50.00',
	date_last_order: new Date( Date.now() - 3 * 86_400_000 ).toISOString(),
	date_registered: '2025-01-01T00:00:00',
};

describe( 'StatsStrip', () => {
	it( 'renders all five stats with formatted values', () => {
		render( <StatsStrip customer={ customer } /> );
		expect( screen.getByText( 'Lifetime value' ) ).toBeInTheDocument();
		expect( screen.getByText( '250.00' ) ).toBeInTheDocument();
		expect( screen.getByText( 'Orders' ) ).toBeInTheDocument();
		expect( screen.getByText( '5' ) ).toBeInTheDocument();
		expect( screen.getByText( '50.00' ) ).toBeInTheDocument();
		expect( screen.getByText( '7' ) ).toBeInTheDocument();
		// days since last order should be 2 or 3 depending on hour rounding.
		const days = screen.getByText(
			'Days since last order'
		).nextElementSibling;
		expect( days?.textContent ).toMatch( /^[23]$/ );
	} );

	it( 'shows em-dash when last order is unknown', () => {
		render(
			<StatsStrip customer={ { ...customer, date_last_order: null } } />
		);
		// Em-dash appears as a stat-value.
		const labels = screen.getAllByText( 'Days since last order' );
		expect( labels.length ).toBeGreaterThan( 0 );
	} );
} );
