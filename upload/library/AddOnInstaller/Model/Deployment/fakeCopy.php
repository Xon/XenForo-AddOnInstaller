<?php

class AddOnInstaller_Model_Deployment_fakeCopy extends AddOnInstaller_Model_Deployment_Abstract
{
    public function is_dir($destination)
    {
        return false;
    }

    public function mkdir($destination)
    {
        XenForo_Error::logException(new Exception("Creating $destination"), false);
        return true;
    }

    public function copy($source, $dest)
    {
        XenForo_Error::logException(new Exception("copying file $source to $dest"), false);
        return true;
    }

    protected function _stop()
    {
        throw new Exception("Aborting deployment");
    }
}