<?php

class AddOnInstaller_XenForo_Deferred_Template extends XFCP_AddOnInstaller_XenForo_Deferred_Template
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
            'position' => 0,
            'mapped' => false,
            'templates' => array(),
        ), $data);

        /* @var $templateModel AddOnInstaller_XenForo_Model_Template */
        $templateModel = XenForo_Model::create('XenForo_Model_Template');

        if ($data['startStyle'] == 0 && !$data['mapped'])
        {
            $s = microtime(true);
            $templateModel->insertTemplateMapForStyles($templateModel->buildTemplateMapForStyleTree(0), true);
            $data['mapped'] = true;

            $maxExec = ($targetRunTime ? $targetRunTime - (microtime(true) - $s) : 0);
        }
        else
        {
            $maxExec = $targetRunTime;
        }

        $actionPhrase = new XenForo_Phrase('rebuilding');
        $typePhrase = new XenForo_Phrase('templates');
        $status = sprintf('%s... %s %s', $actionPhrase, $typePhrase, str_repeat(' . ', $data['position']));

        if (!$targetRunTime || $maxExec > 1)
        {
            $result = $templateModel->compileNamedTemplates($data['templates'], $maxExec, $data['startStyle'], $data['startTemplate']);
        }
        else
        {
            $result = false;
        }

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
    class XFCP_AddOnInstaller_XenForo_Deferred_Template extends XenForo_Deferred_Template
    {
    }
}