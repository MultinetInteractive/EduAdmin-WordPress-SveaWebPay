<?php
defined( 'ABSPATH' ) or die( 'This plugin must be run within the scope of WordPress.' );

require_once( __DIR__ . '/class-edu-sveawebpay-config.php' );

use Svea\WebPay\WebPay;
use Svea\WebPay\WebPayItem;

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

				$deleted = $this->update_booking( intval( EDU()->session['svea-order-id'] ), $booking_id, $programme_booking_id );

				EDU()->session['svea-order-id'] = null;

				if ( $deleted ) {
					$this->handle_cancelled_payment();
				}
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

			if ( ! empty( $ebi->EventBooking['BookingId'] ) ) {
				$booking_id   = intval( $ebi->EventBooking['BookingId'] );
				$reference_id = $booking_id;

				$_event = EDUAPI()->OData->Events->GetItem( $ebi->EventBooking['EventId'], null );

				$eventName = $_event['EventName'];
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
				->setLocale( $selectedLocale )
				->setTermsUri( $defaultTermsUrl )
				->setConfirmationUri( $defaultThankYou )
				->setPushUri( $defaultPushUrl )
				->setCheckoutUri( $defaultCancel ); // We have no "checkout"-url.. So we just cancel the booking instead.

			$orderRow = WebPayItem::orderRow();
			$orderRow->setName( substr( $eventName, 0, 40 ) );
			$orderRow->setQuantity( 0 );
			$orderRow->setAmountIncVat( 0 );
			$orderRow->setVatPercent( 0 );

			$wpBuild->addOrderRow( $orderRow );

			$timeLabel = $programme_booking_id > 0 ? "Programstart" : "Kursstart";

			$orderRow = WebPayItem::orderRow();
			$orderRow->setName( $timeLabel . ": " . date( "Y-m-d H:i", strtotime( $_event['StartDate'] ) ) );
			$orderRow->setQuantity( 0 );
			$orderRow->setAmountIncVat( 0 );
			$orderRow->setVatPercent( 0 );

			$wpBuild->addOrderRow( $orderRow );

			foreach ( $ebi->EventBooking['OrderRows'] as $eduOrderRow ) {
				$orderRow = WebPayItem::orderRow();
				$orderRow->setName( substr( $eduOrderRow['Description'], 0, 40 ) );
				$orderRow->setQuantity( $eduOrderRow['Quantity'] );

				$orderRow->setVatPercent( $eduOrderRow['VatPercent'] );

				if ( $eduOrderRow['PriceIncVat'] ) {
					$orderRow->setAmountIncVat( $eduOrderRow['TotalPrice'] );
				} else {
					$priceInclVat = $eduOrderRow['TotalPrice'];
					if ( $eduOrderRow['VatPercent'] > 0 ) {
						$priceInclVat = $eduOrderRow['TotalPrice'] * ( 1 + ( $eduOrderRow['VatPercent'] / 100 ) );
					}
					$orderRow->setAmountIncVat( $priceInclVat );
				}

				$orderRow->setDiscountPercent( $eduOrderRow['DiscountPercent'] );

				$wpBuild->addOrderRow( $orderRow );
			}

			$wpForm = $wpBuild->createOrder();

			EDU()->session['svea-order-id'] = $wpForm['OrderId'];

			return $wpForm;
		}

		public function process_paymentstatus() {
			if ( ! empty( $_GET['svea_order_id'] ) && intval( $_GET['svea_order_id'] ) != 0 && ! empty( $_GET['status'] ) ) {

				$booking_id           = intval( $_GET['booking_id'] );
				$programme_booking_id = intval( $_GET['programme_booking_id'] );

				$this->update_booking( intval( $_GET['svea_order_id'] ), $booking_id, $programme_booking_id );

				exit( 0 );
			}

			if ( isset( $_REQUEST['edu-thankyou'] ) && isset( $_REQUEST['svea'] ) && ! empty( $_GET['status'] ) ) {
				$booking_id           = intval( $_GET['booking_id'] );
				$programme_booking_id = intval( $_GET['programme_booking_id'] );

				$deleted = $this->update_booking( intval( EDU()->session['svea-order-id'] ), $booking_id, $programme_booking_id );

				EDU()->session['svea-order-id'] = null;

				if ( $deleted ) {
					$this->handle_cancelled_payment();
				}
			}
		}

		private function handle_cancelled_payment() {
			@wp_redirect( get_home_url() );
			wp_add_inline_script( 'edu-svea-redirecthome', "location.href = '" . esc_js( get_home_url() ) . "';" );
			wp_enqueue_script( 'edu-svea-redirecthome', false, array( 'jquery' ) );
			exit( 0 );
		}

		/**
		 * @param $order_id numeric SVEA WebPay OrderId
		 * @param $booking_id
		 * @param $programme_booking_id
		 *
		 * @return bool If the booking was deleted, due to cancellation
		 * @throws \Svea\WebPay\BuildOrder\Validator\ValidationException
		 */
		private function update_booking( $order_id, $booking_id, $programme_booking_id ) {
			if ( 'no' !== $this->get_option( 'testrun', 'no' ) ) {
				$wpConfig = new EduSveaWebPayTestConfig( $this );
			} else {
				$wpConfig = new EduSveaWebPayProductionConfig( $this );
			}

			$wpOrder = WebPay::checkout( $wpConfig );
			$wpOrder->setCheckoutOrderId( $order_id );

			$order = $wpOrder->getOrder();

			$delete_booking = false;

			$patch_booking                  = new stdClass();
			$patch_booking->PaymentMethodId = 2;

			if ( 'Cancelled' === $order['Status'] ) {
				$patch_booking->Paid = false;
				$delete_booking      = true;
			} else if ( 'Final' === $order['Status'] ) {
				$patch_booking->Paid = true;
			} else if ( 'Created' === $order['Status'] ) {
				$patch_booking->Paid = false;
			}

			if ( isset( $_GET['status'] ) && 'cancel' === $_GET['status'] ) {
				$patch_booking->Paid = false;
				$delete_booking      = true;
			}

			if ( $booking_id > 0 ) {
				EDUAPI()->REST->Booking->PatchBooking(
					$booking_id,
					$patch_booking
				);

				if ( $delete_booking ) {
					EDUAPI()->REST->Booking->DeleteBooking( $booking_id );
				}
			}

			if ( $programme_booking_id > 0 ) {

				EDUAPI()->REST->ProgrammeBooking->PatchBooking(
					$programme_booking_id,
					$patch_booking
				);

				if ( $delete_booking ) {
					EDUAPI()->REST->ProgrammeBooking->DeleteBooking( $programme_booking_id );
				}
			}

			return $delete_booking;
		}
	}

endif;
