<?php
namespace webaware\gf_heidelpay;

if (!defined('ABSPATH')) {
	exit;
}

/**
* implement a Gravity Forms Payment Add-on instance
*/
class AddOn extends \GFPaymentAddOn {

	protected $validationMessages;						// any validation messages picked up for the form as a whole
	protected $urlPaymentForm;							// URL for payment form where purchaser will enter credit card details
	protected $feed = null;								// current feed mapping form fields to payment fields
	protected $defaultCurrency;							// default currency from Gravity Forms settings
	protected $currency = null;							// current currency as detected in validation step, via feed settings
	protected $feedDefaultFieldMap;						// map of default fields for feed

	/**
	* static method for getting the instance of this singleton object
	* @return self
	*/
	public static function get_instance() {
		static $instance = null;

		if ($instance === null) {
			$instance = new self();
		}

		return $instance;
	}

	/**
	* declare detail to GF Add-On framework
	*/
	public function __construct() {
		$this->_version						= GFHEIDELPAY_PLUGIN_VERSION;
		$this->_min_gravityforms_version	= MIN_VERSION_GF;
		$this->_slug						= 'gravityforms-heidelpay';		// preserve old pre-wp.org slug here, for GF data compatibility
		$this->_path						= GFHEIDELPAY_PLUGIN_NAME;
		$this->_full_path					= GFHEIDELPAY_PLUGIN_FILE;
		$this->_title						= 'heidelpay';					// NB: no localisation yet
		$this->_short_title					= 'heidelpay';					// NB: no localisation yet
		$this->_supports_callbacks			= true;

		// define capabilities so that they can be assigned by role/permissions editors (e.g. Members plugin)
		$this->_capabilities				= ['gravityforms_heidelpay', 'gravityforms_heidelpay_uninstall'];
		$this->_capabilities_settings_page	= 'gravityforms_heidelpay';
		$this->_capabilities_form_settings	= 'gravityforms_heidelpay';
		$this->_capabilities_uninstall		= 'gravityforms_heidelpay_uninstall';

		parent::__construct();

		add_action('init', [$this, 'lateLocalise'], 9);
		add_filter('gform_pre_render', [$this, 'detectFeedCurrency']);
		add_filter('gform_validation', [$this, 'preValidate'], 15);
		add_filter('gform_validation_message', [$this, 'gformValidationMessage'], 10, 2);
		add_filter('gform_custom_merge_tags', [$this, 'gformCustomMergeTags'], 10, 4);
		add_filter('gform_replace_merge_tags', [$this, 'gformReplaceMergeTags'], 10, 7);
		add_action('wp', [$this, 'processFormConfirmation'], 5);		// process redirect to GF confirmation

		// handle the new Payment Details box
		add_action('gform_payment_details', [$this, 'gformPaymentDetails'], 10, 2);

		// handle deferrals
		add_filter("gform_{$this->_slug}_feed_settings_fields", [$this, 'gformAddSettingsDelayed']);
		add_filter('gform_is_delayed_pre_process_feed', [$this, 'gformIsDelayed'], 10, 4);
		add_filter('gform_disable_post_creation', [$this, 'gformDelayPost'], 10, 3);

		$this->defaultCurrency = \GFCommon::get_currency();
	}

	/**
	* late localisation of strings, after load_plugin_textdomain() has been called
	*/
	public function lateLocalise() {
		$this->_title			= esc_html_x('heidelpay', 'add-on full title', 'gf-heidelpay');
		$this->_short_title		= esc_html_x('heidelpay', 'add-on short title', 'gf-heidelpay');
	}

	/**
	* add our admin initialisation
	*/
	public function init_admin() {
		parent::init_admin();

		add_action('gform_payment_status', [$this, 'gformPaymentStatus'], 10, 3);
		add_action('gform_after_update_entry', [$this, 'gformAfterUpdateEntry'], 10, 2);
	}

	/**
	* null the add-on framework load of text domain, because we already did it, thanks.
	*/
	public function load_text_domain() {
	}

	/**
	* run add-on framework setup routines, then check for upgrade requirements
	*/
	public function setup() {
		parent::setup();

		if (get_plugin_schema_version() < SCHEMA_VERSION) {
			require GFHEIDELPAY_PLUGIN_ROOT . 'includes/class.SchemaUpgrade.php';
			new SchemaUpgrade($this);
		}
	}

	/**
	* enqueue required styles
	*/
	public function styles() {
		$ver = SCRIPT_DEBUG ? time() : GFHEIDELPAY_PLUGIN_VERSION;

		$styles = [

			[
				'handle'		=> 'heidelpay_admin',
				'src'			=> plugins_url('css/admin.css', GFHEIDELPAY_PLUGIN_FILE),
				'version'		=> $ver,
				'enqueue'		=> [
										[
											'admin_page'	=> ['plugin_settings', 'form_settings'],
											'tab'			=> [$this->_slug],
										],
								],
			],

		];

		return array_merge(parent::styles(), $styles);
	}

	/**
	* enqueue required scripts
	*/
	public function scripts() {
		$min = SCRIPT_DEBUG ? '' : '.min';
		$ver = SCRIPT_DEBUG ? time() : GFHEIDELPAY_PLUGIN_VERSION;

		$scripts = [

			[
				'handle'		=> 'heidelpay_feed_admin',
				'src'			=> plugins_url("js/feed-admin$min.js", GFHEIDELPAY_PLUGIN_FILE),
				'version'		=> $ver,
				'deps'			=> ['jquery'],
				'in_footer'		=> true,
				'enqueue'		=> [
										[
											'admin_page'	=> ['form_settings'],
											'tab'			=> [$this->_slug],
										],
								],
			],

		];

		return array_merge(parent::scripts(), $scripts);
	}

	/**
	* set full title of add-on as settings page title
	* @return string
	*/
	public function plugin_settings_title() {
		return esc_html__('heidelpay settings', 'gf-heidelpay');
	}

	/**
	* set icon for settings page in GF < 2.5
	* @return string
	*/
	public function plugin_settings_icon() {
		return $this->get_svg_icon();
	}

	/**
	* set the icon for the settings page menu in GF >= 2.5
	* @return string
	*/
	public function get_menu_icon() {
		return $this->get_svg_icon();
	}

	/**
	* get SVG icon used for plugin
	* @return string
	*/
	protected function get_svg_icon() {
		return file_get_contents(GFHEIDELPAY_PLUGIN_ROOT . '/images/menu-icon.svg');
	}

	/**
	* specify the settings fields to be rendered on the plugin settings page
	* @return array
	*/
	public function plugin_settings_fields() {
		$settings = [
			[
				'title'					=> esc_html__('Live settings', 'gf-heidelpay'),
				'description'			=> esc_html__('These are default settings. Feeds can specify different settings to override these settings.', 'gf-heidelpay'),
				'fields'				=> [

					[
						'name'			=> 'sender',
						'label'			=> esc_html_x('Sender ID', 'feed field name', 'gf-heidelpay'),
						'type'			=> 'text',
						'class'			=> 'large',
						'autocorrect'	=> 'off',
						'autocapitalize' => 'off',
						'spellcheck'	=> 'false',
						'tooltip'		=> esc_html__('Contact heidelpay customer support to get your sender ID.', 'gf-heidelpay'),
					],

					[
						'name'			=> 'login',
						'label'			=> esc_html_x('User Login', 'feed field name', 'gf-heidelpay'),
						'type'			=> 'text',
						'class'			=> 'large',
						'autocorrect'	=> 'off',
						'autocapitalize' => 'off',
						'spellcheck'	=> 'false',
						'tooltip'		=> esc_html__('Contact heidelpay customer support to get your user login.', 'gf-heidelpay'),
					],

					[
						'name'			=> 'password',
						'label'			=> esc_html_x('User Password', 'feed field name', 'gf-heidelpay'),
						'type'			=> 'text',
						'class'			=> 'large',
						'autocorrect'	=> 'off',
						'autocapitalize' => 'off',
						'spellcheck'	=> 'false',
						'tooltip'		=> esc_html__('Contact heidelpay customer support to get your user password.', 'gf-heidelpay'),
					],

					[
						'name'			=> 'channel_OT',
										// translators: field name for CHANNEL OT channel ID
						'label'			=> esc_html_x('Channel OT', 'feed field name', 'gf-heidelpay'),
						'type'			=> 'text',
						'class'			=> 'large',
						'autocorrect'	=> 'off',
						'autocapitalize' => 'off',
						'spellcheck'	=> 'false',
						'tooltip'		=> esc_html__('Contact heidelpay customer support to get your channel ID for CHANNEL OT.', 'gf-heidelpay'),
					],

					[
						'type'			=> 'save',
						'messages'		=> ['success' => esc_html__('Settings updated', 'gf-heidelpay')],
					],

				],
			],
		];

		return $settings;
	}

