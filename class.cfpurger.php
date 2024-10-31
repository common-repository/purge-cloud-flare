<?php

/**
 * Created by PhpStorm.
 * User: kdv
 * Date: 14.03.2016
 * Time: 17:11
 */
class Cfpurger {
	/**
	 *
	 */
	const PAGE_TITILE = 'Purge Cloud Flare Settings';
	/**
	 *
	 */
	const PLUGIN_TITILE = 'Purge Cloud Flare';
	/**
	 *
	 */
	const MENU_TITILE = 'Purge Cloud Flare';
	/**
	 *
	 */
	const MENU_SLUG = 'cf-purger';
	/**
	 * @var array
	 */
	static $options = array();
	/**
	 * @var string
	 */
	static $options_name = "cf-purger";
	/**
	 * @var array
	 */
	static $routes = [
		'zones' => 'https://api.cloudflare.com/client/v4/zones/'
	];
	/**
	 * @var array
	 */
	static $defaults = array(
		"enabled"   => 1,
		"autopurge" => 0,
		"API_key"   => false,
		"email"     => false,
		"zone_id"   => false,
	);

	/**
	 * Creates an instance
	 */
	public static function init() {
		new Cfpurger();
	}

	/**
	 *
	 */
	function __construct() {
		$this->get_options();
		if ( isset( self::$options['API_key'] ) && self::$options['API_key'] && self::$options['zone_id'] ) {
			add_action( 'admin_bar_menu', array( $this, 'custom_adminbar_menu' ), 15 );
			if ( isset( self::$options['autopurge'] ) && self::$options['autopurge'] == 1 ) {
				add_action( 'save_post', [ $this, 'clear_cache' ], 1 );
				add_action( 'admin_notices', array( $this, 'admin_notices' ) );
			}
		}
		if ( is_user_logged_in() ) {
			add_action( 'wp_enqueue_scripts', array( $this, 'register_scripts_styles' ) );
			add_action( 'wp_footer', [ $this, 'thickbox' ], 11 );
		}
		if ( is_admin() ) {
			add_action( 'admin_footer', [ $this, 'thickbox' ] );
		}
		add_action( 'admin_menu', array( $this, 'load_menu' ) );
		add_action( 'admin_init', array( $this, 'page_init' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'register_scripts_styles' ) );
		add_action( 'wp_ajax_purge_cf_cache', array( $this, 'clear_cache' ) );
		add_action( 'wp_ajax_nopriv_purge_cf_cache', array( $this, 'clear_cache' ) );
		add_action( 'admin_notices', array( $this, 'sample_admin_notice_info' ) );
		add_filter( 'plugin_action_links_' . CFPURGER_PLUGIN_BASENAME, array( $this, 'plugin_settings_link' ), 10, 2 );
	}

	/**
	 * @param $links
	 *
	 * @return mixed
	 */
	function plugin_settings_link( $links ) {
		$settings_link = '<a href="admin.php?page=' . self::MENU_SLUG . '">Settings</a>';
		array_unshift( $links, $settings_link );

		return $links;
	}

	/**
	 * @param bool $meta
	 */
	function custom_adminbar_menu( $meta = true ) {
		global $wp_admin_bar;
		if ( ! is_user_logged_in() ) {
			return;
		}
		if ( ! is_super_admin() || ! is_admin_bar_showing() ) {
			return;
		}
		$wp_admin_bar->add_menu( array(
			'parent' => 'top-secondary',
			'id'     => self::MENU_SLUG . '-clear',
			'title'  => __( 'Purge', self::MENU_SLUG ),
			'href'   => '#',
			'meta'   => array(
				'title'    => 'Purge whole Zone',
				'class'    => 'cf-purger-clear',
				'tabindex' => - 1,
				'rel'      => 'all'
			)
		) );
		$wp_admin_bar->add_menu( array(
			'parent' => self::MENU_SLUG . '-clear',
			'title'  => __( 'Specific Files' ),
			'id'     => self::MENU_SLUG . '-clear-specific-files',
			'href'   => '#cloudflare-purger-modal',
			'meta'   => array(
				'title' => 'Purge individuals file by URL',
				'class' => 'cloudflare_clear_files_thickbox_trigger',
			),
		) );

	}

	/**
	 * @param $name
	 * @param $options
	 */
	static function store_options( $name, $options ) {
		add_option( $name, $options ) OR update_option( $name, $options );
	}

	/**
	 * Get options
	 */
	function get_options() {
		$stored_options = get_option( self::$options_name );

		self::$options = array_merge( self::$defaults, $stored_options );
	}

	/**
	 * Enqueue plugin's style and script
	 */
	function register_scripts_styles() {
		wp_enqueue_style( self::MENU_SLUG . 'style', plugins_url( 'style.css', __FILE__ ) );
		wp_enqueue_script( self::MENU_SLUG . 'script', plugins_url( '/js/script.js', __FILE__ ), array( 'jquery' ),
			true );

		wp_localize_script( self::MENU_SLUG . 'script', 'cfp_ajaxurl',
			array( 'ajax_url' => admin_url( 'admin-ajax.php' ) ) );
	}

