<?php

class AddOnInstaller_Model_AddOn extends XFCP_AddOnInstaller_Model_AddOn
{
    /**
    * Extracts the provided file path to the base directory
    *
    * @param string $fileName
    *
    * @returns something
    */
    public function extractZip($fileName, $baseDir = 'install/addons', $installId = null)
    {
        if (!$installId)
        {
            $installId = uniqid();
            XenForo_Helper_File::createDirectory($baseDir . '/' . $installId);
        }

        $extractDir = $baseDir . '/' . $installId;

        $zip = new Zend_Filter_Decompress(array(
            'adapter' => 'Zip',
            'options' => array(
                'target' => $extractDir
            )
        ));

        $zip->filter($fileName);

        return $extractDir;
    }

    protected $_annoyingFilenames = array(
        '.DS_Store', // mac specific
        '.localized', // mac specific
        'Thumbs.db' // windows specific
    );

    /**
    * Recursively copy files from one directory to another
    *
    * @param String $source - Source of files being moved
    * @param String $destination - Destination of files being moved
    */
    protected function _recursiveCopy($source, $destination, array &$failedFiles)
    {
        if(!is_dir($source))
        {
            return false;
        }

        if(!is_dir($destination))
        {
            if(!XenForo_Helper_File::createDirectory($destination))
            {
                $failedFiles[] = $destination;
                return false;
            }
        }

        $dir = new DirectoryIterator($source);
        foreach($dir as $dirInfo)
        {
            if ($dirInfo->isDot())
            {
                continue;
            }

            $filename = $dirInfo->getFilename();
            if (in_array($filename, $this->_annoyingFilenames))
            {
                continue;
            }

            if($dirInfo->isFile())
            {
                $newFilename = $destination . '/' . $filename;
                if (!copy($dirInfo->getRealPath(), $newFilename))
                {
                    $failedFiles[] = $newFilename;
                }
            }
            else if($dirInfo->isDir())
            {
                $this->_recursiveCopy($dirInfo->getRealPath(), $destination . '/' . $filename, $failedFiles);
            }
        }

        return true;
    }

    /**
    * Reset the entire opcache
    *
    * @param string $deployMethod - copy/ftp
    * @param array $addOnDirs - list of directories to deploy
    */
    public function deployFiles($deployMethod, array $addOnDirs = null)
    {
        if ($deployMethod != 'copy')
            throw new Exception('Not implemented');

        $failedFiles = array();
        foreach ($addOnDirs AS $key => $dir)
        {
            if ($key == 'upload')
            {
                $this->_recursiveCopy($dir, '.', $failedFiles);
                break;
            }
            elseif ($key == 'maybeLibrary')
            {
                $this->_recursiveCopy($dir . '/..', './library', $failedFiles);
            }
            elseif ($key == 'js' || $key == 'library' || $key == 'styles')
            {
                $this->_recursiveCopy($dir . '/..', './' . $key, $failedFiles);
            }
        }
        return $failedFiles;
    }

    /**
    * Reset the entire opcache
    *
    * @param string $file The file to reset.
    */
    public static function InvalidateOpCache()
    {
        // opcache
        if (function_exists('opcache_reset'))
        {
            opcache_reset();
        }
    }

    /**
    * Given a directory, this will recursively list all directories within it
    * If the $allowedDirs array is defined, only the directories specified will be listed.
    *
    * @param string $baseDir
    * @param array $allowedDirs
    */
    public function getDirectoryListing($baseDir, array $allowedDirs = null)
    {
        $dir = new RecursiveDirectoryIterator($baseDir);
        $iterator = new RecursiveIteratorIterator($dir, RecursiveIteratorIterator::SELF_FIRST);

        $dirs = array();
        foreach ($iterator AS $dirName => $dirInfo)
        {
            if (strstr($dirName, '__MACOSX'))
            {
                continue;
            }

            if ($allowedDirs)
            {
                if ($dirInfo->isDir() && in_array($dirInfo->getFileName(), $allowedDirs))
                {
                    $dirs[] = array(
                        'file' => $dirInfo->getFileName(),
                        'path' => $dirName
                    );
                }
            }
            else
            {
                if ($dirInfo->isDir())
                {
                    $dirs[] = array(
                        'file' => $dirInfo->getFileName(),
                        'path' => $dirName
                    );
                }
            }
        }

        return $dirs;
    }

