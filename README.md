# SDK for License Manager for WooCommerce
A simple SDK to include in your plugins using the great [License Manager for WooCommerce](https://github.com/drazenbebic/license-manager-for-woocommerce) created by [Drazen Bebic](https://github.com/drazenbebic)

## Configuring Library

To configure the License SDK as a library:

 1. Include the License SDK codebase
 2. Load the library by including the License.php file
 3. Configure the system constants
 4. Set a WP Schedule action to check the license validity

### 1. Including the codebase

Using a subtree in your plugin to include License SDK is the recommended method.

#### Step 1. Add the Repository as a Remote

git remote add -f subtree-license-sdk https://github.com/otakupahp/sdk-license-manager-for-woocommerce.git

Adding the subtree as a remote allows us to refer to it in a short form via the name subtree-license-sdk, instead of the full GitHub URL.

#### Step 2. Add the Repo as a Subtree

git subtree add --prefix libraries/license-sdk subtree-license-sdk master --squash

This will add the master branch of License SDK to your repository in the folder libraries/license-sdk.

You can change the --prefix to change where the code is included.

#### Step 3. Update the Subtree

To update License SDK to a new version, use the commands:

git fetch subtree-license-sdk master
git subtree pull --prefix libraries/license-sdk subtree-license-sdk master --squash

### 2. Loading the library

Regardless of how it is installed, to load License SDK, you only need to include the license-sdk.php file, e.g.

```
<?php
require_once( plugin_dir_path( __FILE__ ) . '/libraries/license-sdk/License.php
```

If you use autoloading, you could use `LMFW\SDK\License` instead of requiring the file

### 3. Configure system constants and create an instance of the SDK

Add the following constants definition in the root of your plugin:

```
# License Manager Constants
define( 'LMFW_API_URL', 'https://example.com' ); //Replace with the URL of your license server (without the trailing slash)
define( 'LMFW_CK', 'ck_xxxxx' ); //Customer key created in the license server
define( 'LMFW_CS', 'cs_yyyyy' ); //Customer secret created in the license server
define( 'LMFW_PRODUCT_ID', [111,222] ); //Set an array of the products IDs on your license server
define( 'LMFW_VALID_OBJECT', 'plugin-is-valid' );  //Set a unique value to avoid conflict with other plugins
define( 'LMFW_VALIDATION_TTL', 5 ); //How many days the valid object will be used before calling the license server
define( 'LMFW_LICENSE_OPTION_KEY', 'plugin_license' ); //Set a unique value to avoid conflict with other plugins
define( 'NO_LICENSE', 'xxxxxx');

# Create an instance of your plugin
$sdk_license = new LMFW\SDK\License( $plugin_name );  // The plugin name is used to manage internationalization
```

The *LMFW_VALID_OBJECT* constant is used to determine if the license is valid or not. An object is stored in the options table to avoid API calls overload to your license server. 

### 4. Set schedule action

```
# Schedule license validity check event
if ( ! wp_next_scheduled( 'lmfw_sdk_license_validity' ) ) {
  wp_schedule_event( time(), 'daily', 'lmfw_sdk_license_validity' );
}

# Add validity function
add_action('lmfw_sdk_license_validity', 'lmfw_license_is_active');
```

## Use the library

The SDK contains many basic functions that use the v2 API routes

### Check if the Licence registered is valid

**END POINT REQUIRED: `GET - v2/licenses/{license_key}`**

To check if the last activated license is valid, you could invoke *valudate_status*.

```
  $sdk_license->validate_status();
```

To check if a specific license is valid, you could send the license and force i

```
  $sdk_license->validate_status('LICENSE-GENERATED-KEY');
```

This function will return an array with 2 keys *is_valid* (boolean that indicates if the license is valid or not) and *error* (a string with the error message in case the license is invalid)


### Activate a license

**END POINT REQUIRED: `GET - v2/licenses/activate/{license_key}`**

Activate the license provided. It is suggested to first validate the license since this will return an error if the license is invalid.

```
  $sdk_license->activate('LICENSE-GENERATED-KEY');
```

The function will create a valid object and store it on the database, then return the license object returned by the license server.

This function throws an Exception if anything fails.

### Deactivate a license

**END POINT REQUIRED: `GET - v2/licenses/deactivate/{license_key}`**

Deactivate the license provided.

```
  $sdk_license->deactivate('LICENSE-GENERATED-KEY');
```

The function will delete the valid object stored on the database.

This function throws an Exception if anything fails.

### Check license validity

This is an internal function to check the license validity if it exists.

```
  $sdk_license->valid_until();
```

This function returns a timestamp value.
