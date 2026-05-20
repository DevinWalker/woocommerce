/**
 * Internal dependencies
 */
import type {
	Customer,
	CustomerNote,
	CustomerTag,
	PaymentEvent,
	TimelineEvent,
} from './types';

export interface CustomerSlice {
	customer: Customer | null;
	notes: CustomerNote[];
	tags: CustomerTag[];
	timeline: TimelineEvent[];
	paymentEvents: PaymentEvent[];
	error: string | null;
}

export interface State {
	byId: Record< number, CustomerSlice >;
	allTags: CustomerTag[] | null;
}

export const DEFAULT_STATE: State = {
	byId: {},
	allTags: null,
};

export const getInitialSlice = (): CustomerSlice => ( {
	customer: null,
	notes: [],
	tags: [],
	timeline: [],
	paymentEvents: [],
	error: null,
} );

export const actionTypes = {
	SET_CUSTOMER: 'SET_CUSTOMER',
	SET_NOTES: 'SET_NOTES',
	ADD_NOTE: 'ADD_NOTE',
	REMOVE_NOTE: 'REMOVE_NOTE',
	UPDATE_NOTE: 'UPDATE_NOTE',
	SET_TAGS: 'SET_TAGS',
	ADD_TAG: 'ADD_TAG',
	REMOVE_TAG: 'REMOVE_TAG',
	SET_TIMELINE: 'SET_TIMELINE',
	SET_PAYMENT_EVENTS: 'SET_PAYMENT_EVENTS',
	SET_ERROR: 'SET_ERROR',
	SET_ALL_TAGS: 'SET_ALL_TAGS',
} as const;

interface BaseAction {
	type: string;
	customerId?: number;
}

interface SetCustomerAction extends BaseAction {
	type: typeof actionTypes.SET_CUSTOMER;
	customerId: number;
	customer: Customer;
}

interface SetNotesAction extends BaseAction {
	type: typeof actionTypes.SET_NOTES;
	customerId: number;
	notes: CustomerNote[];
}

interface AddNoteAction extends BaseAction {
	type: typeof actionTypes.ADD_NOTE;
	customerId: number;
	note: CustomerNote;
}

interface RemoveNoteAction extends BaseAction {
	type: typeof actionTypes.REMOVE_NOTE;
	customerId: number;
	noteId: number;
}

interface UpdateNoteAction extends BaseAction {
	type: typeof actionTypes.UPDATE_NOTE;
	customerId: number;
	note: CustomerNote;
}

interface SetTagsAction extends BaseAction {
	type: typeof actionTypes.SET_TAGS;
	customerId: number;
	tags: CustomerTag[];
}

interface AddTagAction extends BaseAction {
	type: typeof actionTypes.ADD_TAG;
	customerId: number;
	tag: CustomerTag;
}

interface RemoveTagAction extends BaseAction {
	type: typeof actionTypes.REMOVE_TAG;
	customerId: number;
	tagId: number;
}

interface SetTimelineAction extends BaseAction {
	type: typeof actionTypes.SET_TIMELINE;
	customerId: number;
	events: TimelineEvent[];
}

interface SetPaymentEventsAction extends BaseAction {
	type: typeof actionTypes.SET_PAYMENT_EVENTS;
	customerId: number;
	events: PaymentEvent[];
}

interface SetErrorAction extends BaseAction {
	type: typeof actionTypes.SET_ERROR;
	customerId: number;
	error: string | null;
}

interface SetAllTagsAction {
	type: typeof actionTypes.SET_ALL_TAGS;
	tags: CustomerTag[];
}

export type Action =
	| SetCustomerAction
	| SetNotesAction
	| AddNoteAction
	| RemoveNoteAction
	| UpdateNoteAction
	| SetTagsAction
	| AddTagAction
	| RemoveTagAction
	| SetTimelineAction
	| SetPaymentEventsAction
	| SetErrorAction
	| SetAllTagsAction;

const withSlice = (
	state: State,
	customerId: number,
	patch: Partial< CustomerSlice >
): State => {
	const prev = state.byId[ customerId ] ?? getInitialSlice();
	return {
		...state,
		byId: {
			...state.byId,
			[ customerId ]: { ...prev, ...patch },
		},
	};
};

export function reducer( state: State = DEFAULT_STATE, action: Action ): State {
	switch ( action.type ) {
		case actionTypes.SET_CUSTOMER:
			return withSlice( state, action.customerId, {
				customer: action.customer,
			} );

		case actionTypes.SET_NOTES:
			return withSlice( state, action.customerId, {
				notes: action.notes,
			} );

		case actionTypes.ADD_NOTE: {
			const prev = state.byId[ action.customerId ] ?? getInitialSlice();
			return withSlice( state, action.customerId, {
				notes: [ action.note, ...prev.notes ],
			} );
		}

		case actionTypes.REMOVE_NOTE: {
			const prev = state.byId[ action.customerId ] ?? getInitialSlice();
			return withSlice( state, action.customerId, {
				notes: prev.notes.filter(
					( n ) => n.note_id !== action.noteId
				),
			} );
		}

		case actionTypes.UPDATE_NOTE: {
			const prev = state.byId[ action.customerId ] ?? getInitialSlice();
			return withSlice( state, action.customerId, {
				notes: prev.notes.map( ( n ) =>
					n.note_id === action.note.note_id ? action.note : n
				),
			} );
		}

		case actionTypes.SET_TAGS:
			return withSlice( state, action.customerId, { tags: action.tags } );

		case actionTypes.ADD_TAG: {
			const prev = state.byId[ action.customerId ] ?? getInitialSlice();
			if ( prev.tags.some( ( t ) => t.tag_id === action.tag.tag_id ) ) {
				return state;
			}
			return withSlice( state, action.customerId, {
				tags: [ ...prev.tags, action.tag ],
			} );
		}

		case actionTypes.REMOVE_TAG: {
			const prev = state.byId[ action.customerId ] ?? getInitialSlice();
			return withSlice( state, action.customerId, {
				tags: prev.tags.filter( ( t ) => t.tag_id !== action.tagId ),
			} );
		}

		case actionTypes.SET_TIMELINE:
			return withSlice( state, action.customerId, {
				timeline: action.events,
			} );

		case actionTypes.SET_PAYMENT_EVENTS:
			return withSlice( state, action.customerId, {
				paymentEvents: action.events,
			} );

		case actionTypes.SET_ERROR:
			return withSlice( state, action.customerId, {
				error: action.error,
			} );

		case actionTypes.SET_ALL_TAGS:
			return { ...state, allTags: action.tags };

		default:
			return state;
	}
}
