<?php

class AddOnInstaller_ControllerAdmin_AddOn extends XFCP_AddOnInstaller_ControllerAdmin_AddOn
{
	public function actionAutoInstall()
	{
		if ($this->isConfirmedPost())
		{
			$fileTransfer = new Zend_File_Transfer_Adapter_Http();
			if ($fileTransfer->isUploaded('upload_file'))
			{
				$fileInfo = $fileTransfer->getFileInfo('upload_file');
				$fileName = $fileInfo['upload_file']['tmp_name'];
			
			}
			$extractLocation = 'addon-prestage/' . preg_replace('/[^a-z0-9]/i', '_', $fileInfo['upload_file']['name']) . '/';
			$zip = new ZipArchive;
			$res = $zip->open($fileName);
			if ($res === TRUE)
			{
				$zip->extractTo($extractLocation);
				$zip->close();
			}
			else
			{
				return $this->responseError(new XenForo_Phrase('zip_file_could_not_be_opened'));
			}			
			$path = $extractLocation;
			$results = scandir($path);
			
			foreach ($results as $result)
			{
				if ($result === '.' or $result === '..') continue;
				if (is_dir($path . '/' . $result))
				{
					if ($result !== 'upload')
					{
						if (is_dir($path . $result . '/upload'))
						{							
							$uploadDir = $path . $result . '/upload';
							
							$source = $uploadDir;
							
							$destination = '.';
							
							$this->_getAddOnModel()->addOnInstallerCopyFiles($source, $destination);							
						}
					}
					elseif (is_dir($path . '/upload'))
					{
						$uploadDir = $path . '/upload';
							
						$source = $uploadDir;
							
						$destination = '.';
							
						$this->_getAddOnModel()->addOnInstallerCopyFiles($source, $destination);
					}
				}
			}
			if(!isset($uploadDir))
			{
				return $this->responseError(new XenForo_Phrase('no_upload_directory_found'));
			}
				
			$extractDir = scandir($extractLocation);
				
			$xmlFile = implode('', glob($extractLocation . '*.xml'));
	
			$caches = $this->_getAddOnModel()->installAddOnXmlFromFile($xmlFile);
	
			return XenForo_CacheRebuilder_Abstract::getRebuilderResponse($this, $caches, XenForo_Link::buildAdminLink('add-ons'));
		}
		else
		{
			return $this->responseView('XenForo_ViewAdmin_AddOn_Install', 'addon_install_auto');
		}
	}
	
	public function actionAutoUpgrade()
	{
		$addOnId = $this->_input->filterSingle('addon_id', XenForo_Input::STRING);
		if(!$addOnId)
		{
			$addOnModel = $this->_getAddOnModel();
			
			$viewParams = array(
					'addOns' => $addOnModel->getAllAddOns()
			);
			
			return $this->responseView('XenForo_ViewAdmin_AddOn_Upgrade', 'addon_upgrade_auto_selector', $viewParams);
		}
		$addOn = $this->_getAddOnOrError($addOnId);
	
		if ($this->isConfirmedPost())
		{
			$fileTransfer = new Zend_File_Transfer_Adapter_Http();
			if ($fileTransfer->isUploaded('upload_file'))
			{
				$fileInfo = $fileTransfer->getFileInfo('upload_file');
				$fileName = $fileInfo['upload_file']['tmp_name'];
			
			}
			$extractLocation = 'addon-prestage/' . preg_replace('/[^a-z0-9]/i', '_', $fileInfo['upload_file']['name']) . '/';
			$zip = new ZipArchive;
			$res = $zip->open($fileName);
			if ($res === TRUE)
			{
				$zip->extractTo($extractLocation);
				$zip->close();
			}
			else
			{
				return $this->responseError(new XenForo_Phrase('zip_file_could_not_be_opened'));
			}			
			$path = $extractLocation;
			$results = scandir($path);
			
			foreach ($results as $result)
			{
				if ($result === '.' or $result === '..') continue;
				if (is_dir($path . '/' . $result))
				{
					if ($result !== 'upload')
					{
						if (is_dir($path . $result . '/upload'))
						{							
							$uploadDir = $path . $result . '/upload';
							
							$source = $uploadDir;
							
							$destination = '.';
							
							$this->_getAddOnModel()->addOnInstallerCopyFiles($source, $destination);							
						}
					}
					elseif (is_dir($path . '/upload'))
					{
						$uploadDir = $path . '/upload';
							
						$source = $uploadDir;
							
						$destination = '.';
							
						$this->_getAddOnModel()->addOnInstallerCopyFiles($source, $destination);
					}
				}
			}
			if(!isset($uploadDir))
			{
				return $this->responseError(new XenForo_Phrase('no_upload_directory_found'));
			}
				
			$extractDir = scandir($extractLocation);
				
			$xmlFile = implode('', glob($extractLocation . '*.xml'));
	
			$caches = $this->_getAddOnModel()->installAddOnXmlFromFile($xmlFile, $addOn['addon_id']);
	
			return XenForo_CacheRebuilder_Abstract::getRebuilderResponse($this, $caches,
					XenForo_Link::buildAdminLink('add-ons') . $this->getLastHash($addOnId));
		}
		else
		{
			$viewParams = array(
					'addOn' => $addOn
			);
	
			return $this->responseView('XenForo_ViewAdmin_AddOn_Upgrade', 'addon_upgrade_auto', $viewParams);
		}
	}	
	
}