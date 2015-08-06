<?php

class AddOnInstaller_DataWriter_InstallBatchEntry extends XenForo_DataWriterEntry
{
    /**
    * Gets the fields that are defined for the table. See parent for explanation.
    *
    * @return array
    */
    protected function _getFields()
    {
        return array(
            'xf_addon_install_batch'           => array(
                'addon_install_batch_entry_id' => array('type' => self::TYPE_UINT, 'autoIncrement' => true)
                'addon_install_batch_id'       => array('type' => self::TYPE_UINT, 'required' => true)
                'addon_id'                     => array('type' => self::TYPE_STRING, 'max' => 25, 'default' => ''),
                'version_string'               => array('type' => self::TYPE_STRING,  'maxLength' => 30, 'default' => ''),
                'install_phase'                => array('type' => self::TYPE_STRING, 'default' => 'uploaded',
					'allowedValues' => array('uploaded','extracted','deployed','installed'))
                'in_error'                     => array('type' => self::TYPE_UINT_FORCED)
                'original_filename'            => array('type' => self::TYPE_STRING, 'max' => 1024, 'default' => ''),
                'files'                        => array('type' => self::TYPE_STRING, 'max' => 1024, 'default' => ''),
                'xml_file'                     => array('type' => self::TYPE_STRING, 'max' => 1024, 'default' => ''),
                'install_order'                => array('type' => self::TYPE_UINT_FORCED),
            ),
        );
    }

    public $batch = null;

	protected final function _preSave()
	{
		if ($this->isInsert())
        {
            if ($this->batch === null)
            {
                $batch = $this->_getAddOnModel()->getInstallBatchById($this->get('addon_install_batch_entry_id'));
                $this->batch = XenForo_DataWriter::create("AddOnInstaller_DataWriter_InstallBatch");
                $this->batch->setExistingData($batch);
            }
            $addon_count = $this->batch->get('addon_count') + 1;
            $this->batch->set('addon_count', $addon_count);
            $this->batch->save();

            $this->set('install_order', $addon_count);
        }
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
        if (!$id = $this->_getExistingPrimaryKey($data, 'addon_install_batch_entry_id'))
        {
            return false;
        }

        return array('xf_addon_update_check' => $this->_getAddOnModel()->getInstallBatchEntryById($id));
    }

    /**
     * @return XenForo_Model_AddOn
     */
    protected function _getAddOnModel()
    {
        return $this->getModelFromCache('XenForo_Model_AddOn');
    }
}
