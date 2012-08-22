<?php
// No direct access to this file
defined('_JEXEC') or die('Restricted access');
 
/**
 * Script file of com_sso component
 * 
 * goal: remove the admin menu item programatically
 * see:
 * http://forum.joomla.org/viewtopic.php?f=642&t=714195
 */
class com_ssoInstallerScript
{
   /**
    * method to run after an install/update/uninstall method
    *
    * @return void
    */
   function postflight($type, $parent)
   {
      $componentName = $parent->get('manifest')->name;

      $extIds = $this->getExtensionIds($componentName);

      if(count($extIds)) {
         foreach($extIds as $id) {
            if(!$this->removeAdminMenus($id)) {
               echo "notice: failed to remove the component menu link<br>";
            }
         }
      }
   }
   
   /**
   * Retrieves the #__extensions IDs of a component given the component name (eg "com_somecomponent")
   *
   * @param   string   $component The component's name
   * @return   array   An array of component IDs
   */
   protected function getExtensionIds($component) {
      $db = JFactory::getDbo();
      $query = $db->getQuery(true);
      $query->select('extension_id');
      $query->from('#__extensions');
      $cleanComponent = filter_var($component, FILTER_SANITIZE_MAGIC_QUOTES);
      $cleanComponent = strtolower(preg_replace('/\s/', '', $cleanComponent));
      $query->where($query->qn('name') . ' = ' . $query->quote($cleanComponent));
      $db->setQuery($query);
      $ids = $db->loadResultArray();
      return $ids;
   }
   
   /**
   * Removes the admin menu item for a given component
   *
   * This method was pilfered from JInstallerComponent::_removeAdminMenus()
   *
   * @param   int      $id The component's #__extensions id
   * @return   bool   true on success, false on failure
   */
   protected function removeAdminMenus(&$id)
   {
      // Initialise Variables
      $db = JFactory::getDbo();
      $table = JTable::getInstance('menu');
      // Get the ids of the menu items
      $query = $db->getQuery(true);
      $query->select('id');
      $query->from('#__menu');
      $query->where($query->qn('client_id') . ' = 1');
      $query->where($query->qn('component_id') . ' = ' . (int) $id);

      $db->setQuery($query);

      $ids = $db->loadColumn();

      // Check for error
      if ($error = $db->getErrorMsg())
      {
         return false;
      }
      elseif (!empty($ids))
      {
         // Iterate the items to delete each one.
         foreach ($ids as $menuid)
         {
            if (!$table->delete((int) $menuid))
            {
               return false;
            }
         }
         // Rebuild the whole tree
         $table->rebuild();
      }
      return true;
   }
}