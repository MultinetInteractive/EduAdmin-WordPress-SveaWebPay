<?php
defined( 'ABSPATH' ) or die( 'This plugin must be run within the scope of WordPress.' );

require_once( __DIR__ . '/class-edu-sveawebpay-config.php' );

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
			$this->displayName = __( 'Svea Webpay (Checkout)', 'eduadmin-sveawebpay' );
			$this->description = '';
			$this->type        = 'payment';

			$this->init_form_fields();
			$this->init_settings();

			add_action( 'eduadmin-checkpaymentplugins', array( $this, 'intercept_booking' ) );
			add_action( 'eduadmin-processbooking', array( $this, 'process_booking' ) );
			add_action( 'eduadmin-bookingcompleted', array( $this, 'process_svearesponse' ) );
			add_action( 'wp_loaded', array( $this, 'process_paymentstatus' ) );

			add_shortcode( 'eduadmin-svea-testpage', array( $this, 'test_page' ) );
		}

		/**
		 * @param $attributes
		 */
		public function test_page( $attributes ) {
			$attributes = shortcode_atts(
				array(
					'bookingid'          => 0,
					'programmebookingid' => 0,
				),
				normalize_empty_atts( $attributes ),
				'test_page'
			);

			if ( $attributes['bookingid'] > 0 ) {
				$event_booking = EDUAPI()->OData->Bookings->GetItem(
					$attributes['bookingid'],
					null,
					'Customer($select=CustomerId;),ContactPerson($select=PersonId;),OrderRows',
					false
				);
			} elseif ( $attributes['programmebookingid'] > 0 ) {
				$event_booking = EDUAPI()->OData->ProgrammeBookings->GetItem(
					$attributes['programmebookingid'],
					null,
					'Customer($select=CustomerId;),ContactPerson($select=PersonId;),OrderRows',
					false
				);
			}

			$_customer = EDUAPI()->OData->Customers->GetItem(
				$event_booking['Customer']['CustomerId'],
				null,
				"BillingInfo",
				false
			);

			$_contact = EDUAPI()->OData->Persons->GetItem(
				$event_booking['ContactPerson']['PersonId'],
				null,
				null,
				false
			);

			$ebi = new EduAdmin_BookingInfo( $event_booking, $_customer, $_contact );

			if ( ! empty( EDU()->session['svea-order-id'] ) && ! empty( $_GET['svea_order_id'] ) && EDU()->session['svea-order-id'] === $_GET['svea_order_id'] ) {
				do_action( 'eduadmin-bookingcompleted', $ebi );
			} else {
				do_action( 'eduadmin-processbooking', $ebi );
			}
		}


		/**
		 * @param EduAdmin_BookingInfo|null $ebi
		 */
		public function intercept_booking( $ebi = null ) {
			if ( 'no' === $this->get_option( 'enabled', 'no' ) ) {
				return;
			}

			if ( ! empty( $_POST['act'] ) && ( 'bookCourse' === $_POST['act'] || 'bookProgramme' === $_POST['act'] ) ) {
				$ebi->NoRedirect = true;
			}
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
			if ( 'no' === $this->get_option( 'enabled', 'no' ) ) {
				return;
			}

			if ( isset( $_REQUEST['edu-thankyou'] ) && isset( $_REQUEST['svea'] ) ) {
				$booking_id           = intval( $_GET['booking_id'] );
				$programme_booking_id = intval( $_GET['programme_booking_id'] );

				$this->update_booking( intval( $_GET['edu-thankyou'] ), $booking_id, $programme_booking_id );

				EDU()->session['svea-order-id'] = null;
			}
		}

		/**
		 * @param $ebi EduAdmin_BookingInfo|null $bookingInfo
		 */
		public function process_booking( $ebi = null ) {
			if ( 'no' === $this->get_option( 'enabled', 'no' ) ) {
				return;
			}

			$ebi->NoRedirect = true;

			if ( empty( $_GET['svea_order_id'] ) || empty( EDU()->session['svea-order-id'] ) ) {
				$checkout = $this->create_checkout( $ebi );

				$snippet = $checkout['Gui']['Snippet'];
				echo "<div>{$snippet}</div>";
			}
		}

		/**
		 * @param $ebi EduAdmin_BookingInfo|null
		 *
		 * @returns array
		 */
		public function create_checkout( $ebi ) {
			$countries = EDUAPI()->OData->Countries->Search()['value'];

			$selectedCountry = 'SE';
			$selectedLocale  = 'sv-SE';

			$invoiceCountry = $ebi->Customer['BillingInfo']['Country'];
			if ( empty( $invoiceCountry ) ) {
				$invoiceCountry = $ebi->Customer['Country'];
			}

			foreach ( $countries as $country ) {
				if ( $invoiceCountry == $country['CountryName'] ) {
					$selectedCountry = $country['CountryCode'];
					if ( ! empty( $country['CultureName'] ) ) {
						$selectedLocale = $country['CultureName'];
					}
					break;
				}
			}

			$booking_id           = 0;
			$programme_booking_id = 0;

			$reference_id = 0;

			$_event = null;

			$eventName = '';

			$locationAddress    = '';
			$locationCountry    = '';
			$locationPostalCode = '';

			if ( ! empty( $ebi->EventBooking['BookingId'] ) ) {
				$booking_id   = intval( $ebi->EventBooking['BookingId'] );
				$reference_id = $booking_id;

				$_event = EDUAPI()->OData->Events->GetItem( $ebi->EventBooking['EventId'], null, "LocationAddress" );

				$eventName = $_event['EventName'];

				if ( ! empty( $_event['LocationAddress'] ) && $_event['LocationAdress'] != null ) {
					$locationAddress    = $_event['LocationAddress']['Address'];
					$locationCountry    = $_event['LocationAddress']['Country'];
					$locationPostalCode = $_event['LocationAddress']['AddressZip'];
				}
			}

			if ( ! empty( $ebi->EventBooking['ProgrammeBookingId'] ) ) {
				$programme_booking_id = intval( $ebi->EventBooking['ProgrammeBookingId'] );
				$reference_id         = $programme_booking_id;

				$_event = EDUAPI()->OData->ProgrammeStarts->GetItem( $ebi->EventBooking['ProgrammeStartId'] );

				$eventName = $_event['ProgrammeStartName'];
			}

			$currency = EDU()->get_option( 'eduadmin-currency', 'SEK' );

			if ( 'no' !== $this->get_option( 'testrun', 'no' ) ) {
				$wpConfig = new EduSveaWebPayTestConfig( $this );
			} else {
				$wpConfig = new EduSveaWebPayProductionConfig( $this );
			}

			$wpOrder = WebPay::checkout( $wpConfig );

			$orderRow = WebPayItem::orderRow();
			$orderRow->setName( $eventName );
			$orderRow->setQuantity( 1 );

			$vatPercent = ( $ebi->EventBooking['VatSum'] / $ebi->EventBooking['TotalPriceExVat'] ) * 100;
			$orderRow->setVatPercent( $vatPercent );
			$orderRow->setAmountIncVat( (float) $ebi->EventBooking['TotalPriceIncVat'] );

			$customer = WebPayItem::companyCustomer();

			$customerName  = ! empty( $ebi->Customer['BillingInfo']['InvoiceName'] ) ? $ebi->Customer['BillingInfo']['InvoiceName'] : $ebi->Customer['CustomerName'];
			$streetAddress = ! empty( $ebi->Customer['BillingInfo']['Address'] ) ? $ebi->Customer['BillingInfo']['Address'] : $ebi->Customer['Address'];
			$zipCode       = ! empty( $ebi->Customer['BillingInfo']['Zip'] ) ? $ebi->Customer['BillingInfo']['Zip'] : $ebi->Customer['Zip'];
			$city          = $ebi->Customer['BillingInfo']['City'] ? $ebi->Customer['BillingInfo']['City'] : $ebi->Customer['City'];
			$phone         = $ebi->Customer['Phone'];
			$email         = ! empty( $ebi->Customer['BillingInfo']['Email'] ) ? $ebi->Customer['BillingInfo']['Email'] : $ebi->Customer['Email'];

			$customer->setCompanyName( $customerName );
			$customer->setStreetAddress( $streetAddress );
			$customer->setZipCode( $zipCode );
			$customer->setLocality( $city );

			if ( ! empty( $phone ) ) {
				$customer->setPhoneNumber( $phone );
				$phonePreset = WebPayItem::presetValue()
				                         ->setTypeName( \Svea\WebPay\Checkout\Model\PresetValue::PHONE_NUMBER )
				                         ->setValue( $phone )
				                         ->setIsReadonly( false );
				$wpOrder->addPresetValue( $phonePreset );
			}
			$customer->setEmail( $email );

			$zipPreset = WebPayItem::presetValue()
			                       ->setTypeName( \Svea\WebPay\Checkout\Model\PresetValue::POSTAL_CODE )
			                       ->setValue( $zipCode )
			                       ->setIsReadonly( false );
			$wpOrder->addPresetValue( $zipPreset );

			$emailPreset = WebPayItem::presetValue()
			                         ->setTypeName( \Svea\WebPay\Checkout\Model\PresetValue::EMAIL_ADDRESS )
			                         ->setValue( $email )
			                         ->setIsReadonly( false );
			$wpOrder->addPresetValue( $emailPreset );

			$current_url = esc_url( "{$_SERVER['REQUEST_SCHEME']}://{$_SERVER['HTTP_HOST']}{$_SERVER['REQUEST_URI']}" );

			$defaultThankYou = add_query_arg(
				array(
					'edu-thankyou'         => $reference_id,
					'svea'                 => '1',
					'booking_id'           => $booking_id,
					'programme_booking_id' => $programme_booking_id,
					'edu-valid-form'       => wp_create_nonce( 'edu-booking-confirm' ),
					'svea_order_id'        => '{checkout.order.uri}',
					'act'                  => 'paymentCompleted',
				),
				@get_page_link( get_option( 'eduadmin-thankYouPage', '/' ) )
			);

			$defaultCancel = add_query_arg(
				array(
					'edu-thankyou'         => $reference_id,
					'svea'                 => '1',
					'booking_id'           => $booking_id,
					'programme_booking_id' => $programme_booking_id,
					'svea_order_id'        => '{checkout.order.uri}',
					'status'               => 'cancel'
				),
				$current_url
			);

			$defaultPushUrl = add_query_arg(
				array(
					'edu-thankyou'         => $reference_id,
					'svea'                 => '1',
					'booking_id'           => $booking_id,
					'programme_booking_id' => $programme_booking_id,
					'svea_order_id'        => '{checkout.order.uri}',
					'status'               => 'push'
				),
				$current_url
			);

			$defaultTermsUrl = get_option( 'eduadmin-bookingTermsLink' );

			$wpBuild = $wpOrder
				->setCurrency( $currency )
				->setCountryCode( $selectedCountry )
				->setClientOrderNumber( $reference_id )
				->addOrderRow( $orderRow )
				->setLocale( $selectedLocale )
				->setTermsUri( $defaultTermsUrl )
				->setConfirmationUri( $defaultThankYou )
				->setPushUri( $defaultPushUrl )
				->setCheckoutUri( $defaultCancel ); // We have no "checkout"-url.. So we just cancel the booking instead.
			$wpForm  = $wpBuild->createOrder();

			EDU()->session['svea-order-id'] = $wpForm['OrderId'];

			return $wpForm;
		}

		public function process_paymentstatus() {
			if ( ! empty( $_GET['svea_order_id'] ) && ! empty( $_GET['status'] ) ) {

				$booking_id           = intval( $_GET['booking_id'] );
				$programme_booking_id = intval( $_GET['programme_booking_id'] );

				$this->update_booking( intval( $_GET['edu-thankyou'] ), $booking_id, $programme_booking_id );

				exit( 0 );
			}
		}

		private function update_booking( $ecl_id, $booking_id, $programme_booking_id ) {
			if ( 'no' !== $this->get_option( 'testrun', 'no' ) ) {
				$wpConfig = new EduSveaWebPayTestConfig( $this );
			} else {
				$wpConfig = new EduSveaWebPayProductionConfig( $this );
			}

			$wpOrder = WebPay::checkout( $wpConfig );
			$wpOrder->setCheckoutOrderId( $ecl_id );

			$order = $wpOrder->getOrder();

			$patch_booking                  = new stdClass();
			$patch_booking->PaymentMethodId = 2;

			if ( 'Cancelled' === $order['Status'] ) {
				$patch_booking->Paid = false;
			} else if ( 'Final' === $order['Status'] ) {
				$patch_booking->Paid = true;
			} else if ( 'Created' === $order['Status'] ) {
				$patch_booking->Paid = false;
			}

			if ( $booking_id > 0 ) {
				EDUAPI()->REST->Booking->PatchBooking(
					$booking_id,
					$patch_booking
				);
			}

			if ( $programme_booking_id > 0 ) {
				EDUAPI()->REST->ProgrammeBooking->PatchBooking(
					$programme_booking_id,
					$patch_booking
				);
			}
		}
	}

endif;
