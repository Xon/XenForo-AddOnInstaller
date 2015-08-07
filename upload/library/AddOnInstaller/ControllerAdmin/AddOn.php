<?php

class AddOnInstaller_ControllerAdmin_AddOn extends XFCP_AddOnInstaller_ControllerAdmin_AddOn
{
    public function actionInstallLog()
    {
        $addOnModel = $this->_getAddOnModel();
        $addons = $addOnModel->getAddonInstallLog();
        $viewParams = array
        (
            'addons' => $addons,
        );
        return $this->responseView('AddOnInstaller_ViewAdmin_Install', 'addon_install_log', $viewParams);
    }

    public function actionInstallUpgrade()
    {
        $addon_install_batch_id = $this->_input->filterSingle('addon_install_batch_id', XenForo_Input::UINT);
        $addOnModel = $this->_getAddOnModel();
        $batch = $addon_install_batch_id ? $addOnModel->getInstallBatchById($addon_install_batch_id) : array();
        $entries = $addon_install_batch_id ? $addOnModel->getInstallBatchEntrysById($addon_install_batch_id) : array();
        $viewParams = array
        (
            'addon_install_batch_id' => $addon_install_batch_id,
            'addonbatch' => $batch,
            'addons' => $entries,
        );
        return $this->responseView('AddOnInstaller_ViewAdmin_Install', 'addon_install_auto', $viewParams);
    }

    public function _assertInstallBatchOpen($addon_install_batch_id)
    {
        if (!$addon_install_batch_id)
        {
            throw $this->getErrorOrNoPermissionResponseException('unknown_addon_install_batch');
        }

        $addOnModel = $this->_getAddOnModel();
        $batch = $addOnModel->getInstallBatchById($addon_install_batch_id);

        if (!$batch)
        {
            throw $this->getErrorOrNoPermissionResponseException('unknown_addon_install_batch');
        }

        if ($batch['is_completed'])
        {
            throw $this->getErrorOrNoPermissionResponseException('addon_install_batch_completed');
        }

        return $batch;
    }

    public function actionDeleteAddonUpgradeEntry()
    {
        $addon_install_batch_entry_id = $this->_input->filterSingle('addon_install_batch_entry_id', XenForo_Input::UINT);
        $addOnModel = $this->_getAddOnModel();
        $entry = $addOnModel->getInstallBatchEntryById($addon_install_batch_entry_id);
        if (!$entry)
        {
            return $this->responseError(new XenForo_Phrase('unknown_addon_install_batch_entry'));
        }
        $addon_install_batch_id =  $entry['addon_install_batch_id'];
        $batch = $this->_assertInstallBatchOpen($addon_install_batch_id);
        $dw = XenForo_DataWriter::create("AddOnInstaller_DataWriter_InstallBatchEntry");
        $dw->setExistingData($entry);
        $dw->delete();

        return $this->responseRedirect(
            XenForo_ControllerResponse_Redirect::SUCCESS,
            XenForo_Link::buildAdminLink('add-ons/install-upgrade', array(), array('addon_install_batch_id' => $addon_install_batch_id))
        );
    }