	/**
	* title of feed settings
	* @return string
	*/
	public function feed_settings_title() {
		return esc_html__('heidelpay transaction settings', 'gf-heidelpay');
	}

	/**
	* columns to display in list of feeds
	* @return array
	*/
	public function feed_list_columns() {
		$columns = [
			'feedName'				=> esc_html_x('Feed name', 'feed field name', 'gf-heidelpay'),
			'feedItem_useTest'		=> esc_html_x('Mode', 'payment transaction mode', 'gf-heidelpay'),
		];

		return $columns;
	}

	/**
	* feed list value for payment mode
	* @param array $item
	* @return string
	*/
	protected function get_column_value_feedItem_useTest($item) {
		switch (rgars($item, 'meta/useTest')) {

			case '0':
				$value = esc_html_x('Live', 'payment transaction mode', 'gf-heidelpay');
				break;

			case '1':
				$value = esc_html_x('Test', 'payment transaction mode', 'gf-heidelpay');
				break;

			default:
				$value = '';
				break;

		}

		return $value;
	}

	/**
	* configure the fields in a feed
	* @return array
	*/
	public function feed_settings_fields() {
		$this->setFeedDefaultFieldMap();

		// set default transaction type to prevent User Rego add-on breaking new feeds with unmet prerequisite
		$current = $this->get_current_settings();
		if ($current === null) {
			$this->set_settings(['transactionType' => 'product']);
		}

		$fields = [

			#region "core settings"

			[
				'fields' => [

					[
						'name'   		=> 'feedName',
						'label'  		=> esc_html_x('Feed name', 'feed field name', 'gf-heidelpay'),
						'type'   		=> 'text',
						'class'			=> 'medium',
						'tooltip'		=> esc_html__('Give this feed a name, to differentiate it from other feeds.', 'gf-heidelpay'),
						'required'		=> '1',
					],

					[
						'name'   		=> 'useTest',
						'label'  		=> esc_html_x('Mode', 'payment transaction mode', 'gf-heidelpay'),
						'type'   		=> 'radio',
						'tooltip'		=> esc_html__('Credit cards will not be processed in Test mode. Special card numbers must be used.', 'gf-heidelpay'),
						'choices'		=> [
							['value' => '0', 'label' => esc_html_x('Live', 'payment transaction mode', 'gf-heidelpay')],
							['value' => '1', 'label' => esc_html_x('Test', 'payment transaction mode', 'gf-heidelpay')],
						],
						'default_value'	=> '1',
					],

					[
						'name'   		=> 'transactionType',
						'type'   		=> 'hidden',
						'default_value'	=> 'product',
					],

					[
						'name'   		=> 'paymentMethod',
						'label'  		=> esc_html_x('Payment Method', 'feed field name', 'gf-heidelpay'),
						'type'   		=> 'radio',
						'tooltip'		=> esc_html__("Debit processes the payment immediately. Authorize reserves the amount on the customer's card or account for processing later.", 'gf-heidelpay'),
						'choices'		=> [
							['value' => 'debit',		'label' => esc_html_x('Debit', 'payment method', 'gf-heidelpay')],
							['value' => 'authorize',	'label' => esc_html_x('Authorize', 'payment method', 'gf-heidelpay')],
						],
						'default_value'	=> 'debit',
					],

					[
						'name'			=> 'test_3D_secure',
						'label'			=> esc_html_x('Test 3D-secure', 'feed field name', 'gf-heidelpay'),
						'type'			=> 'checkbox',
						'choices'		=> [
							['name' => 'test_3D_secure', 'label' => esc_html__('Enable 3D-secure when the feed is set to Test mode', 'gf-heidelpay')],
						],
					],

					[
						'name'			=> 'customConnection',
						'label'			=> esc_html_x('Customize Connection', 'feed field name', 'gf-heidelpay'),
						'type'			=> 'checkbox',
						'tooltip'		=> esc_html__('You can use different connection settings and currency for each feed if you need to.', 'gf-heidelpay'),
						'choices'		=> [
							['value' => '1', 'name' => 'custom_connection', 'label' => esc_html__('Override the default connection settings, just for this feed', 'gf-heidelpay')],
						],
					],

				],
			],

			#endregion "core settings"

			#region "connection settings"

			[
				'title'					=> esc_html__('Connection Settings', 'gf-heidelpay'),
				'id'					=> 'heidelpay-settings-connection',
				'fields' => [

					[
						'name'			=> 'sender',
						'label'			=> esc_html_x('Sender ID', 'feed field name', 'gf-heidelpay'),
						'type'			=> 'text',
						'class'			=> 'large',
						'autocorrect'	=> 'off',
						'autocapitalize' => 'off',
						'spellcheck'	=> 'false',
						'placeholder'	=> esc_html_x('Leave empty to use add-on settings', 'field placeholder', 'gf-heidelpay'),
						'tooltip'		=> esc_html__('You can use a different sender ID for this feed, or leave it blank to use the add-on settings.', 'gf-heidelpay'),
					],

					[
						'name'			=> 'login',
						'label'			=> esc_html_x('User Login', 'feed field name', 'gf-heidelpay'),
						'type'			=> 'text',
						'class'			=> 'large',
						'autocorrect'	=> 'off',
						'autocapitalize' => 'off',
						'spellcheck'	=> 'false',
						'placeholder'	=> esc_html_x('Leave empty to use add-on settings', 'field placeholder', 'gf-heidelpay'),
						'tooltip'		=> esc_html__('You can use a different login for this feed, or leave it blank to use the add-on settings.', 'gf-heidelpay'),
					],

					[
						'name'			=> 'password',
						'label'			=> esc_html_x('User Password', 'feed field name', 'gf-heidelpay'),
						'type'			=> 'text',
						'class'			=> 'large',
						'autocorrect'	=> 'off',
						'autocapitalize' => 'off',
						'spellcheck'	=> 'false',
						'placeholder'	=> esc_html_x('Leave empty to use add-on settings', 'field placeholder', 'gf-heidelpay'),
						'tooltip'		=> esc_html__('You can use a different password for this feed, or leave it blank to use the add-on settings.', 'gf-heidelpay'),
					],

					[
						'name'			=> 'channel_id',
						'label'			=> esc_html_x('Channel ID', 'feed field name', 'gf-heidelpay'),
						'type'			=> 'text',
						'class'			=> 'large',
						'autocorrect'	=> 'off',
						'autocapitalize' => 'off',
						'spellcheck'	=> 'false',
						'placeholder'	=> esc_html_x('Leave empty to use add-on settings', 'field placeholder', 'gf-heidelpay'),
						'tooltip'		=> esc_html__('You can use a different channel ID for this feed, or leave it blank to use the add-on settings.', 'gf-heidelpay'),
					],

					[
						'name'			=> 'currency',
						'label'			=> esc_html_x('Currency', 'feed field name', 'gf-heidelpay'),
						'type'			=> 'select',
						'tooltip'		=> esc_html__('You can use a different currency for this feed, or use the Gravity Forms settings.', 'gf-heidelpay'),
						'choices'		=> self::getCurrencies(__('Use default currency', 'gf-heidelpay')),
					],

				],
			],

			#endregion "connection settings"

			#region "mapped fields"

			[
				'title'					=> esc_html__('Mapped Field Settings', 'gf-heidelpay'),
				'fields'				=> [

					[
						'name'			=> 'billingInformation',
						'type'			=> 'field_map',
						'field_map'		=> $this->billing_info_fields(),
					],

				],
			],

			#endregion "mapped fields"

			#region "hosted page settings"

			[
				'title'					=> esc_html__('Hosted Page Settings', 'gf-heidelpay'),
				'id'					=> 'heidelpay-settings-shared',
				'fields'				=> [

					[
						'name'			=> 'enabledMethods',
						'label'			=> esc_html_x('Enabled Methods', 'feed field name', 'gf-heidelpay'),
						'type'			=> 'enabled_methods',
						'class'			=> 'heidelpay-feed-enabled-methods',
						'default_value'	=> $this->getDefaultEnabledMethods(),
					],

					[
						'name'			=> 'cancelURL',
						'label'			=> esc_html_x('Cancel URL', 'feed field name', 'gf-heidelpay'),
						'type'			=> 'text',
						'class'  		=> 'large',
						'placeholder'	=> esc_html_x('Leave empty to use default Gravity Forms confirmation handler', 'field placeholder', 'gf-heidelpay'),
						'tooltip'		=> __('Redirect to this URL if the transaction is canceled.', 'gf-heidelpay')
										.  '<br/><br/>'
										.  __('Please note: standard Gravity Forms submission logic applies if the transaction is successful.', 'gf-heidelpay'),
					],

					[
						'name'			=> 'post_payment_actions',
						'label'			=> esc_html_x('Post Payment Actions', 'feed field name', 'gf-heidelpay'),
						'type'			=> 'checkbox',
						'choices'		=> [
							['name' => 'delay_post', 'label' => esc_html__('Create post only when transaction completes', 'gf-heidelpay')],
						],
						'tooltip'		=> esc_html__('Select which actions should only occur after transaction has been completed.', 'gf-heidelpay')
										.  '<br/><br/>'
										.  esc_html__('By default, the transaction must be successful to trigger these actions, or there must be no transaction. You can change that with the Delayed Execute setting.', 'gf-heidelpay'),
					],

					[
						'name'			=> 'execDelayedAlways',
						'label'			=> esc_html_x('Always Execute', 'feed field name', 'gf-heidelpay'),
						'type'			=> 'checkbox',
						'choices'		=> [
							['name' => 'execDelayedAlways', 'label' => esc_html__('Always execute delayed actions, regardless of payment status', 'gf-heidelpay')],
						],
						'tooltip'		=> __('The delayed actions above will only be processed for successful transactions, unless this option is enabled.', 'gf-heidelpay'),
					],

				],
			],

			#endregion "hosted page settings"

			#region "conditional processing settings"

			[
				'title'					=> esc_html__('Feed Conditions', 'gf-heidelpay'),
				'fields'				=> [

					[
						'name'			=> 'condition',
						'label'			=> esc_html_x('heidelpay condition', 'feed field name', 'gf-heidelpay'),
						'type'			=> 'feed_condition',
						'checkbox_label' => 'Enable',
						'instructions'	=> esc_html_x('Send to heidelpay if', 'feed conditions', 'gf-heidelpay'),
						'tooltip'		=> esc_html__('When the heidelpay condition is enabled, form submissions will only be sent to heidelpay when the condition is met. When disabled, all form submissions will be sent to heidelpay.', 'gf-heidelpay'),
					],

				],
			],

			#endregion "conditional processing settings"

		];

		return $fields;
	}

