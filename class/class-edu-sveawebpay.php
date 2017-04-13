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

		$this->init_form_fields();
		$this->init_settings();

		add_action( 'eduadmin-processbooking', array( $this, 'process_booking' ) );
	}

	public function init_form_fields() {
		$this->setting_fields =	array(
			'enabled' => array(
				'title'       => __( 'Enabled', 'eduadmin-sveawebpay' ),
				'type'        => 'checkbox',
				'description' => __( 'Enables/Disables the integration with Svea WebPay', 'eduadmin-sveawebpay' ),
				'default'     => 'no',
			),
			'testrun' => array(
				'title'          => __( 'Sandbox mode', 'eduadmin-sveawebpay' ),
				'type'           => 'checkbox',
				'description'    => __( 'Activate sandbox mode', 'eduadmin-sveawebpay' ),
				'default'		=> 'no'
			),
			'merchant_key' => array(
				'title'       => __( 'Merchant key', 'eduadmin-sveawebpay' ),
				'type'        => 'text',
				'description' => __( 'Please enter your merchant key from Svea WebPay.', 'eduadmin-sveawebpay' ),
				'placeholder' => __( 'Merchant key', 'eduadmin-sveawebpay' ),
			),
			'merchant_secret' => array(
				'title'       => __( 'Merchant secret', 'eduadmin-sveawebpay' ),
				'type'        => 'password',
				'description' => __( 'Please enter your merchant secret from Svea WebPay', 'eduadmin-sveawebpay' ),
				'placeholder' => __( 'Merchant secret', 'eduadmin-sveawebpay' ),
			)
		);
	}

	/**
	 * @param $bookingInfo EduAdminBookingInfo
	 */
	public function process_booking( $bookingInfo = null ) {
		echo '<pre>' . print_r( $this, true ) . '</pre>';
		echo '<pre>' . print_r( $bookingInfo, true ) . '</pre>';
	}
}

endif;