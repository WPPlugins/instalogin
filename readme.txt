=== Plugin Name ===
Contributors: glekli
Tags: admin, authentication, login
Requires at least: 3.0
Tested up to: 4.1
Stable tag: 1.1
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html

Helper plugin for the InstaLogin app.

== Description ==

This is a helper plugin for the [InstaLogin app](http://glekli.github.io/InstaLogin/).
InstaLogin allows you to log in to your websites as any user without having to know their password.

= How it works =

When the user wishes the authenticate using the InstaLogin app, the app requests a one-time login link for the user from the site.
Given the request can be verified using the secret key, the plugin issues the one-time login link, which is then opened in the user's browser.

= Security =

Because InstaLogin enables a new kind of authentication method, it inherently introduces a potential new attack vector by its nature.
However, it was written with security in mind, and having this plugin installed is not expected to pose any considerable risk.
InstaLogin does not transmit any sensitive information in plain text over the network, not even if the site does not support https.

= Requirements =

This module relies on the Mcrypt library, and therefore it is required to have the Mcrypt PHP extension enabled.

== Installation ==

* Upload to the plugins directory.
* Activate the plugin through the 'Plugins' menu in WordPress.
* Enable custom Permalink URLs (set to anything other than 'Default'). This is required for InstaLogin to work.
* Go to Settings/InstaLogin, check the Enabled option, and save the change. This will enable the InstaLogin app to authenticate to the site as any user, given it has the secret key. See the [InstaLogin app](http://glekli.github.io/InstaLogin/) page for instructions on how to configure the app.
* A random secret key is generated automatically when the plugin is activated. You have the option to generate a new key using the Regenerate Secret Key button, should the need arise. This will revoke the current key, and prevent the InstaLogin app from doing any further authentication until the new key is entered.

== Changelog ==

= 1.0 =
* Initial release.

= 1.1 =
* Refined authentication token handling.