    public function actionStepUpload()
    {
        $this->_assertPostOnly();

        $addOnModel = $this->_getAddOnModel();
        $fileTransfer = new Zend_File_Transfer_Adapter_Http();
        $deployMethod = $this->_input->filterSingle('deploy_method', XenForo_Input::STRING);
        $resourceUrl = $this->_input->filterSingle('resource_url', XenForo_Input::STRING);

        $installBatch = null;

        $addon_install_batch_id = $this->_input->filterSingle('addon_install_batch_id', XenForo_Input::UINT);
        if ($addon_install_batch_id)
        {
            $batch = $this->_assertInstallBatchOpen($addon_install_batch_id);
            $installBatch = XenForo_DataWriter::create("AddOnInstaller_DataWriter_InstallBatch");
            $installBatch->setExistingData($batch);
        }

        if ($resourceUrl)
        {
            $username = $this->_input->filterSingle('login', XenForo_Input::STRING);
            $password = $this->_input->filterSingle('password', XenForo_Input::STRING);
            $remember_me = $this->_input->filterSingle('cred_save', XenForo_Input::UINT);

            if ($username && $password && $remember_me)
            {
                $addOnModel->saveRmCredentials($username, $password);
            }

            $options = XenForo_Application::get('options');

            if (!$username && !$password)
            {
                $username = $options->xenforoRmLoginUsername;
                $password = $options->xenforoRmLoginPassword;

                if (!$username || !$password)
                {
                    return $this->responseError(new XenForo_Phrase('login_to_xenforo_has_failed'));
                }
            }

            $client = XenForo_Helper_Http::getClient('https://xenforo.com/community/login/login');

            $client->setCookieJar();

            $client->setParameterPost(array('login' => $username, 'password' => $password, 'redirect' => $resourceUrl));

            $login = $client->request('POST');

            $dom = new Zend_Dom_Query($login->getBody());
            $loggedIn = $dom->query('html .LoggedIn');

            if (!$loggedIn->count())
            {
                return $this->responseError(new XenForo_Phrase('login_to_xenforo_has_failed'));
            }

            $downloadButton = $dom->query('.downloadButton a');

            if (!$downloadButton->count())
            {
                return $this->responseError(new XenForo_Phrase('problem_accessing_resource_page'));
            }

            $downloadUrl = $downloadButton->current()->getAttribute('href');

            if (!$addOnModel->isDownloadUrl($downloadUrl))
            {
                return $this->responseError(new XenForo_Phrase('no_download_url_found_maybe_paid'));
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
                return $this->responseError(new XenForo_Phrase('problem_accessing_resource_page'));
            }
            $newTempFile = tempnam(XenForo_Helper_File::getTempDir(), 'xf');
            $fp = fopen($newTempFile, 'w');
            fwrite($fp, $response->getRawBody());
            fclose($fp);

            try
            {
                $addon_install_batch_entry_id = $addOnModel->addInstallBatchEntry($filename, $newTempFile, $installBatch);
                $dw = XenForo_DataWriter::create("AddOnInstaller_DataWriter_InstallBatchEntry");
                $dw->setExistingData($addon_install_batch_entry_id);
                $dw->set('resource_url', $resourceUrl);
                $dw->save();
            }
            catch(Exception $e)
            {
                @unlink($newTempFile);
                throw $e;
            }
        }
        if ($fileTransfer->isUploaded('upload_file_oldskool') || $fileTransfer->isUploaded('upload_file'))
        {
            foreach ($fileTransfer->getFileInfo() AS $fileInfo)
            {
                if (empty($fileInfo['error'])) //verify no errors e.g. file not exist
                {
                    $fileName = $fileInfo['tmp_name'];
                    $addOnModel->addInstallBatchEntry($fileInfo['name'], $fileName, $installBatch);
                }
            }
        }
        if ($this->_input->filterSingle('server_file_oldskool', XenForo_Input::STRING))
        {
            $fileName = $this->_input->filterSingle('server_file_oldskool', XenForo_Input::STRING);
            $newTempFile = tempnam(XenForo_Helper_File::getTempDir(), 'xf');
            try
            {
                copy($fileName, $newTempFile);
                $addOnModel->addInstallBatchEntry($fileName, $newTempFile, $installBatch);
            }
            catch(Exception $e)
            {
                @unlink($newTempFile);
                throw $e;
            }
        }

        if ($installBatch === null)
        {
            return $this->responseError(new XenForo_Phrase('an_unexpected_error_occurred_while_extracting_addons'));
        }

        if ($this->isConfirmedPost())
        {
            return $this->responseRedirect(
                XenForo_ControllerResponse_Redirect::SUCCESS,
                XenForo_Link::buildAdminLink('add-ons/step-extract', array(), array('addon_install_batch_id' => $installBatch->get('addon_install_batch_id')))
            );
        }

        return $this->responseRedirect(
            XenForo_ControllerResponse_Redirect::SUCCESS,
            XenForo_Link::buildAdminLink('add-ons/install-upgrade', array(), array('addon_install_batch_id' => $installBatch->get('addon_install_batch_id')))
        );

    }

