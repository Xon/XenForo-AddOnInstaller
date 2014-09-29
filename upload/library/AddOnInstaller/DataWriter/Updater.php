<?php

class AddOnInstaller_DataWriter_Updater extends XenForo_DataWriter
{
	/**
	* Gets the fields that are defined for the table. See parent for explanation.
	*
	* @return array
	*/
	protected function _getFields()
	{
		return array(
			'xf_addon_update_check' => array(
				'addon_id'				=> array('type' => self::TYPE_STRING),
				'update_url'			=> array('type' => self::TYPE_STRING,
					'verification' => array('$this', '_verifyUpdateUrl')),
				'check_updates'			=> array('type' => self::TYPE_BOOLEAN),
				'last_checked'			=> array('type' => self::TYPE_UINT),
				'latest_version'		=> array('type' => self::TYPE_STRING),
				'skip_version'			=> array('type' => self::TYPE_STRING),				
			),
		);
	}			
	
	/**
	* Gets the actual existing data out of data that was passed in. See parent for explanation.
	*
	* @param mixed
	*
	* @return array|false
	*/
	protected function _getExistingData($data)
	{
		if (!$id = $this->_getExistingPrimaryKey($data, 'addon_id'))
		{
			return false;
		}

		return array('xf_addon_update_check' => $this->_getAddOnModel()->getUpdateCheckByAddOnId($id));
	}

	/**
	* Gets SQL condition to update the existing record.
	*
	* @return string
	*/
	protected function _getUpdateCondition($tableName)
	{
		return 'addon_id = ' . $this->_db->quote($this->getExisting('addon_id'));
	}	
	
	/**
	 * Verifies that the provided URL is a valid Resource Manager URL.
	 *
	 * @param string $resourceUrl
	 *
	 * @return boolean
	 */
	protected function _verifyUpdateUrl(&$resourceUrl)
	{
		if ($this->_getAddOnModel()->isResourceUrl($resourceUrl))
		{
			$resourceUrl = rtrim($resourceUrl, '/');
			return true;
		}
		else
		{
			$this->error(new XenForo_Phrase('only_resource_manager_urls_are_valid'), 'update_url');
			return false;
		}
	}
	
	/**
	 * @return XenForo_Model_AddOn
	 */
	protected function _getAddOnModel()
	{
		return $this->getModelFromCache('XenForo_Model_AddOn');
	}		
}
