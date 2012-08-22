<?php
/*
Plugin Name: SAML SimpleSAMLphp - integrating SimpleSAMLphp
Plugin URI: http://www.surfnet.nl
Description: Use a configured SimpleSAMLphp instance to take care of authentication for Joomla
Author: M. Dobrinic for SURFnet
Version: 1.0
Author URI: http://www.surfnet.nl
 */
defined('_JEXEC') or die('Restricted access');

class plgAuthenticationSAMLSSP extends JPlugin {
	
	/**
	 * Persist the provided user as a local user in the userstore (database), which
	 * leads to creating a new account when it did not exist yet, or update an existing
	 * account with the provided profile when it already existed.
	 * 
	 * @param	JAuthenticationResponse instance with the authenticated user attributes 
	 */
	function _updateUserDB($response)
	{
		// Establish userid of authenticated user, or 0 when user does not exist
		$userid = JUserHelper::getUserId($response->username);
		
		if ($userid) {
			$user = JFactory::getUser($userid);
			
			// Detect changes, and update if required
			$changed = true;
			if ($changed) {
				$user->bind($response->getProperties());
				$user->save(true);	// true means: updateOnly
			}
		} else {
			// Store as new user
			$user = new JUser();
			
			// explicitly clear password, so it gets initialized randomly by Joomla
			$response->password = null;
			
			$user->bind($response->getProperties());
			$user->save();
		}
	}
	
	
	/**
	 * Retrieve the first attribute value from SimpleSAMLphp multivalue attribute
	 * Returns $default value if key was not found
	 * @param String $key to find 
	 * @param Array $attributes
	 * @param unknown $default Value to return when key was not found
	 */
	private function _getSSPAttributeFirstValue($key, $attributes, $default = null) {
		if (! array_key_exists($key, $attributes)) return $default;
		return $attributes[$key][0];
	}
	
	
	/**
	 * Map the values from $samlresponse-attributes to JAuthenticationResonse
	 * according to the configured attribute mapping
	 * 
	 * All config-parameters with a name 'map_[key]' are considered to be
	 * parameter mappings
	 * 
	 * Note: if there is no mapping for password, the password is initialized to 
	 * a random password by Joomla user registration process
	 * 
	 * @param JAuthenticationResponse $response
	 * @param Array $samlresponse contains the attributes from SAML response
	 */
	private function _mapToResponse(&$response, $samlresponse) {
		// Get all configured parameters into an array
		$configured = $this->params->toArray();
		
		foreach ($configured as $k => $v) {
			// Consider configuration parameter, when its name starts with 'map_'
			if (strpos($k, 'map_')===0) {
				$mapk = substr($k, 4); 
				if ($v != '') {
					$response->$mapk = $this->_getSSPAttributeFirstValue($v, $samlresponse);
				}
			}
		}
		// Done.
	}
	
	
	/**
	 * This method should handle any authentication and report back to the subject
	 * Uses SimpleSAMLphp for supporting SAML;
	 * 
	 * After succesfull authentication, the user exists in the datastore, based on the
	 * attributes that were provided during authentication
	 *
	 * @access	public
	 * @param	array	Array with user credentials, this is ignored
	 * @param	array	Options are ignored as well
	 * @param	object	Authentication response object
	 * @return	boolean
	 * @since 1.5
	 */
	function onUserAuthenticate($credentials, $options, &$response)
	{
		// Include the externally configured SimpleSAMLphp instance 
		require_once($this->params->get('path').'/www/_include.php');
		
		// Instantiate our SP configuration
		$as = new SimpleSAML_Auth_Simple($this->params->get('spdef'));
		
		// Investigate whether to initiate external login, or process response
		if (!$as->isAuthenticated()) {
			
			//$response->status = JAuthentication::STATUS_FAILURE;
			//$response->error_message = JText::_('About to authenticate');
			$as->requireAuth();
			exit();	// halt here, as SSP should have taken over
		}
		

		// Joomla allows the user to be defined in the JAuthenticationResponse instance
		// so provisioning can take place based on the authenticated user
		$this->_mapToResponse($response, $as->getAttributes());
		
		// Do provisioning here, to assure the authenticated user exists in the
		// Joomla User Database
		$this->_updateUserDB($response);
		
		// Return with positive response
		$response->status = JAuthentication::STATUS_SUCCESS;
		$response->error_message = '';
	}
}
