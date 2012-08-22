<?php
// defined('JPATH_BASE') or die;
defined('_JEXEC') or die('Restricted access');


jimport('joomla.utilities.utility');

/**
 * Plugin class for logout redirect handling.
 *
 * @package		Joomla.Plugin
 * @subpackage	System.logout
 */
class plgUserConext_Groups extends JPlugin
{
	
	/**
	 * Session related constants
	 */
	const SESSION_STATE = 'session-state';
	const SESSION_GROUP_CACHE = 'group-cache';
	const CONEXT_SESSION_NAMESPACE = 'conext_groups';
	
	/**
	 * Array of cached groups of the user; array is associative
	 * with key=>value, where key is the username and value is
	 * an array with the (external) group identifiers
	 *
	 * @var    array
	 * @since  1.0
	 */
	protected static $groups = array();
	

	/**
	 * Re-use samlssp context, by setting environment
	 * to point to SimpleSAMLphp when available
	 */
	public static function setSSPContext() {
		// Link to local SimpleSAMLphp instance for OAuth support
		// This is an optional/recommended dependency on the ssplogin plugin:
		$oSSPConext = JPluginHelper::getPlugin('authentication', 'samlssp');
		if (! empty($oSSPConext)) {
			$o = new JRegistry($oSSPConext->params);
			$sspPath = $o->get('path');
			if ($sspPath) {
				define('SIMPLESAML_PATH', $sspPath);
			}
		}		
	}
	
	
	/**
	 * Helper function to establish initialized GroupRelations
	 * instance
	 */
	protected function _getGroupRel() {
		$grouprel_dir = dirname(__FILE__) . '/GroupRel/_include.php';
		require_once($grouprel_dir);

		// Set $grouprel_config in global scope
		global $grouprel_config;
		$grouprel_config = $this->getGroupRelConfig();
		
		$oGroupRel = IGroupRelations::create($grouprel_config['impl']);
		
		return $oGroupRel;
	}
	
	
	/**
	 * This method should handle any authorization applications
	 * Triggered from JAuthentication::authorise()
	 * 
	 * Performs check whether an OAuth access_token is available
	 * for the authenticated user
	 *
	 * @param	array	$user		Holds the user data
	 * @param	array	$options	Array holding options (remember, autoregister, group)
	 *
	 * @return	boolean	True on success
	 * @since	1.5
	 */
	public function onUserAuthorisation($user, $options = array())
	{
		if (!defined('SIMPLESAML_PATH')) self::setSSPContext();
		
		// Establish translations for localized error reporting
		$this->loadLanguage();
		
		// 1: Prepare Client to retrieve external groups
		$oGroupRel = $this->_getGroupRel();
		if ($oGroupRel == null) {
			JError::raiseWarning('xxx', JText::_('CONEXT_GROUPS_NOT_AVAILABLE') . ":" . $e->getMessage());
			return false;	// could not establish group relations
		}
		
		// Initialize session
		$session = JFactory::getSession();
		
		try {
			if ($options['action'] == 'core.login.admin') {
				// No external group support in admin, as
				// auto-sso-login is not possible
				return true;
			}
				
			// Store userinfo-state in session
			$session->set(self::SESSION_STATE, serialize($user), self::CONEXT_SESSION_NAMESPACE);
				
			// Prepare OAuth context; will go out and establish new access_token when
			// required; this means that session scope must be resumable
			$all_groups = $oGroupRel->prepareClient($user->username);
				
			// Clean up session
			$session->clear(self::SESSION_STATE, self::CONEXT_SESSION_NAMESPACE);
		} catch(Exception $e) {
			// Something went wrong with retrieving the groups for the user
			// Log this as warning, but let the user continue, as there
			// is no reason why the existing user authorization should be
			// ignored
			JError::raiseWarning('xxx', JText::_('CONEXT_GROUPS_NOT_AVAILABLE') . ":1-1:" . $e->getMessage());
				
			// Clean up session
			$session->clear(self::SESSION_STATE, self::CONEXT_SESSION_NAMESPACE);
				
			return true;
		}
		
		return true;
	}
	
	
	/**
	 * Retrieve groups from external group provider
	 * @param unknown_type $username UserID (external) to retrieve groups for
	 * @return array with group-identifiers on success, null if error
	 */
	public function getExternalGroups($username)
	{
		if (!defined('SIMPLESAML_PATH')) self::setSSPContext();
		
		// Establish translations for localized error reporting
		$this->loadLanguage();
		
		// debug:
		// $username = '5be86607136b6d8a889d57dee3fd04337abe084e';
		
		// 1: Prepare Client to retrieve external groups
		$oGroupRel = $this->_getGroupRel();
		if ($oGroupRel == null) {
			JError::raiseWarning('xxx', JText::_('CONEXT_GROUPS_NOT_AVAILABLE') . ":2-1:" . $e->getMessage());
			return null;	// could not establish group relations
		}
		
		// Initialize session
		$session = JFactory::getSession();
		
		try {
			// Go out and get groups for the user
		//	$all_groups = $oGroupRel->fetch(array('userId' => $username));
			$all_groups = $oGroupRel->fetch(array('userId' => '@me'));
		} catch(Exception $e) {
			// Something went wrong with retrieving the groups for the user
			// Log this as warning, but let the user continue, as there
			// is no reason why the existing user authorization should be
			// ignored
			JError::raiseWarning('xxx', JText::_('CONEXT_GROUPS_NOT_AVAILABLE') . ":2-2:" . $e->getMessage());
			
			return null;
		}
		
		// Add the groups to local cache instance now:
		$g = array();
		foreach ($all_groups as $o) {
	  		if ($o instanceof Group) {
	  			array_push($g, $o->getIdentifier());
	  		}
	  	}
	  	self::$groups[$username] = $g;
	  	
	  	return $g;
	}	
	
	
	/**
	 * Retrieve groups from session cache
	 * Enter description here ...
	 * @param unknown_type $username
	 */
	public function getSessionGroups($username) 
	{
		$session = JFactory::getSession();
		$o = $session->get(
				plgUserConext_Groups::SESSION_GROUP_CACHE, 
				null,
				plgUserConext_Groups::CONEXT_SESSION_NAMESPACE);

		if ($o == null) return null;
		 
		$t = $o[$username];
		if ($t == null) return null;
		
		return unserialize( $t );
	}
	
	
	/**
	 * Store groups in session under user-id
	 * @param unknown_type $username Username to store groups for
	 * @param unknown_type $groups Groups to store
	 */
	public function setSessionGroups($username, $groups)
	{
		$session = JFactory::getSession();
		$o = $session->get(
				plgUserConext_Groups::SESSION_GROUP_CACHE, 
				null,
				plgUserConext_Groups::CONEXT_SESSION_NAMESPACE);

		if (! is_array($o)) {
			$o = array();
		}
		
		$o[$username] = serialize($groups);
		$session->set(plgUserConext_Groups::SESSION_GROUP_CACHE,
			$o,
			plgUserConext_Groups::CONEXT_SESSION_NAMESPACE);
	}
	
	
	/**
	 * Returns the UserID of the logged in user, or 0
	 * when no user was logged in
	 */
	protected function loggedInUserId() {
		$session = JFactory::getSession();
		$user = $session->get('user', null);

		// Is there a user instance in the session?
		if ($user == null) return 0;
		
		// Is the user instance a guest user?
		if ($user->get('guest') != 0) return 0;
		
		return $user->get('id');
	}
	
	
	/**
	 * Establish the user's groups, first attempt local cache,
	 * then session cache, and ultimately go out and fetch
	 * fresh set of group memberships
	 * @param unknown_type $username userId (external) to retrieve
	 * groups for
	 * $return Always returns an array with group-identifiers (strings); 
	 * if no group-names are established, the array is empty (zero elements)
	 */
	public function getGroups($username) {
		$uid = $this->loggedInUserId();
		
		// Is the user already logged in (as in: is prepareClient() called?)
		if ($uid != $username) {
			return array();
		}
		
		// Check local cache:
		$o = self::$groups[$username];
		if ($o) return $o;
		
		// Check session cache:
		$o = $this->getSessionGroups($username);
		if ($o) {
			self::$groups[$username] = $o;
			return $o;
		}
		
		$o = $this->getExternalGroups($username);
		if ($o) {
			$this->setSessionGroups($username, $o);
			self::$groups[$username] = $o;
			return $o;
		}
		
		// Return empty group list
		return array();
	}
	
	
	/**
	 * Retrieves the list of group-names for the user identified by  
	 * the provided username.
	 * Always returns an array; if no group-names are established, the
	 * array is empty (zero elements)
	 * @param string $username
	 */
	public static function getGroupsForUser($username) {
		if (isset(self::$groups[$username])) {
			return self::$groups[$username]; 
		}
		return array();	// empty list, but always an array
	}
	
	
	
