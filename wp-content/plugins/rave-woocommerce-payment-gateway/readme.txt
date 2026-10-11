=== Flutterwave WooCommerce ===
Contributors: theflutterwave
Tags: fintech,flutterwave, woocommerce, payments, nigeria, mastercard, visa, target,Naira,payments,verve,donation,church,shop,store, ghana, kenya, international, mastercard, visa
Requires at least: 5.6
Tested up to: 7.1.2
Stable tag: 3.3.2
License: MIT
License URI: https://github.com/Flutterwave/Woocommerce/blob/master/LICENSE

The WooCommerce Plugin makes it very easy and quick to add Flutterwave Payment option on Checkout for your online store. Accept Credit card, Debit card and Bank account payment directly on your store with the Flutterwave Plugin for WooCommerce.

== Description ==

Accept Credit card, Debit card and Bank account payment directly on your store with the official Flutterwave Plugin for WooCommerce. This plugin supports WooCommerce Version 6.9 or greater. For WooCommerce Version 6.8 or lower please install Flutterwave WooCommerce Version (2.3.6 or lower) of the plugin [here](https://wordpress.org/plugins/rave-woocommerce-payment-gateway/advanced/) at the section update advanced options.

= Plugin Features =

* Collections: Card, Account, Mobile money, Bank Transfers, USSD, Barter, 1voucher.
* Recurring payments: Tokenization and Subscriptions.
* Split payments: Split payments between multiple recipients.

= Requirements =

1. Flutterwave for business [API Keys](https://developer.flutterwave.com/docs/integration-guides/authentication)
2. [WooCommerce](https://woocommerce.com/) version: >= 6.9
6. Supported PHP version: 7.4.0 - 8.1.0

== Installation ==

= Automatic Installation =
*   Login to your WordPress Dashboard.
*   Click on "Plugins > Add New" from the left menu.
*   In the search box type __Flutterwave Woocommerce__.
*   Click on __Install Now__ on __Flutterwave Woocommerce__ to install the plugin on your site.
*   Confirm the installation.
*   Activate the plugin.
*   Click on "WooCommerce > Settings" from the left menu and click the "Checkout" tab.
*   Click on the __Flutterwave__ link from the available Checkout Options
*   Configure your __Flutterwave Woocommerce__ settings accordingly.

= Manual Installation =
1.  Download the plugin zip file.
2.  Login to your WordPress Admin. Click on "Plugins > Add New" from the left menu.
3.  Click on the "Upload" option, then click "Choose File" to select the zip file you downloaded. Click "OK" and "Install Now" to complete the installation.
4.  Activate the plugin.
5.  Click on "WooCommerce > Settings" from the left menu and click the "Checkout" tab.
6.  Click on the __Flutterwave__ link from the available Checkout Options
7. Configure your __Flutterwave WooCommerce__ settings accordingly.

For FTP manual installation, [check here](http://codex.wordpress.org/Managing_Plugins#Manual_Plugin_Installation).

= Configure the plugin =
To configure the plugin, go to __WooCommerce > Settings__ from the left menu, click __Checkout__ tab. Click on __Flutterwave__.

* __Enable/Disable__ - check the box to enable Flutterwave WooCommerce.
* __Pay Button Public Key__ - Enter your public key which can be retrieved from the "Pay Buttons" page on your Flutterwave account dashboard.
* __Modal Title__ - (Optional) customize the title of the Pay Modal. Default is Flutterwave.
* Click __Save Changes__ to save your changes.

= Webhooks =
Handle Webhooks from Flutterwave with two new actions in WooCommerce.
* flw_webhook_after_action : This action is fired after a transaction is completed and returns the transaction details (json).
* flw_webhook_transaction_failure_action : This action is fired when a transaction fails and returns the transaction details (json).

= Best Practices =
1. When in doubt about a transaction, always check the Flutterwave Dashboard to confirm the status of a transaction.
2. Always ensure you keep your API keys securely and privately. Do not share with anyone
3. Ensure the secret hash on the API & Webhook tab matches the one on your Flutterwave Dashboard. The plugin generates a random one; use the Generate button to replace it at any time
4. Always ensure you install the most recent version of the Flutterwave Wordpress plugin

= Debugging Errors =

We understand that you may run into some errors while integrating our plugin. You can read more about our error messages [here](https://developer.flutterwave.com/docs/integration-guides/errors).

For `authorization` and `validation` error responses, double-check your API keys and request. If you get a `server` error, kindly engage the team for support.

= Support =

For additional assistance using this library, contact the developer experience (DX) team via [email](mailto:developers@flutterwavego.com) or on [slack](https://bit.ly/34Vkzcg). 

You can also follow us [@FlutterwaveEng](https://twitter.com/FlutterwaveEng) and let us know what you think ðŸ˜Š.

= Contribution guidelines =

We love to get your input. Read more about our community contribution guidelines [here](/CONTRIBUTING.md)

= License = 

By contributing to the Flutterwave WooCommerce, you agree that your contributions will be licensed under its [MIT license](/LICENSE).


== Frequently Asked Questions ==

= What Do I Need To Use The Plugin =

1. You need to open an account on [Flutterwave for Business](https://dashboard.flutterwave.com)

== Changelog ==
= 3.3.1 =
* Security: Authenticate the payment callback against the order key; it previously accepted any order ID from an unauthenticated request.
* Security: Confirm cancellations with Flutterwave before changing an order status.
* Security: Reject transaction references and webhooks replayed against an order.
* Security: Use a timing-safe comparison for the webhook signature.
* Security: Encrypt the stored card token at rest and keep customer PII out of the logs.
* Security: Orders with no recorded transaction reference only accept a reference issued in that order's name.
* Fixed: Callback parameters are no longer dropped on stores using plain permalinks.
* Fixed: Order metadata is written through the CRUD API so it works under HPOS.
* Fixed: A successful charge that completes after its order was cancelled now reopens the order instead of being rejected, so customers are not left charged with nothing fulfilled.
= 2.3.6 =
* Fixed: Dynamic Adjustment to Custom Permalink Set by Merchant.
* Fixed: Redirect Payment option return a Payment Mismatch Error.
= 2.3.5 =
* Added: Support for HPOS.
* Fixed: compatibility with WooCommerce 7.1 to 6.9.1
= 2.3.4 = 
* Fix: Webhook Handler Acknowledgement.
= 2.3.2 =
* Added: Support for WooCommerce Blocks.
* Updated: WooCommerce Checkout Process.
= 2.3.0 =
* Fix: Handled MobileMoney Payment Handler Error.
= 2.2.9 =
*  Fixed: PHP 8 support for v3 Webhook Handler.
= 2.2.8 =
*  Fixed: Woocommerce Subscription processing function error.
*  New Feat: Switched to WC-Logger class for logging.
= 2.2.7 =
* fix: on payment completion redirect to order reciept page (redirect Method)
* fix: PHP 8.0 compatibility ( optional method parameter )
= 2.2.0 =
* Use one base URL for live and test mode.

* Merchants can get their [test and live](https://developer.flutterwave.com/docs/api-keys) keys [here](https://rave.flutterwave.com/dashboard/settings/apis)

* Using test keys keeps you in test mode, to move to live mode add live keys.

* Support for Woocommerce recurring, this allows merchants to collect recurring payments in woocommerce.

= 2.1.0 =
* Support for Woocommerce recurring, this allows merchants to collect recurring payments in woocommerce.

= 2.0.0 =
* Support for new currencies (ZMW, UGX, RWF, TZS, SLL).

= 1.0.1 =
*   Add redirect style with admin toogle for redirect or popup payment style 
*   Custom gateway name 
*   Bug fixes for country 

= 1.0.0 =
*   First release

== Screenshots ==



== Other Notes ==
