<p align="center" style="font-size: 34px; font-weight: bold">
    <img src=".github/logo.svg" alt="Composer EDD Plugin"/>
</p>
<p align="center">
    <a href="https://packagist.org/packages/lubusin/composer-edd-plugin"><img src="https://poser.pugx.org/lubusin/composer-edd-plugin/v/stable" alt="Latest Stable Version"></a>
    <a href="https://packagist.org/packages/lubusin/composer-edd-plugin"><img src="https://poser.pugx.org/lubusin/composer-edd-plugin/downloads" alt="Total Downloads"></a>
    <a href="https://github.com/lubusin/composer-edd-plugin/blob/master/LICENSE.txt"><img src="https://poser.pugx.org/lubusin/composer-edd-plugin/license" alt="License"></a>
    <a href="https://github.com/lubusin/composer-edd-plugin/blob/master/contributing.md"><img src="https://img.shields.io/badge/PRs-welcome-brightgreen.svg" alt="PRs"></a>
</p>

# Introduction
Composer EDD Plugin enable installing and manageing WordPress pro offering powered by EDD and software licensing add-on via composer. Works with any plugin or provide using EDD and SL for distrubtion.

## Installation

Install the composer plugin to enable composer package distrubtion via EDD Software licencing.

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
                "url": "https://www.plugin-store.com"
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

Above package details hold important info to connect and download zip from plugin store:

**Important**
- `name` this can be customized as per your need, it's the package name used for `composer require namespace/edd-product-name`
- `version` used to get the zip, check account to get the version number
- `url` store location to send request for zip file (place of purchase)
- `edd_installer` instruct composer to download zip from plugin store
- `item_name` name of product/plugin, you can get it from store account
- `license` name of env variable to get the license key
- `url` name of env variable to get the url associate with the license

**Note:**
- Add multiple package enteris to add more then one plugin
- Most EDD plugins only allow getting the latest versions of their plugins, even if you specifically ask for a version.

### Step 2

Create `.env` file and add the varaible names mentioned in the above step

```
PRODUCT_LICENSE=product-license-key
PRODUCT_ACTIVATION_URL=product-activation-url
```

### Step 3

Add the store access credentials to `auth.json` to download zip file. Find more about composer repositories in the [composer documentation](https://getcomposer.org/doc/articles/http-basic-authentication.md) 

``` json
{
    "http-basic": {
        "edd-store-url.com": {
            "username": "your-username",
            "password": "your-password"
        },
    }
}
```

**Note**

To add credentials more than one plugin stores add multiple credentials keys under `http-basic` 

### Step 4

Install the plugin 

``` bash
composer require namespace/edd-product-name 
```

**Note**

To install multiple plugin add them seperating with space.

## Changelog

Please see the [Changelog](CHANGELOG.md) 

## Feedback/Suggestions

If you have any suggestions/feature request that you would like to see in the upcoming releases, feel free to let us know in the [issues section](https://github.com/lubusIN/composer-edd-plugin/issues)

## Contributing

Thank you in advance if you wish to contribute to the `Composer EDD Plugin`. You can read the contribution guidelines [here](CONTRIBUTING.md)

Check the development tasklist [here](https://github.com/lubusIN/composer-edd-plugin/projects/1), if something interests you or want to suggest something click [here](https://github.com/lubusIN/composer-edd-plugin/issues)

## Security

If you discover any security related issues, please email to [info@lubus.in](mailto:info@lubus.com) instead of using the issue tracker.

## Credits

[Ajit Bohra](http://https://twitter.com/ajitbohra)

##  Support Us

<a href="https://www.patreon.com/lubus">
<img src="https://c5.patreon.com/external/logo/become_a_patron_button.png" alt="Become A Patron"/>
</a>

[LUBUS](http://lubus.in) is a web design agency based in Mumbai, India.

You can pledge on [patreon](https://www.patreon.com/lubus) to support the development & maintenance of various [opensource](https://github.com/lubusIN/) stuffs we are building.

## License

Composer EDD Plugin is open-sourced software licensed under the [MIT license](LICENSE)
