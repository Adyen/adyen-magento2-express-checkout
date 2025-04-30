# Adyen Magento (Adobe) Express Checkout Module
This module gives our customers the ability to add express payment methods into Magento (Adobe) eCommerce PDP, Cart and Mini-Cart pages to increase shopper converison. 
This module is to be used along with the [Adyen Payment plugin for Magento 2](https://github.com/Adyen/adyen-magento2) and is not a standalone product.

## Payment Methods Supported
* Apple Pay express checkout options on the product page, mini cart and cart.
* Google Pay express checkout options on the product page, mini cart and cart.
* PayPal express checkout options on the product page, mini cart and cart.

For the full list of payment methods available with Adyen, refer to [Adyen Docs](https://docs.adyen.com/plugins/adobe-commerce/supported-payment-methods/).

## Requirements
This module supports
* Magento 2 version 2.4 and higher
* Adyen Payment plugin for Magento 2 v8.8.0 and higher

## Installation
You can install our module through Composer:
```
composer require adyen/adyen-magento2-expresscheckout
bin/magento module:enable Adyen_ExpressCheckout
bin/magento setup:upgrade
bin/magento cache:clean
```

## Configuration Steps
1. Add the express payment method in your [Customer Area](https://docs.adyen.com/payment-methods#add-payment-methods-to-your-account).
2. Make sure that Payment methods are activated in your [Magento configuration](https://docs.adyen.com/plugins/adobe-commerce/set-up-the-payment-methods-in-adobe-commerce/).
3. In the Magento admin page, go to Accepting Payments > Online Checkout > Express Payments > Show Google Pay on > Select one or all of your desired options.
4. For Apple Pay: [Use Adyen's Apple Pay Certificate to go live](https://docs.adyen.com/payment-methods/apple-pay/web-component#going-live), without designing your Apple Pay integration.
5. For Google Pay: Set up [Google Pay](https://docs.adyen.com/payment-methods/google-pay/web-component#before-you-go-live).

## Best Practice
1. **Managing Quote Table Growth**: The PDP page refresh with a PDP express payment method enabled creates new `quotes` to store updated payment data data. While this is expected behavior and not critical, it may lead to gradual growth of the quotes table. If relevant for your store, consider setting up a scheduled cleanup for old or inactive quotes.

## Contributing
We strongly encourage you to join us in contributing to this repository so everyone can benefit from:
* New features and functionality
* Resolved bug fixes and issues
* Any general improvements

Read our [**contribution guidelines**](CONTRIBUTING.md) to find out how.

## Releases
1. **Major** releases are done ONLY when absolutely required. We try to not to introduce breaking changes and do major releases as rare as possible. Current average is **yearly**.
2. A minor or a patch release is scheduled but not limited to **once a month.**

**Note: This can be subject to change based on the load and dependencies of the Integration tools team.**

## Support
If you have a feature request, or spotted a bug or a technical problem, create a GitHub issue. For other questions, contact our [support team](https://support.adyen.com/hc/en-us/requests/new?ticket_form_id=360000705420).

## License
MIT license. For more information, see the [LICENSE](LICENSE) file.
