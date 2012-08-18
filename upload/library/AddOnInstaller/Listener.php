<?php
class AddOnInstaller_Listener
{
	
	/*public static function templateHook($hookName, &$contents, array $hookParams, XenForo_Template_Abstract $template)
	{
		if ($hookName == 'criteria_user')
		{
			$params = $template->getParams();
			$params += $hookParams;
			$contents .= $template->create('helper_criteria_user', $params);
			
		}	
	}*/
	
	public static function extendAddOnController($class, array &$extend)
	{
		if ($class == 'XenForo_ControllerAdmin_AddOn')
		{
			$extend[] = 'AddOnInstaller_ControllerAdmin_AddOn';
		}
	}
	
	public static function extendAddOnModel($class, array &$extend)
	{
		if ($class == 'XenForo_Model_AddOn')
		{
			$extend[] = 'AddOnInstaller_Model_AddOn';
		}
	}
	
}