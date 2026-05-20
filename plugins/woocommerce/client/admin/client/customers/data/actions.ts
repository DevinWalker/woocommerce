/**
 * External dependencies
 */
import apiFetch from '@wordpress/api-fetch';
import { addQueryArgs } from '@wordpress/url';

/**
 * Internal dependencies
 */
import { REST_NAMESPACE } from './constants';
import { actionTypes } from './reducer';
import type {
	Customer,
	CustomerNote,
	CustomerTag,
	LifecycleStatus,
	PaymentEvent,
	TimelineEvent,
	TimelineQueryArgs,
} from './types';

export const setCustomer = ( customerId: number, customer: Customer ) =>
	( {
		type: actionTypes.SET_CUSTOMER,
		customerId,
		customer,
	} as const );

export const setNotes = ( customerId: number, notes: CustomerNote[] ) =>
	( { type: actionTypes.SET_NOTES, customerId, notes } as const );

export const addNoteToState = ( customerId: number, note: CustomerNote ) =>
	( { type: actionTypes.ADD_NOTE, customerId, note } as const );

export const removeNoteFromState = ( customerId: number, noteId: number ) =>
	( { type: actionTypes.REMOVE_NOTE, customerId, noteId } as const );

export const updateNoteInState = ( customerId: number, note: CustomerNote ) =>
	( { type: actionTypes.UPDATE_NOTE, customerId, note } as const );

export const setTags = ( customerId: number, tags: CustomerTag[] ) =>
	( { type: actionTypes.SET_TAGS, customerId, tags } as const );

export const addTagToState = ( customerId: number, tag: CustomerTag ) =>
	( { type: actionTypes.ADD_TAG, customerId, tag } as const );

export const removeTagFromState = ( customerId: number, tagId: number ) =>
	( { type: actionTypes.REMOVE_TAG, customerId, tagId } as const );

export const setTimeline = ( customerId: number, events: TimelineEvent[] ) =>
	( { type: actionTypes.SET_TIMELINE, customerId, events } as const );

export const setPaymentEvents = (
	customerId: number,
	events: PaymentEvent[]
) => ( { type: actionTypes.SET_PAYMENT_EVENTS, customerId, events } as const );

export const setError = ( customerId: number, error: string | null ) =>
	( { type: actionTypes.SET_ERROR, customerId, error } as const );

export const setAllTags = ( tags: CustomerTag[] ) =>
	( { type: actionTypes.SET_ALL_TAGS, tags } as const );

const errorMessage = ( e: unknown, fallback: string ): string => {
	if ( e && typeof e === 'object' && 'message' in e ) {
		return String( ( e as { message: string } ).message );
	}
	return fallback;
};

export function* addNote( customerId: number, content: string ) {
	const tempId = -Date.now();
	const optimistic: CustomerNote = {
		note_id: tempId,
		customer_id: customerId,
		author_id: 0,
		content,
		created_at: new Date().toISOString(),
		updated_at: null,
	};
	yield addNoteToState( customerId, optimistic );
	try {
		const saved: CustomerNote = yield apiFetch( {
			path: `${ REST_NAMESPACE }/${ customerId }/notes`,
			method: 'POST',
			data: { content },
		} );
		yield removeNoteFromState( customerId, tempId );
		yield addNoteToState( customerId, saved );
		return saved;
	} catch ( e ) {
		yield removeNoteFromState( customerId, tempId );
		yield setError( customerId, errorMessage( e, 'Failed to add note' ) );
		throw e;
	}
}

export function* editNote(
	customerId: number,
	noteId: number,
	content: string
) {
	try {
		const updated: CustomerNote = yield apiFetch( {
			path: `${ REST_NAMESPACE }/${ customerId }/notes/${ noteId }`,
			method: 'PATCH',
			data: { content },
		} );
		yield updateNoteInState( customerId, updated );
		return updated;
	} catch ( e ) {
		yield setError( customerId, errorMessage( e, 'Failed to edit note' ) );
		throw e;
	}
}

