<?php
defined( 'ABSPATH' ) or die( 'This plugin must be run within the scope of WordPress.' );

use Svea\WebPay\WebPay;
use Svea\WebPay\WebPayItem;
use Svea\WebPay\Config\ConfigurationService;
use Svea\WebPay\Response\SveaResponse;

if ( ! class_exists( 'EDU_SveaWebPay' ) ):

	/**
	 * EDU_SveaWebPay integrates EduAdmin-WordPress plugin with SveaWebPay as payment gateway
	 */
	class EDU_SveaWebPay extends EDU_Integration {
		/**
		 * Constructor
		 */
		public function __construct() {
			$this->id          = 'eduadmin-sveawebpay';
			$this->displayName = __( 'Svea Webpay', 'eduadmin-sveawebpay' );
			$this->description = '';

			$this->init_form_fields();
			$this->init_settings();

			add_action( 'eduadmin-processbooking', array( $this, 'process_booking' ) );

			add_action( 'wp_loaded', array( $this, 'process_svearesponse' ) );
		}

		/**
		 * Initializes the settingsfields
		 */
		public function init_form_fields() {
			$this->setting_fields = array(
				'enabled'         => array(
					'title'       => __( 'Enabled', 'eduadmin-sveawebpay' ),
					'type'        => 'checkbox',
					'description' => __( 'Enables/Disables the integration with Svea WebPay', 'eduadmin-sveawebpay' ),
					'default'     => 'no',
				),
				'testrun'         => array(
					'title'       => __( 'Sandbox mode', 'eduadmin-sveawebpay' ),
					'type'        => 'checkbox',
					'description' => __( 'Activate sandbox mode', 'eduadmin-sveawebpay' ),
					'default'     => 'no',
				),
				'merchant_key'    => array(
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
				),
			);
		}

		/**
		 *
		 */
		public function process_svearesponse() {
			if ( isset( $_REQUEST['edu-thankyou'] ) && isset( $_REQUEST['svea'] ) && isset( $_REQUEST['response'] ) ) {
				$filter = new XFiltering();
				$f      = new XFilter( 'EventCustomerLnkID', '=', $_REQUEST['edu-thankyou'] );
				$filter->AddItem( $f );

				$eventBooking = EDU()->api->GetEventBookingV2( EDU()->get_token(), '', $filter->ToString() )[0];

				$filter = new XFiltering();
				$f      = new XFilter( 'CustomerID', '=', $eventBooking->CustomerID );
				$filter->AddItem( $f );

				$_customer = EDU()->api->GetCustomerV3( EDU()->get_token(), '', $filter->ToString(), false )[0];

				$filter = new XFiltering();
				$f      = new XFilter( 'CustomerContactID', '=', $eventBooking->CustomerContactID );
				$filter->AddItem( $f );

				$_contact = EDU()->api->GetCustomerContactV2( EDU()->get_token(), '', $filter->ToString(), false )[0];

				$ebi = new EduAdminBookingInfo( $eventBooking, $_customer, $_contact );

				$countries = EDU()->api->GetCountries( EDU()->get_token(), 'Swedish' );

				$selectedCountry = 'SE';

				$invoiceCountry = $ebi->Customer->InvoiceCountry;
				if ( empty( $invoiceCountry ) ) {
					$invoiceCountry = $ebi->Customer->Country;
				}

				foreach ( $countries as $country ) {
					if ( $invoiceCountry == $country->CountryName ) {
						$selectedCountry = $country->CountryCode;
						break;
					}
				}

				$wpConfig = ConfigurationService::getDefaultConfig();

				$response = ( new SveaResponse( $_REQUEST, $selectedCountry, $wpConfig ) )->getResponse();

				if ( $response->accepted ) {
					EDU()->api->SetValidPayment( EDU()->get_token(), $ebi->EventBooking->EventCustomerLnkID );
				} else {
					EDU()->api->SetInvalidPayment( EDU()->get_token(), $ebi->EventBooking->EventCustomerLnkID );
				}

				$surl    = get_home_url();
				$cat     = get_option( 'eduadmin-rewriteBaseUrl' );
				$baseUrl = $surl . '/' . $cat;

				wp_redirect( $baseUrl . '/profile/myprofile?payment=' . ( $response->accepted ? '1' : '0' ) );
				exit();
			}
		}

		/**
		 * @param $bookingInfo EduAdminBookingInfo
		 */
		public function process_booking( $bookingInfo = null ) {
			if ( isset( $_POST['act'] ) && 'bookCourse' === $_POST['act'] ) {
				$bookingInfo->NoRedirect = true;

				$countries = EDU()->api->GetCountries( EDU()->get_token(), 'Swedish' );

				$selectedCountry = 'SE';
				$selectedLocale  = 'sv-SE';

				$invoiceCountry = $bookingInfo->Customer->InvoiceCountry;
				if ( empty( $invoiceCountry ) ) {
					$invoiceCountry = $bookingInfo->Customer->Country;
				}

				foreach ( $countries as $country ) {
					if ( $invoiceCountry == $country->CountryName ) {
						$selectedCountry = $country->CountryCode;
						if ( ! empty( $country->CultureName ) ) {
							$selectedLocale = $country->CultureName;
						}
						break;
					}
				}

				$selectedLocale = explode( '-', $selectedLocale )[0];

				$currency = get_option( 'eduadmin-currency', 'SEK' );

				$wpConfig = ConfigurationService::getDefaultConfig();
				$wpOrder  = WebPay::createOrder( $wpConfig );

				$orderRow = WebPayItem::orderRow();
				$orderRow->setName( $bookingInfo->EventBooking->EventDescription );
				$orderRow->setQuantity( 1 );

				$vatPercent = ( $bookingInfo->EventBooking->VatSum / $bookingInfo->EventBooking->TotalPriceExVat ) * 100;
				$orderRow->setVatPercent( $vatPercent );
				$orderRow->setAmountIncVat( (float) $bookingInfo->EventBooking->TotalPriceIncVat );

				$customer = WebPayItem::companyCustomer();

				if ( ! empty( $bookingInfo->Customer->InvoiceName ) ) {
					$customer->setCompanyName( $bookingInfo->Customer->InvoiceName );
				} else {
					$customer->setCompanyName( $bookingInfo->Customer->CustomerName );
				}

				if ( ! empty( $bookingInfo->Customer->InvoiceAddress1 ) ) {
					$customer->setStreetAddress( $bookingInfo->Customer->InvoiceAddress1 );
				} else {
					$customer->setStreetAddress( $bookingInfo->Customer->Address1 );
				}

				if ( ! empty( $bookingInfo->Customer->InvoiceZip ) ) {
					$customer->setZipCode( $bookingInfo->Customer->InvoiceZip );
				} else {
					$customer->setZipCode( $bookingInfo->Customer->Zip );
				}

				if ( ! empty( $bookingInfo->Customer->InvoiceCity ) ) {
					$customer->setLocality( $bookingInfo->Customer->InvoiceCity );
				} else {
					$customer->setLocality( $bookingInfo->Customer->City );
				}

				if ( ! empty( $bookingInfo->Customer->Phone ) ) {
					$customer->setPhoneNumber( $bookingInfo->Customer->Phone );
				}

				if ( ! empty( $bookingInfo->Customer->InvoiceEmail ) ) {
					$customer->setEmail( $bookingInfo->Customer->InvoiceEmail );
				} else {
					$customer->setEmail( $bookingInfo->Customer->Email );
				}

				$customer->setIpAddress( EDU()->get_ip_adress() );

				$surl    = get_home_url();
				$cat     = get_option( 'eduadmin-rewriteBaseUrl' );
				$baseUrl = $surl . '/' . $cat;

				$defaultThankYou = @get_page_link( get_option( 'eduadmin-thankYouPage', '/' ) ) . "?edu-thankyou=" . $bookingInfo->EventBooking->EventCustomerLnkID . '&svea=1';
				$defaultCancel   = $baseUrl . "?edu-cancel=" . $bookingInfo->EventBooking->EventCustomerLnkID . '&svea=1';

				$wpForm = $wpOrder
					->setCurrency( $currency )
					->setCountryCode( $selectedCountry )
					->setOrderDate( date( 'c' ) )
					->setClientOrderNumber( $bookingInfo->EventBooking->EventCustomerLnkID )
					->addOrderRow( $orderRow )
					->addCustomerDetails( $customer )
					->usePayPage()
					->setPayPageLanguage( $selectedLocale )
					->setReturnUrl( apply_filters( 'eduadmin-thankyou-url', $defaultThankYou ) )
					->setCancelUrl( apply_filters( 'eduadmin-cancel-url', $defaultCancel ) )
					->getPaymentUrl();

				if ( $wpForm->accepted ) {
					if ( 'no' === $this->get_option( 'testrun', 'no' ) ) {
						echo '<script type="text/javascript">location.href = "' . $wpForm->url . '";</script>';
					} else {
						echo '<script type="text/javascript">location.href = "' . $wpForm->testurl . '";</script>';
					}
				} else {
					add_filter( 'edu-booking-error', function( $errors ) use ( &$wpForm ) {
						$errors[] = $wpForm->errormessage;
					} );
				}
			}
		}
	}

endif;