    /**
    * Given a directory, this will recursively list all files within it
    *
    * @param string $baseDir
    */
    public function getFileListing($baseDir)
    {
        $dir = new RecursiveDirectoryIterator($baseDir);
        $iterator = new RecursiveIteratorIterator($dir, RecursiveIteratorIterator::SELF_FIRST);

        foreach ($iterator AS $fileName => $fileInfo)
        {
            if (strstr($fileName, '__MACOSX'))
            {
                continue;
            }

            if ($fileInfo->isFile())
            {
                $files[] = array(
                    'file' => $fileInfo->getFileName(),
                    'path' => $fileName
                );
            }
        }

        return $files;
    }

    /**
    * Ascertains the type of XML file. Currently detects a XenForo XML for install.
    *
    * @param string $xmlFile
    */
    public function getXmlType($xmlFile)
    {
        $xml = new SimpleXMLElement($xmlFile, 0, true);

        $xmlDetails = array(
            'type' => (string)$xml->getName(),
            'addon_id' => (string)$xml['addon_id'],
            'version_string' => (string)$xml['version_string'],
            'resource_url' => (string)$xml['url'],
        );

        return $xmlDetails;
    }

    /**
    * Accepts the path of a directory then recursively deletes all files in that directory
    * and then removes the directory.
    *
    * If $empty is set to true, then the directory is emptied but not deleted.
    *
    * @param string $directory
    * @param bool $empty
    *
    * return bool
    */
    public function deleteAll($directory, $empty = false)
    {
        if (substr($directory, -1) == '/')
        {
            $directory = substr($directory, 0, -1);
        }

        if (!file_exists($directory) || !is_dir($directory))
        {
            return false;
        }
        elseif(!is_readable($directory))
        {
            return false;
        }
        else
        {
            $directoryHandle = opendir($directory);

            while (($contents = readdir($directoryHandle)) !== false)
            {
                if($contents != '.' && $contents != '..')
                {
                    $path = $directory . '/' . $contents;

                    if(is_dir($path))
                    {
                        $this->deleteAll($path);
                    }
                    else
                    {
                        unlink($path);
                    }
                }
            }

            closedir($directoryHandle);

            if($empty == false)
            {
                if(!rmdir($directory))
                {
                    return false;
                }
            }

            return true;
        }
    }

    public function isDownloadUrl($downloadUrl)
    {
        $pattern = '#(resources/[a-z0-9_\-]+\.[0-9]+/download\?version=[0-9]+)#';

        preg_match($pattern, $downloadUrl, $matches);

        if ($matches)
        {
            return true;
        }

        return false;
    }

    public function isResourceUrl($resourceUrl)
    {
        /** Temp fix for change of XF URL. In future, we will accept URLs from multiple sources, other RMs, other add-ons etc. */
        return (strstr($resourceUrl, 'xenforo.com/community/resources') || $resourceUrl === '');
    }

