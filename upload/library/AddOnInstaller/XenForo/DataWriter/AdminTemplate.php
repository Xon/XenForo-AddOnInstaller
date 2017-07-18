<?php

class AddOnInstaller_XenForo_DataWriter_AdminTemplate extends XFCP_AddOnInstaller_XenForo_DataWriter_AdminTemplate
{
    protected function _postSaveAfterTransaction()
    {
        parent::_postSaveAfterTransaction();

        if ($this->get('addon_id') && XenForo_Application::isRegistered('nf_addoninstallimprove_installing'))
        {
            AddOnInstaller_Tools::addTemplateToData('admin', $this->get('title'));
        }
    }
}

if (false)
{
    class XFCP_AddOnInstaller_XenForo_DataWriter_AdminTemplate extends XenForo_DataWriter_AdminTemplate
    {
    }
}