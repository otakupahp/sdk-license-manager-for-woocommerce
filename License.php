<?php

	namespace LMFW\SDK;

	use ErrorException;

	if ( ! class_exists( 'LMFW\SDK\License' ) ) {

		/**
		 * License Manager for WooCommerce SDK to let communication with the API
		 *
		 * Defines basic functionality to connect with the API
		 *
		 * @since      1.0.0
		 * @version    1.1.0
		 * @package    Pahp/SDK
		 * @subpackage License
		 * @author     Pablo Hernández (OtakuPahp) <pablo@otakupahp.com>
		 */
		class License {

			/**
			 * License Manager for WooCommerce API base URL.
			 *
			 * @since 1.0.0
			 * @version 1.1.0
			 * @access private
			 * @var string
			 */
			private string $api_url;

			/**
			 * Customer key used to authenticate with the API.
			 *
			 * @since   1.0.0
			 * @version 1.1.0
			 * @access  private
			 * @var string $customer_key
			 */
			private string $customer_key;

			/**
			 * Customer secret used to authenticate with the API.
			 *
			 * @since   1.0.0
			 * @version 1.1.0
			 * @access private
			 * @var string $customer_secret
			 */
			private string $customer_secret;

			/**
			 * Cached license validation state stored in the WordPress options table.
			 *
			 * @since 1.0.0
			 * @version 1.1.0
			 * @access private
			 * @var array{nextValidation?: int, is_valid?: bool, error?: string, valid_until?: int|null}
			 */
			private array $valid_status;

			/**
			 * Product IDs accepted by this SDK consumer plugin.
			 *
			 * @since 1.0.0
			 * @version 1.1.0
			 * @access private
			 * @var array<int, int|string>
			 */
			private array $product_ids;

			/**
			 * License key stored by the consumer plugin, if any.
			 *
			 * @since 1.0.0
			 * @version 1.1.0
			 * @access private
			 * @var string|null
			 */
			private ?string $stored_license;

			/**
			 * Option key used to store the cached validation status.
			 *
			 * @since 1.0.0
			 * @version 1.1.0
			 * @access private
			 * @var string
			 */
			private string $valid_object;

			/**
			 * Days before a valid cached status is revalidated.
			 *
			 * @since 1.0.0
			 * @version 1.1.0
			 * @access private
			 * @var int
			 */
			private int $ttl;

			/**
			 * License constructor
			 *
			 * @param string                                                   $server_url      License server base URL, without trailing slash.
			 * @param string                                                   $customer_key    License Manager for WooCommerce consumer key.
			 * @param string                                                   $customer_secret License Manager for WooCommerce consumer secret.
			 * @param array<int, int|string>|int|string                        $product_ids     Accepted product IDs, or an empty array to skip product validation.
			 * @param array{settings_key?: string, option_key?: string}|string $license_options License option key, or WP Settings API keys.
			 * @param string                                                   $valid_object    Option key for cached validation state.
			 * @param int                                                      $ttl             Days before revalidating cached status.
			 * @since 1.0.0
			 * @version 1.1.0
			 *
			 */
			public function __construct(
				string $server_url,
				string $customer_key,
				string $customer_secret,
				array|int|string $product_ids,
				array|string $license_options,
				string $valid_object,
				int $ttl
			) {

				// Connection variables
				$this->api_url         = rtrim( $server_url, '/' ) . '/wp-json/lmfwc/v2/';
				$this->customer_key    = $customer_key;
				$this->customer_secret = $customer_secret;

				// Product IDs
				$product_ids       = is_array( $product_ids ) ? $product_ids : array( $product_ids );
				$this->product_ids = array_values(
					array_filter(
						$product_ids,
						static function ( $product_id ) {
							return is_int( $product_id ) || is_string( $product_id );
						}
					)
				);

				// Get license key stored in the database
				$this->stored_license = null;
				if ( is_array( $license_options ) && isset( $license_options['settings_key'], $license_options['option_key'] ) ) {  // Check if WP Settings are used to store the license key
					$license = get_option( (string) $license_options['settings_key'] );
					if ( is_array( $license ) && isset( $license[ $license_options['option_key'] ] ) && is_scalar( $license[ $license_options['option_key'] ] ) ) {
						$this->stored_license = (string) $license[ $license_options['option_key'] ];
					}
				} elseif ( is_array( $license_options ) && isset( $license_options['option_key'] ) ) {  // If no WP Settings are used to store the license key
					$stored_license = get_option( (string) $license_options['option_key'] );
					$this->stored_license = is_scalar( $stored_license ) ? (string) $stored_license : null;
				} elseif ( is_string( $license_options ) && '' !== $license_options ) {
					$stored_license = get_option( $license_options );
					$this->stored_license = is_scalar( $stored_license ) ? (string) $stored_license : null;
				}

				// License variables
				$this->valid_object = $valid_object;
				$this->ttl          = $ttl;
				$valid_status       = get_option( $this->valid_object, array() );
				$this->valid_status = is_array( $valid_status ) ? $valid_status : array();

			}

			/**
			 * HTTP Request call.
			 *
			 * @param string    $endpoint API endpoint relative to the v2 base URL.
			 *
			 * @return array<string, mixed>
			 * @throws ErrorException When the server response is a WP error or an unsuccessful HTTP status.
			 * @since 1.0.0
			 * @version 1.1.0
			 *
			 */
			private function call( string $endpoint ): array {
				// Populate the correct endpoint for the API request
				$url = "{$this->api_url}{$endpoint}?" . http_build_query(
					array(
						'consumer_key'    => $this->customer_key,
						'consumer_secret' => $this->customer_secret,
					),
					'',
					'&',
					PHP_QUERY_RFC3986
				);

				// Create header
				$headers = array(
					'Accept'       => 'application/json',
					'Content-Type' => 'application/json; charset=UTF-8',
				);

				// Initialize wp_args
				$wp_args = array(
					'headers' => $headers,
					'method'  => 'GET',
					'timeout' => 5,
				);

				// Make the call and store the response in $res
				/** @var array<string, mixed>|object $res */
				$res = wp_remote_request( $url, $wp_args );

				if ( is_wp_error( $res ) ) {
					throw new ErrorException( __( 'Unknown error', 'sdk-lmfw' ), 500 );
				}

				$status_code = function_exists( 'wp_remote_retrieve_response_code' ) ? (int) wp_remote_retrieve_response_code( $res ) : ( isset( $res['response']['code'] ) ? (int) $res['response']['code'] : 500 );
				$body        = function_exists( 'wp_remote_retrieve_body' ) ? wp_remote_retrieve_body( $res ) : ( isset( $res['body'] ) ? (string) $res['body'] : '' );
				$response    = json_decode( $body, true );
				$response    = is_array( $response ) ? $response : array();

				// Check for success
				if ( in_array( $status_code, array( 200, 201 ), true ) ) {
					return $response;
				}

				$error_data   = isset( $response['data'] ) && is_array( $response['data'] ) ? $response['data'] : array();
				$error_status = isset( $error_data['status'] ) ? (int) $error_data['status'] : $status_code;
				if ( isset( $response['code'] ) && 'rest_no_route' === $response['code'] ) {
					$error_status = 500;
				}

				$message = sprintf(
					__( 'License server request failed with HTTP status %d', 'sdk-lmfw' ),
					$status_code
				);
				if ( isset( $response['message'] ) && is_scalar( $response['message'] ) && '' !== (string) $response['message'] ) {
					$message .= ': ' . $response['message'];
				} elseif ( '' !== $body ) {
					$message .= ': ' . $body;
				}

				throw new ErrorException( $message, $error_status );
			}

			/**
			 * Activate license.
			 *
			 * @param string $license_key License key to activate.
			 * @return array<string, mixed>|null
			 * @throws ErrorException When activation fails.
			 *
			 * @since 1.0.0
			 * @version 1.1.0
			 *
			 */
			public function activate( string $license_key ): ?array {
				$license     = null;
				if ( '' !== $license_key ) {
					$response = $this->call( 'licenses/activate/' . rawurlencode( $license_key ) );
					if ( isset( $response['success'] ) && (bool) $response['success'] ) {
						$license = isset( $response['data'] ) && is_array( $response['data'] ) ? $response['data'] : array();
					} else {
						$message                               = isset( $response['message'] ) && is_scalar( $response['message'] ) && '' !== (string) $response['message'] ? (string) $response['message'] : __( 'The license could not be activated', 'sdk-lmfw' );
						$this->valid_status['is_valid']       = false;
						$this->valid_status['error']          = $message;
						$this->valid_status['nextValidation'] = time();
						unset( $this->valid_status['valid_until'] );
						update_option( $this->valid_object, $this->valid_status );
						throw new ErrorException( $message );
					}
				}

				return $license;
			}

			/**
			 * Deactivate license.
			 *
			 * @param string $license_key License key to deactivate.
			 * @throws ErrorException When deactivation fails.
			 * @since 1.0.0
			 * @version 1.1.0
			 */
			public function deactivate( string $license_key ): void {
				if ( '' !== $license_key ) {
					$this->call( 'licenses/deactivate/' . rawurlencode( $license_key ) );
				}
				delete_option( $this->valid_object );
			}

			/**
			 * Verify if the license is valid.
			 *
			 * @param string $license_key Optional license key to validate immediately.
			 * @return array{is_valid: bool, error: string}
			 *
			 * @since 1.0.0
			 * @version 1.1.0
			 *
			 */
			public function validate_status( string $license_key = '' ): array {
				$license_key = is_scalar( $license_key ) ? (string) $license_key : '';

				// Generic valid result
				$valid_result = array(
					'is_valid' => false,
					'error'    => __( 'The license has not been activated yet', 'sdk-lmfw' ),
				);

				$current_time = time();
				$ttl          = 0;

				$cached_is_valid = isset( $this->valid_status['is_valid'] ) && (bool) $this->valid_status['is_valid'];
				$cached_error    = isset( $this->valid_status['error'] ) && is_scalar( $this->valid_status['error'] ) ? (string) $this->valid_status['error'] : __( 'The license has not been activated yet', 'sdk-lmfw' );

				// Use validation object if not force validating
				if (
					empty( $license_key ) &&
					isset( $this->valid_status['nextValidation'] ) &&
					(int) $this->valid_status['nextValidation'] > $current_time
				) {
					$valid_result['is_valid'] = $cached_is_valid;
					$valid_result['error']    = $cached_error;
				} else {

					$preserve_valid_until = false;

					// If no license send, look for the one stored in database
					if ( empty( $license_key ) ) {
						$license_key = $this->stored_license;
					}

					// If there is no license
					if ( empty( $license_key ) ) {
						$valid_result['error'] = __( 'A license has not been submitted', 'sdk-lmfw' );
					} else {
						try {
							$response = $this->call( 'licenses/' . rawurlencode( $license_key ) );
							if ( isset( $response['success'] ) && (bool) $response['success'] && isset( $response['data'] ) && is_array( $response['data'] ) ) {

								$valid_until = null;
								if ( isset( $response['data']['expiresAt'] ) && is_scalar( $response['data']['expiresAt'] ) ) {
									$valid_until = strtotime( (string) $response['data']['expiresAt'] );
									$valid_until = false !== $valid_until ? $valid_until : null;
								}

								$product_id  = isset( $response['data']['productId'] ) && is_scalar( $response['data']['productId'] ) ? (string) $response['data']['productId'] : '';
								$product_ids = array_map( 'strval', $this->product_ids );

								if (
									! empty( $product_ids ) &&
									! in_array( $product_id, $product_ids, true )
								) { // If license key does not belong to the Product id and if not Product id is defined, then this validation is omitted
									$valid_result['error'] = __( 'The license entered does not belong to this plugin', 'sdk-lmfw' );
								} elseif ( // Check that the license has not reached the expiration date. If no expiration date is set, omit this
									null !== $valid_until &&
									$valid_until < $current_time
								) {
									$valid_result['error'] = __( 'The license expired', 'sdk-lmfw' );
								} else {
									$valid_result['is_valid'] = true;
									$valid_result['error']    = '';
									$ttl                      = $this->ttl;
								}

								if ( $valid_result['is_valid'] && null !== $valid_until ) {
									$this->valid_status['valid_until'] = $valid_until;
									$preserve_valid_until              = true;
								}
							} else {
								$valid_result['error'] = isset( $response['message'] ) && is_scalar( $response['message'] ) && '' !== (string) $response['message'] ? (string) $response['message'] : __( 'License validation failed', 'sdk-lmfw' );
							}
						} catch ( ErrorException $exception ) {
							if ( 500 === $exception->getCode() ) {  // License server unavailable
								$valid_result['is_valid'] = $cached_is_valid;
								$valid_result['error']    = $cached_error;
								$ttl                      = 1; // Set 1 day TTL to try the next day
								$preserve_valid_until     = isset( $this->valid_status['valid_until'] );
							} else {
								$valid_result['error'] = $exception->getMessage();
							}
						}
					}

					if ( ! $preserve_valid_until ) {
						unset( $this->valid_status['valid_until'] );
					}

					// Update validation object
					$this->valid_status['nextValidation'] = $current_time + ( ( defined( 'DAY_IN_SECONDS' ) ? DAY_IN_SECONDS : 86400 ) * $ttl );
					$this->valid_status['is_valid']       = $valid_result['is_valid'];
					$this->valid_status['error']          = $valid_result['error'];
					update_option( $this->valid_object, $this->valid_status );

				}

				return $valid_result;

			}

			/**
			 * Returns time of license validity.
			 *
			 * @return int|null
			 * @since 1.0.0
			 * @version 1.1.0
			 */
			public function valid_until(): ?int {
				return isset( $this->valid_status['valid_until'] ) ? (int) $this->valid_status['valid_until'] : null;
			}

		}

	}