	/**
	* title of fields column for mapped fields
	* @return string
	*/
	public function field_map_title() {
		return esc_html_x('heidelpay field', 'mapped fields title', 'gf-heidelpay');
	}

	/**
	* build map of field types to fields, for default field mappings
	*/
	protected function setFeedDefaultFieldMap() {
		$this->feedDefaultFieldMap = [];

		$form_id = rgget( 'id' );
		$form = \GFFormsModel::get_form_meta( $form_id );

		if (!isset($this->feedDefaultFieldMap['billingInformation_description'])) {
			$this->feedDefaultFieldMap['billingInformation_description']			= 'form_title';
		}

		if (is_array($form['fields'])) {
			foreach ($form['fields'] as $field) {

				switch ($field->type) {

					case 'name':
						if (!isset($this->feedDefaultFieldMap['billingInformation_title'])) {
							$this->feedDefaultFieldMap['billingInformation_title']			= $field->id . '.2';
							$this->feedDefaultFieldMap['billingInformation_firstName']		= $field->id . '.3';
							$this->feedDefaultFieldMap['billingInformation_lastName']		= $field->id . '.6';
						}
						break;

					case 'address':
						if (!isset($this->feedDefaultFieldMap['billingInformation_address'])) {
							// assign first address field to billing address
							$this->feedDefaultFieldMap['billingInformation_address']		= $field->id . '.1';
							$this->feedDefaultFieldMap['billingInformation_address2']		= $field->id . '.2';
							$this->feedDefaultFieldMap['billingInformation_city']			= $field->id . '.3';
							$this->feedDefaultFieldMap['billingInformation_state']			= $field->id . '.4';
							$this->feedDefaultFieldMap['billingInformation_zip']			= $field->id . '.5';
							$this->feedDefaultFieldMap['billingInformation_country']		= $field->id . '.6';
						}
						break;

					case 'email':
						if (!isset($this->feedDefaultFieldMap['billingInformation_email'])) {
							$this->feedDefaultFieldMap['billingInformation_email']			= $field->id;
						}
						break;

					case 'phone':
						if (!isset($this->feedDefaultFieldMap['billingInformation_phone'])) {
							// assign first phone field to billing and shipping phone number
							$this->feedDefaultFieldMap['billingInformation_phone']			= $field->id;
						}
						elseif (!isset($this->feedDefaultFieldMap['billingInformation_mobile'])) {
							// assign second phone field to mobile number
							$this->feedDefaultFieldMap['billingInformation_mobile']			= $field->id;
						}
						break;

				}
			}
		}
	}

