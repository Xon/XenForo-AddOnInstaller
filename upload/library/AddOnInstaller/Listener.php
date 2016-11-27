<?php
class AddOnInstaller_Listener
{
    const AddonNameSpace = 'AddOnInstaller_';

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
            if ($update['check_updates'] && !empty($update['outstanding_update']))
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
            if ($deployMethod == 'ftp' && !extension_loaded('ftp'))
            {
                continue;
            }
            if ($deployMethod)
            {
                $deployMethods[$deployMethod] = 'AddOnInstaller_Model_Deployment_' . $deployMethod;
            }
        }
    }

    static $addOnModel = null;

    public static function template_file_change($file, $action)
    {
        if (self::$addOnModel == null)
        {
            self::$addOnModel = XenForo_Model::create('XenForo_Model_AddOn');
        }
        self::$addOnModel->InvalidateFileOpCache($file, $action);
    }

    public static function load_class($class, array &$extend)
    {
        $extend[] = self::AddonNameSpace.$class;
    }

    public static function extendAddOnController($class, array &$extend)
    {
        $extend[] = self::AddonNameSpace.$class;
    }

    public static function extendAddOnModel($class, array &$extend)
    {
        $extend[] = self::AddonNameSpace.$class;
    }

    public static function extendAddOnDataWriter($class, array &$extend)
    {
        $extend[] = self::AddonNameSpace.$class;
    }
}