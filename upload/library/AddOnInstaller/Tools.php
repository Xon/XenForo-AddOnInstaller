<?php

class AddOnInstaller_Tools
{
    public static function save()
    {
        $_changedTemplates = array();
        foreach(self::$_changedTemplates as $type => $templates)
        {
            $_changedTemplates[$type] = array_unique($templates);
        }
        return $_changedTemplates;
    }

    public static function load($_changedTemplates)
    {
        if ($_changedTemplates && is_string($_changedTemplates))
        {
            $_changedTemplates = @unserialize($_changedTemplates);
        }
        if (!is_array($_changedTemplates))
        {
            return;
        }
        // merge with any existing lists
        foreach($_changedTemplates as $type => $templates)
        {
            self::$_changedTemplates[$type] = array_unique(array_merge(self::$_changedTemplates[$type], $templates));
        }
    }

    protected static $_changedTemplates = array(
        'admin' => array(),
        'public' => array(),
        'email' => array(),
    );

    protected static $_typeMap = array(
        'admin' => array('AdminTemplateReparse', 'AdminTemplate'),
        'public' => array('TemplateReparse', 'Template'),
        'email' => array('EmailTemplateReparse', 'EmailTemplate'),
    );

    public static function addTemplateToData($type, $templateTitle)
    {
        self::$_changedTemplates[$type][] = $templateTitle;
    }

    public static function getDataForRebuild()
    {
        $output = array();

        foreach (self::$_changedTemplates AS $type => $templates)
        {
            $templates = array_unique($templates);

            sort($templates);

            $output[] = array(
                'classes' => self::$_typeMap[$type],
                'data' => array(
                    'templates' => $templates
                ),
            );
        }

        return $output;
    }
}