    protected $MaximumRuntime = 30 * 1000000;

    public function actionStepExtract()
    {
        $addon_install_batch_id = $this->_input->filterSingle('addon_install_batch_id', XenForo_Input::UINT);
        $batch = $this->_assertInstallBatchOpen($addon_install_batch_id);

        $addOnModel = $this->_getAddOnModel();
        $entries = $addOnModel->getInstallBatchEntrysById($addon_install_batch_id);

        $next_phase = 'step-deploy';
        $start = microtime(true);
        foreach($entries as &$entry)
        {
            if (microtime(true) - $start > $this->MaximumRuntime )
            {
                $next_phase = 'step-extract';
                break;
            }
            if ($entry['install_phase'] != 'uploaded' || $entry['in_error'])
            {
                continue;
            }

            $parts = pathinfo($entry['source_file']);

            $error = true;
            if ($parts['extension'] == 'zip')
            {
                try
                {
                    $newFiles = $addOnModel->extractZip($entry['source_file'], $parts['dirname'], $parts['filename']);
                    $error = false;
                }
                catch(Exception $e)
                {
                    XenForo_Error::logException($e, false);
                }
            }
            else
            {
                XenForo_Error::logException(new Exception("Expected ".$entry['original_filename']." to be a zip"), false);
            }

            $dw = XenForo_DataWriter::create("AddOnInstaller_DataWriter_InstallBatchEntry");
            $dw->setExistingData($entry);
            if (!$error)
            {
                $dw->set('install_phase', 'extracted');
                $dw->set('extracted_files', $newFiles);
            }
            else
            {
                $dw->set('in_error', 1);
            }

            $dw->save();
        }

        return $this->responseRedirect(
            XenForo_ControllerResponse_Redirect::SUCCESS,
            XenForo_Link::buildAdminLink('add-ons/' . $next_phase, array(), array('addon_install_batch_id' => $addon_install_batch_id))
        );
    }

