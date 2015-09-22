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
        }

        $extractDir = $baseDir . '/' . $installId;
        XenForo_Helper_File::createDirectory($extractDir);

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
        '.svn' => true,
        '.git' => true,
        '.gitmodules' => true,
        '.gitignore' => true,
        '.ds_store' => true, // mac specific
        '.localized' => true, // mac specific
        'thumbs.db' => true, // windows specific
    );

    /**
    * Recursively copy files from one directory to another
    *
    * @param String $source - Source of files being moved
    * @param String $destination - Destination of files being moved
    * @param Array $failedFiles - list of files which failed to copy
    */
    protected function _recursiveCopy(AddOnInstaller_Model_Deployment_Abstract $deployer, $source, $destination, array &$failedFiles)
    {
        if(!is_dir($source))
        {
            return false;
        }

        if(!$deployer->is_dir($destination))
        {
            if(!$deployer->mkdir($destination))
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
            if (isset($this->_annoyingFilenames[strtolower($filename)]))
            {
                continue;
            }

            if($dirInfo->isFile())
            {
                $newFilename = $destination . '/' . $filename;
                if (!$deployer->copy($dirInfo->getRealPath(), $newFilename))
                {
                    $failedFiles[] = $newFilename;
                }
            }
            else if($dirInfo->isDir())
            {
                $this->_recursiveCopy($deployer, $dirInfo->getRealPath(), $destination . '/' . $filename, $failedFiles);
            }
        }

        return true;
    }

    public function getAddonDeploymentMethods()
    {
        $deployMethods = array();
        XenForo_CodeEvent::fire('addon_deployment', array(&$deployMethods));
        if (empty($deployMethods))
        {
            $deployMethod = 'copy';
            $deployMethods[$deployMethod] = 'AddOnInstaller_Model_Deployment_' . $deployMethod;
        }
        return $deployMethods;
    }

    public function getAddonDeploymentMethodPhrases()
    {
        $methods = $this->getAddonDeploymentMethods();
        foreach($methods as $key => &$method)
        {
            $method = new XenForo_Phrase('deployment_method_' . $key);
        }
        return $methods;
    }

    /**
    * Gets the specific class which implements a deployment method
    *
    * @param string $deployMethod
    */
    public function getAddonDeployer($deployMethod)
    {
        // resolve the deployment method
        $methods = $this->getAddonDeploymentMethods();
        $deployMethodClass = isset($methods[$deployMethod]) ? $methods[$deployMethod] : '';
        if (!empty($deployMethodClass))
        {
            $deployMethodClass = XenForo_Application::resolveDynamicClass($deployMethodClass);
        }
        if (empty($deployMethodClass) || !class_exists($deployMethodClass))
        {
            throw new XenForo_Exception(new XenForo_Phrase('deployment_method_x_not_implemented', array('method' => $deployMethod), true));
        }
        return new $deployMethodClass();
    }

    /**
    * Deploys a set of files using a given deployment agent, reporting the ones which failed.
    *
    * @param string $addonDeployer - the addon deployment implementation to use
    * @param array $addOnDirs - list of directories to deploy
    */
    public function deployFiles(AddOnInstaller_Model_Deployment_Abstract $addonDeployer, array $addOnDirs = null)
    {
        // deploy the files
        $failedFiles = array();
        foreach ($addOnDirs AS $key => $dir)
        {
            if ($key == 'upload')
            {
                $this->_recursiveCopy($addonDeployer, $dir, '.', $failedFiles);
                break;
            }
            elseif ($key == 'maybeLibrary')
            {
                $this->_recursiveCopy($addonDeployer, $dir, './library', $failedFiles);
            }
            elseif ($key == 'js' || $key == 'library' || $key == 'styles')
            {
                $this->_recursiveCopy($addonDeployer, $dir, './' . $key, $failedFiles);
            }
        }

        return $failedFiles;
    }

    /**
    * Reset the entire opcache
    *
    * @param string $file The file to reset.
    */
    public function InvalidateOpCache()
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
        if (method_exists('XenForo_Helper_DevelopmentXml', 'scanFile'))
        {
            $xml = XenForo_Helper_DevelopmentXml::scanFile($xmlFile);
        }
        else
        {
            $xml = new SimpleXMLElement($xmlFile, 0, true);
        }

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

    public function bulkUpdateAddOnCheck()
    {
        $db = $this->_getDb();
        $addons = $db->fetchAll("
            SELECT addon.addon_id, addon.url as update_url, coalesce(update_check.check_updates, 1) as check_updates
            FROM xf_addon addon
            LEFT JOIN xf_addon_update_check AS update_check ON update_check.addon_id = addon.addon_id
            WHERE (update_check.update_url is null or update_check.update_url = '') and addon.url is not null and addon.url <> ''
        ");

        foreach($addons as $addon)
        {
            if (!empty(trim($addon['update_url'])) && $this->isResourceUrl($addon['update_url']))
            {
                $writer = XenForo_DataWriter::create('AddOnInstaller_DataWriter_Updater');

                if ($this->isDwUpdate($addon['addon_id']))
                {
                    $writer->setExistingData($addon['addon_id']);
                }

                $writer->bulkSet($addon);
                $writer->save();
            }
        }
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

    protected function downloadResourceManagerRelease($username, $password, $resourceUrl)
    {
        $client = XenForo_Helper_Http::getClient('https://xenforo.com/community/login/login');

        $client->setCookieJar();

        $client->setParameterPost(array('login' => $username, 'password' => $password, 'redirect' => $resourceUrl));

        $login = $client->request('POST');

        $dom = new Zend_Dom_Query($login->getBody());
        $loggedIn = $dom->query('html .LoggedIn');

        if (!$loggedIn->count())
        {
            throw new XenForo_Exception(new XenForo_Phrase('login_to_xenforo_has_failed'), true);
        }

        $downloadButton = $dom->query('.downloadButton a');

        if (!$downloadButton->count())
        {
            throw new XenForo_Exception(new XenForo_Phrase('problem_accessing_resource_page'), true);
        }

        $downloadUrl = $downloadButton->current()->getAttribute('href');

        if (!$this->isDownloadUrl($downloadUrl))
        {
            throw new XenForo_Exception(new XenForo_Phrase('no_download_url_found_maybe_paid'), true);
        }

        $client->setUri('https://xenforo.com/community/' . $downloadUrl);

        $response = $client->request('GET');
        $content_disposition = $response->getHeader("Content-Disposition");
        if(preg_match('/.*filename=[\'\"]([^\'\"]+)/', $content_disposition, $matches))
        {
            $filename = $matches[1];
        }
        // if filename is not quoted, we take all until the next space
        else if(preg_match("/.*filename=([^ ]+)/", $content_disposition, $matches))
        {
            $filename = $matches[1];
        }
        else
        {
            throw new XenForo_Exception(new XenForo_Phrase('problem_accessing_resource_page'), true);
        }
        $newTempFile = tempnam(XenForo_Helper_File::getTempDir(), 'xf');
        $fp = fopen($newTempFile, 'w');
        fwrite($fp, $response->getRawBody());
        fclose($fp);

        return array($reponse, $newTempFile, $filename);
    }

    public function downloadResourceFromUrl($username, $password, $resourceUrl)
    {
        if (!$this->isResourceUrl($resourceUrl))
        {
            throw new XenForo_Exception(new XenForo_Phrase('only_resource_manager_urls_are_valid'), true);
        }
        return $this->downloadResourceManagerRelease($username, $password, $resourceUrl);
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
        // make sure the selected deployment method is valid and implemented
        $method = XenForo_Application::getOptions()->deploymentmethod;
        if (empty($method))
        {
            throw new XenForo_Exception(new XenForo_Phrase('no_deployment_method_set'), true);
        }
        $deployer = $this->getAddonDeployer($method);
        $visitor = XenForo_Visitor::getInstance();
        $dw = XenForo_DataWriter::create("AddOnInstaller_DataWriter_InstallBatch");
        $dw->set('deploy_method', $method);
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
                throw new XenForo_Exception(new XenForo_Phrase('could_not_create_directory_permissions'), true);
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
            throw new XenForo_Exception(new XenForo_Phrase('could_not_create_directory_permissions'), true);
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
            throw new XenForo_Exception(new XenForo_Phrase('could_not_create_directory_permissions'), true);
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
        $options = XenForo_Application::getOptions();
        if ($options->addoninstaller_supress_cache_rebuild)
        {
            $options->set('addoninstaller_cache_rebuild_required', true);
            $this->getModelFromCache('XenForo_Model_CodeEvent')->rebuildEventListenerCache();
            return;
        }

        parent::rebuildAddOnCaches();
    }

    public function massUpdateInstallOrder($addon_install_batch_id, $order)
    {
        $sqlOrder = '';
        $db = $this->_getDb();
        $args = array();

        foreach ($order AS $displayOrder => $data)
        {
            $addon_install_batch_entry_id = is_array($data) ? intval($data[0]) : intval($data);
            if (empty($addon_install_batch_entry_id))
            {
                continue;
            }
            $displayOrder = (int)$displayOrder;

            $args[] = $addon_install_batch_entry_id;
            $args[] = $displayOrder;
            $sqlOrder .= "WHEN ? THEN ? \n";
        }

        if (empty($args))
        {
            return;
        }

        $args[] = $addon_install_batch_id;

        $db->query('
            UPDATE xf_addon_install_batch_entry SET
                install_order = CASE addon_install_batch_entry_id
                ' . $sqlOrder . '
                ELSE 0 END
            WHERE addon_install_batch_id = ?
        ', $args);
    }

    public function prepareInstallBatch(array $addonbatch)
    {
        $method = empty($addonbatch['deploy_method'])
                  ? XenForo_Application::getOptions()->deploymentmethod
                  : $addonbatch['deploy_method'];
        if ($method)
        {
            $addonbatch['deploymentMethod'] = new XenForo_Phrase('deployment_method_' . $method);
        }
        return $addonbatch;
    }
}