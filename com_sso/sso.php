<?php
/**
 * @package		Single Sign On for Joomla
 * @subpackage	com_sso
 * @license		GNU General Public License version 3 or later; see LICENSE.txt
 */

// no direct access
defined('_JEXEC') or die;

$app = JFactory::getApplication();

// Initialize credentials
$credentials = array();
$credentials['username'] = null;
$credentials['password'] = null;

// Perform configured login
if (true === $app->login($credentials)) {
	// Success, take user to homepage
	$url = JRoute::_(JURI::base());
	$app->redirect($url);
}

// Nothing more to do; control is delegated to login