    public function actionStepDeploy()
    {
        $addon_install_batch_id = $this->_input->filterSingle('addon_install_batch_id', XenForo_Input::UINT);
        $batch = $this->_assertInstallBatchOpen($addon_install_batch_id);

        $addOnModel = $this->_getAddOnModel();
        $entries = $addOnModel->getInstallBatchEntrysById($addon_install_batch_id);

        $next_phase = 'step-install';
        $start = microtime(true);
        foreach($entries as &$entry)
        {
            if (microtime(true) - $start > $this->MaximumRuntime )
            {
                $next_phase = 'step-deploy';
                break;
            }
            if ($entry['install_phase'] != 'extracted' || $entry['in_error'])
            {
                continue;
            }
            $extractDir = $entry['extracted_files'];

            $fileList = $addOnModel->getFileListing($extractDir);

            $xmlFile = array();
            foreach ($fileList AS $file)
            {
                $ext = strtolower(pathinfo($file['file'], PATHINFO_EXTENSION));
                if ($ext == 'xml')
                {
                    $xmlDetails = $addOnModel->getXmlType($file['path']);

                    if ($xmlDetails['type'] === 'addon')
                    {
                        $xmlFile = array(
                            'path' => $file['path'],
                            'addon_id' => $xmlDetails['addon_id'],
                            'version_string' => $xmlDetails['version_string'],
                            'resource_url' => $xmlDetails['resource_url'],
                        );

                        break;
                    }
                }
            }
            $error = false;
            if (!$xmlFile)
            {
                $error = true;
                XenForo_Error::logException(new XenForo_Exception(new XenForo_Phrase('a_valid_installable_xml_not_found')), false);
            }
            else
            {
                $allowedDirs = array(
                    'js',
                    'library',
                    'styles',
                    'UPLOAD',
                    'upload'
                );

                $dirList = $addOnModel->getDirectoryListing($extractDir, $allowedDirs);

                $addOnDirs = array();
                foreach ($dirList AS $dir)
                {
                    switch ($dir['file'])
                    {
                        case 'UPLOAD':
                        case 'upload':
                            $addOnDirs['upload'] = $dir['path'];
                            break;

                        case 'js':
                            $addOnDirs['js'] = $dir['path'];
                            break;

                        case 'library':
                            $addOnDirs['library'] = $dir['path'];
                            break;

                        case 'styles':
                            $addOnDirs['styles'] = $dir['path'];
                            break;
                    }
                }

                if (!$dirList)
                {
                    $dirList = $addOnModel->getDirectoryListing($extractDir);

                    $commonLibDirs = array(
                        'Authentication' => true,
                        'BbCode' => true,
                        'Captcha' => true,
                        'ControllerAdmin' => true,
                        'ControllerPublic' => true,
                        'CronEntry' => true,
                        'DataWriter' => true,
                        'Importer' => true,
                        'Model' => true,
                        'Option' => true,
                        'Route' => true,
                        'Template' => true,
                        'ViewAdmin' => true,
                        'ViewPublic' => true,
                    );

                    foreach ($dirList AS $dir)
                    {
                        if (isset($commonLibDirs[$dir['file']]))
                        {
                            $addOnDirs['maybeLibrary'] = $dir['path'] . '/..';
                        }
                    }
                }

                try
                {
                    $failedFiles = $addOnModel->deployFiles($batch['deploy_method'], $addOnDirs);
                }
                catch(Exception $e)
                {
                    XenForo_Error::logException($e, false);
                    $error = true;
                }
            }
            $dw = XenForo_DataWriter::create("AddOnInstaller_DataWriter_InstallBatchEntry");
            $dw->setExistingData($entry);
            if ($xmlFile)
            {
                $dw->set('xml_file', $xmlFile['path']);
                $dw->set('addon_id', $xmlFile['addon_id']);
                $dw->set('version_string', $xmlFile['version_string']);
                $dw->set('resource_url', $xmlFile['resource_url']);
            }
            if ($error)
            {
                $dw->set('in_error', 1);
            }
            else
            {
                $dw->set('install_phase', 'deployed');
            }
            $dw->save();
        }

        $addOnModel->InvalidateOpCache();

        return $this->responseRedirect(
            XenForo_ControllerResponse_Redirect::SUCCESS,
            XenForo_Link::buildAdminLink('add-ons/' . $next_phase, array(), array('addon_install_batch_id' => $addon_install_batch_id))
        );
    }

