<?php

class AddOnInstaller_Option_DeploymentMethodChooser
{
    public static function renderOption(XenForo_View $view, $fieldPrefix, array $preparedOption, $canEdit)
    {
        $methods = XenForo_Model::create('XenForo_Model_AddOn')->getAddonDeploymentMethods();

        $options = array();
        foreach($methods as $key => $method)
        {
			$options[] = array(
				'value' => $key,
				'label' => new XenForo_Phrase('deployment_method_'.$key),
				'selected' => $preparedOption['option_value'] == $key,
				'depth' => 0,
			);
        }

        $preparedOption['formatParams'] = $options;

		return XenForo_ViewAdmin_Helper_Option::renderOptionTemplateInternal(
			'option_deployment_select', $view, $fieldPrefix, $preparedOption, $canEdit
		);
    }
}