<?php

class AddOnInstaller_Install
{
	public static function installer()
	{
		$db = XenForo_Application::getDb();

		$db->query("
			CREATE TABLE IF NOT EXISTS `xf_addon_update_check` (
			`addon_id` VARCHAR(25) NOT NULL DEFAULT '',
			`update_url` varchar(250) NOT NULL DEFAULT '',
			`check_updates` int(3) unsigned NOT NULL DEFAULT 1,
			`last_checked` int(10) unsigned NOT NULL DEFAULT 0,
			`latest_version` varchar(30) NOT NULL DEFAULT '',
			PRIMARY KEY (`addon_id`))
		");
		
		self::addRemoveColumn('xf_addon_update_check', 'skip_version', 'add', "varchar(30) NOT NULL DEFAULT ''", 'latest_version');
	}

	public static function uninstaller()
	{
		AddOnInstaller_Listener::$_addOnModelEnabled = false;
		
		$db = XenForo_Application::getDb();

		$db->query('
			DROP TABLE IF EXISTS `xf_addon_update_check`;
		');
	}

	public static function addRemoveColumn($tableName, $columnName, $action = 'remove', $fieldDef = NULL, $after = NULL)
	{
		$db = XenForo_Application::get('db');
		$exists = self::doesColumnExist($tableName, $columnName);

		if ($action == 'remove')
		{
			if ($exists)
			{
				$db->query("
					ALTER TABLE {$tableName} DROP COLUMN {$columnName}
					");                
			}
		}
		elseif ($action == 'add')
		{
			if (!$exists)
			{
				$db->query("
					ALTER TABLE {$tableName} ADD {$columnName} {$fieldDef} AFTER {$after}
					");
			}            
		}
	}

	public static function doesColumnExist($tableName, $columnName)
	{
		$db = XenForo_Application::get('db');

		return $db->fetchRow("
			SHOW COLUMNS
			FROM $tableName
			WHERE Field = ?
			", $columnName);
	}	
}