    public function actionStepInstall()
    {
        $addon_install_batch_id = $this->_input->filterSingle('addon_install_batch_id', XenForo_Input::UINT);
        $batch = $this->_assertInstallBatchOpen($addon_install_batch_id);

        $addOnModel = $this->_getAddOnModel();
        $entries = $addOnModel->getInstallBatchEntrysById($addon_install_batch_id);

        $next_phase = 'install-upgrade';
        $start = microtime(true);
        $caches = array();
        $installed_addons = false;
        $options = XenForo_Application::get('options');
        $options->set('addoninstaller_supress_cache_rebuild', true);
        try
        {
        foreach($entries as &$entry)
        {
            if (microtime(true) - $start > $this->MaximumRuntime )
            {
                $next_phase = 'step-install';
                break;
            }
            if ($entry['install_phase'] != 'deployed' || $entry['in_error'])
            {
                continue;
            }
            $installed_addons = true;

            $xmlFile = array(
                'path' => $entry['xml_file'],
                'addon_id' => $entry['addon_id'],
                'version_string' => $entry['version_string']
            );

            $error = false;
            try
            {
                $addOnExists = $addOnModel->getAddOnById($xmlFile['addon_id']);
                if ($addOnExists)
                {
                    $addOnModel->installAddOnXmlFromFile($xmlFile['path'], $xmlFile['addon_id']);
                }
                else
                {
                    $addOnModel->installAddOnXmlFromFile($xmlFile['path']);
                }
            }
            catch(Exception $e)
            {
                XenForo_Error::logException($e, false);
                $error = true;
            }
            $dw = XenForo_DataWriter::create("AddOnInstaller_DataWriter_InstallBatchEntry");
            $dw->setExistingData($entry);
            if ($error)
            {
                $dw->set('in_error', 1);
            }
            else
            {
                $dw->set('install_phase', 'installed');
            }
            $dw->save();

            $data = array(
                'addon_id' => $xmlFile['addon_id'],
                'update_url' => $dw->get('resource_url'),
                'check_updates' => 1,
                'last_checked' => XenForo_Application::$time,
                'latest_version' => $xmlFile['version_string']
            );

            $writer = XenForo_DataWriter::create('AddOnInstaller_DataWriter_Updater');

            if ($addOnModel->isDwUpdate($data['addon_id']))
            {
                $writer->setExistingData($data['addon_id']);
            }

            $writer->bulkSet($data);
            $writer->save();

            // cleanup
            $addOnModel->deleteAll($entry['extracted_files']);
        }
        }
        catch(Exception $e)
        {
            $options->set('addoninstaller_supress_cache_rebuild', false);
            if ($installed_addons)
            {
                $addOnModel->rebuildAddOnCaches();
            }
            throw $e;
        }
        $options->set('addoninstaller_supress_cache_rebuild', false);
        if ($installed_addons)
        {
            $addOnModel->rebuildAddOnCaches();
        }

        if ($next_phase == 'step-install')
        {
            return $this->responseRedirect(
                XenForo_ControllerResponse_Redirect::SUCCESS,
                XenForo_Link::buildAdminLink('add-ons/' . $next_phase, array(), array('addon_install_batch_id' => $addon_install_batch_id))
            );
        }

        $caches = $addOnModel->completeInstallBatch($addon_install_batch_id);

        return XenForo_CacheRebuilder_Abstract::getRebuilderResponse(
            $this, $caches,
            XenForo_Link::buildAdminLink('add-ons/' . $next_phase, array(), array('addon_install_batch_id' => $addon_install_batch_id))
        );
    }

    public function actionUpdateCheck()
    {
        $addOnId = $this->_input->filterSingle('addon_id', XenForo_Input::STRING);

        if ($addOnId)
        {
            return $this->responseReroute("XenForo_ControllerAdmin_AddOn", 'check');
        }

        $addOnModel = $this->_getAddOnModel();
        $addOns = $addOnModel->getAllUpdateChecks();

        $viewParams = array(
            'addOns' => $addOns
        );

        return $this->responseView('AddOnInstaller_ViewAdmin_UpdateList', 'addon_install_update_list', $viewParams);
    }

    public function actionUpdateCheckAll()
    {
        XenForo_Model::create('XenForo_Model_AddOn')->checkForUpdates();

        return $this->responseRedirect(
            XenForo_ControllerResponse_Redirect::SUCCESS,
            XenForo_Link::buildAdminLink('add-ons/update-check')
        );
    }


