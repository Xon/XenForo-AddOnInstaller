<?php

class AddOnInstaller_Model_Deployment_copy extends AddOnInstaller_Model_Deployment_Abstract
{
    public function is_dir($destination)
    {
        return is_dir($destination);
    }

    public function mkdir($destination)
    {
        return XenForo_Helper_File::createDirectory($destination);
    }

    public function copy($source, $dest)
    {
        return @copy($source, $dest);
    }

    protected function _stop()
    {
        if ($this->addonModel)
        {
            $this->addonModel->InvalidateOpCache();
        }
    }
}