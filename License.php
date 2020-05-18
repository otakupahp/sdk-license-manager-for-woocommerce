<?php

namespace LMFW\SDK;

use ErrorException;

/**
 * License Manager for WooCommerce SDK to let communication with the API
 *
 * Defines basic functionality to connect with the API
 *
 * @since      1.0.0
 * @package    Pahp/SDK
 * @subpackage License
 * @author     Pablo Hernández (OtakuPahp) <pablo@otakupahp.com>
 */
class License {

	/**
	 * @since 1.0.0
	 * @access private
	 * @var string
	 */
	private $plugin_name;

	/**
	 * @since 1.0.0
	 * @access private
	 * @var string $api_url
	 */
	private $api_url;

	/**
	 * @since 1.0.0
	 * @access private
	 * @var string $customer_key
	 */
	private $customer_key;

	/**
	 * @since 1.0.0
	 * @access private
	 * @var string $customer_secret
	 */
	private $customer_secret;

	/**
	 * @since 1.0.0
	 * @access private
	 * @var array $valid_status
	 */
	private $valid_status;

	/**
	 * @since 1.0.0
	 * @access private
	 * @var array
	 */
	private $product_ids;

	/**
	 * @since 1.0.0
	 * @access private
	 * @var string
	 */
	private $stored_license;
	
	/**
	 * @since 1.0.0
	 * @access private
	 * @var string
	 */
	private $valid_object;
	
	/**
	 * @since 1.0.0
	 * @access private
	 * @var int
	 */
	private $ttl;

	/**
	 * License constructor
	 *
	 * @since 1.0.0
	 *
	 * @param string $plugin_name
	 * @param string $server_url
	 * @param string $customer_key
	 * @param string $customer_secret
	 * @param mixed $product_ids
	 * @param array $license_options
	 * @param string $valid_object
	 * @param int $ttl
	 */
	public function __construct(
		$plugin_name, 
		$server_url, 
		$customer_key, 
		$customer_secret, 
		$product_ids,
		$license_options,
		$valid_object,
		$ttl
	) {

		# Set plugin name for internationalization
		$this->plugin_name = $plugin_name;

		# Connection variables
		$this->api_url         = "{$server_url}/wp-json/lmfwc/v2/";
		$this->customer_key    = $customer_key;
		$this->customer_secret = $customer_secret;
		
    		# Product IDs
		$this->product_ids =  is_array( $product_ids ) ? $product_ids : [$product_ids];
		
    		# Get license key stored in the database
		$this->stored_license = null;
		if( isset($license_options['settings_key']) ) {  // Check if WP Settings are used to store the license key
			$license = get_option($license_options['settings_key']);
			if($license !== false) {
				$this->stored_license = $license[$license_options['option_key']];
			}
		}
		elseif( isset($license_options['option_key']) ) {  // If no WP Settings are used to store the license key
			$this->stored_license = get_option($license_options['option_key']);
		}
		
		# License variables
		$this->valid_object = $valid_object;
		$this->ttl = $ttl;
		$this->valid_status = get_option($valid_object, []);

	}

	/**
	 * HTTP Request call
	 *
	 * @since 1.0.0
	 *
	 * @param string $endpoint
	 * @param string $method
	 * @param string $args
	 *
	 * @return array
	 * @throws ErrorException
	 */
	private function call($endpoint, $method = 'GET', $args = '' ) {

		# Populate the correct endpoint for the API request
		$url = "{$this->api_url}{$endpoint}?consumer_key={$this->customer_key}&consumer_secret={$this->customer_secret}";

		# Create header
		$headers = [
			'Accept'        => 'application/json',
			'Content-Type'  => 'application/json; charset=UTF-8',
		];

		# Initialize wp_args
		$wp_args = [
			'headers' => $headers,
			'method' => $method,
			'timeout' => 5
		];

		# Populate the args for use in the wp_remote_request call
		if( !empty($args) ) {
			$wp_args['body'] = $args;
		}

		# Make the call and store the response in $res
		$res = wp_remote_request($url, $wp_args);

		# Check for success
		if(!is_wp_error($res) && ($res['response']['code'] == 200 || $res['response']['code'] == 201)) {
			return json_decode($res['body'], TRUE);
		}
		elseif (is_wp_error($res)) {
			throw new ErrorException( 'Unknown error', 500);
		}
		else {
			$response = json_decode($res['body'], TRUE);
			throw new ErrorException( $response['message'], $response['data']['status']);
		}
	}

