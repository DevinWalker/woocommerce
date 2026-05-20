/**
 * External dependencies
 */
import { Button } from '@wordpress/components';
import { useDispatch, useSelect } from '@wordpress/data';
import { useEffect, useState } from '@wordpress/element';
import { __ } from '@wordpress/i18n';

/**
 * Internal dependencies
 */
import { STORE_NAME } from '../data/store';
import type { TimelineEvent } from '../data/types';

const FILTER_OPTIONS: Array< { value: string; label: string } > = [
	{ value: 'order', label: __( 'Orders', 'woocommerce' ) },
	{ value: 'payment', label: __( 'Payments', 'woocommerce' ) },
	{ value: 'note', label: __( 'Notes', 'woocommerce' ) },
	{ value: 'lifecycle', label: __( 'Lifecycle', 'woocommerce' ) },
	{ value: 'tag', label: __( 'Tags', 'woocommerce' ) },
	{ value: 'merge', label: __( 'Merges', 'woocommerce' ) },
];

const formatRelative = ( iso: string ): string => {
	const t = Date.parse( iso );
	if ( ! Number.isFinite( t ) ) {
		return iso;
	}
	const diff = Date.now() - t;
	const minutes = Math.floor( diff / 60_000 );
	if ( minutes < 1 ) {
		return __( 'just now', 'woocommerce' );
	}
	if ( minutes < 60 ) {
		return `${ minutes }m ago`;
	}
	const hours = Math.floor( minutes / 60 );
	if ( hours < 24 ) {
		return `${ hours }h ago`;
	}
	const days = Math.floor( hours / 24 );
	if ( days < 30 ) {
		return `${ days }d ago`;
	}
	return new Date( t ).toLocaleDateString();
};

interface EventRowProps {
	event: TimelineEvent;
}

type Payload = Record< string, unknown >;

const summarize = ( type: string, payload: Payload ): string => {
	switch ( type ) {
		case 'note_added':
			return String( payload.content ?? '' );
		case 'payment_event':
			return [
				payload.event_type,
				payload.amount,
				payload.currency,
				`via ${ payload.gateway }`,
				`(${ payload.status })`,
			]
				.filter( Boolean )
				.join( ' ' );
		case 'order_placed':
			return `Order #${ payload.order_id } — ${ payload.status } (${ payload.total_sales })`;
		default:
			return JSON.stringify( payload );
	}
};

const EventRow = ( { event }: EventRowProps ) => {
	const payload =
		event.payload && typeof event.payload === 'object'
			? ( event.payload as Payload )
			: ( {} as Payload );
	return (
		<li
			className={ `wc-customer-view__timeline-event wc-customer-view__timeline-event--${ event.type }` }
		>
			<span className="wc-customer-view__timeline-type">
				{ event.type }
			</span>
			<time
				className="wc-customer-view__timeline-time"
				dateTime={ event.occurred_at }
			>
				{ formatRelative( event.occurred_at ) }
			</time>
			<div className="wc-customer-view__timeline-payload">
				{ summarize( event.type, payload ) }
			</div>
		</li>
	);
};

export function Timeline( { customerId }: { customerId: number } ) {
	const [ activeTypes, setActiveTypes ] = useState< string[] >( [] );
	const events = useSelect(
		( select ) =>
			select( STORE_NAME ).getTimeline( customerId ) as TimelineEvent[],
		[ customerId ]
	);
	const { fetchTimeline } = useDispatch( STORE_NAME );

	// Initial load + refetch when filters change. Guard against bad ids so a
	// partial / corrupted customer object never produces /customer-view/undefined/timeline.
	useEffect( () => {
		if ( ! Number.isFinite( customerId ) || customerId <= 0 ) {
			return;
		}
		fetchTimeline( customerId, { types: activeTypes } );
		// eslint-disable-next-line react-hooks/exhaustive-deps
	}, [ customerId, activeTypes.join( ',' ) ] );

	const toggle = ( value: string ) => {
		setActiveTypes( ( prev ) =>
			prev.includes( value )
				? prev.filter( ( t ) => t !== value )
				: [ ...prev, value ]
		);
	};

	return (
		<section className="wc-customer-view__section wc-customer-view__timeline">
			<h2>{ __( 'Activity', 'woocommerce' ) }</h2>
			<div
				className="wc-customer-view__timeline-filters"
				role="group"
				aria-label={ __( 'Filter timeline', 'woocommerce' ) }
			>
				{ FILTER_OPTIONS.map( ( opt ) => (
					<Button
						key={ opt.value }
						variant={
							activeTypes.includes( opt.value )
								? 'primary'
								: 'tertiary'
						}
						size="small"
						onClick={ () => toggle( opt.value ) }
					>
						{ opt.label }
					</Button>
				) ) }
			</div>
			{ events.length === 0 ? (
				<p className="wc-customer-view__empty">
					{ __( 'No activity to show.', 'woocommerce' ) }
				</p>
			) : (
				<ul className="wc-customer-view__timeline-list">
					{ events.map( ( event ) => (
						<EventRow key={ event.id } event={ event } />
					) ) }
				</ul>
			) }
		</section>
	);
}
