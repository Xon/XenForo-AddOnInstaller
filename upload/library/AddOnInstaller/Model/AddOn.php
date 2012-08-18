<?php

class AddOnInstaller_Model_AddOn extends XFCP_AddOnInstaller_Model_AddOn
{
	public function addOnInstallerCopyFiles($source, $destination)
	{
		@mkdir($destination);
		$directory = dir( $source );
		while (FALSE !== ($readdirectory = $directory->read()))
		{
			if ($readdirectory == '.' || $readdirectory == '..')
			{
				continue;
			}
			$PathDir = $source . '/' . $readdirectory;
			if (is_dir($PathDir))
			{
				self::addOnInstallerCopyFiles($PathDir, $destination . '/' . $readdirectory);
				continue;
			}
			copy($PathDir, $destination . '/' . $readdirectory);
		}
		
		$directory->close();
		
		return;
	}
}