	/**
	 * Run admin page method
	 */
	function load_menu() {
		add_menu_page( self::PAGE_TITILE, self::MENU_TITILE, 'edit_others_posts', self::MENU_SLUG,
			array( $this, 'create_admin_page' ), plugins_url( 'images/cf-icon.png', __FILE__ ), 6 );
	}

	/**
	 * Store defaults
	 */
	public static function plugin_activation() {
		Cfpurger::store_options( self::$options_name, self::$defaults );
	}

	/**
	 * Removes plugin settings on deactivation
	 */
	public static function plugin_deactivation() {
		delete_option( Cfpurger::$options_name );
	}

	/**
	 * Info section if empty settings
	 */
	function sample_admin_notice_info() {
		$this->get_options();
		if ( isset( $_GET['page'] ) && $_GET['page'] == self::MENU_SLUG &&
		     ( self::$options['API_key'] == '' || self::$options['email'] == '' || self::$options['email'] == false ||
		       self::$options['zone_id'] == false )
		): ?>
			<div class="notice notice-success is-dismissible">
			<p><?php _e( 'You will need to enter your API key, Zone ID and Email Address for your account first. You can
                    locate the API key by going to your <a target="_blank" href="https://www.cloudflare.com/my-account.html">Account</a>',
					'cf-purger' ); ?></p>
			</div><?php
		endif;
	}

	/**
	 * Makes admin page
	 */
	public function create_admin_page() {
		// Set class property
		$this->get_options(); ?>
		<div class="wrap">
			<form method="post" action="options.php"><?php
				// This prints out all hidden setting fields
				settings_fields( self::$options_name . '_group' );
				do_settings_sections( self::MENU_SLUG );
				if ( self::$options['API_key'] !== false && ! empty( self::$options['API_key'] ) &&
				     ! empty( self::$options['zone_id'] )
				) {
					$this->render_purge_button();
				}
				submit_button(); ?>
			</form>
		</div>
	<?php
	}

	/**
	 * Renders purge button obviously
	 */
	function render_purge_button() {
		echo '<button onclick="jQuery(\'#wp-admin-bar-cf-purger-clear>a\').click(); return false;" class="ab-item button-primary cf-purger-clear" tabindex="-1" href="#">Purge  <span class="dashicons dashicons-cloud"></span></span></button>';
	}

	/**
	 * Register and add settings
	 */
	public function page_init() {
		register_setting( self::$options_name . '_group', // Option group
			self::$options_name, // Option name
			array( $this, 'sanitize' ) // Sanitize
		);

		add_settings_section( 'setting_section_id', // ID
			self::PAGE_TITILE, // Title
			array( $this, 'print_section_info' ), // Callback
			self::MENU_SLUG // Page
		);

		add_settings_field( 'enabled', // ID
			'Enable Plugin', // Title
			array( $this, 'enabled_callback' ), // Callback
			self::MENU_SLUG, // Page
			'setting_section_id' // Section
		);

		add_settings_field( 'email', // ID
			'CloudFlare Email Address', // Title
			array( $this, 'email_callback' ), // Callback
			self::MENU_SLUG, // Page
			'setting_section_id' // Section
		);

		add_settings_field( 'API_key', // ID
			'CloudFlare API Token', // Title
			array( $this, 'API_key_callback' ), // Callback
			self::MENU_SLUG, // Page
			'setting_section_id' // Section
		);

		add_settings_field( 'zone_id', // ID
			'Zone Id ', // Title
			array( $this, 'zone_id_callback' ), // Callback
			self::MENU_SLUG, // Page
			'setting_section_id' // Section
		);
		add_settings_field( 'autopurge', // ID
			'Clear Cache after Save Post action', // Title
			array( $this, 'autopurge_callback' ), // Callback
			self::MENU_SLUG, // Page
			'setting_section_id' // Section
		);
	}

	/**
	 * Sanitize each setting field as needed
	 *
	 * @param array $input Contains all settings fields as array keys
	 */
	public function sanitize( $input ) {
		$new_input = array();

		$new_input['autopurge'] = (int)isset( $input['autopurge'] );
		$new_input['enabled'] = (int)isset( $input['enabled'] );

		if ( isset( $input['email'] ) ) {
			$new_input['email'] = sanitize_text_field( $input['email'] );
		}

		if ( isset( $input['API_key'] ) ) {
			$new_input['API_key'] = sanitize_text_field( $input['API_key'] );
		}

		if ( isset( $input['zone_id'] ) ) {
			$new_input['zone_id'] = sanitize_text_field( $input['zone_id'] );
		}

		return $new_input;
	}

	/**
	 * Print the Section text
	 */
	public function print_section_info() {
		print 'Plugin purges entire cache for domain';
	}

	/**
	 * Renders enabled checkbox
	 */
	public function enabled_callback() {
		printf( '<input class="checkbox" type="checkbox" id="enabled" name="' . self::$options_name .
		        '[enabled]" "%s" value="1" />', checked( 1,  self::$options['enabled'], false ));
	}

