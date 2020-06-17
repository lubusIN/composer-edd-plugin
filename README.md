<p align="center" style="font-size: 34px; font-weight: bold">
    <img src=".github/logo.svg" alt="Composer EDD Plugin"/>
</p>
<p align="center">
    <a href="https://packagist.org/packages/lubusin/composer-edd-plugin"><img src="https://poser.pugx.org/lubusin/composer-edd-plugin/v/stable" alt="Latest Stable Version"></a>
    <a href="https://packagist.org/packages/lubusin/composer-edd-plugin"><img src="https://poser.pugx.org/lubusin/composer-edd-plugin/downloads" alt="Total Downloads"></a>
    <a href="https://github.com/lubusin/composer-edd-plugin/blob/master/LICENSE.txt"><img src="https://poser.pugx.org/lubusin/composer-edd-plugin/license" alt="License"></a>
    <a href="https://github.com/lubusin/composer-edd-plugin/blob/master/contributing.md"><img src="https://img.shields.io/badge/PRs-welcome-brightgreen.svg" alt="PRs"></a>
</p>

## Introduction

Composer EDD Plugin enable installing and managing WordPress pro offerings powered by [EasyDigitalDownloads](https://easydigitaldownloads.com/) and [software licensing add-on](https://easydigitaldownloads.com/downloads/software-licensing/) via composer. Works with any plugin or theme delivered via EasyDigitalDownloads and software licensing for distribution.

## Installation

Install the composer plugin to enable composer package via EDD Software licencing.

``` bash
composer require lubusin/composer-edd-plugin
```

## Usage 

### Step 1

Add the desired WordPress premium offering(s) as package to the repositories field in composer.json. Find more about composer repositories in the [composer documentation](https://getcomposer.org/doc/05-repositories.md#package-2)

``` json
{
    "type": "package",
    "package": {
            "name": "namespace/edd-product-name",
            "version": "version-number",
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
```

Above package details hold important info to connect and download zip from product store:

**Important**

- `name` this can be customized as per your need, it's the package name used for `composer require namespace/edd-product-name` later
- `version` used to get the zip, check account for version number
- `type` use wordpress-plugin or wordpress-theme,

- `url` product website url (include https)
- `edd_installer` enable package via edd powered store 
- `item_name` name of product, can be found under account info
- `license` name of env variable to get the license key *(do not add your actual key here)*
- `url` name of env variable to get the website url associated with the license. *(do not add your actual website url here)*

**Note:**

- Add multiple package enteris to add more then one product
- Most EDD products only allow getting the latest versions of their product, even if you specifically ask for a version.
- Make sure license is already activated for the url.

### Step 2

Create `.env` and add the varaible names mentioned in the above step. Find more about .env [here](https://github.com/vlucas/phpdotenv) 

```
PRODUCT_LICENSE=product-license-key
PRODUCT_ACTIVATION_URL=product-activation-url
```

### Step 3

Create the `auth.json` and Add the store access credentials. Find more about http-basic-authentication in the [composer documentation](https://getcomposer.org/doc/articles/http-basic-authentication.md) 

``` json
{
    "http-basic": {
        "www.productwebsite.com": {
            "username": "your-username",
            "password": "your-password"
        },
    }
}
```

**Important**

- `www.productwebsite.com` product website host name
- `username` login username for product website
- `password` login password for product website

**Note**

To add credentials for more than one product stores add multiple credentials under `http-basic` 

### Step 4

Install the plugin 

``` bash
composer require namespace/edd-product-name 
```

## Troubleshooting

```
[Composer\Downloader\TransportException]
  Your configuration does not allow connections to http://www.productwebsite.com See https://getcomposer.org/doc/06-config.md#sec
  ure-http for details.
```
you will get above error if edd store deliver file over `http` instead of `https`. To fix this config composer to allow non secure url by setting `secure-http` to false. Find more about secure-http in the [composer documentation](https://getcomposer.org/doc/06-config.md#secure-http)   

**Note**
- Use the name set in step 1 
- To install multiple products add them seperating with space.

## Changelog

Please see the [Changelog](CHANGELOG.md) 

## Feedback / Suggestions

If you have any suggestions/feature request that you would like to see in the upcoming releases, feel free to let us know in the [issues section](https://github.com/lubusIN/composer-edd-plugin/issues)

## Contributing

Thank you in advance if you wish to contribute to the `Composer EDD Plugin`. You can read the contribution guidelines [here](CONTRIBUTING.md)

Check the development tasklist [here](https://github.com/lubusIN/composer-edd-plugin/projects/1), if something interests you or want to suggest something click [here](https://github.com/lubusIN/composer-edd-plugin/issues)

## Security

If you discover any security related issues, please email to [info@lubus.in](mailto:info@lubus.com) instead of using the issue tracker.

## Credits

[Ajit Bohra](http://https://twitter.com/ajitbohra)

## Special Mentions

- Inspiration https://github.com/polylang/polylang/issues/3
- Motivation [@szepeviktor](https://github.com/szepeviktor) | https://github.com/polylang/polylang/issues/3#issuecomment-636411477
- Logo icon credits [prosymbols](https://thenounproject.com/prosymbols)
- Code inspiration
    - https://github.com/ffraenz/private-composer-installer/
    - https://github.com/junaidbhura/composer-wp-pro-plugins/
    - https://github.com/szepeviktor/composer-envato
    - https://github.com/mautic/composer-plugin/

##  Support Us

<a href="https://www.patreon.com/lubus">
<img src="https://c5.patreon.com/external/logo/become_a_patron_button.png" alt="Become A Patron"/>
</a>

[LUBUS](http://lubus.in) is a web design agency based in Mumbai, India.

You can pledge on [patreon](https://www.patreon.com/lubus) to support the development & maintenance of various [opensource](https://github.com/lubusIN/) stuffs we are building.

## License

Composer EDD Plugin is open-sourced software licensed under the [MIT license](LICENSE)
