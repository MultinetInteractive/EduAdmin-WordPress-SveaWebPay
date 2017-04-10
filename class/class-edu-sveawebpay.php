<?php
defined( 'ABSPATH' ) or die( 'This plugin must be run within the scope of WordPress.' );

use Svea\WebPay\WebPay;
use Svea\WebPay\WebPayItem;
use Svea\WebPay\Config\ConfigurationService;
use Svea\WebPay\Checkout\Model\PresetValue;

if(!class_exists('EDU_SveaWebPay')):

class EDU_SveaWebPay extends EDU_Integration {
	public function __construct() {
		$this->id = 'eduadmin-sveawebpay';
		$this->displayName = __('Svea Webpay', 'eduadmin-sveawebpay');
		$this->description = '';

		$this->init_settings();
	}

	public function init_settings() {
		$this->setting_fields =	array(
			'enabled' => array(
				'title'			=> __('Enabled', 'eduadmin-sveawebpay'),
				'type'			=> 'checkbox',
				'description'	=> __('Enables/Disabled the integration with Svea WebPay', 'eduadmin-sveawebpay'),
				'default'		=> 'no'
			),
			'merchant_key' => array(
				'title'			=> __('Merchant key', 'eduadmin-sveawebpay'),
				'type'			=> 'text',
				'description'	=> __('Please enter your merchant key from Svea WebPay.', 'eduadmin-sveawebpay')
			),
			'merchant_secret' => array(
				'title'			=> __('Merchant secret', 'eduadmin-sveawebpay'),
				'type'			=> 'password',
				'description'	=> ''
			)
		);
	}
}

endif;