	/**
	 * Prepend the name fields to the default billing_info_fields added by the framework.
	 *
	 * @return array
	 */
	public function billing_info_fields() {
		$fields = [
			[
				'name' => 'description',
				'label' => esc_html_x('Invoice Description', 'mapped field name', 'gf-heidelpay'),
				'required' => false,
			],
			[
				'name' => 'title',
				'label' => esc_html_x('Customer Title', 'mapped field name', 'gf-heidelpay'),
				'required' => false,
			],
			[
				'name' => 'firstName',
				'label' => esc_html_x('Customer First Name', 'mapped field name', 'gf-heidelpay'),
				'required' => true,
			],
			[
				'name' => 'lastName',
				'label' => esc_html_x('Customer Last Name', 'mapped field name', 'gf-heidelpay'),
				'required' => true,
			],
			[
				'name' => 'birthDate',
				'label' => esc_html_x('Birth Date', 'mapped field name', 'gf-heidelpay'),
				'required' => false,
			],
			[
				'name' => 'companyName',
				'label' => esc_html_x('Company Name', 'mapped field name', 'gf-heidelpay'),
				'required' => false,
			],
			[
				'name' => 'email',
				'label' => esc_html_x('Email', 'mapped field name', 'gf-heidelpay'),
				'required' => false,
			],
			[
				'name' => 'address',
				'label' => esc_html_x('Address', 'mapped field name', 'gf-heidelpay'),
				'required' => true,
			],
			[
				'name' => 'address2',
				'label' => esc_html_x('Address 2', 'mapped field name', 'gf-heidelpay'),
				'required' => false,
			],
			[
				'name' => 'city',
				'label' => esc_html_x('City', 'mapped field name', 'gf-heidelpay'),
				'required' => true,
			],
			[
				'name' => 'state',
				'label' => esc_html_x('State', 'mapped field name', 'gf-heidelpay'),
				'required' => false,
			],
			[
				'name' => 'zip',
				'label' => esc_html_x('Postcode', 'mapped field name', 'gf-heidelpay'),
				'required' => true,
			],
			[
				'name' => 'country',
				'label' => esc_html_x('Country', 'mapped field name', 'gf-heidelpay'),
				'required' => true,
			],
			[
				'name' => 'phone',
				'label' => esc_html_x('Phone', 'mapped field name', 'gf-heidelpay'),
				'required' => false,
			],
			[
				'name' => 'mobile',
				'label' => esc_html_x('Mobile', 'mapped field name', 'gf-heidelpay'),
				'required' => false,
			],
		];

		return $fields;
	}

	/**
	* override to set default mapped field selections from first occurring field of type
	* @param  array $field
	* @return string|null
	*/
	public function get_default_field_select_field($field) {
		if (!empty($this->feedDefaultFieldMap[$field['name']])) {
			return $this->feedDefaultFieldMap[$field['name']];
		}

		return parent::get_default_field_select_field($field);
	}

	/**
	* get default selections for Enabled Methods field
	* @return array
	*/
	protected function getDefaultEnabledMethods() {
		return [
			'CC'		=> 1,
			'DC'		=> 1,
			'DD'		=> 1,
			'OT'		=> 1,
			'VA'		=> 1,
			'PC'		=> 0,
			'IV'		=> 0,
			'PP'		=> 0,

			// credit cards
			'VISA'		=> 1,
			'MASTER'	=> 1,
			'AMEX'		=> 1,
			'JCB'		=> 1,
			'DINERS'	=> 0,
			'DISCOVER'	=> 0,
		];
	}

	/**
	* show an Enabled Methods field
	* @param array $field
	* @param bool $echo
	* @return string
	*/
	public function settings_enabled_methods($field, $echo = true) {
		$methods = [
			'CC'		=> _x('Credit Card',		'payment method', 'gf-heidelpay'),
			'DC'		=> _x('Debit Card',			'payment method', 'gf-heidelpay'),
			'DD'		=> _x('Direct Debit',		'payment method', 'gf-heidelpay'),
			'OT'		=> _x('Online Transfer',	'payment method', 'gf-heidelpay'),
			'VA'		=> _x('PayPal',				'payment method', 'gf-heidelpay'),
			'PC'		=> _x('Payment Card',		'payment method', 'gf-heidelpay'),
			'IV'		=> _x('Invoice',			'payment method', 'gf-heidelpay'),
			'PP'		=> _x('Prepayment',			'payment method', 'gf-heidelpay'),
		];

		$creditcards = [
        	'VISA'		=> _x('Visa',						'credit cards', 'gf-heidelpay'),
        	'MASTER'	=> _x('Mastercard',					'credit cards', 'gf-heidelpay'),
        	'AMEX'		=> _x('American Express',			'credit cards', 'gf-heidelpay'),
        	'JCB'		=> _x('Japan Credit Bureau (JCB)',	'credit cards', 'gf-heidelpay'),
        	'DINERS'	=> _x('Diners Club',				'credit cards', 'gf-heidelpay'),
        	'DISCOVER'	=> _x('Discover',					'credit cards', 'gf-heidelpay'),
		];

		$selections = $this->get_setting($field['name'], rgar($field, 'default_value'));

		ob_start();

		// hidden field to hold the recorded value
		$value = rgar($field, 'value') ? rgar($field, 'value') : rgar($field, 'default_value');
		$this->settings_hidden(['name'	=> $field['name'], 'id'	=> $field['name'], 'value' => $value]);

		// list of checkboxes, recorded in hidden field as JSON via JavaScript event monitoring
		require GFHEIDELPAY_PLUGIN_ROOT . 'views/admin-feed-settings-enabled-methods.php';

		$html = ob_get_clean();

		if ($echo) {
			echo $html;
		}

		return $html;
	}

	/**
	* specify where the "Post Payment Action" setting should appear on the payment add-on feed
	* @param string $feed_slug
	* @return array
	*/
	public function get_post_payment_actions_config( $feed_slug ) {
		return [
			'position' => 'after',
			'setting'  => 'options',
		];
	}

	/**
	* add fields for delayed actions
	* @param array $fields
	* @return array
	*/
	public function gformAddSettingsDelayed($fields) {
		// detect Gravity Forms < 2.4.14 (which added automatic support for post payment actions to non-PayPal payment add-ons)
		if (!method_exists($this, 'add_post_payment_actions')) {
			// handle add-ons that support delayed payment conventions
			$addons = self::get_registered_addons();
			foreach ($addons as $class_name) {
				if (method_exists($class_name, 'get_instance')) {
					$addon = call_user_func([$class_name, 'get_instance']);
					if (!empty($addon->delayed_payment_integration)) {
						$fields = $addon->add_paypal_post_payment_actions($fields);
					}
				}
			}
		}

		// manually add some supported add-ons that don't comply with delayed payment conventions
		if (method_exists('GFZapier', 'add_paypal_post_payment_actions')) {
			// Zapier add-on versions 1.6 - 3.1
			$fields = \GFZapier::add_paypal_post_payment_actions($fields, $this);
		}

		return $fields;
	}

	/**
	* get list of currencies for select lists
	* @param string $default_label
	* @return array
	*/
	protected function getCurrencies($default_label) {
		if (!class_exists('RGCurrency', false)) {
			require_once \GFCommon::get_base_path() . '/currency.php';
		}
		$currencies = \RGCurrency::get_currencies();

		$options = [];

		if ($default_label) {
			$options[] = ['value' => '', 'label' => $default_label];
		}

		// translators: 1: currency code; 2: currency name
		$optionFormat = __('%1$s &mdash; %2$s', 'currency list', 'gf-heidelpay');

		foreach ($currencies as $ccode => $currency) {
			$options[] = ['value' => $ccode, 'label' => sprintf($optionFormat, esc_html($ccode), $currency['name'])];
		}

		return $options;
	}

	/**
	* detect that active feed is modifying currency, set currency hooks
	* @param array $form
	* @return array
	*/
	public function detectFeedCurrency($form) {
		$this->currency = null;

		$feeds = $this->get_active_feeds($form['id']);

		foreach ($feeds as $feed) {
			// must meet feed conditions, if any
			if (!$this->is_feed_condition_met($feed, $form, [])) {
				continue;
			}

			// pick up the currency of this feed, if different to global setting
			$feedCurrency = $this->getActiveCurrency($feed);
			if ($this->defaultCurrency !== $feedCurrency) {
				$this->currency = $feedCurrency;
				add_filter('gform_currency', [$this, 'gformCurrency']);
				break;
			}
		}

		return $form;
	}

	/**
	* steps prior to form validation
	* @param array $validation_result
	* @return array
	*/
	public function preValidate($validation_result) {
		$form = $validation_result['form'];

		$this->detectFeedCurrency($form);

		return $validation_result;
	}

