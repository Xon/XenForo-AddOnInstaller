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
			if (!XenForo_Helper_File::createDirectory($baseDir . '/' . $installId))
			{
				return false;
			}
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
	
	/**
	* Recursively copy files from one directory to another
	* 
	* @param String $source - Source of files being moved
	* @param String $destination - Destination of files being moved
	*/
	public function recursiveCopy($source, $destination)
	{
		if(!is_dir($source))
		{
			return false;	
		}

		if(!is_dir($destination))
		{ 
			if(!XenForo_Helper_File::createDirectory($destination))
			{
				return false;
			}    
		}

		$dir = new DirectoryIterator($source);
		foreach($dir as $dirInfo)
		{
			if($dirInfo->isFile())
			{
				copy($dirInfo->getRealPath(), $destination . '/' . $dirInfo->getFilename());
			}
			else if(!$dirInfo->isDot() && $dirInfo->isDir())
			{
				$this->recursiveCopy($dirInfo->getRealPath(), $destination . '/' . $dirInfo);
			}
		}
		
		return true;
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
			'version_string' => (string)$xml['version_string']
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
		return (strstr($resourceUrl, 'xenforo.com/community/resources'));
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
					$updateUrl = 'http://xenforo.com/community/' . $updates->current()->getAttribute('href');
					
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
}