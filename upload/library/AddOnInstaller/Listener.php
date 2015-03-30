<?php
class AddOnInstaller_Listener
{
	public static function containerAdminParams(array &$params, XenForo_Dependencies_Abstract $dependencies)
	{
		if (isset($params['adminNavigation']['sideLinks']['addOns']['installAddOn']))
		{
			unset($params['adminNavigation']['sideLinks']['addOns']['installAddOn']);	
		}
	}
	
	public static function templatePostRender($templateName, &$content, array &$containerData, XenForo_Template_Abstract $template)
	{
		if ($templateName == 'addon_list')
		{
			$containerData['topctrl'] = str_replace('add-ons/install', 'add-ons/install-upgrade', $containerData['topctrl']);
		}
		
		if ($templateName == 'PAGE_CONTAINER' && $template instanceof XenForo_Template_Admin && self::$_addOnModelEnabled)
		{
			$addOnModel = XenForo_Model::create('XenForo_Model_AddOn');

			$updates = $addOnModel->getAllUpdateChecks();
			
			$updateCount = 0;
			foreach ($updates AS $update)
			{
				if ($update['check_updates'] && !empty($update['latest_version']) && $update['version_string'] != $update['latest_version'] && $update['latest_version'] != $update['skip_version'])
				{
					$updateCount++;
				}
			}			
			
			$content = str_replace('{updates}', "[$updateCount]", $content);
		}
	}
	
	public static function extendAddOnController($class, array &$extend) 
	{
	}
	
	public static function extendAddOnModel($class, array &$extend)
	{
	}
	
	public static function extendAddOnDataWriter($class, array &$extend)
	{
	}

	public static function load_class($class, array &$extend)
	{
		$extend[] = str_replace('AddOnInstaller_', 'XenForo_', $class) ;
	}
}