	/**
	* test form for product fields
	* @param array $form
	* @return bool
	*/
	protected static function hasProductFields($form) {
		foreach ($form['fields'] as $field) {
			if ($field->type === 'shipping' || $field->type === 'product') {
				return true;
			}
		}

		return false;
	}

	/**
	* process form validation
	* @param array $data an array with elements is_valid (boolean) and form (array of form elements)
	* @return array
	*/
	public function validation($data) {
		try {
			$data = parent::validation($data);

			if ($data['is_valid'] && $this->is_payment_gateway) {
				$form  = $data['form'];

				// make sure form hasn't already been submitted / processed
				if (has_form_been_processed($form['id'])) {
					throw new GFHeidelpayException(__('Payment already submitted and processed - please close your browser window.', 'gf-heidelpay'));
				}

				// set hook to request redirect URL
				add_filter('gform_entry_post_save', [$this, 'requestRedirectUrl'], 9, 2);
			}
		}
		catch (GFHeidelpayException $e) {
			$data['is_valid'] = false;
			$this->validationMessages[] = nl2br(esc_html($e->getMessage()));
		}

		return $data;
	}

	/**
	* pre-process feeds to ensure that they have necessary gateway credentials and pick up overridden currency
	* @param array $feeds
	* @param array $entry
	* @param array $form
	* @return array
	* @throws GFHeidelpayException
	*/
	public function pre_process_feeds($feeds, $entry, $form) {
		$this->currency = null;

		if (is_array($feeds)) {
			foreach ($feeds as $feed) {
				// feed must be active and meet feed conditions, if any
				if (!$feed['is_active'] || !$this->is_feed_condition_met($feed, $form, [])) {
					continue;
				}

				// make sure that gateway credentials have been set for feed, or globally
				$creds = new Credentials($this, $feed);
				if ($creds->isIncomplete()) {
					throw new GFHeidelpayException(__('Incomplete credentials for heidelpay payment; please tell the web master.', 'gf-heidelpay'));
				}

				// pick up the currency of this feed, if different to global setting and not already defined by a feed
				$feedCurrency = $this->getActiveCurrency($feed);
				if (is_null($this->currency) && $this->defaultCurrency !== $feedCurrency) {
					$this->currency = $feedCurrency;
					add_filter('gform_currency', [$this, 'gformCurrency']);
				}
			}
		}

		return $feeds;
	}

	/**
	* attempt to get shared page URL for transaction
	* @param array $entry the form entry
	* @param array $form the form submission data
	* @return array
	*/
	public function requestRedirectUrl($entry, $form) {
		$feed				= $this->current_feed;
		$submission_data	= $this->current_submission_data;

		$this->log_debug('========= initiating transaction request');
		$this->log_debug(sprintf('%s: feed #%d - %s', __FUNCTION__, $feed['id'], $feed['meta']['feedName']));

		try {
			$paymentReq = $this->getPaymentRequest($submission_data, $feed, $form, $entry);

			$returnURL						= $this->getReturnURL($paymentReq->transactionNumber);
			$paymentReq->redirectURL		= $returnURL;
			$paymentReq->cancelUrl			= $returnURL;

			// record some payment meta
			gform_update_meta($entry['id'], META_TRANSACTION_ID, $paymentReq->transactionNumber);
			gform_update_meta($entry['id'], META_FEED_ID, $feed['id']);

			$response = $paymentReq->requestSharedPage();

			if ($response && $response->POST_VALIDATION === 'ACK') {
				$this->urlPaymentForm = $response->FRONTEND_REDIRECT_URL;
				\GFFormsModel::update_lead_property($entry['id'], 'payment_status', 'Processing');
				$entry['payment_status']	= 'Processing';
			}
			else {
				$entry['payment_status']	= 'Failed';
				$entry['payment_date']		= date('Y-m-d H:i:s');
				if ($this->currency) {
					$entry['currency']		= $this->currency;
				}

				$paymentMethod = rgar($feed['meta'], 'paymentMethod', 'debit');

				$this->log_debug(sprintf('%s: failed', __FUNCTION__));

				$error_msg = esc_html__('Transaction request failed', 'gf-heidelpay');

				$note = $this->getFailureNote($paymentMethod, [$error_msg]);
				$this->add_note($entry['id'], $note, 'error');

				// record payment failure, and set hook for displaying error message
				$this->error_msg = $error_msg;
				add_filter('gform_confirmation', [$this, 'displayPaymentFailure'], 1000, 4);
			}
		}
		catch (GFHeidelpayException $e) {
			$this->log_error(__FUNCTION__ . ': exception = ' . $e->getMessage());

			// record payment failure, and set hook for displaying error message
			\GFFormsModel::update_lead_property($entry['id'], 'payment_status', 'Failed');
			$this->error_msg = $e->getMessage();
			add_filter('gform_confirmation', [$this, 'displayPaymentFailure'], 1000, 4);
		}

		return $entry;
	}

	/**
	* display a payment request failure message
	* @param mixed $confirmation text or redirect for form submission
	* @param array $form the form submission data
	* @param array $entry the form entry
	* @param bool $ajax form submission via AJAX
	* @return mixed
	*/
	public function displayPaymentFailure($confirmation, $form, $entry, $ajax) {
		// record entry's unique ID in database, to signify that it has been processed so don't attempt another payment!
		gform_update_meta($entry['id'], META_UNIQUE_ID, \GFFormsModel::get_form_unique_id($form['id']));

		// create a "confirmation message" in which to display the error
		$default_anchor = count(\GFCommon::get_fields_by_type($form, ['page'])) > 0 ? 1 : 0;
		$default_anchor = apply_filters('gform_confirmation_anchor_'.$form['id'], apply_filters('gform_confirmation_anchor', $default_anchor));
		$anchor = $default_anchor ? "<a id='gf_{$form["id"]}' name='gf_{$form["id"]}' class='gform_anchor' ></a>" : '';
		$cssClass = rgar($form, 'cssClass');
		$error_msg = wpautop($this->error_msg);

		ob_start();
		include GFHEIDELPAY_PLUGIN_ROOT . 'views/error-payment-failure.php';
		return ob_get_clean();
	}

	/**
	* create and populate a Payment Request object
	* @param GFHeidelpayFormData $formData
	* @param array $feed
	* @param array $form
	* @param array|false $entry
	* @return HeidelpayAPI
	*/
	protected function getPaymentRequest($formData, $feed, $form, $entry = false) {
		// build a payment request and execute on API
		$creds		= new Credentials($this, $feed);
		$useTest	= !empty($feed['meta']['useTest']);
		$paymentReq	= new HeidelpayAPI($creds, $useTest);

		// generate a unique transaction ID to avoid collisions, e.g. between different installations using the same gateway account
		$transactionID = uniqid();

		// allow plugins/themes to modify transaction ID; NB: must remain unique for gateway account!
		$transactionID = apply_filters('gfheidelpay_invoice_trans_number', $transactionID, $form);

		switch (rgar($feed['meta'], 'paymentMethod', 'debit')) {

			case 'authorize':
				$paymentReq->transactionType = HeidelpayAPI::TRANS_CODE_RESERVE;
				break;

			default:
				$paymentReq->transactionType = HeidelpayAPI::TRANS_CODE_DEBIT;
				break;

		}

		$paymentReq->amount					= $formData['payment_amount'];
		$paymentReq->currencyCode			= $this->getActiveCurrency($feed);
		$paymentReq->transactionNumber		= $transactionID;
		$paymentReq->invoiceDescription		= $formData['description'];
		$paymentReq->languageCode			= get_locale();
		$paymentReq->enabledMethods			= rgar($feed['meta'], 'enabledMethods', ['CC' => 1]);

		if (!empty($entry['id'])) {
			$paymentReq->invoiceReference	= sprintf('F%d:E%d', $form['id'], $entry['id']);
		}
		else {
			$paymentReq->invoiceReference	= 'F' . $form['id'];
		}

		// billing details
		$paymentReq->title					= $formData['title'];
		$paymentReq->lastName				= $formData['lastName'];
		$paymentReq->firstName				= $formData['firstName'];
		$paymentReq->birthDate				= $formData['birthDate'];
		$paymentReq->companyName			= $formData['companyName'];
		$paymentReq->address1				= $formData['address'];
		$paymentReq->address2				= $formData['address2'];
		$paymentReq->suburb					= $formData['city'];
		$paymentReq->state					= $formData['state'];
		$paymentReq->postcode				= $formData['zip'];
		$paymentReq->country				= \GFCommon::get_country_code($formData['country']);
		$paymentReq->emailAddress			= $formData['email'];
		$paymentReq->phone					= $formData['phone'];
		$paymentReq->mobile					= $formData['mobile'];

		// allow plugins/themes to modify invoice description and reference, and set option fields
		$paymentReq->invoiceDescription		= apply_filters('gfheidelpay_invoice_desc', $paymentReq->invoiceDescription, $form);
		$paymentReq->invoiceReference		= apply_filters('gfheidelpay_invoice_ref', $paymentReq->invoiceReference, $form);

		return $paymentReq;
	}

