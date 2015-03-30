<?php

class AddOnInstaller_DataWriter_AddOn extends XFCP_AddOnInstaller_DataWriter_AddOn
{
	/**
	 * Post-delete handling.
	 */
	protected function _postDelete()
	{
		$parent = parent::_postDelete();
		
		$addOnModel = $this->_getAddOnModel();
		$addOnModel->deleteAddOnUpdates($this->get('addon_id'));
		
		return $parent;
	}
}