	/**
	 * Establish the Joomla User Groups that the user is member of, based
	 * on the external group provider.
	 * 
	 * Groups delivered by the Group Provider must match the title-attribute
	 * of the Joomla User Groups.
	 * 
	 * The returned array of group-id's is raw, as in, it is unsorted and may
	 * contain doubles or NULL-values
	 * 
	 * @param int $userId the id of the authenticated Joomla user
	 * @param boolean $recursive when set to true, also resolve implicit group
	 *   memberships
	 * @return an array with group id's that the user is member of
	 */
	function onGetGroupsByUser($userId, $recursive)
	{
		// Do not work for guest users:
		if ($userId == 0) return;
		 
		// Establish the groupnames that the user is member of
		$groupnames = $this->getGroups($userId);
		
		// When no groups are contained, we are done
		if (empty($groupnames)) return array();
	
		// now figure out the group id's that are linked to the
		// provided group-names 
		// also include implicity contained groups
		$db = JFactory::getDbo();
		
		// prepare text input for sql query
		$sql_groupnames = array();
		foreach ($groupnames as $groupname) $sql_groupnames[] = "'" . $db->escape($groupname) . "'";
		
		$query = $db->getQuery(true);
		$query->select($recursive ? 'b.id' : 'a.id');
		
		$query->from('#__usergroups AS a');
		$query->where('a.title in (' . implode(",", $sql_groupnames) .')');
		
		// If we want the rules cascading up to the global asset node we need a self-join.
		if ($recursive) {
			$query->leftJoin('#__usergroups AS b ON b.lft <= a.lft AND b.rgt >= a.rgt');
		}

		// Execute the query and load the rules from the result.
		$db->setQuery($query);
		$result = $db->loadColumn();
		
		return $result;
	}

	
	/**
	 * Helper to establish whether a provided string
	 * is a http(s) URL
	 * @param (string) $url provided URL when true, null if provided
	 *   url was not a valid http(s) url 
	 */
	private static function _urlOrNull($url) {
		$s = substr($url, 0, 4);
		if (strcasecmp($s, 'http')==0) {
			return $url;
		}
		return null;
	}
	
	
	/**
	 * Create the config structure here
	 */
	function getGroupRelConfig() {
		// Build configuration structure:
		global $grouprel_config;
		
		$grouprel_config = array(
				/* cache_ttl: defines how many seconds a fetched instance is cached */
				'cache_ttl' => 2,

				/* userIdAttribute: the userId-attribute to use as (external) userId in openSocial calls */
				'userIdAttribute' => 'NameID',	// set by NameIDAttribute-module in SSP	-- OpenSocial UserID, Grouper UserID
		
				/* impl: defines a configuration for the actual fetching code */
		    	'impl' => array(
					/* class: Worker class instance of IGroupRelations, used to retrieve Group relations */
					'class' => 'OpenSocial_GroupRelationsImpl',
					// 'class' => 'Development_DevGroupRelationsImp',
				
					/* configuration for the worker class; see documentation below */
					'consumerkey' => $this->params->get('consumer_key'),
					'consumersecret' => $this->params->get('consumer_secret'),
					'provider' => array(
						'providerName' => 'conext',
						'class' => 'osapiGroupRelProvider',
						'requestTokenUrl' => $this->params->get('request_url'),
						'authorizeUrl' => $this->params->get('authorize_url'),
						'accessTokenUrl' => $this->params->get('access_url'), 
						'restEndpoint' => self::_urlOrNull($this->params->get('rest_url')),
						'rpcEndpoint' => self::_urlOrNull($this->params->get('rpc_url')),
						),
					'strictMode' => FALSE,
		    	),
		    );

		return $grouprel_config;
	}
}
