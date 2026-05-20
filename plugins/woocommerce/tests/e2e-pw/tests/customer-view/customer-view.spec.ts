/**
 * External dependencies
 */
import { WC_API_PATH } from '@woocommerce/e2e-utils-playwright';

/**
 * Internal dependencies
 */
import { expect, test as baseTest } from '../../fixtures/fixtures';
import { ADMIN_STATE_PATH } from '../../playwright.config';

interface CustomerSeed {
	id: number;
	user_id: number;
	customer_id: number;
	email: string;
}

const test = baseTest.extend< {
	seededCustomer: CustomerSeed;
} >( {
	storageState: ADMIN_STATE_PATH,
	seededCustomer: async ( { restApi }, use ) => {
		const now = Date.now();
		const email = `cv-${ now }@example.com`;

		const userResp = await restApi.post( `${ WC_API_PATH }/customers`, {
			email,
			first_name: 'Customer',
			last_name: 'View',
			username: `cv-${ now }`,
		} );
		const user = ( await userResp.json() ) as { id: number };

		// Ensure a wc_customer_lookup row exists by placing a small order.
		const orderResp = await restApi.post( `${ WC_API_PATH }/orders`, {
			status: 'completed',
			customer_id: user.id,
			billing: {
				email,
				first_name: 'Customer',
				last_name: 'View',
			},
			line_items: [],
		} );
		const order = ( await orderResp.json() ) as { id: number };

		const reportResp = await restApi.get(
			`/wc-analytics/reports/customers?search=${ encodeURIComponent(
				email
			) }`
		);
		const report = ( await reportResp.json() ) as Array< { id: number } >;
		const customerId = report[ 0 ]?.id ?? 0;

		await use( {
			id: order.id,
			user_id: user.id,
			customer_id: customerId,
			email,
		} );

		await restApi.delete( `${ WC_API_PATH }/orders/${ order.id }`, {
			force: true,
		} );
		await restApi.delete( `${ WC_API_PATH }/customers/${ user.id }`, {
			force: true,
		} );
	},
} );

test.describe( 'Customer view', () => {
	test.beforeEach( async ( { page } ) => {
		// Enable the feature flag for this test session.
		await page.evaluate( () => {
			// no-op; real enablement happens via the wc-admin admin features
			// option, which the test environment opts into via a setup step.
		} );
	} );

	test( 'admin can open detail page and add a note', async ( {
		page,
		seededCustomer,
	} ) => {
		test.skip(
			! seededCustomer.customer_id,
			'No customer_id available — wc_customer_lookup row was not created.'
		);

		await page.goto(
			`/wp-admin/admin.php?page=wc-admin&path=/customers/${ seededCustomer.customer_id }`
		);
		await expect( page.locator( '.wc-customer-view' ) ).toBeVisible();

		const noteInput = page.getByLabel( /add a note/i );
		await noteInput.fill( 'E2E test note' );
		await page.getByRole( 'button', { name: /save note/i } ).click();

		await expect( page.getByText( 'E2E test note' ) ).toBeVisible();
	} );

	test( 'admin can create and attach a tag', async ( {
		page,
		seededCustomer,
	} ) => {
		test.skip( ! seededCustomer.customer_id );

		await page.goto(
			`/wp-admin/admin.php?page=wc-admin&path=/customers/${ seededCustomer.customer_id }`
		);

		await page.getByRole( 'button', { name: /add tag/i } ).click();
		await page.getByLabel( 'Slug' ).fill( `e2e-tag-${ Date.now() }` );
		await page
			.getByRole( 'button', { name: /create and attach/i } )
			.click();

		// After attach, modal closes and the chip appears in the header.
		await expect(
			page.locator( '.wc-customer-view__tag-chip' )
		).toBeVisible();
	} );

	test( 'invalid customer id shows error state', async ( { page } ) => {
		await page.goto(
			'/wp-admin/admin.php?page=wc-admin&path=/customers/abc'
		);
		await expect(
			page.locator( '.wc-customer-view--error' )
		).toContainText( /Invalid customer id/i );
	} );
} );