	/**
	* generate an entry-based return URL for passing information back from gateway
	* @param string $transactionNumber
	* @return string
	*/
	protected function getReturnURL($transactionNumber) {
		$args = [
			'callback'		=> $this->_slug,
			'txid'			=> $transactionNumber,
		];
		$hash = wp_hash(wp_json_encode($args));
		$args['hash'] = $hash;

		$url = home_url('/');

		$url = add_query_arg($args, $url);

		return $url;
	}

	/**
	* alter the validation message
	* @param string $msg
	* @param array $form
	* @return string
	*/
	public function gformValidationMessage($msg, $form) {
		if (!empty($this->validationMessages)) {
			$msg = sprintf('<div class="validation_error">%s</div>', implode('<br />', $this->validationMessages));
		}

		return $msg;
	}

	/**
	* change the entry currency just before saving
	* @param string $currency
	* @return string
	*/
	public function gformCurrency($currency) {
		if (!is_null($this->currency)) {
			$currency = $this->currency;
		}

		return $currency;
	}

	/**
	* return redirect URL for the payment gateway
	* @param array $feed
	* @param array $submission_data
	* @param array $form
	* @param array $entry
	* @return string
	*/
	public function redirect_url($feed, $submission_data, $form, $entry) {
		if ($this->urlPaymentForm) {
			// record entry's unique ID in database, to signify that it has been processed so don't attempt another payment!
			gform_update_meta($entry['id'], META_UNIQUE_ID, \GFFormsModel::get_form_unique_id($form['id']));
		}

		return $this->urlPaymentForm;
	}

	/**
	* test for valid callback from gateway
	*/
	public function is_callback_valid() {
		if (rgget('callback') != $this->_slug) {
			return false;
		}

		$hash = rgget('hash');
		if (empty($hash)) {
			return false;
		}

		$args = [
			'callback'	=> rgget('callback'),
			'txid'		=> rgget('txid'),
		];
		if ($hash !== wp_hash(wp_json_encode($args))) {
			return false;
		}

		return true;
	}

	/**
	* process the gateway callback
	*/
	public function callback() {
		$this->log_debug('========= processing transaction result');

		$transactionNumber = rgget('txid');

		try {
			$search = [
				'field_filters' => [
					[
						'key'		=> META_TRANSACTION_ID,
						'value'		=> $transactionNumber,
					],
				],
			];
			$entries = \GFAPI::get_entries(0, $search);

			// must have an entry, or nothing to do
			if (empty($entries)) {
				throw new GFHeidelpayException(sprintf(__('Invalid transaction number: %s', 'gf-heidelpay'), $transactionNumber));
			}
			$entry = $entries[0];
			$lead_id = rgar($entry, 'id');

			$response = new ResponseCallback();
			$response->loadResponse($_POST);

			$form = \GFFormsModel::get_form_meta($entry['form_id']);
			$feed = $this->getFeed($lead_id);

			// capture current state of lead
			$initial_status = $entry['payment_status'];

			$capture = (rgar($feed['meta'], 'paymentMethod', 'debit') !== 'authorize');

			if (rgar($entry, 'payment_status') === 'Processing') {
				// update lead entry, with success/fail details
				if ($response->PROCESSING_RESULT === 'ACK') {
					$action = [
						'type'						=> 'complete_payment',
						'payment_status'			=> $capture ? 'Paid' : 'Pending',
						'payment_date'				=> $response->CLEARING_DATE,
						'amount'					=> $response->CLEARING_AMOUNT,
						'currency'					=> $response->CLEARING_CURRENCY,
						'transaction_id'			=> $response->IDENTIFICATION_UNIQUEID,
					];
					$action['note']					=  $this->getPaymentNote($capture, $action, $response->getProcessingMessages());
					$entry[META_SHORT_ID]			=  $response->IDENTIFICATION_SHORTID;
					$entry[META_RETURN_CODE]		=  $response->PROCESSING_RETURN_CODE;
					$entry['currency']				=  $response->CLEARING_CURRENCY;
					$this->complete_payment($entry, $action);

					$this->log_debug(sprintf('%s: success, date = %s, id = %s, status = %s, amount = %s',
						__FUNCTION__, $entry['payment_date'], $entry['transaction_id'], $entry['payment_status'], $entry['payment_amount']));
					$this->log_debug(sprintf('%s: %s', __FUNCTION__, $response->PROCESSING_RETURN));
					$this->log_debug(sprintf('%s: %s', __FUNCTION__, $response->CLEARING_DESCRIPTOR));
				}
				else {
					$entry['payment_status']		=  'Failed';
					$entry['payment_date']			=  $response->CLEARING_DATE;
					$entry['currency']				=  $response->CLEARING_CURRENCY;
					$entry[META_RETURN_CODE]		=  $response->PROCESSING_RETURN_CODE;

					// fail_payment() below doesn't update whole entry, so we need to do it here
					\GFAPI::update_entry($entry);

					if ($response->FRONTEND_REQUEST_CANCELLED) {
						$note = esc_html('Transaction canceled by customer', 'gf-heidelpay');
					}
					else {
						$note = $this->getFailureNote($capture, $response->getProcessingMessages());
					}

					$action = [
						'type'						=> 'fail_payment',
						'payment_status'			=> 'Failed',
						'note'						=> $note,
					];
					$this->fail_payment($entry, $action);

					$this->log_debug(sprintf('%s: failed; %s', __FUNCTION__, $this->getErrorsForLog($response->getProcessingMessages())));
				}

				// if order hasn't been fulfilled, process any deferred actions
				if ($initial_status === 'Processing') {
					$this->log_debug('processing deferred actions');

					$this->processDelayed($feed, $entry, $form);

					// allow hookers to trigger their own actions
					$hook_status = $response->PROCESSING_RESULT === 'ACK' ? 'approved' : 'failed';
					do_action("gfheidelpay_process_{$hook_status}", $entry, $form, $feed);
				}
			}

			if ($entry['payment_status'] === 'Failed' && $feed['meta']['cancelURL']) {
				// on failure, redirect to failure page if set
				$redirect_url = esc_url_raw($feed['meta']['cancelURL']);
			}
			else {
				// otherwise, redirect to Gravity Forms page, passing form and lead IDs, encoded to deter simple attacks
				$query = [
					'form_id'	=> $entry['form_id'],
					'lead_id'	=> $entry['id'],
				];
				$hash = wp_hash(wp_json_encode($query));
				$query['hash']	=  $hash;
				$query = base64_encode(wp_json_encode($query));
				$redirect_url = esc_url_raw(add_query_arg(ENDPOINT_CONFIRMATION, $query, $entry['source_url']));
			}
			echo $redirect_url;
			exit;
		}
		catch (GFHeidelpayException $e) {
			// TODO: what now?
			echo nl2br(esc_html($e->getMessage()));
			$this->log_error(__FUNCTION__ . ': ' . $e->getMessage());
			exit;
		}
	}

