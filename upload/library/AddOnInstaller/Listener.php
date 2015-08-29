<?php
class AddOnInstaller_Listener
{
    public static $_UninstallingSelf = false;

    public static function containerAdminParams(array &$params, XenForo_Dependencies_Abstract $dependencies)
    {
        if (isset($params['adminNavigation']['sideLinks']['addOns']['installAddOn']))
        {
            unset($params['adminNavigation']['sideLinks']['addOns']['installAddOn']);
        }
    }

    public static function getAddonInstallerUpdateCount($content, $params, XenForo_Template_Abstract $template)
    {
        $addOnModel = XenForo_Model::create('XenForo_Model_AddOn');

        if (!method_exists($addOnModel, 'getAllUpdateChecks'))
        {
            return '[0]';
        }

        $updates = $addOnModel->getAllUpdateChecks();

        $updateCount = 0;
        foreach ($updates AS $update)
        {
            if ($update['check_updates'] && !empty($update['latest_version']) && $update['version_string'] != $update['latest_version'] && $update['latest_version'] != $update['skip_version'])
            {
                $updateCount++;
            }
        }

        return "[$updateCount]";
    }

    public static function addon_deployment(&$deployMethods)
    {
        $builtins = explode(',', XenForo_Application::getOptions()->builtin_deploymentmethods);
        foreach($builtins as $deployMethod)
        {
            $deployMethod = trim($deployMethod);
            if ($deployMethod)
            {
                $deployMethods[$deployMethod] = 'AddOnInstaller_Model_Deployment_' . $deployMethod;
            }
        }
    }

    public static function load_class($class, array &$extend)
    {
        $extend[] = str_replace('XenForo_', 'AddOnInstaller_', $class) ;
    }

    public static function extendAddOnController($class, array &$extend)
    {
        if ($class == 'XenForo_ControllerAdmin_AddOn')
        {
            self::load_class($class, $extend);
        }
    }

    public static function extendAddOnModel($class, array &$extend)
    {
        if ($class == 'XenForo_Model_AddOn')
        {
            self::load_class($class, $extend);
        }
    }

    public static function extendAddOnDataWriter($class, array &$extend)
    {
        if ($class == 'XenForo_DataWriter_AddOn')
        {
            self::load_class($class, $extend);
        }
    }
}