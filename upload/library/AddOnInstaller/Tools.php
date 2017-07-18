<?php

class AddOnInstaller_Tools
{
    public function save()
    {
        $_changedTemplates = array();
        foreach(self::$_changedTemplates as $type => $templates)
        {
            $_changedTemplates[$type] = array_unique($templates);
        }
        return $_changedTemplates;
    }

    public function load($_changedTemplates)
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

    protected static $_changedTemplates = [
        'admin' => [],
        'public' => [],
        'email' => []
    ];

    protected static $_typeMap = [
        'admin' => ['AdminTemplateReparse', 'AdminTemplate'],
        'public' => ['TemplateReparse', 'Template'],
        'email' => ['EmailTemplateReparse', 'EmailTemplate'],
    ];

    public static function addTemplateToData($type, $templateTitle)
    {
        self::$_changedTemplates[$type][] = $templateTitle;
    }

    public static function getDataForRebuild()
    {
        $output = [];

        foreach (self::$_changedTemplates AS $type => $templates)
        {
            $templates = array_unique($templates);

            sort($templates);

            $output[] = [
                'classes' => self::$_typeMap[$type],
                'data' => [
                    'templates' => $templates
                ]
            ];
        }

        return $output;
    }
}