<p align="center"><a href="https://slick-pay.com" target="_blank"><img src="https://azimutbscenter.com/logos/slick-pay.png" width="380" height="auto" alt="Slick-Pay Logo"></a></p>

## Description

[WordPress](https://wordpress.org) plugin for [Slick-Pay](https://slick-pay.com) API implementation, it provides a new payment gateway to the [WooCommerce](https://wordpress.org/plugins/woocommerce) payment methods.

**[ New ]**: New feature implemented, now you are able to make your client pay only a **deposit** during the checkout.

* [Prerequisites](#prerequisites)
* [Installation](#installation)
* [Configuration](#configuration)
* [How to use?](#how-to-use)
* [More help](#morehelp)

## Prerequisites

   - PHP 7.3 or above ;
   - [curl](https://secure.php.net/manual/en/book.curl.php) extension must be enabled ;
   - [WordPress](https://wordpress.org) 6.0 or above.
   - [WooCommerce](https://wordpress.org/plugins/woocommerce) 7.7 or above.

## Installation

1. First download this repository content from **Code** >> **Download ZIP**
2. Unzip and Copy the **slickpay-payement-gateway** folder inside the **wp-content**/**plugins** website directory.
3. Now, from your website dashboard, go to **Plugins** then click on **Install** under the plugin named as **Slick-Pay Payment Gateway**, and voilà !!

## Configuration

After the plugin being installed, it provides you a new payment method for your e-commerce website.

To enable this bright new payment method, you have to go from your website dashboard to **WooCommerce*** >> **Settings**, then click on the **Payments** tab to see the whole list of the payment methods available on your website.

From there, switch-on the method named **Slick-Pay Payment Gateway** and click **Save changes**.

Finally, don't forget to setup your API credentials within the plugin settings by clicking on the **Manage** button.

Each of options is explained below :

### Account type

Select your account type that you created from **Slick-Pay.com**.

### User bank account

This field will be used only in the case of Account type User, to specify wich account will receive the transfer amount.

### API environment

From this option, you will be able do tests or activate the production environment.

### API module

Choose the API service related to you Account type.

### Public Key

Enter your public key available from your **Slick-Pay.com** account.

## How to use?

Nothing else to do, now from your front-office website, your customers will be able to select the **Slick-Pay Payment Gateway** as a payment method to complete their orders.

## More help
   * [Slick-Pay website](https://slick-pay.com)
   * [Reporting Issues / Feature Requests](https://github.com/Slick-Pay-Algeria/slickpay-wp/issues)