    public function getUpdateCheckByAddOnId($addOnId)
    {
        return $this->_getDb()->fetchRow('
            SELECT addonupdate.*, addon.*
            FROM xf_addon AS addon
            LEFT JOIN xf_addon_update_check AS addonupdate ON
                (addonupdate.addon_id = addon.addon_id)
            WHERE addon.addon_id = ?
        ', $addOnId);
    }

    public function isDwUpdate($addOnId)
    {
        return $this->_getDb()->fetchRow('
            SELECT *
            FROM xf_addon_update_check
            WHERE addon_id = ?
        ', $addOnId);
    }

    public function getAllUpdateChecks()
    {
        return $this->fetchAllKeyed('
            SELECT addonupdate.*, addon.*
            FROM xf_addon AS addon
            LEFT JOIN xf_addon_update_check AS addonupdate ON
                (addon.addon_id = addonupdate.addon_id)
            ORDER BY addonupdate.check_updates DESC, addon.title ASC
        ', 'addon_id');
    }

    public function checkForUpdates()
    {
        try
        {
            $updates = $this->getAllUpdateChecks();

            foreach ($updates AS $update)
            {
                if ($update['check_updates'] && $update['update_url'])
                {
                    $this->checkForUpdate($update, true);
                }
            }
        }
        catch (Exception $e) {XenForo_Error::Debug($e); }
    }

    public function checkForUpdate($addOn, $checkOnly = false)
    {
        $client = XenForo_Helper_Http::getClient($addOn['update_url'] . '/updates');
        $request = $client->request('GET');

        $dom = new Zend_Dom_Query($request->getBody());

        $version = $dom->query('h1 .muted');

        if (!$version->count())
        {
            return false;
        }

        $versionText = $version->current()->textContent;

        $writer = XenForo_DataWriter::create('AddOnInstaller_DataWriter_Updater');

        if ($this->isDwUpdate($addOn['addon_id']))
        {
            $writer->setExistingData($addOn['addon_id']);
        }

        $data = array(
            'last_checked' => XenForo_Application::$time,
            'latest_version' => $versionText
        );

        $writer->bulkSet($data);
        $writer->save();

        if (!$checkOnly)
        {
            if ($addOn['version_string'] != $versionText)
            {
                $updates = $dom->query('.updateContainer .resourceUpdate h2 a');

                $updateData = array(
                    'update' => false
                );

                if ($updates->count())
                {
                    $updateUrl = 'https://xenforo.com/community/' . $updates->current()->getAttribute('href');

                    $client->setUri($updateUrl);

                    $dom->setDocumentHtml($client->request('GET')->getBody());

                    $updateTitle = $dom->query('.resourceUpdate .textHeading a')->current()->textContent;
                    $updateText = $dom->query('.resourceUpdate article .messageText');
                    $updateText = $updateText->getDocument()->saveXML($updateText->current());

                    $updateData = array(
                        'update' => true,
                        'updateVersion' => $versionText,
                        'updateTitle' => $updateTitle,
                        'updateText' => $updateText,
                        'resourceUrl' => $addOn['update_url']
                    );
                }

                return $updateData;
            }

            return false;
        }
    }

    public function saveRmCredentials($username, $password)
    {
        $db = $this->_getDb();

        $db->update('xf_option', array('option_value' => $username), 'option_id = "xenforoRmLoginUsername"');
        $db->update('xf_option', array('option_value' => $password), 'option_id = "xenforoRmLoginPassword"');

        return $this->getModelFromCache('XenForo_Model_Option')->rebuildOptionCache();
    }

    public function deleteAddOnUpdates($addOnId)
    {
        $db = $this->_getDb();

        $addOnIdQuoted = $db->quote($addOnId);

        return $db->delete('xf_addon_update_check', "addon_id = $addOnIdQuoted");
    }

    public function getNextInstallOrder($addonInstallBatchId)
    {
        return $this->_getDb()->fetchRow('
            SELECT max(install_order) + 1
            FROM xf_addon_install_batch_entry
            WHERE addon_install_batch_id = ?
        ', $addonInstallBatchId);
    }

    public function getInstallBatchById($addonInstallBatchId)
    {
        return $this->_getDb()->fetchRow('
            SELECT *
            FROM xf_addon_install_batch
            WHERE addon_install_batch_id = ?
        ', $addonInstallBatchId);
    }

    public function getInstallBatchEntryById($addonInstallBatchEntryId)
    {
        return $this->_getDb()->fetchRow('
            SELECT *
            FROM xf_addon_install_batch_entry
            WHERE addon_install_batch_entry_id = ?
        ', $addonInstallBatchEntryId);
    }

    public function getInstallBatchEntrysById($addonInstallBatchId)
    {
        return $this->fetchAllKeyed('
            SELECT *
            FROM xf_addon_install_batch_entry
            WHERE addon_install_batch_id = ?
            order by install_order
        ', 'addon_install_batch_entry_id', $addonInstallBatchId);
    }

    public function getAddonInstallLog()
    {
        return $this->fetchAllKeyed("
            SELECT batch.*, entry.*
            FROM xf_addon_install_batch AS batch
            join xf_addon_install_batch_entry AS entry on entry.addon_install_batch_id = batch.addon_install_batch_id
            order by batch.install_date, entry.install_order
        ", 'addon_install_batch_entry_id');
    }

    public function addInstallBatch()
    {
        $visitor = XenForo_Visitor::getInstance();
        $dw = XenForo_DataWriter::create("AddOnInstaller_DataWriter_InstallBatch");
        $dw->set('deploy_method', 'copy');
        $dw->set('user_id', $visitor['user_id']);
        $dw->set('username', $visitor['username']);
        $dw->save();

        return $dw;
    }

    /**
    * Adds an addon to an install-batch. If the file is an XML file, it will be parsed and basic info extracted
    *
    * @param string $original_filename The human readable name.
    * @param string $filename Filename of the temp file
    * @param AddOnInstaller_DataWriter_InstallBatch $batch The install batch (dynamically created as needed)
    */
    public function addInstallBatchEntry($original_filename, $filename, AddOnInstaller_DataWriter_InstallBatch &$batch = null)
    {
        $original_filename = pathinfo($original_filename, PATHINFO_BASENAME);
        $extension = strtolower(pathinfo($original_filename, PATHINFO_EXTENSION));
        if (empty($batch))
        {
            $batch = $this->addInstallBatch();
            $path = 'install/addons/' . $batch->get('addon_install_batch_id') . '/';
            if (!XenForo_Helper_File::createDirectory($path))
            {
                throw new XenForo_Exception(new XenForo_Phrase('could_not_create_directory_permissions', true));
            }
        }
        else
        {
            $path = 'install/addons/' . $batch->get('addon_install_batch_id') . '/';
        }

        $uniqueId = uniqid('', true);
        $newfilename = $path . $uniqueId . '.' . $extension;

        if (!XenForo_Helper_File::createDirectory($path. $uniqueId . '/'))
        {
            throw new XenForo_Exception(new XenForo_Phrase('could_not_create_directory_permissions', true));
        }

        $xmlDetails = null;
        $error = false;
        if ($extension == 'xml')
        {
            try
            {
                $xmlDetails = $this->getXmlType($filename);
            }
            catch(Exception $e)
            {
                $error = true;
                XenForo_Error::logException($e, false);
            }
        }

        // make sure the XML file is an addon
        if (isset($xmlDetails['addon_id']) && $xmlDetails['type'] != 'addon')
        {
            $error = true;
            $xmlDetails = null;
        }

        $dw = XenForo_DataWriter::create("AddOnInstaller_DataWriter_InstallBatchEntry");
        $dw->InstallBatch = $batch;
        $dw->set('addon_install_batch_id', $batch->get('addon_install_batch_id'));
        if ($error)
        {
            $dw->set('in_error', 1);
        }
        if (isset($xmlDetails['addon_id']))
        {
            $dw->set('install_phase', 'deployed');
            $dw->set('addon_id', $xmlDetails['addon_id']);
            $dw->set('version_string', $xmlDetails['version_string']);
            $dw->set('resource_url', $xmlDetails['resource_url']);
            $dw->set('xml_file', $newfilename);
        }
        $dw->set('original_filename', $original_filename);
        $dw->set('source_file', $newfilename);
        $dw->save();

        if (!XenForo_Helper_File::safeRename($filename, $newfilename))
        {
            throw new XenForo_Exception(new XenForo_Phrase('could_not_create_directory_permissions', true));
        }

        return $dw->get('addon_install_batch_entry_id');
    }

    /**
    * Marks an install batch as complete if all the addons in the batch installed sucessfully
    */
    public function completeInstallBatch($addon_install_batch_id)
    {
        $batch = $this->getInstallBatchById($addon_install_batch_id);
        $entries = $this->getInstallBatchEntrysById($addon_install_batch_id);
        $all_installed = true;
        foreach($entries as &$entry)
        {
            if ($entry['in_error'] || $entry['install_phase'] != 'installed')
            {
                $all_installed = false;
                break;
            }
        }

        $installBatch = XenForo_DataWriter::create("AddOnInstaller_DataWriter_InstallBatch");
        $installBatch->setExistingData($batch);
        $installBatch->set('is_completed', $all_installed);
        $installBatch->save();

        return true;
    }

    /**
     * Rebuilds all caches that are touched by add-ons.
     */
    public function rebuildAddOnCaches()
    {
        if (XenForo_Application::get('options')->addoninstaller_supress_cache_rebuild)
        {
            $this->getModelFromCache('XenForo_Model_CodeEvent')->rebuildEventListenerCache();
            return;
        }

        parent::rebuildAddOnCaches();
    }
}