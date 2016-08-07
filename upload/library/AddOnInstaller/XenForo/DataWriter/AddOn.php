<?php

class AddOnInstaller_XenForo_DataWriter_AddOn extends XFCP_AddOnInstaller_XenForo_DataWriter_AddOn
{
    /**
     * Post-delete handling.
     */
    protected function _postDelete()
    {
        parent::_postDelete();

        if (empty(AddOnInstaller_Listener::$_UninstallingSelf))
        {
            $addOnModel = $this->_getAddOnModel();
            $addOnModel->deleteAddOnUpdates($this->get('addon_id'));
        }
    }

    protected function _getAddOnModel()
	{
		/** @var AddOnInstaller_XenForo_Model_AddOn $addOnModel */
		$addOnModel = parent::_getAddOnModel();
		return $addOnModel;
	}
}

// ******************** FOR IDE AUTO COMPLETE ********************
if (false)
{
	class XFCP_AddOnInstaller_XenForo_DataWriter_AddOn extends XenForo_DataWriter_AddOn {}
}