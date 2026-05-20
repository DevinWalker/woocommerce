/**
 * External dependencies
 */
import { fireEvent, render, screen } from '@testing-library/react';

/**
 * Internal dependencies
 */
import { LifecycleBadge } from '../lifecycle-badge';

describe( 'LifecycleBadge', () => {
	it( 'renders the localized label for known status', () => {
		render( <LifecycleBadge status="active" /> );
		expect( screen.getByText( 'Active' ) ).toBeInTheDocument();
	} );

	it( 'applies the overridden modifier when set', () => {
		const { container } = render(
			<LifecycleBadge status="at-risk" overridden />
		);
		expect(
			container.querySelector(
				'.wc-lifecycle--at-risk.wc-lifecycle--overridden'
			)
		).not.toBeNull();
	} );

	it( 'renders as a button when onClick is provided and fires the handler', () => {
		const onClick = jest.fn();
		render( <LifecycleBadge status="new" onClick={ onClick } /> );
		const button = screen.getByRole( 'button' );
		fireEvent.click( button );
		expect( onClick ).toHaveBeenCalledTimes( 1 );
	} );
} );
