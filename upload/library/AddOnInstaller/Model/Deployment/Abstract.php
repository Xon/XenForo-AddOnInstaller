<?php

abstract class AddOnInstaller_Model_Deployment_Abstract
{
    protected $addonModel = null;
    protected $isStarted = false;

    public function isStarted()
    {
        return $isStarted;
    }

    public final function start(AddOnInstaller_Model_AddOn $addonModel)
    {
        if ($this->isStarted)
        {
            return true;
        }
        $this->isStarted = true;
        $this->addonModel = $addonModel;
        $ret = $this->_start();
        return $ret;
    }

    public final function stop()
    {
        $this->isStarted = false;
        return $this->_stop();
    }

    protected function _start()
    {
        return true;
    }

    protected function _stop()
    {
        return true;
    }

    public abstract function is_dir($destination);
    public abstract function mkdir($dir);
    public abstract function copy($source, $dest);

    protected function getAddonModel()
    {
        if (empty($this->addonModel))
        {
            $this->addonModel = XenForo_Model::create('AddOnInstaller_Model_AddOn');
        }
        return $this->addonModel;
    }
}