	/**
	* filter whether post creation from form is enabled (yet)
	* @param bool $is_delayed
	* @param array $form
	* @param array $entry
	* @return bool
	*/
	public function gformDelayPost($is_delayed, $form, $entry) {
		$feed = $this->get_single_submission_feed($entry);

		if ($entry['payment_status'] === 'Processing' && !empty($feed['meta']['delay_post'])) {
			$is_delayed = true;
			$this->log_debug(sprintf('delay post creation: form id %s, lead id %s', $form['id'], $entry['id']));
		}

		return $is_delayed;
	}

	/**
	* filter whether form delays some actions (e.g. MailChimp)
	* @param bool $is_delayed
	* @param array $form
	* @param array $entry
	* @param string $addon_slug
	* @return bool
	*/
	public function gformIsDelayed($is_delayed, $form, $entry, $addon_slug) {
		if ($entry['payment_status'] === 'Processing') {
			$feed = $this->get_single_submission_feed($entry);

			if ($feed) {

				$is_delayed = $entry['payment_status'] === 'Processing' && !empty($feed['meta']['delay_' . $addon_slug]);
				if ($is_delayed) {
					$this->log_debug(sprintf('delay %s: form id %s, entry id %s', $addon_slug, $form['id'], $entry['id']));
				}

			}

		}

		return $is_delayed;
	}

	/**
	* process any delayed actions
	* @param array $feed
	* @param array $entry
	* @param array $form
	*/
	protected function processDelayed($feed, $entry, $form) {
		// default to only performing delayed actions if payment was successful, unless feed opts to always execute
		// can filter each delayed action to permit / deny execution
		$execute_delayed = in_array($entry['payment_status'], ['Paid', 'Pending']) || $feed['meta']['execDelayedAlways'];

		if ($feed['meta']['delay_post']) {
			if (apply_filters('gfheidelpay_delayed_post_create', $execute_delayed, $entry, $form, $feed)) {
				$this->log_debug(sprintf('executing delayed post creation; form id %s, lead id %s', $form['id'], $entry['id']));
				\GFFormsModel::create_post($form, $entry);
			}
		}

		if ($execute_delayed) {
			do_action('gform_paypal_fulfillment', $entry, $feed, $entry['transaction_id'], $entry['payment_amount']);
		}
	}

	/**
	* allow edits to payment status
	* @param string $content
	* @param array $form
	* @param array $entry
	* @return string
	*/
	public function gformPaymentStatus($content, $form, $entry) {
		// make sure that we're editing the entry and are allowed to change it
		if ($this->canEditPaymentDetails($entry, 'edit')) {
			// create drop down for payment status
			ob_start();
			include GFHEIDELPAY_PLUGIN_ROOT . 'views/admin-entry-payment-status.php';
			$content = ob_get_clean();
		}

		return $content;
	}

	/**
	* update payment status if it has changed
	* @param array $form
	* @param int $entry_id
	*/
	public function gformAfterUpdateEntry($form, $entry_id) {
		// make sure we have permission
		check_admin_referer('gforms_save_entry', 'gforms_save_entry');

		$entry = \GFFormsModel::get_lead($entry_id);

		// make sure that we're editing the entry and are allowed to change it
		if (!$this->canEditPaymentDetails($entry, 'update')) {
			return;
		}

		// make sure we have new values
		$payment_status = rgpost('payment_status');

		if (empty($payment_status)) {
			return;
		}

		$note = __('Payment information was manually updated.', 'gf-heidelpay');

		if ($entry['payment_status'] !== $payment_status) {
			// translators: 1: old payment status; 2: new payment status
			$note .= "\n" . sprintf(__('Payment status changed from %1$s to %2$s.', 'gf-heidelpay'), $entry['payment_status'], $payment_status);
			$entry['payment_status'] = $payment_status;
		}


		\GFAPI::update_entry($entry);

		$user = wp_get_current_user();
		\GFFormsModel::add_note($entry['id'], $user->ID, $user->display_name, esc_html($note));
	}

	/**
	* payment processed and recorded, show confirmation message / page
	*/
	public function processFormConfirmation() {
		// check for redirect to Gravity Forms page with our encoded parameters
		if (isset($_GET[ENDPOINT_CONFIRMATION])) {
			do_action('gfheidelpay_process_confirmation');

			// decode the encoded form and lead parameters
			$query = json_decode(base64_decode($_GET[ENDPOINT_CONFIRMATION]), true);
			$check = [
				'form_id'	=> rgar($query, 'form_id'),
				'lead_id'	=> rgar($query, 'lead_id'),
			];

			// make sure we have a match
			if ($query && wp_hash(wp_json_encode($check)) === rgar($query, 'hash')) {

				// load form and lead data
				$form = \GFFormsModel::get_form_meta($query['form_id']);
				$lead = \GFFormsModel::get_lead($query['lead_id']);

				do_action('gfheidelpay_process_confirmation_parsed', $lead, $form);

				// get confirmation page
				if (!class_exists('GFFormDisplay', false)) {
					require_once(\GFCommon::get_base_path() . '/form_display.php');
				}
				$confirmation = \GFFormDisplay::handle_confirmation($form, $lead, false);

				// preload the GF submission, ready for processing the confirmation message
				\GFFormDisplay::$submission[$form['id']] = [
					'is_confirmation'		=> true,
					'confirmation_message'	=> $confirmation,
					'form'					=> $form,
					'lead'					=> $lead,
				];

				// if it's a redirection (page or other URL) then do the redirect now
				if (is_array($confirmation) && isset($confirmation['redirect'])) {
					wp_safe_redirect($confirmation['redirect']);
					exit;
				}
			}
		}
	}

	/**
	* supported notification events
	* @param array $form
	* @return array
	*/
	public function supported_notification_events( $form ) {
		if (!$this->has_feed($form['id'])) {
			return false;
		}

		return [
			'complete_payment'		=> esc_html_x('Payment Completed', 'notification event', 'gf-heidelpay'),
			'fail_payment'			=> esc_html_x('Payment Failed', 'notification event', 'gf-heidelpay'),
		];
	}

	/**
	* activate and configure custom entry meta
	* @param array $entry_meta
	* @param int $form_id
	* @return array
	*/
	public function get_entry_meta($entry_meta, $form_id) {
		// not on feed admin screen
		if (is_admin()) {
			global $hook_suffix;
			$subview = isset($_GET['subview']) ? $_GET['subview'] : '';

			if ($hook_suffix === 'toplevel_page_gf_edit_forms' && $subview === $this->_slug) {
				return $entry_meta;
			}
		}

		$entry_meta[META_SHORT_ID] = [
			'label'					=> esc_html_x('heidelpay short ID', 'entry meta label', 'gf-heidelpay'),
			'is_numeric'			=> false,
			'is_default_column'		=> false,
			'filter'				=> [
											'operators' => ['is', 'isnot']
									],
		];

		$entry_meta[META_RETURN_CODE] = [
			'label'					=> esc_html_x('heidelpay return code', 'entry meta label', 'gf-heidelpay'),
			'is_numeric'			=> false,
			'is_default_column'		=> false,
			'filter'				=> [
											'operators' => ['is', 'isnot', 'starts_with', 'ends_with', '<', '>']
									],
		];

		return $entry_meta;
	}

