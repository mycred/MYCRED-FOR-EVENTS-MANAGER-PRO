<?php
/**
 * Plugin Name: myCRED for Events Manager Pro
 * Plugin URI: http://mycred.me
 * Description: This plugin connects myCRED with the Events Manager Pro plugin.
 * Version: 1.0.1
 * Tags: mycred, events-manager pro, points, events, pay, credit
 * Author: Gabriel S Merovingi
 * Author URI: http://www.merovingi.com
 * Author Email: support@mycred.me
 * Requires at least: WP 4.0
 * Tested up to: WP 4.8.1
 * Text Domain: mycred_em
 * Domain Path: /lang
 * License: GPLv2 or later
 * License URI: http://www.gnu.org/licenses/gpl-2.0.html
 * 
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
 * IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
 * FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
 * AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
 * LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
 * OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN
 * THE SOFTWARE.
 */
if ( ! class_exists( 'myCRED_Events_Manager_Pro' ) ) :
	final class myCRED_Events_Manager_Pro {

		// Plugin Version
		public $version             = '1.0.1';

		// Instnace
		protected static $_instance = NULL;

		// Current session
		public $session             = NULL;

		public $slug                = '';
		public $domain              = '';
		public $plugin              = NULL;
		public $plugin_name         = '';
		protected $update_url       = 'http://mycred.me/api/plugins/';

		/**
		 * Setup Instance
		 * @since 1.0
		 * @version 1.0
		 */
		public static function instance() {
			if ( is_null( self::$_instance ) ) {
				self::$_instance = new self();
			}
			return self::$_instance;
		}

		/**
		 * Not allowed
		 * @since 1.0
		 * @version 1.0
		 */
		public function __clone() { _doing_it_wrong( __FUNCTION__, 'Cheatin&#8217; huh?', '1.0' ); }

		/**
		 * Not allowed
		 * @since 1.0
		 * @version 1.0
		 */
		public function __wakeup() { _doing_it_wrong( __FUNCTION__, 'Cheatin&#8217; huh?', '1.0' ); }

		/**
		 * Define
		 * @since 1.0
		 * @version 1.0
		 */
		private function define( $name, $value, $definable = true ) {
			if ( ! defined( $name ) )
				define( $name, $value );
		}

		/**
		 * Require File
		 * @since 1.0
		 * @version 1.0
		 */
		public function file( $required_file ) {
			if ( file_exists( $required_file ) )
				require_once $required_file;
		}

		/**
		 * Construct
		 * @since 1.0
		 * @version 1.0
		 */
		public function __construct() {

			$this->slug        = 'mycred-events-manager';
			$this->plugin      = plugin_basename( __FILE__ );
			$this->domain      = 'mycred_em';
			$this->plugin_name = 'myCRED for Events Manager Pro';

			$this->define_constants();
			$this->plugin_updates();

			add_filter( 'mycred_init',            array( $this, 'load_gateway' ), 99 );
			add_action( 'mycred_init',            array( $this, 'load_textdomain' ) );
			add_action( 'mycred_all_references',  array( $this, 'add_badge_support' ) );
			add_action( 'mycred_parse_log_entry', array( $this, 'parse_log_entries' ), 10, 2 );

		}

		/**
		 * Define Constants
		 * @since 1.0
		 * @version 1.0
		 */
		public function define_constants() {

			$this->define( 'MYCRED_EM_VERSION',       $this->version );
			$this->define( 'MYCRED_EM_SLUG',          $this->slug );
			$this->define( 'MYCRED_DEFAULT_TYPE_KEY', 'mycred_default' );

			$this->define( 'MYCRED_EM_THIS',          __FILE__ );
			$this->define( 'MYCRED_EM_ROOT_DIR',      plugin_dir_path( MYCRED_EM_THIS ) );
			$this->define( 'MYCRED_EM_GATEWAY_DIR',   MYCRED_EM_ROOT_DIR . 'gateway/' );

		}

		/**
		 * Includes
		 * @since 1.0
		 * @version 1.0
		 */
		public function includes() { }

		/**
		 * Load Gateway
		 * @since 1.0
		 * @version 1.0
		 */
		public function load_gateway() {

			if ( class_exists( 'EM_Pro' ) && class_exists( 'EM_Gateways' ) ) {

				$this->file( MYCRED_EM_GATEWAY_DIR . 'mycred-payments.php' );

				// In case the built-in gateway is enabled from the myCRED plugin,
				// remove it so we can take over.
				$installed = EM_Gateways::gateways_list();
				if ( ! empty( $installed ) && array_key_exists( MYCRED_SLUG, $installed ) ) {

					global $EM_Gateways;

					unset( $EM_Gateways[ MYCRED_SLUG ] );

				}

				EM_Gateways::register_gateway( MYCRED_SLUG, 'EM_Gateway_myCRED_Payments' );

			}

		}

		/**
		 * Load Textdomain
		 * @since 1.0
		 * @version 1.0
		 */
		public function load_textdomain() {

			// Load Translation
			$locale = apply_filters( 'plugin_locale', get_locale(), $this->domain );

			load_textdomain( $this->domain, WP_LANG_DIR . '/' . $this->slug . '/' . $this->domain . '-' . $locale . '.mo' );
			load_plugin_textdomain( $this->domain, false, dirname( $this->plugin ) . '/lang/' );

		}

		/**
		 * Parse Booking ID
		 * @since 1.0.1
		 * @version 1.0
		 */
		public function parse_log_entries( $content, $log_entry ) {

			if ( in_array( $log_entry->ref, array( 'ticket_purchase', 'ticket_purchase_refund' ) ) ) {

				$booking_id = $log_entry->ref_id;
				$data       = maybe_unserialize( $log_entry->data );
				if ( array_key_exists( 'bid', $data ) )
					$booking_id = $data['bid'];

				$content    = str_replace( '%bookingid%', $booking_id, $content );

			}

			return $content;

		}

		/**
		 * Add Badge Support
		 * @since 1.0
		 * @version 1.0
		 */
		public function add_badge_support( $references ) {

			if ( ! class_exists( 'EM_Pro' ) ) return $references;

			$references['ticket_purchase']        = __( 'Event Payment (Events Manager)', 'mycred_em' );
			$references['ticket_sale']            = __( 'Event Sale (Events Manager)', 'mycred_em' );
			$references['ticket_purchase_refund'] = __( 'Event Payment Refund (Events Manager)', 'mycred_em' );
			$references['ticket_sale_refund']     = __( 'Event Sale Refund (Events Manager)', 'mycred_em' );

			return $references;

		}

		/**
		 * Plugin Updates
		 * @since 1.0
		 * @version 1.0
		 */
		public function plugin_updates() {

			add_filter( 'pre_set_site_transient_update_plugins', array( $this, 'check_for_plugin_update' ), 360 );
			add_filter( 'plugins_api',                           array( $this, 'plugin_api_call' ), 360, 3 );
			add_filter( 'plugin_row_meta',                       array( $this, 'plugin_view_info' ), 360, 3 );

		}

		/**
		 * Plugin Update Check
		 * @since 1.0
		 * @version 1.0
		 */
		public function check_for_plugin_update( $checked_data ) {

			global $wp_version;

			if ( empty( $checked_data->checked ) )
				return $checked_data;

			$args = array(
				'slug'    => $this->slug,
				'version' => $this->version,
				'site'    => site_url()
			);
			$request_string = array(
				'body'       => array(
					'action'     => 'version', 
					'request'    => serialize( $args ),
					'api-key'    => md5( get_bloginfo( 'url' ) )
				),
				'user-agent' => 'WordPress/' . $wp_version . '; ' . get_bloginfo( 'url' )
			);

			// Start checking for an update
			$response = wp_remote_post( $this->update_url, $request_string );

			if ( ! is_wp_error( $response ) ) {

				$result = maybe_unserialize( $response['body'] );

				if ( is_object( $result ) && ! empty( $result ) )
					$checked_data->response[ $this->slug . '/' . $this->slug . '.php' ] = $result;

			}

			return $checked_data;

		}

		/**
		 * Plugin View Info
		 * @since 1.0
		 * @version 1.0
		 */
		public function plugin_view_info( $plugin_meta, $file, $plugin_data ) {

			if ( $file != $this->plugin ) return $plugin_meta;

			$plugin_meta[] = sprintf( '<a href="%s" class="thickbox" aria-label="%s" data-title="%s">%s</a>',
				esc_url( network_admin_url( 'plugin-install.php?tab=plugin-information&plugin=' . $this->slug .
				'&TB_iframe=true&width=600&height=550' ) ),
				esc_attr( __( 'More information about this plugin', 'mycred_em' ) ),
				esc_attr( $this->plugin_name ),
				__( 'View details', 'mycred_em' )
			);

			return $plugin_meta;

		}

		/**
		 * Plugin New Version Update
		 * @since 1.0
		 * @version 1.0
		 */
		public function plugin_api_call( $result, $action, $args ) {

			global $wp_version;

			if ( ! isset( $args->slug ) || ( $args->slug != $this->slug ) )
				return $result;

			// Get the current version
			$args = array(
				'slug'    => $this->slug,
				'version' => $this->version,
				'site'    => site_url()
			);
			$request_string = array(
				'body'       => array(
					'action'     => 'info', 
					'request'    => serialize( $args ),
					'api-key'    => md5( get_bloginfo( 'url' ) )
				),
				'user-agent' => 'WordPress/' . $wp_version . '; ' . get_bloginfo( 'url' )
			);

			$request = wp_remote_post( $this->update_url, $request_string );

			if ( ! is_wp_error( $request ) )
				$result = maybe_unserialize( $request['body'] );

			return $result;

		}

	}
endif;

function mycred_for_events_manaager_pro_plugin() {
	return myCRED_Events_Manager_Pro::instance();
}
mycred_for_events_manaager_pro_plugin();
