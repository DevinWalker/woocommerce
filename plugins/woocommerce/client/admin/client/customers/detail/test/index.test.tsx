/**
 * External dependencies
 */
import { render, screen } from '@testing-library/react';

/**
 * Internal dependencies
 */
import CustomerDetail from '../index';
import type { Customer } from '../../data/types';

const useSelectMock = jest.fn();

jest.mock( '@wordpress/data', () => ( {
	__esModule: true,
	useSelect: ( fn: ( select: unknown ) => unknown ) => useSelectMock( fn ),
	createReduxStore: jest.fn( ( name ) => ( { name } ) ),
	register: jest.fn(),
	select: jest.fn(),
	dispatch: jest.fn(),
} ) );

const baseCustomer = ( overrides: Partial< Customer > = {} ): Customer => ( {
	id: 42,
	user_id: 0,
	email: 'a@b.test',
	username: '',
	first_name: 'Aiko',
	last_name: 'Bell',
	lifecycle_status: 'active',
	lifecycle_overridden: false,
	created_via: 'order',
	merged_into_customer_id: null,
	tags: [],
	notes_count: 0,
	is_registered: false,
	orders_count: 3,
	total_spend: '120.00',
	avg_order_value: '40.00',
	date_last_order: '2026-05-01T00:00:00',
	date_registered: '2025-01-01T00:00:00',
	...overrides,
} );

describe( 'CustomerDetail placeholder', () => {
	beforeEach( () => {
		useSelectMock.mockReset();
	} );

	it( 'shows a spinner while the customer is resolving', () => {
		// 1st useSelect call → getCustomer (returns null); 2nd → isResolving (true).
		useSelectMock
			.mockImplementationOnce( () => null )
			.mockImplementationOnce( () => true );

		const { container } = render(
			<CustomerDetail params={ { id: '42' } } />
		);

		expect(
			container.querySelector( '.wc-customer-view--loading' )
		).not.toBeNull();
	} );

	it( 'shows the name and lifecycle once loaded', () => {
		useSelectMock
			.mockImplementationOnce( () => baseCustomer() )
			.mockImplementationOnce( () => false );

		render( <CustomerDetail params={ { id: '42' } } /> );

		expect( screen.getByText( 'Aiko Bell' ) ).toBeInTheDocument();
		expect( screen.getByText( 'a@b.test' ) ).toBeInTheDocument();
		expect( screen.getByText( 'active' ) ).toBeInTheDocument();
	} );

	it( 'falls back to email when name is empty', () => {
		useSelectMock
			.mockImplementationOnce( () =>
				baseCustomer( { first_name: '', last_name: '' } )
			)
			.mockImplementationOnce( () => false );

		render( <CustomerDetail params={ { id: '42' } } /> );

		// h1 + p both render the email.
		expect( screen.getAllByText( 'a@b.test' ).length ).toBeGreaterThan( 0 );
	} );

	it( 'shows an error for invalid customer id', () => {
		// useSelect should NOT be relied on for invalid id, but jest mocks must
		// still return something if called. Safe defaults.
		useSelectMock
			.mockImplementationOnce( () => null )
			.mockImplementationOnce( () => false );

		render( <CustomerDetail params={ { id: 'not-a-number' } } /> );

		expect(
			screen.getByText( 'Invalid customer id.' )
		).toBeInTheDocument();
	} );
} );
