/**
 * External dependencies
 */
import { render, screen } from '@testing-library/react';

/**
 * Internal dependencies
 */
import CustomerDetail from '../index';
import type { Customer } from '../../data/types';

jest.mock( '../header', () => ( {
	Header: ( { customer }: { customer: Customer } ) => (
		<div data-testid="header">{ customer.email }</div>
	),
} ) );
jest.mock( '../stats-strip', () => ( {
	StatsStrip: () => <div data-testid="stats-strip" />,
} ) );
jest.mock( '../timeline', () => ( {
	Timeline: () => <div data-testid="timeline" />,
} ) );
jest.mock( '../notes-section', () => ( {
	NotesSection: () => <div data-testid="notes-section" />,
} ) );
jest.mock( '../orders-section', () => ( {
	OrdersSection: () => <div data-testid="orders-section" />,
} ) );
jest.mock( '../addresses-section', () => ( {
	AddressesSection: () => <div data-testid="addresses-section" />,
} ) );
jest.mock( '../payment-events-section', () => ( {
	PaymentEventsSection: () => <div data-testid="payment-events" />,
} ) );
jest.mock( '../subscriptions-section', () => ( {
	SubscriptionsSection: () => <div data-testid="subscriptions" />,
} ) );
jest.mock( '../extension-slot', () => ( {
	ExtensionSlot: () => <div data-testid="extension-slot" />,
} ) );

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

describe( 'CustomerDetail shell', () => {
	beforeEach( () => {
		useSelectMock.mockReset();
	} );

	it( 'shows a spinner while the customer is resolving', () => {
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

	it( 'renders all section placeholders once loaded', () => {
		useSelectMock
			.mockImplementationOnce( () => baseCustomer() )
			.mockImplementationOnce( () => false );

		render( <CustomerDetail params={ { id: '42' } } /> );

		expect( screen.getByTestId( 'header' ) ).toBeInTheDocument();
		expect( screen.getByTestId( 'stats-strip' ) ).toBeInTheDocument();
		expect( screen.getByTestId( 'timeline' ) ).toBeInTheDocument();
		expect( screen.getByTestId( 'notes-section' ) ).toBeInTheDocument();
		expect( screen.getByTestId( 'orders-section' ) ).toBeInTheDocument();
		expect( screen.getByTestId( 'addresses-section' ) ).toBeInTheDocument();
		expect( screen.getByTestId( 'payment-events' ) ).toBeInTheDocument();
		expect( screen.getByTestId( 'subscriptions' ) ).toBeInTheDocument();
		expect( screen.getByTestId( 'extension-slot' ) ).toBeInTheDocument();
	} );

	it( 'shows not-found when the resolver returns null and is not resolving', () => {
		useSelectMock
			.mockImplementationOnce( () => null )
			.mockImplementationOnce( () => false );

		render( <CustomerDetail params={ { id: '42' } } /> );

		expect( screen.getByText( 'Customer not found.' ) ).toBeInTheDocument();
	} );

	it( 'shows an error for invalid customer id', () => {
		useSelectMock
			.mockImplementationOnce( () => null )
			.mockImplementationOnce( () => false );

		render( <CustomerDetail params={ { id: 'not-a-number' } } /> );

		expect(
			screen.getByText( 'Invalid customer id.' )
		).toBeInTheDocument();
	} );
} );
