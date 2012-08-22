== Joomla SURFconext integration ==

This document describes how to establish SURFconext integration with Joomla. The following features are included:
- Integrating SimpleSAML to use SURFfederatie as Identity Provider
- Integrating osapi-php to use SURFconext as Group Provider

The complete package consists of 4 plugins:
1) com_sso, helper to make use of Single Sign On flow
2) samlssp, SAML-SP support, making use of SimpleSAMLphp library
3) conext_groups, applies a user’s external OpenSocial groups to Joomla groups
4) conext_groups_helper, required for OAuth token setup flow


== Configuring SSO using SimpleSAMLphp ==

*** com_sso
The com_sso component takes care of triggering the login event without user interaction. When used in combination with a (SAML) third party authenticate plugin, it results in Joomla trying to resume a Single Sign On session with SURFfederatie, or allowing SURFfederatie to setup a new Single Sign On session for the user.

1) Enter Joomla administration
2) Select Extension Manager from the main menu
3) Select the ‘Install’ tab, and upload the com_sso.tgz package file

Now you need to create the Login menu-item. You can do this by adding a new menu-item, or by changing the current Login menu-item to perform the authenticate action. Either way, it involves editing the Menu Item’s properties. For ‘Menu Item Type’, select the type ‘Direct authentication’ from the ‘com_sso’ section.
This will change the ‘Link’ property to ‘index.php?option=com_sso’ (or something like that).
Now ‘Save and Close’ the page.

Note: com_sso without a SSO authentication plugin is pointless, as the plugin is only capable of taking a user to an external authentication source to login!

*** samlssp
The samlssp plugin adds SimpleSAMLphp support to Joomla. It does this by referencing a configured SimpleSAMLphp instance from Joomla, and integrating the login functionality. To make use of the SimpleSAMLphp plugin, it is required to first configure SimpleSAMLphp as a SP before using it with Joomla.


-- Configure SimpleSAMLphp --
Install SimpleSAMLphp by downloading the latest package from www.simplesamlphp.org and follow the instructions to install it as SP.

* config.php
For setting up config.php, ensure to:
  - update ‘baseurlpath’ to the Alias that you configured in Apache config (i.e. ‘joomla/simplesaml’)
  - update ‘auth.adminpassword’
  - update ‘secretsalt’
  - update ‘technicalcontact_name’ and ‘technicalcontact_email’
  - update authentication processing filter for SP, authproc.sp, to include:
	20  => array(
		'class' => 'saml:NameIDAttribute',
		'attribute' => 'uid',
		'format' => '%V',
	),
  - when you get ‘State Information Lost’ errors from SimpleSAML, after you have authenticated at the IDP and are returning to Joomla, update ‘store.type’ to ‘memcache’, and make sure to install memcached. This solves conflicting session management between SimpleSAMLphp and the Joomla application

* saml20-remote-idp.php
For setting up the IDP, add the metadata of the SURFfederatie IDP to metadata/saml20-remote-idp.php


* authsources.php
The authsources.php file defines the profile that is used to estabish the IDP to use for authentication. It is suggested to always enforce SURFfederatie IDP. To configure, setup authsources.php with:

For setting up authsources.php, update the ‘default-sp’ configuration to
       'default-sp' => array(
                'saml:SP',
                'privatekey' => 'spjoomla.pem',
                'certificate' =>  'spjoomla.crt',
                'entityID' => NULL,
                'idp' => 'https://engine.dev.surfconext.nl/authentication/idp/metadata',
                'discoURL' => NULL,
        ),

Note: please also create your own certificates. It’s easy when you have openssl installed:
In the cert/-directory of simplesamlphp, enter:
$ openssl req -newkey rsa:2048 -new -x509 -days 3652 -nodes -out spjoomla.crt -keyout spjoomla.pem
.. and answer some questions regarding the certificate. The resulting certificate is self-signed.


-- Configure samlssp --
Install and configure the component in Joomla, by opening the Extension Manager in Joomla’s administration interface, select the samlssp.zip-file, and upload and install it.

