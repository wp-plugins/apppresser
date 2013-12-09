<?php
/**
 * Plugin Updater
 *
 * @package AppPresser
 * @subpackage Admin
 * @license http://www.opensource.org/licenses/gpl-license.php GPL v2.0 (or later)
 */

class AppPresser_Updater extends AppPresser {

	// A single instance of this class.
	public static $included = false;
	public static $updaters = array();
	const AUTHOR            = 'AppPresser Team';
	const STORE_URL         = 'http://appp.wpengine.com';

	/**
	 * Includes the EDD_SL_Plugin_Updater class if needed
	 * @since  1.0.0
	 */
	public static function include_updater() {
		if ( ! self::$included && ! class_exists( 'EDD_SL_Plugin_Updater' ) ) {
			// load our custom updater
			include( self::$inc_path . 'EDD_SL_Plugin_Updater.php' );
		}
		self::$included = true;
	}

	/**
	 * Add a EDD_SL_Plugin_Updater instance
	 * @since  1.0.0
	 * @param  string $plugin_file    Path to the plugin file.
	 * @param  string $option_key     `appp_get_setting` setting key
	 * @param  array  $api_data       Optional data to send with API calls.
	 * @return EDD_SL_Plugin_Updater	 object instance
	 */
	public static function add( $plugin_file, $option_key = '', $api_data = array() ) {

		// Include the updater if we haven't
		self::include_updater();

		$base_name = plugin_basename( $plugin_file );
		if ( $option_key ) {
			// Add to the list of keys to save license statuses
			AppPresser_Admin_Settings::$license_keys[ $option_key ] = $base_name;
		}

		$api_data = wp_parse_args( $api_data, array(
			'author'  => self::AUTHOR,
			'url'     => self::STORE_URL,
			'license' => trim( appp_get_setting( $option_key ) ),
		) );

		$api_url = $api_data['url'];
		unset( $api_data['url'] );

		// Init updater
		$updater = new EDD_SL_Plugin_Updater( $api_url, $plugin_file, $api_data	);

		// Add passed-in vars to the object since the vars are private (derp).
		$updater->public = $api_data + array( 'api_url' => $api_url, 'plugin_file' => $plugin_file );

		// Add this updater instance to our array
		self::$updaters[ $base_name ] = $updater;
		return $updater;
	}

	/**
	 * Retrieve a EDD_SL_Plugin_Updater instance
	 * @since  1.0.0
	 * @param  string $plugin_file    Path to the plugin file.
	 * @return EDD_SL_Plugin_Updater	 object instance
	 */
	public static function get_updater( $plugin_file ) {

		if ( isset( self::$updaters[ $plugin_file ] ) )
			return self::$updaters[ $plugin_file ];

		$base_name = plugin_basename( $plugin_file );
		if ( isset( self::$updaters[ $base_name ] ) )
			return self::$updaters[ $base_name ];

		return false;
	}

	/**
	 * Retrieves a license key's status from the store
	 * @since  1.0.0
	 * @param  string $license      License Key
	 * @param  string $plugin_file Plugin dir/file
	 * @return mixed                License status or false if failure
	 */
	public static function get_license_status( $license, $plugin_file ) {

		if ( ! ( $updater = self::get_updater( $plugin_file ) ) )
			return false;

		$license = trim( $license );
		if ( empty( $license ) )
			return false;

		// Call the custom API.
		$response = wp_remote_post( add_query_arg( array(
			'edd_action'=> 'activate_license',
			'license' 	=> $license,
			// 'the_title' filter needed to match EDD's check
			'item_name' => urlencode( apply_filters( 'the_title', $updater->public['item_name'], 0 ) ),
		), $updater->public['api_url'] ) );

		// make sure the response came back okay
		if ( is_wp_error( $response ) )
			return false;

		// decode the license data
		$license_data = json_decode( wp_remote_retrieve_body( $response ) );

		// Send back license status
		return isset( $license_data->license ) ? $license_data->license : false;
	}

}

/**
 * Add a EDD_SL_Plugin_Updater instance
 * @since  1.0.0
 * @param  string $plugin_file   Path to the plugin file.
 * @param  string $option_key    `appp_get_setting` setting key
 * @param  array  $api_data      Optional data to send with API calls.
 * @return EDD_SL_Plugin_Updater	object instance
 */
function appp_updater_add( $plugin_file, $option_key = '', $api_data = array() ) {
	return AppPresser_Updater::add( $plugin_file, $option_key, $api_data );
}

/**
 * Helper function. Retrieves a license key's status from the store
 * @since  1.0.0
 * @param  string $license      License Key
 * @param  string $plugin_file  Plugin dir/file
 * @return mixed                License status or false if failure
 */
function appp_get_license_status( $license, $plugin_file ) {
	return AppPresser_Updater::get_license_status( $license, $plugin_file );
}
