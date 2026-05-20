/**
 * External dependencies
 */
import { __, _n } from '@wordpress/i18n';
import { Fragment, useContext } from '@wordpress/element';
import { useSelect } from '@wordpress/data';
import { Tooltip } from '@wordpress/components';
import { Date, Link, Pill } from '@woocommerce/components';
import { formatValue } from '@woocommerce/number';
import { getAdminLink } from '@woocommerce/settings';
import { defaultTableDateFormat } from '@woocommerce/date';
import { COUNTRIES_STORE_NAME } from '@woocommerce/data';
import { CurrencyContext } from '@woocommerce/currency';

/**
 * Internal dependencies
 */
import ReportTable from '../../components/report-table';
import { getAdminSetting } from '~/utils/admin-settings';
import { isFeatureEnabled } from '~/utils/features';

function CustomersReportTable( {
	isRequesting,
	query,
	filters,
	advancedFilters,
} ) {
	const context = useContext( CurrencyContext );
	const { countries, loadingCountries } = useSelect( ( select ) => {
		const { getCountries, hasFinishedResolution } =
			select( COUNTRIES_STORE_NAME );
		return {
			countries: getCountries(),
			loadingCountries: ! hasFinishedResolution( 'getCountries' ),
		};
	} );

	const customerViewEnabled = isFeatureEnabled( 'customer_view' );

	const getHeadersContent = () => {
		const headers = [
			{
				label: __( 'Name', 'woocommerce' ),
				key: 'name',
				required: true,
				isLeftAligned: true,
				isSortable: true,
			},
			{
				label: __( 'Username', 'woocommerce' ),
				key: 'username',
				hiddenByDefault: true,
			},
			{
				label: __( 'Last active', 'woocommerce' ),
				key: 'date_last_active',
				defaultSort: true,
				isSortable: true,
			},
			{
				label: __( 'Date registered', 'woocommerce' ),
				key: 'date_registered',
				isSortable: true,
			},
			{
				label: __( 'Email', 'woocommerce' ),
				key: 'email',
			},
			{
				label: __( 'Orders', 'woocommerce' ),
				key: 'orders_count',
				isSortable: true,
				isNumeric: true,
			},
			{
				label: __( 'Total spend', 'woocommerce' ),
				key: 'total_spend',
				isSortable: true,
				isNumeric: true,
			},
			{
				label: __( 'AOV', 'woocommerce' ),
				screenReaderLabel: __( 'Average order value', 'woocommerce' ),
				key: 'avg_order_value',
				isNumeric: true,
			},
			{
				label: __( 'Country / Region', 'woocommerce' ),
				key: 'country',
				isSortable: true,
			},
			{
				label: __( 'City', 'woocommerce' ),
				key: 'city',
				hiddenByDefault: true,
				isSortable: true,
			},
			{
				label: __( 'Region', 'woocommerce' ),
				key: 'state',
				hiddenByDefault: true,
				isSortable: true,
			},
			{
				label: __( 'Postal code', 'woocommerce' ),
				key: 'postcode',
				hiddenByDefault: true,
				isSortable: true,
			},
		];
		if ( customerViewEnabled ) {
			headers.push(
				{
					label: __( 'Lifecycle', 'woocommerce' ),
					key: 'lifecycle_status',
					hiddenByDefault: true,
				},
				{
					label: __( 'Tags', 'woocommerce' ),
					key: 'tags',
					hiddenByDefault: true,
				}
			);
		}
		return headers;
	};

	const getCountryName = ( code ) => {
		return typeof countries[ code ] !== 'undefined'
			? countries[ code ]
			: null;
	};

	const getRowsContent = ( customers ) => {
		const dateFormat = getAdminSetting(
			'dateFormat',
			defaultTableDateFormat
		);
		const {
			formatAmount,
			formatDecimal: getCurrencyFormatDecimal,
			getCurrencyConfig,
		} = context;

		return customers?.map( ( customer ) => {
			const {
				avg_order_value: avgOrderValue,
				date_last_active: dateLastActive,
				date_registered: dateRegistered,
				email,
				name,
				id: customerId,
				user_id: userId,
				orders_count: ordersCount,
				username,
				total_spend: totalSpend,
				postcode,
				city,
				state,
				country,
				lifecycle_status: lifecycleStatus,
				tags: customerTags,
			} = customer;
			const countryName = getCountryName( country );
			const customerName =
				name?.trim() !== '' ? (
					name
				) : (
					<Pill>{ __( 'Guest', 'woocommerce' ) }</Pill>
				);

			let customerNameLink;
			if ( customerViewEnabled && customerId ) {
				customerNameLink = (
					<Link
						href={ getAdminLink(
							`admin.php?page=wc-admin&path=/customers/${ customerId }`
						) }
						type="wc-admin"
					>
						{ name || customerName }
					</Link>
				);
			} else if ( userId ) {
				customerNameLink = (
					<Link
						href={ getAdminLink(
							'user-edit.php?user_id=' + userId
						) }
						type="wp-admin"
					>
						{ name }
					</Link>
				);
			} else {
				customerNameLink = customerName;
			}

			const dateLastActiveDisplay = dateLastActive ? (
				<Date date={ dateLastActive } visibleFormat={ dateFormat } />
			) : (
				'—'
			);

			const dateRegisteredDisplay = dateRegistered ? (
				<Date date={ dateRegistered } visibleFormat={ dateFormat } />
			) : (
				'—'
			);

			const countryDisplay = (
				<Fragment>
					<Tooltip text={ countryName }>
						<span aria-hidden="true">{ country }</span>
					</Tooltip>
					<span className="screen-reader-text">{ countryName }</span>
				</Fragment>
			);

			return [
				{
					display: customerNameLink,
					value: name,
				},
				{
					display: username,
					value: username,
				},
				{
					display: dateLastActiveDisplay,
					value: dateLastActive,
				},
				{
					display: dateRegisteredDisplay,
					value: dateRegistered,
				},
				{
					display: <a href={ 'mailto:' + email }>{ email }</a>,
					value: email,
				},
				{
					display: formatValue(
						getCurrencyConfig(),
						'number',
						ordersCount
					),
					value: ordersCount,
				},
				{
					display: formatAmount( totalSpend ),
					value: getCurrencyFormatDecimal( totalSpend ),
				},
				{
					display: formatAmount( avgOrderValue ),
					value: getCurrencyFormatDecimal( avgOrderValue ),
				},
				{
					display: countryDisplay,
					value: country,
				},
				{
					display: city,
					value: city,
				},
				{
					display: state,
					value: state,
				},
				{
					display: postcode,
					value: postcode,
				},
				...( customerViewEnabled
					? [
							{
								display: lifecycleStatus ? (
									<span
										className={ `wc-lifecycle wc-lifecycle--${ lifecycleStatus }` }
									>
										{ lifecycleStatus }
									</span>
								) : (
									'—'
								),
								value: lifecycleStatus,
							},
							{
								display:
									Array.isArray( customerTags ) &&
									customerTags.length > 0 ? (
										<ul className="wc-customer-tags-cell">
											{ customerTags.map( ( tag ) => (
												<li
													key={ tag.tag_id }
													className="wc-tag-chip"
													style={
														tag.color
															? {
																	backgroundColor:
																		tag.color,
															  }
															: undefined
													}
												>
													{ tag.name }
												</li>
											) ) }
										</ul>
									) : (
										'—'
									),
								value: Array.isArray( customerTags )
									? customerTags
											.map( ( t ) => t.slug )
											.join( ',' )
									: '',
							},
					  ]
					: [] ),
			];
		} );
	};

	const getSummary = ( totals ) => {
		const {
			customers_count: customersCount = 0,
			avg_orders_count: avgOrdersCount = 0,
			avg_total_spend: avgTotalSpend = 0,
			avg_avg_order_value: avgAvgOrderValue = 0,
		} = totals;
		const { formatAmount, getCurrencyConfig } = context;
		const currency = getCurrencyConfig();
		return [
			{
				label: _n(
					'customer',
					'customers',
					customersCount,
					'woocommerce'
				),
				value: formatValue( currency, 'number', customersCount ),
			},
			{
				label: _n(
					'Average order',
					'Average orders',
					avgOrdersCount,
					'woocommerce'
				),
				value: formatValue( currency, 'number', avgOrdersCount ),
			},
			{
				label: __( 'Average lifetime spend', 'woocommerce' ),
				value: formatAmount( avgTotalSpend ),
			},
			{
				label: __( 'Average order value', 'woocommerce' ),
				value: formatAmount( avgAvgOrderValue ),
			},
		];
	};

	return (
		<ReportTable
			endpoint="customers"
			getHeadersContent={ getHeadersContent }
			getRowsContent={ getRowsContent }
			getSummary={ getSummary }
			summaryFields={ [
				'customers_count',
				'avg_orders_count',
				'avg_total_spend',
				'avg_avg_order_value',
			] }
			isRequesting={ isRequesting || loadingCountries }
			itemIdField="id"
			query={ query }
			labels={ {
				placeholder: __( 'Search by customer name', 'woocommerce' ),
			} }
			searchBy="customers"
			title={ __( 'Customers', 'woocommerce' ) }
			columnPrefsKey="customers_report_columns"
			filters={ filters }
			advancedFilters={ advancedFilters }
		/>
	);
}

export default CustomersReportTable;