    public function actionCheck()
    {
        $addOnModel = $this->_getAddOnModel();

        $addOnId = $this->_input->filterSingle('addon_id', XenForo_Input::STRING);
        $addOn = $this->_getAddOnModel()->getUpdateCheckByAddOnId($addOnId);

        if (empty($addOn['update_url']))
        {
            return $this->responseError(new XenForo_Phrase('this_addon_has_no_resource_url'));
        }

        $viewParams = array();

        $updateData = $addOnModel->checkForUpdate($addOn);

        if ($updateData)
        {
            $skipUpdate = false;
            if (isset($updateData['updateVersion']) && $updateData['updateVersion'] == $addOn['skip_version'])
            {
                $skipUpdate = true;
            }

            $viewParams = array(
                'updateData' => $updateData,
                'skipUpdate' => $skipUpdate
            );
        }

        $viewParams += array(
            'addOn' => $addOn
        );

        return $this->responseView('AddOnInstaller_ViewAdmin_UpdateCheck', 'addon_install_update_check', $viewParams);
    }

    public function actionUpdateSkip()
    {
        $addOnModel = $this->_getAddOnModel();

        $addOnId = $this->_input->filterSingle('addon_id', XenForo_Input::STRING);
        $addOn = $this->_getAddOnModel()->getUpdateCheckByAddOnId($addOnId);

        if ($addOn['version_string'] == $addOn['latest_version'])
        {
            return $this->responseError(new XenForo_Phrase('this_addon_doesnt_require_update'));
        }

        if ($this->isConfirmedPost())
        {
            $writer = XenForo_DataWriter::create('AddOnInstaller_DataWriter_Updater');

            if ($addOnModel->isDwUpdate($addOnId))
            {
                $writer->setExistingData($addOnId);
            }

            $writer->set('skip_version', $addOn['latest_version']);
            $writer->save();

            return $this->responseRedirect(
                XenForo_ControllerResponse_Redirect::SUCCESS,
                XenForo_Link::buildAdminLink('add-ons/update-check'),
                new XenForo_Phrase('addon_update_has_been_skipped')
            );
        }
        else
        {
            $viewParams = array(
                'addOn' => $addOn
            );

            return $this->responseView('AddOnInstaller_ViewPublic_SkipUpdate', 'addon_update_skip', $viewParams);
        }
    }

    public function actionUpdateAdd()
    {
        $addOnId = $this->_input->filterSingle('addon_id', XenForo_Input::STRING);
        $addOn = $this->_getAddOnModel()->getAddOnById($addOnId);

        $addOn += array(
            'update_url' => '',
            'check_updates' => 1,
            'auto_update' => 0,
        );

        return $this->_getUpdateAddEditResponse($addOn);
    }

    public function actionUpdateEdit()
    {
        $addOnId = $this->_input->filterSingle('addon_id', XenForo_Input::STRING);
        $addOn = $this->_getAddOnModel()->getUpdateCheckByAddOnId($addOnId);

        return $this->_getUpdateAddEditResponse($addOn);
    }

    protected function _getUpdateAddEditResponse(array $addOn)
    {
        $viewParams = array(
            'addOn' => $addOn
        );

        return $this->responseView('AddOnInstaller_ViewAdmin_UpdateEdit', 'addon_install_update_edit', $viewParams);
    }

    public function actionUpdateSave()
    {
        $addOnModel = $this->_getAddOnModel();
        $addOnId = $this->_input->filterSingle('addon_id', XenForo_Input::STRING);
        $addOn = $this->_getAddOnModel()->getUpdateCheckByAddOnId($addOnId);

        $data = $this->_input->filter(array(
            'addon_id' => XenForo_Input::STRING,
            'update_url' => XenForo_Input::STRING,
            'check_updates' => XenForo_Input::UINT,
        ));

        $writer = XenForo_DataWriter::create('AddOnInstaller_DataWriter_Updater');

        if ($addOnModel->isDwUpdate($addOnId))
        {
            $writer->setExistingData($data['addon_id']);
        }

        $writer->bulkSet($data);
        $writer->save();

        return $this->responseRedirect(
            XenForo_ControllerResponse_Redirect::SUCCESS,
            XenForo_Link::buildAdminLink('add-ons/update-check')
        );
    }
}