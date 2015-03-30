<?php

class AddOnInstaller_CronEntry_UpdateCheck
{
	public static function checkUpdates()
	{
		if (XenForo_Application::getOptions()->addoninstaller_check_update)
		try
		{
			$addOnModel = XenForo_Model::create('XenForo_Model_AddOn');

			$updates = $addOnModel->getAllUpdateChecks();

			foreach ($updates AS $update)
			{
				if ($update['check_updates'] && $update['update_url'])
				{
					$addOnModel->checkForUpdate($update, true);
				}
			}
		}
		catch (Exception $e) {XenForo_Error::Debug($e); }
	}
}