# XenForo-AddOnInstaller
Ultimate tool to Install or Upgrade Add-ons from ZIP/XML files or direct from the XenForo Community Resource Manager.

# Introduction

Add-on Install & Upgrade is an invaluable tool for Admins as it aims to make every aspect of add-on management as easy as possible. This includes installation by ZIP files, installation direct from the XenForo Community Resource Manager or the manual upload and XML import method.

Files can either be deployed by copying the files across the file system or copying using an FTP client built into the add-on.

# Known Issues
 Please be aware of these known issues:
- Some server configurations may not support Add-on Install & Upgrade
 - If you get permissions errors, please contact your host or server person.
 - The general problem is usually the user which your web server runs as doesn't have permission on the file system to copy, delete or create files.
 - A solution for this is completely beyond the scope of any developer. A server configuration change is required.

- If you receive an error relating to the ZipArchive class, this means your PHP wasn't compiled with this (pretty standard) feature. Consult your host or server person.
- If you've only ever registered using Facebook then your account actually doesn't have a password at all. To use this feature go and set a password in your Account settings. Once you've done that you can log in with a password. Facebook login will still work too. Just not with my add on (it wouldn't be possible).
- If you have Two Step Verification enabled on your XenForo Community account, you will not be able to install add-ons via the RM.

# Installation
1. Download the zip file.
2. Extract its contents to your hard drive.
3. Upload the contents of the upload folder to the root of your XenForo installation.
4. Complete install via the Admin CP using the provided XML file.
