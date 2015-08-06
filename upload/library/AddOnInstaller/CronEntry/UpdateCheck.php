<?php

class AddOnInstaller_CronEntry_UpdateCheck
{
    public static function checkUpdates()
    {
        if (XenForo_Application::getOptions()->addoninstaller_check_update)
        {
            XenForo_Model::create('XenForo_Model_AddOn')->checkForUpdates();
        }
    }
}