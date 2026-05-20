/**
 * External dependencies
 */
import { Button, TextareaControl } from '@wordpress/components';
import { useDispatch, useSelect } from '@wordpress/data';
import { useState } from '@wordpress/element';
import { __ } from '@wordpress/i18n';

/**
 * Internal dependencies
 */
import { STORE_NAME } from '../data/store';
import type { CustomerNote } from '../data/types';

const formatDateTime = ( iso: string ): string => {
	const t = Date.parse( iso );
	if ( ! Number.isFinite( t ) ) {
		return iso;
	}
	return new Date( t ).toLocaleString();
};

interface NoteItemProps {
	note: CustomerNote;
	onDelete: ( id: number ) => void;
	canDelete: boolean;
}

const NoteItem = ( { note, onDelete, canDelete }: NoteItemProps ) => (
	<li className="wc-customer-view__note">
		<div className="wc-customer-view__note-meta">
			<span className="wc-customer-view__note-author">
				{ note.author_name ?? __( 'Author', 'woocommerce' ) }
			</span>
			<time
				className="wc-customer-view__note-time"
				dateTime={ note.created_at }
			>
				{ formatDateTime( note.created_at ) }
			</time>
		</div>
		<div
			className="wc-customer-view__note-content"
			dangerouslySetInnerHTML={ { __html: note.content } }
		/>
		{ canDelete && (
			<Button
				variant="link"
				isDestructive
				onClick={ () => onDelete( note.note_id ) }
			>
				{ __( 'Delete', 'woocommerce' ) }
			</Button>
		) }
	</li>
);

export function NotesSection( { customerId }: { customerId: number } ) {
	const [ draft, setDraft ] = useState( '' );
	const [ submitting, setSubmitting ] = useState( false );

	const notes = useSelect(
		( select ) =>
			select( STORE_NAME ).getNotes( customerId ) as CustomerNote[],
		[ customerId ]
	);

	// Trigger resolver.
	useSelect(
		( select ) => select( STORE_NAME ).getNotes( customerId ),
		[ customerId ]
	);

	const { addNote, deleteNote } = useDispatch( STORE_NAME );

	const handleSubmit = async ( e: React.FormEvent ) => {
		e.preventDefault();
		const trimmed = draft.trim();
		if ( ! trimmed ) {
			return;
		}
		setSubmitting( true );
		try {
			await addNote( customerId, trimmed );
			setDraft( '' );
		} finally {
			setSubmitting( false );
		}
	};

	return (
		<section className="wc-customer-view__section wc-customer-view__notes">
			<h2>{ __( 'Notes', 'woocommerce' ) }</h2>
			<form onSubmit={ handleSubmit }>
				<TextareaControl
					label={ __( 'Add a note', 'woocommerce' ) }
					value={ draft }
					onChange={ setDraft }
					disabled={ submitting }
					rows={ 3 }
				/>
				<Button
					type="submit"
					variant="primary"
					disabled={ ! draft.trim() || submitting }
				>
					{ __( 'Save note', 'woocommerce' ) }
				</Button>
			</form>
			{ notes.length === 0 ? (
				<p className="wc-customer-view__empty">
					{ __( 'No notes yet.', 'woocommerce' ) }
				</p>
			) : (
				<ul className="wc-customer-view__note-list">
					{ notes.map( ( note ) => (
						<NoteItem
							key={ note.note_id }
							note={ note }
							onDelete={ ( id ) => deleteNote( customerId, id ) }
							canDelete
						/>
					) ) }
				</ul>
			) }
		</section>
	);
}