Next, go to the ‘Plug-in Manager’, and see that there is a new plugin in the list:
Authentication - SAML (SimpleSAMLphp)
It is not yet enabled. Before enabling it, you need to configure it.
- Ensure that the ‘Path to SimpleSAMLphp’ is set correctly; the path should not end with a ‘/‘ and point to the directory that contains the directories ‘attributemap’, ‘cert’, etc.
- Ensure the SP-profile is the profile in authsources that is configured to take the user to SURFfederatie
- The mappings can finetune how the attributes, as returned from the IDP, are applied to Joomla user settings. For SURFfederatie, these are
	Joomla.Username is configured from SURFfederatie (uid)
	Joomla.Email is configured from SURFfederatie (urn:mace:dir:attribute-def:mail)
	Joomla.Fullname is configured from SURFfederatie (urn:mace:dir:attribute-def:fullname)
	Joomla.Language is configured from SURFfederatie (urn:mace:dir:attribute-def:preferredLanguage)

As of the publication of the samlssp plugin, there are no other sensible attribute mappings that can be made for SURFfederatie.

The next thing to do, is to enable the plugin.
Note: Leave Joomla authentication enabled, as this remains the only way to login to the administrative area!



Troubleshooting
Symptom: When trying to login, authentication succeeds, and you are returned to Joomla, a white screen appears and an HTTP Status 500 is returned.
Analysis: See logfiles, especially /var/log/syslog and /var/log/apache2/error.log
Is memcache available for PHP?
If not, install it, with:
$ sudo apt-get install php5-memcache memcached
..and restart apache:
$ sudo apachectl restart



Note: the samlssp-plugin was developed and tested with SimpleSAMLphp version 1.9.0.







* Configure surfconext_groups
To install the SURFconext Groups plugin to take care of authorisation using SURFteams, there are some patches that need to be made to the Joomla core. 

1, Bugfix: authentication.php
Open the file libraries/joomla/user/authentication.php

On line 347, the function authorise() is defined.

In this function, replace the lines:
		JPluginHelper::getPlugin('user');
		JPluginHelper::getPlugin('authentication');
with
		JPluginHelper::importPlugin('user');
		JPluginHelper::importPlugin('authentication');



Patch: access.php
* Open the file libraries/joomla/access/access/php

* Insert the static member variable before the first function declaration, around line #63:
...
	/**
	 * Semaphore to guard against nested calls of getUsersByGroup
	 * When set to true, JPluginHelper::importPlugin() is not called again
	 * @var boolean
	 */
	protected static $loaded = false;
...

* On line 277, the function getGroupsByUser() is defined

In this function, a query is executed (around line 317). Insert the event trigger code after this query execution code and before the result processing, so it looks like this:

	....
	// Execute the query and load the rules from the result.
	$db->setQuery($query);
	$result = $db->loadColumn();

				// Add trigger to allow for external group assignment
				// nesting prevention:
				$l = self::$loaded; if (!$l) {
					self::$loaded=true;
					JPluginHelper::importPlugin('user');
				}
				$dispatcher = JDispatcher::getInstance(); 
				$r = $dispatcher->trigger('onGetGroupsByUser', array($userId, $recursive));

				if (! empty($r)) {
					// Collect result from all fired plugins
					foreach ($r as $plugin_r) {
						$result = array_merge($result, $plugin_r);
					}
				}
				// Continue execution

	// Clean up any NULL or duplicate values, just in case
	JArrayHelper::toInteger($result);
	....




Now install and configure the components, by opening the Extension Manager in Joomla’s administration interface, select the conext_groups.tgz-file, and upload and install it. Also, upload and install conext_group_helper.tgz in the same way.

Next, go to the ‘Plug-in Manager’, and see that there are two new plugins in the list:
1) Authentication - SURFconext Group Relations helper
Enable this plugin; no further configuration required.

2) User - SURFconext Group Relations
It is not yet enabled. Before enabling it, you need to configure it with the parameters that were provided by the Group Provider.

- Enter the OAuth consumer key and secret
- Enter the OAuth service URLs; note that the RPC-endpoint might no longer be supported by SURFconext

Now enable the plugin.

Ensure that the samlssp plugin provides an identity that is recognized by the group provider!

