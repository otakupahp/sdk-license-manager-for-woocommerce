# AGENTS.md

This file provides guidance to agents when working with code in this repository.

- Distribution is via [`git subtree`](README.md:23), not Composer — do not add a [`composer.json`](composer.json) unless explicitly asked.
- No build/lint/test infrastructure exists in this repo; do not invent scripts or CI.
- PHP files use **tab indentation** (per [`.editorconfig`](.editorconfig:1)); YAML/JSON use 2 spaces; Markdown preserves trailing whitespace; [`*.txt`](README.md:1) is CRLF.
- The class is [`LMFW\SDK\License`](License.php:20) in [`License.php`](License.php:1), guarded by [`class_exists()`](License.php:7), intended for multiple safe inclusion.
- HTTP calls use [`wp_remote_request()`](License.php:206) with a hardcoded 5s timeout; only HTTP 200/201 are treated as success.
- License state is cached in a WP option with a [`nextValidation`](License.php:385) timestamp; HTTP 500 falls back to the last known state and revalidates after 1 day.
- Constructor accepts [`$product_ids`](License.php:103) as an array — non-empty values are enforced against the validated license's [`productId`](License.php:342).
- When modifying an existing function or property, preserve its PHPDoc [`@since`](License.php:107) tag as the creation version and add or update [`@version`](License.php:108) with the current SDK SEM version to document changes; do not increment [`@since`](License.php:107) for existing symbols.
- User-facing strings must go through [`__()`](License.php:209) with [`$this->plugin_name`](License.php:209) as the text domain; the SDK is integrated into a consumer plugin that supplies that text domain.
- Errors are surfaced as PHP [`ErrorException`](License.php:5).
