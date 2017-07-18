<?php

class AddOnInstaller_XenForo_DataWriter_AdminTemplateModification extends XFCP_AddOnInstaller_XenForo_DataWriter_AdminTemplateModification
{
    protected function _postSaveAfterTransaction()
    {
        parent::_postSaveAfterTransaction();

        if ($this->get('addon_id') && XenForo_Application::isRegistered('nf_addoninstallimprove_installing'))
        {
            AddOnInstaller_Tools::addTemplateToData('admin', $this->get('template'));
        }
    }
}

if (false)
{
    class XFCP_AddOnInstaller_XenForo_DataWriter_AdminTemplateModification extends XenForo_DataWriter_AdminTemplateModification
    {
    }
}
