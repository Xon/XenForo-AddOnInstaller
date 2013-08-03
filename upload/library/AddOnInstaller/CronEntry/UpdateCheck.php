<?php

class AddOnInstaller_CronEntry_UpdateCheck
{
	public static function checkUpdates()
	{
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
		catch (Exception $e) {}
	}
}