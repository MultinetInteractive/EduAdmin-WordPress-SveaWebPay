<?php

class EduSveaWebPayProductionConfig implements \Svea\WebPay\Config\ConfigurationProvider {

	/**
	 * @var EDU_SveaWebPay
	 */
	public $plugin;

	/**
	 * EduSveaWebPayProductionConfig constructor.
	 *
	 * @param EDU_SveaWebPay $_plugin
	 */
	public function __construct( $_plugin ) {
		$this->plugin = $_plugin;
	}

	/**
	 * fetch username, used with invoice or payment plan (i.e. Svea WebService Europe API)
	 *
	 * @return string
	 *
	 * @param string $type    Svea\WebPay\Config\ConfigurationProvider::INVOICE_TYPE, ::PAYMENTPLAN_TYPE can be used if needed to match different configuration settings
	 * @param string $country iso3166 alpha-2 CountryCode, eg. SE, NO, DK, FI, NL, DE can be used if needed to match different configuration settings
	 *
	 * @throws \Svea\WebPay\HostedService\Helper\InvalidTypeException  in case of unsupported $type
	 * @throws \Svea\WebPay\HostedService\Helper\InvalidCountryException  in case of unsupported $country
	 */
	public function getUsername( $type, $country ) {
	}

	/**
	 * fetch password, used with invoice or payment plan (i.e. Svea WebService Europe API)
	 *
	 * @return string
	 *
	 * @param string $type    Svea\WebPay\Config\ConfigurationProvider::INVOICE_TYPE, ::PAYMENTPLAN_TYPE can be used if needed to match different configuration settings
	 * @param string $country iso3166 alpha-2 CountryCode, eg. SE, NO, DK, FI, NL, DE can be used if needed to match different configuration settings
	 *
	 * @throws \Svea\WebPay\HostedService\Helper\InvalidTypeException  in case of unsupported $type
	 * @throws \Svea\WebPay\HostedService\Helper\InvalidCountryException  in case of unsupported $country
	 */
	public function getPassword( $type, $country ) {
	}

	/**
	 * fetch client number, used with invoice or payment plan (i.e. Svea WebService Europe API)
	 *
	 * @return \Svea\WebPay\Config\ClientNumber
	 *
	 * @param string $type    Svea\WebPay\Config\ConfigurationProvider::INVOICE_TYPE, ::PAYMENTPLAN_TYPE can be used if needed to match different configuration settings
	 * @param string $country iso3166 alpha-2 CountryCode, eg. SE, NO, DK, FI, NL, DE can be used if needed to match different configuration settings
	 *
	 * @throws \Svea\WebPay\HostedService\Helper\InvalidTypeException  in case of unsupported $type
	 * @throws \Svea\WebPay\HostedService\Helper\InvalidCountryException  in case of unsupported $country
	 */
	public function getClientNumber( $type, $country ) {
	}

	/**
	 * fetch merchant id, used with card or direct bank payments (i.e. Svea Hosted Web Service API)
	 *
	 * @return string
	 *
	 * @param string $type    Svea\WebPay\Config\ConfigurationProvider::INVOICE_TYPE, ::PAYMENTPLAN_TYPE can be used if needed to match different configuration settings
	 * @param string $country CountryCode eg. SE, NO, DK, FI, NL, DE
	 */
	public function getMerchantId( $type, $country ) {
		return $this->plugin->get_option( 'merchant_key', '' );
	}

	/**
	 * fetch secret word, used with card or direct bank payments (i.e. Svea Hosted Web Service API)
	 *
	 * @return string
	 *
	 * @param string $type    Svea\WebPay\Config\ConfigurationProvider::INVOICE_TYPE, ::PAYMENTPLAN_TYPE can be used if needed to match different configuration settings
	 * @param string $country CountryCode eg. SE, NO, DK, FI, NL, DE
	 */
	public function getSecret( $type, $country ) {
		return $this->plugin->get_option( 'merchant_secret', '' );
	}

	/**
	 * Constants for the endpoint url found in the class ConfigurationService.php
	 * getEndPoint() should return an url corresponding to $type.
	 *
	 * @param string $type one of Svea\WebPay\Config\ConfigurationProvider::HOSTED_TYPE, ::INVOICE_TYPE, ::PAYMENTPLAN_TYPE, ::HOSTED_ADMIN_TYPE, ::ADMIN_TYPE
	 *
	 * @throws Exception
	 */

	public function getEndPoint( $type ) {
		switch ( strtoupper( $type ) ) {
			case "HOSTED":
				return Svea\WebPay\Config\ConfigurationService::SWP_PROD_URL;
				break;
			case "INVOICE":
			case "PAYMENTPLAN":
				return Svea\WebPay\Config\ConfigurationService::SWP_PROD_WS_URL;
				break;
			case "HOSTED_ADMIN":
				return Svea\WebPay\Config\ConfigurationService::SWP_PROD_HOSTED_ADMIN_URL;
				break;
			case "ADMIN":
				return Svea\WebPay\Config\ConfigurationService::SWP_PROD_ADMIN_URL;
				break;
			default:
				throw new Exception( 'Invalid type. Accepted values: INVOICE, PAYMENTPLAN, HOSTED, HOSTED_ADMIN' );
				break;
		}
	}

	/**
	 * fetch Checkout Merchant id, used for Checkout order type
	 *
	 * @return string
	 */
	public function getCheckoutMerchantId() {
		// TODO: Implement getCheckoutMerchantId() method.
	}

	/**
	 * fetch Checkout Secret word, used for Checkout order type
	 *
	 * @return string
	 */
	public function getCheckoutSecret() {
		// TODO: Implement getCheckoutSecret() method.
	}
}

