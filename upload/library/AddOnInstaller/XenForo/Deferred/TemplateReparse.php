<?php

class AddOnInstaller_XenForo_Deferred_TemplateReparse extends XFCP_AddOnInstaller_XenForo_Deferred_TemplateReparse
{
    public function execute(array $deferred, array $data, $targetRunTime, &$status)
    {
        if (!isset($data['templates']))
        {
            return parent::execute($deferred, $data, $targetRunTime, $status);
        }

        $data = array_merge(array(
            'startStyle' => 0,
            'startTemplate' => null,
            'startTemplate' => 0,
            'position' => 0,
            'templates' => array(),
        ), $data);

        /* @var $templateModel AddOnInstaller_XenForo_Model_Template */
        $templateModel = XenForo_Model::create('XenForo_Model_Template');

        $actionPhrase = new XenForo_Phrase('reparsing');
        $typePhrase = new XenForo_Phrase('templates');
        $status = sprintf('%s... %s %s', $actionPhrase, $typePhrase, str_repeat(' . ', $data['position']));

        if (empty($data['templates']))
        {
            return false;
        }

        $result = $templateModel->reparseNamedTemplates($data['templates'], $targetRunTime, $data['startStyle'], $data['startTemplate']);

        if ($result === null)
        {
            return true;
        }
        else
        {
            $data['startStyle'] = $result[0];
            $data['startTemplate'] = $result[1];
            $data['templates'] = $result[2];
            $data['position']++;

            return $data;
        }
    }
}

if (false)
{
    class XFCP_AddOnInstaller_XenForo_Deferred_TemplateReparse extends XenForo_Deferred_TemplateReparse
    {
    }
}
