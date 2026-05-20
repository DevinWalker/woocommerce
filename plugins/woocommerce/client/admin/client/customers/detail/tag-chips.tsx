/**
 * Internal dependencies
 */
import type { CustomerTag } from '../data/types';

export function TagChips( {
	tags,
}: {
	tags: CustomerTag[] | undefined | null;
} ) {
	if ( ! tags || ! tags.length ) {
		return null;
	}
	return (
		<ul className="wc-customer-view__tag-chips" aria-label="Tags">
			{ tags.map( ( tag ) => (
				<li
					key={ tag.tag_id }
					className="wc-customer-view__tag-chip"
					style={
						tag.color
							? ( {
									backgroundColor: tag.color,
							  } as React.CSSProperties )
							: undefined
					}
				>
					{ tag.name }
				</li>
			) ) }
		</ul>
	);
}