	/**
	* add custom merge tags
	* @param array $merge_tags
	* @param int $form_id
	* @param array $fields
	* @param int $element_id
	* @return array
	*/
	public function gformCustomMergeTags($merge_tags, $form_id, $fields, $element_id) {
		if ($form_id) {
			$feeds = $this->get_feeds($form_id);
			if (!empty($feeds)) {
				// at least one feed for this add-on, so add our merge tags
				$tags = array_flip(wp_list_pluck($merge_tags, 'tag'));

				$custom_tags = [
					['label' => esc_html_x('Transaction ID', 'merge tag label', 'gf-heidelpay'), 'tag' => '{transaction_id}'],
					['label' => esc_html_x('Short ID',       'merge tag label', 'gf-heidelpay'), 'tag' => '{heidelpay_short_id}'],
					['label' => esc_html_x('Return Code',    'merge tag label', 'gf-heidelpay'), 'tag' => '{heidelpay_return_code}'],
					['label' => esc_html_x('Payment Amount', 'merge tag label', 'gf-heidelpay'), 'tag' => '{payment_amount}'],
					['label' => esc_html_x('Payment Status', 'merge tag label', 'gf-heidelpay'), 'tag' => '{payment_status}'],
					['label' => esc_html_x('Entry Date',     'merge tag label', 'gf-heidelpay'), 'tag' => '{date_created}'],
				];

				foreach ($custom_tags as $custom) {
					if (!isset($tags[$custom['tag']])) {
						$merge_tags[] = $custom;
					}
				}
			}
		}

		return $merge_tags;
	}

	/**
	* replace custom merge tags
	* @param string $text
	* @param array $form
	* @param array $entry
	* @param bool $url_encode
	* @param bool $esc_html
	* @param bool $nl2br
	* @param string $format
	* @return string
	*/
	public function gformReplaceMergeTags($text, $form, $entry, $url_encode, $esc_html, $nl2br, $format) {
		// handle invalid calls, e.g. Gravity Forms User Registration login widget
		if (empty($form['id'])) {
			return $text;
		}

		$gateway = gform_get_meta($entry['id'], 'payment_gateway');

		if ($gateway === $this->_slug) {
			$heidelpay_short_id    = gform_get_meta($entry['id'], META_SHORT_ID);
			$heidelpay_return_code = gform_get_meta($entry['id'], META_RETURN_CODE);

			// format payment amount as currency
			if (isset($entry['payment_amount'])) {
				$payment_amount = \GFCommon::format_number($entry['payment_amount'], 'currency', rgar($entry, 'currency', ''));
			}
			else {
				$payment_amount = '';
			}

			$tags = [
				'{transaction_id}',
				'{payment_status}',
				'{payment_amount}',
				'{heidelpay_short_id}',
				'{heidelpay_return_code}',
				'{date_created}',
			];
			$values = [
				rgar($entry, 'transaction_id', ''),
				rgar($entry, 'payment_status', ''),
				$payment_amount,
				!empty($heidelpay_short_id)    ? $heidelpay_short_id    : '',
				!empty($heidelpay_return_code) ? $heidelpay_return_code : '',
				\GFCommon::format_date(rgar($entry, 'date_created'), false, '', false),
			];

			// maybe encode the results
			if ($url_encode) {
				$values = array_map('urlencode', $values);
			}
			elseif ($esc_html) {
				$values = array_map('esc_html', $values);
			}

			$text = str_replace($tags, $values, $text);
		}

		return $text;
	}

	/**
	* get feed for lead/entry
	* @param int $lead_id the submitted entry's ID
	* @return array
	*/
	protected function getFeed($lead_id) {
		if ($this->feed !== false && (empty($this->feed['lead_id']) || $this->feed['lead_id'] != $lead_id)) {
			$this->feed = $this->get_feed(gform_get_meta($lead_id, META_FEED_ID));
			if ($this->feed) {
				$this->feed['lead_id'] = $lead_id;
			}
		}

		return $this->feed;
	}

	/**
	* action hook for building the entry details view
	* @param int $form_id
	* @param array $entry
	*/
	public function gformPaymentDetails($form_id, $entry) {
		$payment_gateway = gform_get_meta($entry['id'], 'payment_gateway');
		if ($payment_gateway === $this->_slug) {
			$return_code	= gform_get_meta($entry['id'], META_RETURN_CODE);
			$short_id		= gform_get_meta($entry['id'], META_SHORT_ID);
			$txn_id			= gform_get_meta($entry['id'], META_TRANSACTION_ID);

			require GFHEIDELPAY_PLUGIN_ROOT . 'views/admin-entry-payment-details.php';
		}
	}

	/**
	* test whether we can edit payment details
	* @param array $entry
	* @param string $action
	* @return bool
	*/
	protected function canEditPaymentDetails($entry, $action) {
		// make sure payment is not Approved already (can't go backwards!)
		// no Paid (or Approved), and no Active recurring payments
		$payment_status = rgar($entry, 'payment_status');
		if ($payment_status === 'Approved' || $payment_status === 'Paid' || $payment_status === 'Active') {
			return false;
		}

		// check that we're editing the lead
		if (strcasecmp(rgpost('save'), $action) !== 0) {
			return false;
		}

		// make sure payment is one of ours
		if (gform_get_meta($entry['id'], 'payment_gateway') !== $this->_slug) {
			return false;
		}

		return true;
	}

	/**
	* get currency for feed
	* @param array $feed
	* @return string
	*/
	protected function getActiveCurrency($feed) {
		// if feed has specified a currency, use that
		if (!empty($feed['meta']['custom_connection']) && !empty($feed['meta']['currency'])) {
			return $feed['meta']['currency'];
		}

		// get the Gravity Forms currency setting as the default
		return $this->defaultCurrency;
	}

	/**
	* get payment note based on payment method, with details, and gateway response messages
	* @param bool $capture
	* @param array $results
	* @param array $messages
	* @return string
	*/
	protected function getPaymentNote($capture, $results, $messages) {
		if ($capture) {
			$message = esc_html__('Payment has been captured successfully. Amount: %1$s. Transaction ID: %2$s.', 'gf-heidelpay');
		}
		else {
			$message = esc_html__('Payment has been authorized successfully. Amount: %1$s. Transaction ID: %2$s.', 'gf-heidelpay');
		}

		$amount = \GFCommon::to_money($results['amount'], $results['currency']);

		$note = sprintf($message, $amount, $results['transaction_id']);
		if (!empty($messages)) {
			$note .= "\n" . esc_html(implode("\n", $messages));
		}

		return $note;
	}

	/**
	* get failure note based on payment method, with gateway response messages
	* @param string $paymentMethod
	* @param array $messages
	* @return string
	*/
	protected function getFailureNote($paymentMethod, $messages) {
		switch ($paymentMethod) {

			case 'authorize':
				$note = esc_html__('Payment authorization failed.', 'gf-heidelpay');
				break;

			default:
				$note = esc_html__('Failed to capture payment.', 'gf-heidelpay');
				break;

		}

		if (!empty($messages)) {
			$note .= "\n" . esc_html(implode("\n", $messages));
		}

		return $note;
	}

	/**
	* get formatted error message for front end, with gateway errors appended
	* @param string $error_msg
	* @param array $errors
	* @return string
	*/
	protected function getErrorMessage($error_msg, $errors) {
		if (!empty($errors)) {
			// add detailed error messages
			$error_msg .= "\n" . implode("\n", $errors);
		}

		return $error_msg;
	}

	/**
	* get errors and response messages as a string, for logging
	* @param array $errors
	* @return string
	*/
	protected function getErrorsForLog($errors) {
		return implode('; ', (array) $errors);
	}

}
