<?php

class AddOnInstaller_DataWriter_InstallBatch extends XenForo_DataWriter
{
    /**
    * Gets the fields that are defined for the table. See parent for explanation.
    *
    * @return array
    */
    protected function _getFields()
    {
        return array(
            'xf_addon_install_batch' => array(
                'addon_install_batch_id' => array('type' => self::TYPE_UINT, 'autoIncrement' => true),
                'install_date'           => array('type' => self::TYPE_UINT, 'required' => true, 'default' => XenForo_Application::$time),
                'addon_count'            => array('type' => self::TYPE_UINT_FORCED, 'default' => 0),
                'is_completed'           => array('type' => self::TYPE_BOOLEAN, 'default' => 0),
                'deploy_method'          => array('type' => self::TYPE_STRING, 'required' => true, 'maxLength' => 50),
                'user_id'                => array('type' => self::TYPE_UINT, 'required' => true),
                'username'               => array('type' => self::TYPE_STRING, 'required' => true, 'maxLength' => 50),
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
        if (!$id = $this->_getExistingPrimaryKey($data, 'addon_install_batch_id'))
        {
            return false;
        }

        return array('xf_addon_install_batch' => $this->_getAddOnModel()->getInstallBatchById($id));
    }

    /**
    * Gets SQL condition to update the existing record.
    *
    * @return string
    */
    protected function _getUpdateCondition($tableName)
    {
        return 'addon_install_batch_id = ' . $this->_db->quote($this->getExisting('addon_install_batch_id'));
    }

    /**
     * @return XenForo_Model_AddOn
     */
    protected function _getAddOnModel()
    {
        return $this->getModelFromCache('XenForo_Model_AddOn');
    }
}
