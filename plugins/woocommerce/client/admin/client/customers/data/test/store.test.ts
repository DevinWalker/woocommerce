/**
 * External dependencies
 */
import { select, dispatch } from '@wordpress/data';

/**
 * Internal dependencies
 */
import { STORE_NAME, registerCustomerViewStore } from '../store';
import type { CustomerNote, CustomerTag } from '../types';

describe( 'customer-view store', () => {
	beforeAll( () => {
		registerCustomerViewStore();
	} );

	it( 'registers under the expected store name', () => {
		expect( STORE_NAME ).toBe( 'wc/customer-view' );
		// Calling getCustomer for an unknown id should return null, not throw.
		const customer = select( STORE_NAME ).getCustomer( 999 );
		expect( customer ).toBeNull();
	} );

	it( 'adds notes to state via setNotes', () => {
		const notes: CustomerNote[] = [
			{
				note_id: 1,
				customer_id: 42,
				author_id: 7,
				content: 'Hello',
				created_at: '2026-05-20T12:00:00',
				updated_at: null,
			},
		];
		dispatch( STORE_NAME ).setNotes( 42, notes );
		expect( select( STORE_NAME ).getNotes( 42 ) ).toEqual( notes );
	} );

	it( 'adds and removes a single note', () => {
		const note: CustomerNote = {
			note_id: 10,
			customer_id: 99,
			author_id: 1,
			content: 'note',
			created_at: '2026-05-20T12:00:00',
			updated_at: null,
		};
		dispatch( STORE_NAME ).addNoteToState( 99, note );
		expect( select( STORE_NAME ).getNotes( 99 ) ).toContainEqual( note );
		dispatch( STORE_NAME ).removeNoteFromState( 99, 10 );
		expect( select( STORE_NAME ).getNotes( 99 ) ).toHaveLength( 0 );
	} );

	it( 'is idempotent on duplicate tag add', () => {
		const tag: CustomerTag = {
			tag_id: 5,
			slug: 'vip',
			name: 'VIP',
			color: '#ff0000',
		};
		dispatch( STORE_NAME ).addTagToState( 7, tag );
		dispatch( STORE_NAME ).addTagToState( 7, tag );
		expect( select( STORE_NAME ).getTags( 7 ) ).toHaveLength( 1 );
	} );
} );
