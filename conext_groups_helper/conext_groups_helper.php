<?php
// defined('JPATH_BASE') or die;
defined('_JEXEC') or die('Restricted access');


jimport('joomla.utilities.utility');

/**
 * Plugin for helping the authentication procedure
 * during OAuth Token Setup process
 */
class plgAuthenticationConext_Groups_Helper extends JPlugin
{
	
	/**
	 * This method handles authentication during OAuth Token setup
	 * On return from authorizing the Request Token, the Joomla authentication
	 * procedure will be resumed, so authorization can be finalized with
	 * a valid AccessToken, and groups can be retrieved etc etc. 
	 * 
	 * @access	public
	 * @param	array	Array with user credentials, this is ignored
	 * @param	array	Options are ignored as well
	 * @param	object	Authentication response object
	 * @return	boolean
	 * @since 1.5
	 */
	function onUserAuthenticate($credentials, $options, &$response) {
		// Require the conext_groups plugin to be available
		$oCGPlugin = JPluginHelper::getPlugin('user', 'conext_groups');
		
		print_r($oCGPlugin); exit();
		
		if ($oCGPlugin == null) {
			JError::raiseWarning('xxx', JText::_('CONEXT_GROUPS_PLUGIN_NOT_AVAILABLE'));
			return false;
		}
		
		// Is there a session to resume?
		$session = JFactory::getSession();
		$GCAuthResponse = $session->get(self::SESSION_STATE, null, self::CONEXT_SESSION_NAMESPACE);

		print_r('Debug.'); exit();
		
		if ($GCAuthResponse != null) {
			// Clean up, as we're consuming the result:
			$session->clear(self::SESSION_STATE, self::CONEXT_SESSION_NAMESPACE);
			
			// Inspect session state
			if ($GCAuthResponse->status == JAuthentication::STATUS_SUCCESS) {
				// The session stored a succesfull authentication result;
				// resume this session:
				$response = $GCAuthResponse;
				return true; 
			}
		} 
		
		// No authentication was established.
		return false;
		
	}
}
