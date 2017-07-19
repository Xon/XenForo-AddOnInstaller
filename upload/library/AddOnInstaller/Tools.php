<?php

class AddOnInstaller_Tools
{
    public static function save()
    {
        $_changeTracking = array();
        foreach(self::$_changeTracking as $key => $values)
        {
            if (isset(self::$_typeMap[$key]) && is_array($values))
            {
                $_changeTracking[$key] = array_unique($values);
                if (empty($_changeTracking[$key]))
                {
                    unset($_changeTracking[$key]);
                }
            }
            else
            {
                $_changeTracking[$key] = $values;
            }
        }
        return $_changeTracking;
    }

    public static function load($_changeTracking)
    {
        if ($_changeTracking && is_string($_changeTracking))
        {
            $_changeTracking = @unserialize($_changeTracking);
        }
        if (!is_array($_changeTracking))
        {
            return;
        }
        // merge with any existing lists
        foreach($_changeTracking as $type => $value)
        {                
            if (isset(self::$_typeMap[$type]) && isset(self::$_changeTracking[$type]) && is_array(self::$_changeTracking[$type]))
            {
                self::$_changeTracking[$type] = array_unique(array_merge(self::$_changeTracking[$type], $value));
            }
            else if (array_key_exists($type, self::$_changeTracking))
            {
                self::$_changeTracking[$type] = $value;
            }
                
        }
    }

    protected static $_changeTracking = array(
        'admin' => array(),
        'public' => array(),
        'email' => array(),
        'permissionHash' => null, 
    );

    protected static $_typeMap = array(
        'admin' => array('AdminTemplateReparse', 'AdminTemplate'),
        'public' => array('TemplateReparse', 'Template'),
        'email' => array('EmailTemplateReparse', 'EmailTemplate'),
    );

    public static function setPermissionsHash($hash)
    {
        self::$_changeTracking['permissionHash'] = $hash;
    }
    
    public static function getPermissionsHash()
    {
        return self::$_changeTracking['permissionHash'];
    }

    public static function addTemplateToData($type, $templateTitle)
    {
        self::$_changeTracking[$type][] = $templateTitle;
    }

    public static function getDataForRebuild()
    {
        $output = array();

        foreach (self::$_changeTracking AS $type => $templates)
        {
            if (empty(self::$_typeMap[$type]))
            {
                continue;
            }
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