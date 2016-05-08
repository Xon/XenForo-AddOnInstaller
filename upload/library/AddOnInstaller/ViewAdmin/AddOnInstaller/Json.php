<?php

class AddOnInstaller_ViewAdmin_Json extends XenForo_ViewAdmin_Base
{
    public function renderJson()
    {
        return json_encode($this->_params);
    }
}