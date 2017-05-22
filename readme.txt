=== Gravity Forms heidelpay ===
Contributors: webaware
Plugin Name: Gravity Forms heidelpay
Plugin URI: https://shop.webaware.com.au/downloads/gravity-forms-heidelpay/
Author: WebAware
Author URI: https://shop.webaware.com.au/
Donate link: https://shop.webaware.com.au/donations/?donation_for=Gravity+Forms+heidelpay
Tags: gravityforms, gravity forms, heidelpay, credit cards, donation, donations, payment
Requires at least: 4.2
Tested up to: 4.7
Stable tag: 1.1.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Easily create online payment forms with Gravity Forms and heidelpay.

== Description ==

Easily create online payment forms with Gravity Forms and heidelpay.

Gravity Forms heidelpay integrates the [heidelpay credit card payment gateway](http://www.heidelpay.com/payment/) with [Gravity Forms](https://webaware.com.au/get-gravity-forms) advanced form builder.

* build online donation forms
* build online booking forms
* build simple Buy Now forms

> NB: this plugin extends Gravity Forms; you still need to install and activate [Gravity Forms](https://webaware.com.au/get-gravity-forms)!

= Sponsorships =

* creation of this plugin was generously sponsored by Tom Gysel.

Thanks for sponsoring new features on Gravity Forms heidelpay!

= Translations =

If you'd like to help out by translating this plugin, please [sign up for an account and dig in](https://translate.wordpress.org/projects/wp-plugins/gf-heidelpay).

= Requirements =

* you need to install the [Gravity Forms](https://webaware.com.au/get-gravity-forms) plugin
* you need an SSL/TLS certificate for your hosting account
* you need an account with heidelpay

= Contributions =

* [Fork me on GitHub](https://github.com/webaware/gravityforms-heidelpay)

== Frequently Asked Questions ==

= What is heidelpay? =

Heidelpay is a hosted credit card payment gateway, accepting payments in over 160 countries.

= Will this plugin work without installing Gravity Forms? =

No. This plugin adds a heidelpay payment gateway to Gravity Forms so that you can add online payments to your forms. You must purchase and install a copy of the [Gravity Forms](https://webaware.com.au/get-gravity-forms) plugin too.

= What Gravity Forms license do I need? =

Any Gravity Forms license will do. You can use this plugin with the Personal, Business or Developer licenses.

= How do I build a form with credit card payments? =

* add one or more Product fields or a Total field to your form. The plugin will automatically detect the values assigned to these pricing fields
* add customer name and contact information fields to your form. These fields can be mapped when creating a heidelpay feed and their values stored against each transaction in your heidelpay console
* add a heidelpay feed, mapping your form fields to heidelpay transaction fields

= What is the difference between Live and Test mode? =

Test mode enables you to run transactions on the heidelpay test environment. It allows you to run tests without using real credit cards or bank accounts. You must use special test credit card details when using the test environment.

NB: the test environment is visible to anyone who wants to log in and perform testing. Never use real personal details or credit card details in the test environment, because they will be seen by other testers!

=  Where can I find dummy credit card details for testing purposes? =

You can find test credentials on the [heidelpay test environment developer page](https://dev.heidelpay.de/testumgebung/).

=  Where will the customer be directed after they complete their transaction? =

Standard Gravity Forms submission logic applies. The customer will either be shown your chosen confirmation message, directed to a nominated page on your website or sent to a custom URL.

= Where do I find the heidelpay transaction number? =

Successful transaction details including the heidelpay transaction number and return code are shown in the Info box when you view the details of a form entry in the WordPress admin.

= How do I add a confirmed payment amount and transaction number to my Gravity Forms admin or customer email? =

Browse to your Gravity Form, select [Notifications](https://www.gravityhelp.com/documentation/article/configuring-notifications-in-gravity-forms/) and use the Insert Merge Tag dropdown (Payment Amount, Transaction ID, and Return Code will appear under Custom at the very bottom of the dropdown list).

NB: these custom merge tags will only work for notifications triggered by Payment Completed and Payment Failed events.

= How do I change my currency type? =

Use your Gravity Forms Settings page to select the currency type to pass to heidelpay. You can override this setting for individual forms by editing the form's heidelpay feed settings and ticking the checkbox to customize the connection.

Please ensure your currency type is supported by heidelpay.

= Debit or Authorize? =

Debit charges the customer immediately. This is the default payment method, and is the method most websites will use for payments.

Authorize checks to see that the transaction would be approved, but does not process it. Once the transaction has been authorized, you can complete it manually in your heidelpay console. You cannot complete Authorize transactions from WordPress/Gravity Forms.

= Can I do recurring payments? =

Not yet.

= I get an SSL error when my form attempts to connect with heidelpay =

This is a common problem in local testing environments. Please [read this post](https://snippets.webaware.com.au/howto/stop-turning-off-curlopt_ssl_verifypeer-and-fix-your-php-config/) for more information.

== Screenshots ==

1. Settings screen
2. A sample donation form
3. A list of heidelpay feeds for a form
4. A heidelpay feed (mapping form fields to heidelpay)
5. The sample donation form as it appears on a page
6. A successful entry in Gravity Forms admin

== Changelog ==

### 1.1.0, 2017-05-16

* released as a free plugin on wordpress.org
