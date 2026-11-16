<p align="center">
  <img src=".github/assets/logo.svg" alt="Composer EDD Plugin Logo" height="60">
</p>

<p align="center">
  <a href="https://packagist.org/packages/lubusin/composer-edd-plugin"><img src="https://img.shields.io/packagist/v/lubusin/composer-edd-plugin.svg?style=flat-square" alt="Latest Version on Packagist"></a>
  <a href="https://github.com/lubusIN/composer-edd-plugin/actions/workflows/ci.yml"><img src="https://img.shields.io/github/actions/workflow/status/lubusIN/composer-edd-plugin/ci.yml?branch=master&label=tests&style=flat-square" alt="GitHub Tests Action Status"></a>
  <a href="https://packagist.org/packages/lubusin/composer-edd-plugin"><img src="https://img.shields.io/packagist/dt/lubusin/composer-edd-plugin.svg?style=flat-square" alt="Total Downloads"></a>
  <a href="https://github.com/lubusIN/composer-edd-plugin/blob/master/LICENSE"><img src="https://img.shields.io/github/license/lubusIN/composer-edd-plugin.svg?style=flat-square" alt="License"></a>
</p>

## Introduction

Composer EDD Plugin allows installing and updating commercial WordPress plugins and themes powered by [Easy Digital Downloads](https://easydigitaldownloads.com/) and the [Software Licensing add-on](https://easydigitaldownloads.com/downloads/software-licensing/) via Composer.

## Requirements

- PHP >= 7.4
- Composer >= 2.0

## Installation

```bash
composer require lubusin/composer-edd-plugin
```

## Usage

### Step 1: Add package to `composer.json`

Add the product to the `repositories` array in your `composer.json`:

```json
{
  "repositories": [
    {
      "type": "package",
      "package": {
        "name": "namespace/edd-product-name",
        "version": "1.2.3",
        "type": "wordpress-plugin",
        "dist": {
          "type": "zip",
          "url": "https://www.productwebsite.com"
        },
        "extra": {
          "edd_installer": true,
          "item_name": "Product Name",
          "license": "PRODUCT_LICENSE",
          "url": "PRODUCT_ACTIVATION_URL"
        }
      }
    }
  ]
}
```

**Package configuration options:**

- `name`: Package name you will use for `composer require`.
- `version`: Version to install.
- `type`: Package type (`wordpress-plugin` or `wordpress-theme`).
- `dist.url`: Product store URL.
- `extra.edd_installer`: Set to `true` to enable EDD licensing.
- `extra.item_name`: Name of the product in the EDD store (or use `item_id`).
- `extra.item_id`: Numeric item ID in the EDD store (alternative to `item_name`).
- `extra.license` (or `extra.license_var`): Name of the environment variable storing your license key.
- `extra.url` (or `extra.url_var`): Name of the environment variable storing your site URL (or a direct URL like `https://example.com`).

### Step 2: Set environment variables

Add the license and site URL to your `.env` file (or set them in your server/CI environment):

```env
PRODUCT_LICENSE=your-license-key
PRODUCT_ACTIVATION_URL=https://yourwebsite.com
```

### Step 3: Install the package

```bash
composer require namespace/edd-product-name
```

### Optional: Private stores with HTTP Basic Authentication

If the vendor store sits behind HTTP Basic Auth, add your credentials in `auth.json`:

```json
{
  "http-basic": {
    "www.productwebsite.com": {
      "username": "your-username",
      "password": "your-password"
    }
  }
}
```

## Troubleshooting

### Insecure HTTP connection
```
[Composer\Downloader\TransportException]
Your configuration does not allow connections to http://www.productwebsite.com.
```
If the EDD store serves files over HTTP instead of HTTPS, allow it in `composer.json`:
```json
{
  "config": {
    "secure-http": false
  }
}
```

### Licensing errors
If the EDD store rejects the license request, the error message returned by the store is shown:
```
EDD Error for package "namespace/edd-product-name": Your license key for this product has expired.
```
Check that:
- The license key is active.
- The activation URL matches the site registered on the vendor store.
- The `item_name` or `item_id` matches the vendor's product.

## Development

```bash
composer test     # Run PHPUnit test suite
composer phpstan  # Run static analysis
composer check    # Run both tests and analysis
```

## Support & Contributions

Composer EDD Plugin is a free and open-source project maintained by [LUBUS](https://lubus.in/).

- **Bugs & Feature Requests:** Please open an issue in the [GitHub Issue Tracker](https://github.com/lubusIN/composer-edd-plugin/issues).
- **Contributions:** Contributions are welcome! Please review [CONTRIBUTING.md](CONTRIBUTING.md) before submitting pull requests.
- **Commercial Support:** If your organization needs dedicated assistance, custom implementation, or priority support, get in touch with [LUBUS](https://lubus.in/contact-us).

## Security

Please do not report security vulnerabilities publicly. If you identify a security issue, contact us privately at [https://lubus.in/contact-us](https://lubus.in/contact-us).

## Special Mentions

- Inspiration: https://github.com/polylang/polylang/issues/3
- Motivation: [@szepeviktor](https://github.com/szepeviktor) | https://github.com/polylang/polylang/issues/3#issuecomment-636411477
- Beta testing: [@nirbhay](https://twitter.com/Nirbhay_18) & [@ideabox](https://twitter.com/helloideabox)
- Logo icon credits: [prosymbols](https://thenounproject.com/prosymbols)
- Code inspiration:
  - https://github.com/ffraenz/private-composer-installer/
  - https://github.com/junaidbhura/composer-wp-pro-plugins/
  - https://github.com/szepeviktor/composer-envato
  - https://github.com/mautic/composer-plugin/

## Meet Your Artisans

[LUBUS](https://lubus.in/?utm_source=github&utm_medium=open-source&utm_campaign=composer-edd-plugin) is a web design and engineering agency based in Mumbai.

<a href="https://cal.com/lubus">
<img src="https://raw.githubusercontent.com/lubusIN/.github/refs/heads/main/profile/banner.png" />
</a>

## License

Composer EDD Plugin is open-sourced software licensed under the [MIT license](LICENSE).
