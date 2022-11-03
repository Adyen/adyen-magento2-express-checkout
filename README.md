# Adyen Magento 2 Express Checkout Module
Short description explaining (1) what the repo contains, (2) how it works and (3) why you would need it.

## Contributing
We strongly encourage you to join us in contributing to this repository so everyone can benefit from:
* New features and functionality
* Resolved bug fixes and issues
* Any general improvements

Read our [**contribution guidelines**](CONTRIBUTING.md) to find out how.
## Requirements
This plugin supports 
* Magento2 version 2.4 and higher
* Adyen Payment plugin for Magento 2 v8.8.0 and higher

## Releases
1. **Major** releases are done ONLY when absolutely required. We try to not to introduce breaking changes and do major releases as rare as possible. Current average is **yearly**.
2. A minor or a patch release is scheduled but not limited to **once a month.**

**Note: This can be subject to change based on the load and dependancies of the Integration tools team.**


## Installation
You can install our plugin through Composer:
```
composer require adyen/adyen-magento2-express-checkout
bin/magento module:enable Adyen_ExpressCheckout
bin/magento setup:upgrade
bin/magento cache:clean
```
For more information see our [installation section](https://docs.adyen.com/developers/plugins/magento-2/set-up-the-plugin-in-magento?redirect#step1installtheplugin).

## Usage
Explain how to use after installation.

## Documentation
[Magento 2 Express Checkout documentation](https://docs.adyen.com/developers/plugins/magento-2)

## Verified payment methods

* Apple Pay
* Google Pay

## Support
If you have a feature request, or spotted a bug or a technical problem, create a GitHub issue. For other questions, contact our [support team](https://support.adyen.com/hc/en-us/requests/new?ticket_form_id=360000705420).

## License
MIT license. For more information, see the [LICENSE](LICENSE) file.