export function* deleteNote( customerId: number, noteId: number ) {
	try {
		yield apiFetch( {
			path: `${ REST_NAMESPACE }/${ customerId }/notes/${ noteId }`,
			method: 'DELETE',
		} );
		yield removeNoteFromState( customerId, noteId );
	} catch ( e ) {
		yield setError(
			customerId,
			errorMessage( e, 'Failed to delete note' )
		);
		throw e;
	}
}

export function* attachTag(
	customerId: number,
	tag: { slug: string; name?: string; color?: string | null }
) {
	try {
		const saved: CustomerTag = yield apiFetch( {
			path: `${ REST_NAMESPACE }/${ customerId }/tags`,
			method: 'POST',
			data: tag,
		} );
		yield addTagToState( customerId, saved );
		return saved;
	} catch ( e ) {
		yield setError( customerId, errorMessage( e, 'Failed to attach tag' ) );
		throw e;
	}
}

export function* detachTag( customerId: number, tagId: number ) {
	try {
		yield apiFetch( {
			path: `${ REST_NAMESPACE }/${ customerId }/tags/${ tagId }`,
			method: 'DELETE',
		} );
		yield removeTagFromState( customerId, tagId );
	} catch ( e ) {
		yield setError( customerId, errorMessage( e, 'Failed to detach tag' ) );
		throw e;
	}
}

export function* setLifecycle(
	customerId: number,
	status: LifecycleStatus,
	reason?: string
) {
	try {
		const updated: Customer = yield apiFetch( {
			path: `${ REST_NAMESPACE }/${ customerId }/lifecycle`,
			method: 'POST',
			data: { status, reason },
		} );
		yield setCustomer( customerId, updated );
		return updated;
	} catch ( e ) {
		yield setError(
			customerId,
			errorMessage( e, 'Failed to set lifecycle' )
		);
		throw e;
	}
}

export function* clearLifecycleOverride( customerId: number ) {
	try {
		const updated: Customer = yield apiFetch( {
			path: `${ REST_NAMESPACE }/${ customerId }/lifecycle`,
			method: 'DELETE',
		} );
		yield setCustomer( customerId, updated );
		return updated;
	} catch ( e ) {
		yield setError(
			customerId,
			errorMessage( e, 'Failed to clear lifecycle override' )
		);
		throw e;
	}
}

interface MergeSummary {
	target_customer_id: number;
	moved: Record< string, number >;
}

export function* mergeCustomer( sourceId: number, targetId: number ) {
	try {
		const summary: MergeSummary = yield apiFetch( {
			path: `${ REST_NAMESPACE }/${ targetId }/merge`,
			method: 'POST',
			data: { source_id: sourceId },
		} );
		// The merge endpoint returns a summary, not a full Customer. Re-fetch
		// the canonical record so the store stays consistent.
		const refreshed: Customer = yield apiFetch( {
			path: `${ REST_NAMESPACE }/${ targetId }`,
		} );
		yield setCustomer( targetId, refreshed );
		return summary;
	} catch ( e ) {
		yield setError(
			targetId,
			errorMessage( e, 'Failed to merge customer' )
		);
		throw e;
	}
}

export function* fetchTimeline(
	customerId: number,
	args: TimelineQueryArgs = {}
) {
	try {
		const path = addQueryArgs(
			`${ REST_NAMESPACE }/${ customerId }/timeline`,
			{
				types: args.types?.join( ',' ),
				page: args.page,
				per_page: args.per_page,
			}
		);
		const raw: unknown = yield apiFetch( { path } );
		const events: TimelineEvent[] = Array.isArray( raw )
			? ( raw as TimelineEvent[] )
			: [];
		yield setTimeline( customerId, events );
		return events;
	} catch ( e ) {
		yield setError(
			customerId,
			errorMessage( e, 'Failed to load timeline' )
		);
		throw e;
	}
}
