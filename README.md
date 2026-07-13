# SDK for License Manager for WooCommerce
A simple SDK to include in your plugins using the great [License Manager for WooCommerce](https://github.com/drazenbebic/license-manager-for-woocommerce) created by [Drazen Bebic](https://github.com/drazenbebic).

You could find more info about the plugin in its [web page](https://www.licensemanager.at/).

Pull requests to improve this SDK are greatly appreciated.

## Version and requirements

- SDK version: `2.0.0`.
- Minimum PHP version: `8.2`.
- WordPress is required because the SDK uses WordPress APIs such as HTTP requests, options and scheduling.

## Configuring Library

To configure the License SDK as a library:

 1. Include the License SDK codebase
 2. Load the library by including the License.php file
 3. Create an instance of the SDK
 4. Set a WP Schedule action to check the license validity

### 1. Including the codebase

Using a subtree in your plugin to include License SDK is the recommended method.

#### Step 1. Add the Repository as a Remote

```
git remote add -f subtree-license-sdk https://github.com/otakupahp/sdk-license-manager-for-woocommerce.git
```

Adding the subtree as a remote allows us to refer to it in a short form via the name subtree-license-sdk, instead of the full GitHub URL.

#### Step 2. Add the Repo as a Subtree

```
git subtree add --prefix libraries/license-sdk subtree-license-sdk master --squash
```

This will add the master branch of License SDK to your repository in the folder libraries/license-sdk.

You can change the --prefix to point where the code is included.

#### Step 3. Update the Subtree

To update License SDK to a new version, use the commands:

```
git fetch subtree-license-sdk master
git subtree pull --prefix libraries/license-sdk subtree-license-sdk master --squash
```

### 2. Loading the library

Regardless of how it is installed, to load License SDK, you only need to include the License.php file, e.g.

```php
<?php
require_once plugin_dir_path( __FILE__ ) . 'libraries/license-sdk/License.php';
```

By default, load the SDK with `require_once` for `License.php`. You can omit `require_once` only if the consuming plugin explicitly configures an autoloader, such as PSR-4 or an equivalent mapping, that maps `LMFW\SDK\License` to `License.php`. This SDK does not register autoloading by itself.

### 3. Create an instance of the SDK

Create the License SDK instance in your plugin. The SDK supports two request modes:

- Broker mode is the official and recommended mode for new integrations. It sends SDK requests to your own proxy/broker endpoint and does not append `consumer_key` or `consumer_secret` to requests made by the SDK.
- Direct mode is kept for legacy integrations and private deployments. It sends the License Manager for WooCommerce consumer credentials to the configured license server.

For distributed plugins, use broker mode so real License Manager for WooCommerce URLs and credentials are not shipped in plugin code. The broker must be a server-side component you control. A standalone broker template is available as an optional, copyable reference implementation in this Gist: https://gist.github.com/otakupahp/6c0d70b40a4f2390dda0f3c2a0dfd024. It is intentionally not included in this repository, so consumers who import the SDK through `git subtree` do not accidentally bundle server-side broker code into their plugins.

Broker mode example (recommended):

```php
# Create an instance of the License SDK in broker mode
$sdk_license = new LMFW\SDK\License(
	'https://broker.example.com/lmfwc/v2/', // Broker URL that receives logical endpoints such as licenses/{license_key}
	null, // Omit the customer key to enable broker mode
	null, // Omit the customer secret to enable broker mode
	array( 111, 222 ), // Product IDs accepted by this plugin, or an empty array to skip product validation
	'plugin_license', // Option key where the license key is stored
	'plugin-is-valid', // Option key used to cache validation status
	5 // Days before revalidating cached status
);
```

Broker mode is autodetected when both `$customer_key` and `$customer_secret` are `null` or empty strings. In that case, the first constructor parameter (`$server_url`) is interpreted as the broker URL and SDK requests are built as `broker URL + logical endpoint`, for example `https://broker.example.com/lmfwc/v2/licenses/LICENSE-GENERATED-KEY`.

The previous `broker_url` request option remains supported as a compatibility alias for integrations created before SDK `2.0.0`:

```php
# Backward-compatible explicit broker_url configuration
$sdk_license = new LMFW\SDK\License(
	'https://example.com', // Ignored for HTTP calls when broker_url is provided
	'', // Not sent by the SDK in broker mode
	'', // Not sent by the SDK in broker mode
	array( 111, 222 ),
	'plugin_license',
	'plugin-is-valid',
	5,
	array(
		'broker_url' => 'https://broker.example.com/lmfwc/v2/',
	)
);
```

Broker mode precedence is explicit:

1. If `$request_options['broker_url']` is present and non-empty, it wins and is used for HTTP calls.
2. If `broker_url` is not present and both credentials are omitted, `$server_url` is used as the broker URL.
3. If `broker_url` is not present and both credentials are provided, `$server_url` is used as the License Manager for WooCommerce server URL in direct legacy mode.
4. If only one credential is provided, the SDK treats the configuration as invalid and throws an `ErrorException` from the constructor.

Direct mode example (legacy/private deployments):

```php
# Create an instance of the License SDK in direct legacy mode
$sdk_license = new LMFW\SDK\License(
	'https://example.com', // License Manager for WooCommerce server URL, without the trailing slash
	'ck_placeholder', // Customer key created in the license server
	'cs_placeholder', // Customer secret created in the license server
	array( 111, 222 ), // Product IDs accepted by this plugin, or an empty array to skip product validation
	'plugin_license', // Option key where the license key is stored
	'plugin-is-valid', // Option key used to cache validation status
	5 // Days before revalidating cached status
);
```

The constructor signature is:

```php
public function __construct(
	string $server_url,
	?string $customer_key,
	?string $customer_secret,
	array|int|string $product_ids,
	array|string $license_options,
	string $valid_object,
	int $ttl,
	array $request_options = array()
)
```

Parameters:

- `$server_url`: in broker mode, the broker base URL, without the trailing slash requirement because the SDK normalizes it. In direct legacy mode, the License Manager for WooCommerce server base URL, without the trailing slash; the SDK calls `$server_url . '/wp-json/lmfwc/v2/'`.
- `$customer_key`: License Manager for WooCommerce consumer key for direct legacy mode. Use `null` or an empty string for broker mode.
- `$customer_secret`: License Manager for WooCommerce consumer secret for direct legacy mode. Use `null` or an empty string for broker mode.
- `$product_ids`: accepted product IDs as an array, integer or string. Scalar values are converted to an array. Use an empty array to skip product validation. When values are provided, they are compared in normalized string form with the license `productId` returned by the server.
- `$license_options`: either a string option key where the license key is stored, or an array with extra options.
- `$valid_object`: unique option key used to store the cached validation status.
- `$ttl`: number of days before a valid cached status is revalidated.
- `$request_options`: optional array for request behavior. Set `broker_url` to a proxy base URL to explicitly enable broker mode and override broker autodetection.

In broker mode, the broker receives `GET` requests for the same logical endpoints used by this SDK: `licenses/{license_key}`, `licenses/activate/{license_key}` and `licenses/deactivate/{license_key}`. The license key is part of the endpoint path. The SDK sends JSON headers but does not send License Manager for WooCommerce `consumer_key` or `consumer_secret` query arguments. Product IDs are not sent to the broker by the SDK; they are checked client-side against the `productId` returned by the broker/license server.

The broker is responsible for storing the real License Manager for WooCommerce server URL and real consumer credentials server-side, forwarding requests to the actual license server, and returning the same response shape expected by this SDK, including `success`, `data`, `message`, `data.productId` and `data.expiresAt` where applicable.

### Standalone broker/proxy reference

A standalone PHP broker/proxy template for SDK `2.0.0` is published outside this repository as an optional, copyable Gist: https://gist.github.com/otakupahp/6c0d70b40a4f2390dda0f3c2a0dfd024. Use it as a reference implementation for broker mode when you need a simple PHP broker/proxy deployment.

The template is intentionally kept out of the main repository. The SDK is commonly imported into consumer plugins through `git subtree`, and keeping the broker in a Gist avoids accidentally adding server-side broker code to distributed plugin packages.

The broker template has no WordPress dependency. It receives the logical SDK endpoints, forwards them to the real License Manager for WooCommerce REST API, appends the real `consumer_key` and `consumer_secret` server-side, and returns the upstream JSON payload and HTTP status expected by the SDK.

Basic deployment flow:

1. Copy the broker template from the Gist to a server you control, for example `https://broker.example.com/broker.php`.
2. Edit the configuration constants at the top of your copied broker file:
   - `LMFWC_SITE_URL`: the real WordPress site URL where License Manager for WooCommerce is installed.
   - `LMFWC_CONSUMER_KEY`: the real consumer key.
   - `LMFWC_CONSUMER_SECRET`: the real consumer secret.
   - `LMFWC_ALLOWED_PRODUCT_IDS`: optional broker-side product ID allow-list. Leave it empty to skip broker-side product checks and let the SDK validate `productId` client-side.
   - `BROKER_PUBLIC_TOKEN`: optional simple token. SDK `2.0.0` does not append arbitrary query parameters by itself, so leave it empty unless your web server/rewrite layer injects the token query parameter or your integration wraps the SDK request URL in a way that sends it.
3. Configure your plugin SDK instance to point to the broker base URL, with both credentials omitted:

```php
$sdk_license = new LMFW\SDK\License(
	'https://broker.example.com/broker.php/',
	null,
	null,
	array( 111, 222 ),
	'plugin_license',
	'plugin-is-valid',
	5
);
```

With URL rewriting, you can expose a cleaner base URL such as `https://broker.example.com/lmfwc/v2/` as long as requests for `licenses/{license_key}`, `licenses/activate/{license_key}` and `licenses/deactivate/{license_key}` are routed to your copied broker file while preserving the path information.

The broker template implements minimal standalone defenses:

- only `GET` requests are accepted, matching the SDK HTTP method;
- only the three SDK logical endpoint patterns are allowed;
- path traversal, backslashes and externally injected URLs are rejected;
- credentials are only appended to upstream server-side requests and are not included in broker errors;
- all responses are JSON with safe error payloads;
- upstream calls use a fixed timeout and prefer cURL, with PHP streams as fallback.

For serious production deployments, add server-level controls around the broker: HTTPS only, firewall/IP or country controls when appropriate, request rate limiting, logs/monitoring, abuse detection, and stronger authentication if your integration can send it without exposing secrets in the distributed plugin.

If WP Setting API is used, the `$license_options` string can be replaced by an array containing the setting object and the license key like this:

```php
array(
	'settings_key' => 'plugin_settings',
	'option_key'   => 'plugin_license',
)
```

If you store the license directly in an option but still prefer array syntax, use:

```php
array(
	'option_key' => 'plugin_license',
)
```

The `$valid_object` string is used to determine if the license is valid or not. An object is stored in the options table to avoid API calls overload to your license server.

### 4. Set schedule action

```php
# Schedule license validity check event (use a unique schedule name)
if ( ! wp_next_scheduled( 'unique_plugin_name_license_validity' ) ) {
	wp_schedule_event( time(), 'daily', 'unique_plugin_name_license_validity' );
}

# Add validity function hook
add_action( 'unique_plugin_name_license_validity', 'unique_validation_function' );

# Create the validity function called by the hook
function unique_validation_function() {
	$valid_status = $sdk_license->validate_status();
	return $valid_status['is_valid'];
}
```

You could modify the validation function as needed.

## Use the library

The SDK contains basic functions that use the v2 API routes.

### Check if the license registered is valid

**END POINT REQUIRED: `GET - v2/licenses/{license_key}`**

To check if the last activated license is valid, you could invoke `validate_status()`.

```php
$sdk_license->validate_status();
```

To check if a specific license is valid, you could send the license key and force the validation:

```php
$sdk_license->validate_status( 'LICENSE-GENERATED-KEY' );
```

Signature:

```php
public function validate_status( string $license_key = '' ): array
```

This function returns an array with `is_valid` (boolean that indicates if the license is valid or not), `error` (a string with the error message in case the license is invalid) and, when available, `valid_until` as an integer timestamp.

### Activate a license

**END POINT REQUIRED: `GET - v2/licenses/activate/{license_key}`**

Activate the license provided. It is suggested to first validate the license since this will return an error if the license is invalid.

```php
$sdk_license->activate( 'LICENSE-GENERATED-KEY' );
```

Signature:

```php
public function activate( string $license_key ): ?array
```

The function returns the license data array returned by the license server, or `null` when an empty license key is provided.

This function throws an `ErrorException` if anything fails.

### Deactivate a license

**END POINT REQUIRED: `GET - v2/licenses/deactivate/{license_key}`**

Deactivate the license provided.

```php
$sdk_license->deactivate( 'LICENSE-GENERATED-KEY' );
```

Signature:

```php
public function deactivate( string $license_key ): void
```

The function deletes the valid object stored on the database and does not return a value.

This function throws an `ErrorException` if anything fails.

### Check license validity timestamp

Use `valid_until()` to read the cached license expiration timestamp, if one is available.

```php
$sdk_license->valid_until();
```

Signature:

```php
public function valid_until(): ?int
```

This function returns an integer timestamp value or `null`.

## Cache and HTTP behavior

The SDK stores validation status in a WordPress option named by `$valid_object`. The cached object includes `nextValidation`, `is_valid` and `error`, and can include `valid_until` for licenses with an expiration date.

In SDK `1.1.0`, credentials and license keys are encoded safely in request URLs, HTTP and JSON responses are handled defensively, product ID comparison is normalized, and `nextValidation` is calculated from the current time.

In SDK `1.2.0`, broker mode was introduced through the explicit `broker_url` request option so distributed plugins can avoid sending License Manager for WooCommerce consumer credentials from plugin code.

In SDK `2.0.0`, broker mode is the official recommended mode for new integrations. When both credentials are omitted, the SDK autodetects broker mode and interprets `$server_url` as the broker URL. The explicit `$request_options['broker_url']` compatibility alias remains supported and takes precedence over autodetection. A copyable standalone broker template is available in the optional Gist reference implementation for creators who need a simple PHP broker/proxy deployment: https://gist.github.com/otakupahp/6c0d70b40a4f2390dda0f3c2a0dfd024.

HTTP calls use WordPress `wp_remote_request()` with a fixed 5 second timeout, the `GET` method and JSON headers. Only HTTP `200` and `201` are treated as successful responses. Failures are surfaced as `ErrorException`.

For issue #3, when `validate_status()` needs to call the server and the request fails with HTTP `500` or a WordPress HTTP error mapped to `500`, the SDK treats the license server as unavailable. In that case, it preserves and returns the last known cached `is_valid` and `error` values, keeps `valid_until` when one was cached, and sets `nextValidation` to retry after 1 day. Other HTTP errors do not use this server-unavailable fallback.
