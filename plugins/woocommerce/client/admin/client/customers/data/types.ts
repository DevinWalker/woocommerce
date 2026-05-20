export type LifecycleStatus =
	| 'prospect'
	| 'new'
	| 'active'
	| 'at-risk'
	| 'dormant'
	| 'merged';

export type CreatedVia = 'order' | 'manual' | 'merge';

export interface CustomerTag {
	tag_id: number;
	slug: string;
	name: string;
	color: string | null;
}

export interface CustomerNote {
	note_id: number;
	customer_id: number;
	author_id: number;
	author_name?: string;
	content: string;
	created_at: string;
	updated_at: string | null;
}

export interface PaymentEvent {
	event_id: number;
	customer_id: number;
	order_id: number;
	type: string;
	amount: string;
	currency: string;
	gateway: string;
	status: string;
	external_id: string | null;
	created_at: string;
}

export interface TimelineEvent {
	id: string;
	type: string;
	occurred_at: string;
	actor: number;
	payload: Record< string, unknown >;
}

export interface Customer {
	id: number;
	user_id: number;
	email: string;
	username: string;
	first_name: string;
	last_name: string;
	lifecycle_status: LifecycleStatus;
	lifecycle_overridden: boolean;
	created_via: CreatedVia;
	merged_into_customer_id: number | null;
	tags: CustomerTag[];
	notes_count: number;
	is_registered: boolean;
	orders_count: number;
	total_spend: string;
	avg_order_value: string;
	date_first_order: string | null;
	date_last_order: string | null;
	date_registered: string;
}

export interface TimelineQueryArgs {
	types?: string[];
	page?: number;
	per_page?: number;
}
