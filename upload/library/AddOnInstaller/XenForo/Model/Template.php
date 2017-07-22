<?php

class AddOnInstaller_XenForo_Model_Template extends XFCP_AddOnInstaller_XenForo_Model_Template
{
    public function reparseNamedTemplates(array $templates, $maxExecution = 0, $startStyle = 0)
    {
        $db = $this->_getDb();

        $styles = $this->getModelFromCache('XenForo_Model_Style')->getAllStyles();
        $styleIds = array_merge([0], array_keys($styles));
        sort($styleIds);

        $lastStyle = 0;
        $lastTemplate = null;
        $startTime = microtime(true);
        $complete = true;

        XenForo_Db::beginTransaction($db);

        foreach ($styleIds AS $styleId)
        {
            if ($styleId < $startStyle)
            {
                continue;
            }

            $lastStyle = $styleId;
            $lastTemplate = null;

            $fullTemplates = $this->getTemplatesInStyleByTitles($templates, $styleId);
            ksort($fullTemplates);
            foreach ($fullTemplates AS $key => $template)
            {
                if ($lastTemplate && strcmp($key, $lastTemplate) < 0)
                {
                    continue;
                }
                $lastTemplate = $key;

                $this->reparseTemplate($template, false);

                if ($maxExecution && (microtime(true) - $startTime) > $maxExecution)
                {
                    $complete = false;
                    break 2;
                }
            }
        }

        XenForo_Db::commit($db);

        if ($complete)
        {
            return true;
        }
        else
        {
            return array($lastStyle, $lastTemplate, $templates);
        }
    }

    public function compileNamedTemplates(array $templates, $maxExecution = 0, $startStyle = 0)
    {
        $db = $this->_getDb();

        $styles = $this->getModelFromCache('XenForo_Model_Style')->getAllStyles();
        $styleIds = array_merge(array(0), array_keys($styles));
        sort($styleIds);

        $lastStyle = 0;
        $lastTemplate = null;
        $startTime = microtime(true);
        $complete = true;

        XenForo_Db::beginTransaction($db);

        foreach ($styleIds AS $styleId)
        {
            if ($styleId < $startStyle)
            {
                continue;
            }

            $lastStyle = $styleId;
            $lastTemplate = null;

            $fullTemplates = $this->getTemplatesInStyleByTitles($templates, $styleId);
            ksort($fullTemplates);
            foreach ($fullTemplates AS $key => $template)
            {
                if ($lastTemplate && strcmp($key, $lastTemplate) < 0)
                {
                    continue;
                }
                $lastTemplate = $key;

                $this->compileNamedTemplateInStyleTree($template['title'], $template['style_id']);

                if ($maxExecution && (microtime(true) - $startTime) > $maxExecution)
                {
                    $complete = false;
                    break 2;
                }
            }
        }

        $compiledRemove = array();
        if ($complete)
        {
            $compiledRemove = $db->fetchAll("
				SELECT DISTINCT c.title, c.style_id
				FROM xf_template_compiled AS c
				LEFT JOIN xf_template_map AS m ON (c.title = m.title AND c.style_id = m.style_id)
				WHERE m.title IS NULL
			");
            foreach ($compiledRemove AS $remove)
            {
                $db->delete('xf_template_compiled',
                    "style_id = " . $db->quote($remove['style_id']) . " AND title = " . $db->quote($remove['title'])
                );
            }

            $this->getModelFromCache('XenForo_Model_Style')->updateAllStylesLastModifiedDate();
            $this->getModelFromCache('XenForo_Model_AdminTemplate')->updateAdminStyleLastModifiedDate();
        }

        XenForo_Db::commit($db);
        if (XenForo_Application::get('options')->templateFiles)
        {
            foreach($compiledRemove as $remove)
            {
                XenForo_Template_FileHandler::delete($remove['title'], $remove['style_id'], null);
            }
        }

        if ($complete)
        {
            return true;
        }
        else
        {
            return array($lastStyle, $lastTemplate, $templates);
        }
    }
}

if (false)
{
    class XFCP_AddOnInstaller_XenForo_Model_Template extends XenForo_Model_Template
    {
    }
}
