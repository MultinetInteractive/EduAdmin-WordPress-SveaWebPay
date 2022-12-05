<?php
defined( 'ABSPATH' ) or die( 'This plugin must be run within the scope of WordPress.' );

/*
 * Plugin Name:	EduAdmin Booking - SVEA Webpay-plugin
 * Plugin URI:	http://www.eduadmin.se
 * Description:	EduAdmin plugin to allow visitors to book courses at your website
 * Tags:	booking, participants, courses, events, eduadmin, lega online
 * Version:	3.0.1
 * GitHub Plugin URI: multinetinteractive/eduadmin-wordpress-sveawebpay
 * GitHub Plugin URI: https://github.com/multinetinteractive/eduadmin-wordpress-sveawebpay
 * Requires at least: 5.0
 * Tested up to: 5.8
 * Author:	Chris Gårdenberg, MultiNet Interactive AB
 * Author URI:	http://www.multinet.se
 * License:	GPL3
 * Text Domain:	eduadmin-sveawebpay
 * Domain Path: /languages/
 */
/*
    EduAdmin Booking plugin
    Copyright (C) 2015-2021 Chris Gårdenberg, MultiNet Interactive AB

    This program is free software: you can redistribute it and/or modify
    it under the terms of the GNU General Public License as published by
    the Free Software Foundation, either version 3 of the License, or
    (at your option) any later version.

    This program is distributed in the hope that it will be useful,
    but WITHOUT ANY WARRANTY; without even the implied warranty of
    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
    GNU General Public License for more details.

    You should have received a copy of the GNU General Public License
    along with this program. If not, see <http://www.gnu.org/licenses/>.
 */

add_action( 'admin_init', 'checkForEduAdminPlugin' );
function checkForEduAdminPlugin() {
	if ( is_admin() && current_user_can( 'activate_plugins' ) && ( ! is_plugin_active( 'eduadmin-booking/eduadmin.php' ) && ! is_plugin_active( 'eduadmin/eduadmin.php' ) ) ) {
		add_action( 'admin_notices', function () {
			?>
            <div class="error">
            <p><?php _e( 'This plugin requires the EduAdmin-WordPress-plugin to be installed and activated.', 'eduadmin-sveawebpay' ); ?></p>
            </div><?php
		} );
		deactivate_plugins( plugin_basename( __FILE__ ) );

		if ( isset( $_GET['activate'] ) ) {
			unset( $_GET['activate'] );
		}
	}
}

if ( ! class_exists( 'EDU_SveaWebPay_Loader' ) ):

	final class EDU_SveaWebPay_Loader {
		public function __construct() {
			add_action( 'plugins_loaded', array( $this, 'init' ) );
		}

		public function init() {
			if ( class_exists( 'EDU_Integration' ) ) {
				require_once( __DIR__ . '/vendor/autoload.php' ); // Load dependencies
				require_once( __DIR__ . '/class/class-edu-sveawebpay.php' );

				add_filter( 'edu_integrations', array( $this, 'add_integration' ) );
			}
		}

		public function add_integration( $integrations ) {
			$integrations[] = 'EDU_SveaWebPay';

			return $integrations;
		}
	}

	$edu_sveawebpay_loader = new EDU_SveaWebPay_Loader( __FILE__ );
endif;
