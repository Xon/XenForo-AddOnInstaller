<?php

class AddOnInstaller_ControllerAdmin_AddOn extends XFCP_AddOnInstaller_ControllerAdmin_AddOn
{
	public function actionInstallUpgrade()
	{
		$addOnModel = $this->_getAddOnModel();
		
		if ($this->isConfirmedPost())
		{
			$fileTransfer = new Zend_File_Transfer_Adapter_Http();
			$resourceUrl = $this->_input->filterSingle('resource_url', XenForo_Input::STRING);
			
			$installId = uniqid();
			
			$addToUpdates = false;

			$extractDirs = array();
			if ($fileTransfer->isUploaded('upload_file_oldskool'))
			{
				foreach ($fileTransfer->getFileInfo() AS $fileInfo)
				{
					if (empty($fileInfo['error'])) //verify no errors e.g. file not exist
					{
						$fileName = $fileInfo['tmp_name'];

						$xmlDetails = $addOnModel->getXmlType($fileName);

						$addOnExists = $addOnModel->getAddOnById($xmlDetails['addon_id']);
						if ($addOnExists)
						{
							$caches = $addOnModel->installAddOnXmlFromFile($fileName, $addOnExists['addon_id']);
						}
						else
						{
							$caches = $addOnModel->installAddOnXmlFromFile($fileName);
						}
					}
				}

				return XenForo_CacheRebuilder_Abstract::getRebuilderResponse($this, $caches, XenForo_Link::buildAdminLink('add-ons'));
			}
			elseif ($this->_input->filterSingle('server_file_oldskool', XenForo_Input::STRING))
			{
				$fileName = $this->_input->filterSingle('server_file_oldskool', XenForo_Input::STRING);

				$xmlDetails = $addOnModel->getXmlType($fileName);

				$addOnExists = $addOnModel->getAddOnById($xmlDetails['addon_id']);
				if ($addOnExists)
				{
					$caches = $addOnModel->installAddOnXmlFromFile($fileName, $addOnExists['addon_id']);
				}
				else
				{
					$caches = $addOnModel->installAddOnXmlFromFile($fileName);
				}

				return XenForo_CacheRebuilder_Abstract::getRebuilderResponse($this, $caches, XenForo_Link::buildAdminLink('add-ons'));
			}
			elseif ($fileTransfer->isUploaded('upload_file'))
			{
				foreach ($fileTransfer->getFileInfo() AS $fileInfo)
				{
					if (empty($fileInfo['error'])) //verify no errors e.g. file not exist
					{
						$fileName = $fileInfo['tmp_name'];

						if (!$dir = $addOnModel->extractZip($fileName))
						{
							continue;
						}

						$extractDirs[] = $dir;
					}
				}
			}
			elseif ($resourceUrl)
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

				if (!XenForo_Helper_File::createDirectory('install/addons/' . $installId))
				{
					return $this->responseError(new XenForo_Phrase('could_not_create_directory_permissions'));
				}

				$fileName = 'install/addons/' . $installId . '/' . $installId . '.zip';
				
				$fp = fopen($fileName, 'w');
				
				fwrite($fp, $client->request('GET')->getRawBody());
				fclose($fp);
				
				$extractDirs[] = $addOnModel->extractZip($fileName, 'install/addons', $installId);
				
				$addToUpdates = true;
			}
			$caches = array();

			if (!$extractDirs)
			{
				return $this->responseError(new XenForo_Phrase('an_unexpected_error_occurred_while_extracting_addons'));
			}

			foreach ($extractDirs AS $extractDir)
			{
				$fileList = $addOnModel->getFileListing($extractDir);
				
				$xmlFiles = array();
				$xmlFile = array();
				foreach ($fileList AS $file)
				{
					if (strstr($file['file'], '.xml'))
					{
						$xmlDetails = $addOnModel->getXmlType($file['path']);
						
						if ($xmlDetails['type'] === 'addon')
						{
							$xmlFile = array(
								'path' => $file['path'],
								'addon_id' => $xmlDetails['addon_id'],
								'version_string' => $xmlDetails['version_string']
							);
							
							break;
						}
					}
				}	
				
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
				
				$copiedFiles = array();
				foreach ($addOnDirs AS $key => $dir)
				{
					if ($key == 'upload')
					{
						$copiedFiles['upload'] = $addOnModel->recursiveCopy($dir, '.');
						
						break;
					}
					elseif ($key == 'maybeLibrary')
					{
						$addOnModel->recursiveCopy($dir . '/..', './library');
					}
					elseif ($key == 'js' || $key == 'library' || $key == 'styles')
					{
						$addOnModel->recursiveCopy($dir . '/..', './' . $key);
					}
				}
				
				if (!$xmlFile)
				{
					$addOnModel->deleteAll($extractDir);
					return $this->responseError(new XenForo_Phrase('a_valid_installable_xml_not_found'));
				}
				
				$addOnExists = $addOnModel->getAddOnById($xmlFile['addon_id']);			
				if ($addOnExists)
				{
					$caches = $addOnModel->installAddOnXmlFromFile($xmlFile['path'], $xmlFile['addon_id']);
				}
				else
				{
					$caches = $addOnModel->installAddOnXmlFromFile($xmlFile['path']);
				}
				
				$addOnModel->deleteAll($extractDir);
			}
			
			if ($addToUpdates)
			{
				$data = array(
					'addon_id' => $xmlFile['addon_id'],
					'update_url' => $resourceUrl,
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
			}

			return XenForo_CacheRebuilder_Abstract::getRebuilderResponse($this, $caches, XenForo_Link::buildAdminLink('add-ons'));
		}
		else
		{
			$viewParams = array();
			
			return $this->responseView('AddOnInstaller_ViewAdmin_Install', 'addon_install_auto', $viewParams);
		}
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