	/**
	 * Activate license
	 *
	 * @since 1.0.0
	 *
	 * @param $license_key
	 * @return array|null
	 * @throws ErrorException
	 *
	 */
	public function activate($license_key) {
		$license = null;
		if( !empty($license_key) ) {
			$response = $this->call( "licenses/activate/{$license_key}");
			if( isset($response['success']) && $response['success'] === true ) {
				$license = $response['data'];
			}
			else {
				$this->valid_status['is_valid'] = false;
				$this->valid_status['error'] = $response['message'];
				$this->valid_status['nextValidation'] = time();
				update_option($this->valid_object, $this->valid_status);
				throw new ErrorException($response['message']);
			}
		}

		return $license;
	}

	/**
	 * Deactivate license
	 *
	 * @since 1.0.0
	 * @param $license_key
	 * @throws ErrorException
	 */
	public function deactivate($license_key) {
		if( !empty($license_key) ) {
			$this->call( "licenses/deactivate/{$license_key}" );
		}
		delete_option($this->valid_object);
	}

	/**
	 * Verify if the license is valid
	 *
	 * @since 1.0.0
	 *
	 * @param $license_key
	 * @return array
	 *
	 */
	public function validate_status($license_key = '') {

		# Generic valid result
		$valid_result = [
			'is_valid' => false,
			'error' => __('The license has not been activated yet', $this->plugin_name ),
		];

		$current_time = time();

		# Use validation object if not force to validate
		if( empty( $license_key ) && isset($this->valid_status['nextValidation']) && $this->valid_status['nextValidation'] > $current_time ) {
			$valid_result['is_valid'] = $this->valid_status['is_valid'];
			$valid_result['error'] = $this->valid_status['error'];
		}
		else {

			# If no license send, look for the one stored in database
			if ( empty( $license_key ) ) {
				$license_key = $this->stored_license;
			}

			# If there is no license
			if ( empty( $license_key ) ) {
				$valid_result['error'] = __( 'A license has not been submitted', $this->plugin_name );
			}
			else {
				try {
					$response = $this->call( "licenses/{$license_key}" );
					if ( isset($response['success']) && $response['success'] === true ) {

						# Calculate license expiration date
						$this->valid_status['valid_until'] = ( $response['data']['expiresAt'] !== null ) ? strtotime( $response['data']['expiresAt'] ) : null;

						# If license key does not belongs to the Product id.
						# if not Product id is defined, then this validation is omitted
						if ( ! empty( $this->product_ids ) && ! in_array( $response['data']['productId'], $this->product_ids ) ) {
							$valid_result['error'] = __( 'The license entered does not belong to this plugin', $this->plugin_name );
						}
						# Check that the license has not reached the expiration date
						# if no expiration date is set, omit this
						elseif ( $this->valid_status['valid_until'] !== null && $this->valid_status['valid_until'] < time() ) {
							$valid_result['error'] = __( 'The license entered is expired', $this->plugin_name );
						} else {
							$valid_result['is_valid'] = true;
							$valid_result['error'] = '';
						}

					}

				}
				catch ( ErrorException $exception ) {
					$valid_result['error'] = $exception->getMessage();
				}
			}
		}

		# Update validation object
	$this->valid_status['nextValidation'] = strtotime(date('Y-m-d') . "+ {$this->ttl} days" );
		$this->valid_status['is_valid'] = $valid_result['is_valid'];
		$this->valid_status['error'] = $valid_result['error'];
		update_option($this->valid_object, $this->valid_status);

		return $valid_result;

	}

	/**
	 * Returns time of license validity
	 *
	 * @return int|null
	 * @since 1.0.0
	 */
	public function valid_until() {
		return isset($this->valid_status['valid_until']) ? $this->valid_status['valid_until'] : null;
	}

}
