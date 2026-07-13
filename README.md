# SDK for License Manager for WooCommerce
A simple SDK to include in your plugins using the great [License Manager for WooCommerce](https://github.com/drazenbebic/license-manager-for-woocommerce) created by [Drazen Bebic](https://github.com/drazenbebic).

You could find more info about the plugin in its [web page](https://www.licensemanager.at/).

Pull requests to improve this SDK are greatly appreciated.

## Version and requirements

- SDK version: `1.1.0`.
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

Create the License SDK instance in your plugin:

```php
# Create an instance of the License SDK
$sdk_license = new LMFW\SDK\License(
	'https://example.com', // Replace with the URL of your license server (without the trailing slash)
	'ck_xxxxx', // Customer key created in the license server
	'cs_yyyyy', // Customer secret created in the license server
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
	string $customer_key,
	string $customer_secret,
	array|int|string $product_ids,
	array|string $license_options,
	string $valid_object,
	int $ttl
)
```

Parameters:

- `$server_url`: license server base URL, without the trailing slash.
- `$customer_key`: License Manager for WooCommerce consumer key.
- `$customer_secret`: License Manager for WooCommerce consumer secret.
- `$product_ids`: accepted product IDs as an array, integer or string. Scalar values are converted to an array. Use an empty array to skip product validation. When values are provided, they are compared in normalized string form with the license `productId` returned by the server.
- `$license_options`: either a string option key where the license key is stored, or an array with extra options.
- `$valid_object`: unique option key used to store the cached validation status.
- `$ttl`: number of days before a valid cached status is revalidated.

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

HTTP calls use WordPress `wp_remote_request()` with a fixed 5 second timeout. Only HTTP `200` and `201` are treated as successful responses. Failures are surfaced as `ErrorException`. If validation fails with HTTP `500`, the SDK preserves the last known cached state and retries validation after 1 day.