	/**
	 * Renders autopurge checkbox
	 */
	public function autopurge_callback() {
		printf( '<input class="checkbox" type="checkbox" id="autopurge" name="' . self::$options_name .
		        '[autopurge]" "%s" />', checked( 1,  self::$options['autopurge'], false ));
	}

	/**
	 * Get the settings option array and print one of its values
	 */
	public function email_callback() {
		printf( '<input class="regular-text code" type="text" id="email" name="' . self::$options_name .
		        '[email]" value="%s" />', isset( self::$options['email'] ) ? esc_attr( self::$options['email'] ) : '' );
	}

	/**
	 * Renders Zode Id input
	 */
	public function zone_id_callback() {
		printf( '<input class="regular-text code" type="text" id="zone_id" name="' . self::$options_name .
		        '[zone_id]" value="%s" />',
			isset( self::$options['zone_id'] ) ? esc_attr( self::$options['zone_id'] ) : '' );
	}

	/**
	 * Renders API key setting input
	 */
	public function API_key_callback() {
		printf( '<input autosuggest="true" class="regular-text code" type="password" id="API_key" name="' .
		        self::$options_name . '[API_key]" value="%s" />
            <button id="visibility_toggle" type="button" class="button button-secondary wp-hide-pw hide-if-no-js" data-toggle="0" data-label="Show">
                <span class="dashicons dashicons-visibility"></span>
                <span class="text">Show</span>
            </button>', isset( self::$options['API_key'] ) ? esc_attr( self::$options['API_key'] ) : '' );
	}

	/**
	 * Method which is called from js - clears whole cache
	 */
	public function clear_cache( $rel = false ) {
		$rel = !$rel?$_POST['rel']:$rel;
		if ( $rel == 'files' ) {
			$files = $_POST['files'];
			$body  = json_encode( [ "files" => $files ] );
		} else {
			$body = json_encode( [ "purge_everything" => true ] );
		}
		$this->get_options();
		$options    = self::$options;
		$auth_email = $options['email'];
		$auth_key   = $options['API_key'];
		$zone_id    = $options['zone_id'];
		$headers    = [
			'X-Auth-Email' => $auth_email,
			'X-Auth-Key'   => $auth_key,
			'Content-Type' => 'application/json'
		];


		$clear_cache_request = wp_remote_request( self::$routes['zones'] . $zone_id . '/purge_cache',
			[ 'method' => 'DELETE', 'headers' => $headers, 'body' => $body ] );

		if ( wp_remote_retrieve_response_code( $clear_cache_request ) == 200 ) {
			$response_body = wp_remote_retrieve_body( $clear_cache_request );
		} else {
			$response_body = json_encode( [
				'success' => false,
				'msg'     => wp_remote_retrieve_response_message( $clear_cache_request )
			] );
		}
		if( defined('DOING_AJAX') && $_POST['action'] == 'purge_cf_cache' ) {
			echo $response_body;
			die;
		} else {
			add_filter( 'redirect_post_location', array( $this, 'add_notice_query_var' ), 99 );
		}

	}

	public function admin_notices() {
		if ( ! isset( $_GET['CF_CACHE_CLEAR'] ) ) {
			return;
		}
		?>
		<div class="updated notice notice-success is-dismissible">
			<p><?php esc_html_e( 'CloudFlare cache clear attempt was done', 'cf-purger' ); ?></p>
		</div>
	<?php
	}

	public function add_notice_query_var( $location ) {
		remove_filter( 'redirect_post_location', array( $this, 'add_notice_query_var' ), 99 );
		return add_query_arg( array( 'CF_CACHE_CLEAR' => 'ID' ), $location );
	}


	/**
	 * Renders Thickbox for Purge Individual files
	 */
	function thickbox() { ?>
		<!-- Modal -->
		<div id="cloudflare-purger-modal" class="cfp-modal md-effect-2">
			<div class="cfp-modal__content">
				<span data-dismiss="cfp-modal"
				      class="cfp-modal__button-close dashicons dashicons-no"></span>

				<div class="cfp-modal__header">
					<h4 class="cfp-modal__title">Purge individual files by URL</h4>

					<div class="cfp-control__text">
						<p>You can purge up to 30 files at a time.</p>

						<p><strong>Note:</strong> Wildcards are not supported with single file purge at this time. You
							will need to
							specify the full path to the file.
						</p>
					</div>
				</div>
				<div class="cfp-modal__body">
					<div class="cfp-modal__inputs">
						<label class="cfp-modal__label">Separate URL(s) with spaces, or list one per line</label>
						<textarea name="files" class="cfp-modal__textarea"
						          placeholder="http://www.domain.com/images/example.jpg" rows="5"></textarea>
					</div>
				</div>
				<footer class="cfp-modal__footer cfp-clearfix">
					<a href="#" class="cfp-modal__button-submit" rel="files"
					   data-action=""><?php _e( 'Purge Individual Files', 'cf-purger' ); ?></a>
				</footer>
				<!-- Modal content-->
			</div>
		</div>
		<div class="cfp-modal-backdrop"></div>
	<?php
	}

}