class EduSveaWebPayTestConfig implements \Svea\WebPay\Config\ConfigurationProvider {

	/**
	 * @var EDU_SveaWebPay
	 */
	public $plugin;

	/**
	 * EduSveaWebPayTestConfig constructor.
	 *
	 * @param EDU_SveaWebPay $_plugin
	 */
	public function __construct( $_plugin ) {
		$this->plugin = $_plugin;
	}

	/**
	 * fetch username, used with invoice or payment plan (i.e. Svea WebService Europe API)
	 *
	 * @return string
	 *
	 * @param string $type    Svea\WebPay\Config\ConfigurationProvider::INVOICE_TYPE, ::PAYMENTPLAN_TYPE can be used if needed to match different configuration settings
	 * @param string $country iso3166 alpha-2 CountryCode, eg. SE, NO, DK, FI, NL, DE can be used if needed to match different configuration settings
	 *
	 * @throws \Svea\WebPay\HostedService\Helper\InvalidTypeException  in case of unsupported $type
	 * @throws \Svea\WebPay\HostedService\Helper\InvalidCountryException  in case of unsupported $country
	 */
	public function getUsername( $type, $country ) {
	}

	/**
	 * fetch password, used with invoice or payment plan (i.e. Svea WebService Europe API)
	 *
	 * @return string
	 *
	 * @param string $type    Svea\WebPay\Config\ConfigurationProvider::INVOICE_TYPE, ::PAYMENTPLAN_TYPE can be used if needed to match different configuration settings
	 * @param string $country iso3166 alpha-2 CountryCode, eg. SE, NO, DK, FI, NL, DE can be used if needed to match different configuration settings
	 *
	 * @throws \Svea\WebPay\HostedService\Helper\InvalidTypeException  in case of unsupported $type
	 * @throws \Svea\WebPay\HostedService\Helper\InvalidCountryException  in case of unsupported $country
	 */
	public function getPassword( $type, $country ) {
	}

	/**
	 * fetch client number, used with invoice or payment plan (i.e. Svea WebService Europe API)
	 *
	 * @return \Svea\WebPay\Config\ClientNumber
	 *
	 * @param string $type    Svea\WebPay\Config\ConfigurationProvider::INVOICE_TYPE, ::PAYMENTPLAN_TYPE can be used if needed to match different configuration settings
	 * @param string $country iso3166 alpha-2 CountryCode, eg. SE, NO, DK, FI, NL, DE can be used if needed to match different configuration settings
	 *
	 * @throws \Svea\WebPay\HostedService\Helper\InvalidTypeException  in case of unsupported $type
	 * @throws \Svea\WebPay\HostedService\Helper\InvalidCountryException  in case of unsupported $country
	 */
	public function getClientNumber( $type, $country ) {
	}

	/**
	 * fetch merchant id, used with card or direct bank payments (i.e. Svea Hosted Web Service API)
	 *
	 * @return string
	 *
	 * @param string $type    Svea\WebPay\Config\ConfigurationProvider::INVOICE_TYPE, ::PAYMENTPLAN_TYPE can be used if needed to match different configuration settings
	 * @param string $country CountryCode eg. SE, NO, DK, FI, NL, DE
	 */
	public function getMerchantId( $type, $country ) {
		return $this->plugin->get_option( 'merchant_key', '' );
	}

	/**
	 * fetch secret word, used with card or direct bank payments (i.e. Svea Hosted Web Service API)
	 *
	 * @return string
	 *
	 * @param string $type    Svea\WebPay\Config\ConfigurationProvider::INVOICE_TYPE, ::PAYMENTPLAN_TYPE can be used if needed to match different configuration settings
	 * @param string $country CountryCode eg. SE, NO, DK, FI, NL, DE
	 */
	public function getSecret( $type, $country ) {
		return $this->plugin->get_option( 'merchant_secret', '' );
	}

	/**
	 * Constants for the endpoint url found in the class ConfigurationService.php
	 * getEndPoint() should return an url corresponding to $type.
	 *
	 * @param string $type one of Svea\WebPay\Config\ConfigurationProvider::HOSTED_TYPE, ::INVOICE_TYPE, ::PAYMENTPLAN_TYPE, ::HOSTED_ADMIN_TYPE, ::ADMIN_TYPE
	 *
	 * @throws Exception
	 */
	public function getEndPoint( $type ) {
		switch ( strtoupper( $type ) ) {
			case "HOSTED":
				return Svea\WebPay\Config\ConfigurationService::SWP_TEST_URL;
				break;
			case "INVOICE":
			case "PAYMENTPLAN":
				return Svea\WebPay\Config\ConfigurationService::SWP_TEST_WS_URL;
				break;
			case "HOSTED_ADMIN":
				return Svea\WebPay\Config\ConfigurationService::SWP_TEST_HOSTED_ADMIN_URL;
				break;
			case "ADMIN":
				return Svea\WebPay\Config\ConfigurationService::SWP_TEST_ADMIN_URL;
				break;
			default:
				throw new Exception( 'Invalid type. Accepted values: INVOICE, PAYMENTPLAN, HOSTED, HOSTED_ADMIN' );
				break;
		}
	}

	/**
	 * fetch Checkout Merchant id, used for Checkout order type
	 *
	 * @return string
	 */
	public function getCheckoutMerchantId() {
		// TODO: Implement getCheckoutMerchantId() method.
	}

	/**
	 * fetch Checkout Secret word, used for Checkout order type
	 *
	 * @return string
	 */
	public function getCheckoutSecret() {
		// TODO: Implement getCheckoutSecret() method.
	}
}