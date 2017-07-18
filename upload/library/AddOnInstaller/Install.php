<?php

class AddOnInstaller_Install
{
    public static function installer($existingAddOn, array $addOnData, SimpleXMLElement $xml)
    {
        if (XenForo_Application::$versionId < 1020070)
        {
            throw new XenForo_Exception("Minimum supported version is XF 1.2.0", true);
        }

        if (!extension_loaded('zip'))
        {
            throw new XenForo_Exception("This addon requires ZipArchive support. This requires the 'zip' extension, which your host must be configured with.", true);
        }

        $version = isset($existingAddOn['version_id']) ? $existingAddOn['version_id'] : 0;
        $db = XenForo_Application::getDb();

        $db->query("
            CREATE TABLE IF NOT EXISTS `xf_addon_update_check` (
            `addon_id` VARCHAR(25) NOT NULL DEFAULT '',
            `update_url` varchar(250) NOT NULL DEFAULT '',
            `check_updates` int(3) unsigned NOT NULL DEFAULT 1,
            `last_checked` int(10) unsigned NOT NULL DEFAULT 0,
            `latest_version` varchar(30) NOT NULL DEFAULT '',
            PRIMARY KEY (`addon_id`)
        ) ENGINE=InnoDB CHARACTER SET utf8 COLLATE utf8_general_ci");

        $db->query("
            CREATE TABLE IF NOT EXISTS `xf_addon_install_batch` (
            `addon_install_batch_id` int(10) unsigned NOT NULL AUTO_INCREMENT,
            `install_date` int(3) unsigned NOT NULL DEFAULT 0,
            `addon_count` int(10) unsigned NOT NULL DEFAULT 0,
            `is_completed` tinyint(3) unsigned NOT NULL DEFAULT 0,
            `deploy_method` VARCHAR(50) NOT NULL DEFAULT 'copy',
            `user_id` int(10) unsigned NOT NULL DEFAULT 0,
            `username` VARCHAR(50) NOT NULL DEFAULT '',
            `changeTracking` blob DEFAULT null,
            PRIMARY KEY (`addon_install_batch_id`),
            KEY (`install_date`)
        ) ENGINE=InnoDB CHARACTER SET utf8 COLLATE utf8_general_ci");

        if ($version == 1030000)
        {
            $db->query("
                ALTER TABLE `xf_addon_install_batch` CHANGE COLUMN `deploy_method` `deploy_method` VARCHAR(50) NOT NULL DEFAULT 'copy' ;
            ");
        }

        $db->query("
            CREATE TABLE IF NOT EXISTS `xf_addon_install_batch_entry` (
            `addon_install_batch_entry_id` int(10) unsigned NOT NULL AUTO_INCREMENT,
            `addon_install_batch_id` int(10) unsigned NOT NULL,
            `addon_id` VARCHAR(25) NOT NULL DEFAULT '',
            `version_string` VARCHAR(30) NOT NULL DEFAULT '',
            `install_phase` enum('uploaded','extracted','deployed','installed') NOT NULL DEFAULT 'uploaded',
            `in_error` tinyint(3) unsigned NOT NULL DEFAULT 0,
            `install_order` int(10) unsigned NOT NULL DEFAULT 0,
            `original_filename` VARCHAR(1024) NOT NULL DEFAULT '',
            `source_file` VARCHAR(1024) NOT NULL DEFAULT '',
            `extracted_files` VARCHAR(1024) NOT NULL DEFAULT '',
            `xml_file` VARCHAR(1024) NOT NULL DEFAULT '',
            `resource_url` VARCHAR(250) NOT NULL DEFAULT '',
            PRIMARY KEY (`addon_install_batch_entry_id`),
            KEY (`addon_install_batch_id`, `install_order`)
        ) ENGINE=InnoDB CHARACTER SET utf8 COLLATE utf8_general_ci");

        self::addRemoveColumn('xf_addon_update_check', 'skip_version', 'add', "varchar(30) NOT NULL DEFAULT ''", 'latest_version');
        self::addRemoveColumn('xf_addon_install_batch', 'changeTracking', 'add', "blob DEFAULT null", 'username');

        if ($xml)
        {
            foreach ($xml->cron->entry AS $cronEntry)
            {
                $entry = json_decode($cronEntry[0], true);
                $entry['hours'] = array(mt_rand(0, 23));
                $entry['minutes'] = array(mt_rand(0, 59));

                $cronEntry[0] = json_encode($entry);
            }
        }

        // if this addon is disabled, and then upgraded via XenForo addon upgrade proccess,
        // the method that is expected to exist will not.
        $addOnModel = XenForo_Model::create("XenForo_Model_AddOn");
        if (method_exists($addOnModel, 'bulkUpdateAddOnCheck'))
        {
            // scan existing addons and load their url if it looks like something we support updating from.
            $addOnModel->bulkUpdateAddOnCheck();
        }
    }

    public static function uninstaller()
    {
        AddOnInstaller_Listener::$_UninstallingSelf = true;

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