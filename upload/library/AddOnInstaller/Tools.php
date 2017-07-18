<?php

class AddOnInstaller_Tools
{
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