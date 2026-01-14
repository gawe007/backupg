# Backupgg 
Version 1.1
PHP Zipping Class for files backup. Provided with symfony/mailer for notification.
This class is to help creating a script to backup all files within a folder.

## Case Point
1. To build a script to backup some folder.
2. To create a cronjob / task scheduled backup script.
3. To be a PHP class for your backup APP or Utility.

## Requirement
1. Running PHP >= 7.4
2. Composer

## Installation
Pull / Download all the files and install using composer :
```
composer install
```
Or, just download the `Backupg.php` an use it directly :
```
require("Backupg.php");
$params = ['BackupTargetFolder' => 'path/to/dir'];
$b = new Backupg($params);
```

## Usage
1. Build the script inside the `/public` folder.
2. Execute the script via `terminal` or `command line`, ex:
   ```
   php -f "path/to/your/script.php"
   ```

## Code Example
Create the script inside `/public` folder.
1. Normal Backup to some folder with no compression.
   ```
   <?php
    require __DIR__ . '/../vendor/autoload.php';
    use Backupgg\Backupg;
    $target = realpath("D:/path/to/some/folder");
    $saveLocation = realpath("D:/path/to/save/location");
    $params = [
      'BackupgTargetFolder' => $target,
      'zipSaveLocation' => $saveLocation,
      'useCompression' => false
    ];
    $b = new Backupg($params);
    $logFilePath = $b->getLogFilePath();
    $logFile = $b->getLogFileName();
    ?>
   ```
2. Backup with replace flags, custom name and compression.
   ```
   <?php
    require __DIR__ . '/../vendor/autoload.php';
    use Backupgg\Backupg;

    $customName = "Custom ZIP Name";
    $target = realpath("D:/path/to/some/folder");
    $saveLocation = realpath("D:/path/to/save/location");
    $params = [
      'BackupgTargetFolder' => $target,
      'zipSaveLocation' => $saveLocation,
      'customZipName' => $customName,
      'useCompression' => true
    ];
    $b = new Backup($params);
    $logFilePath = $b->getLogFilePath();
    $logFile = $b->getLogFileName();
    ?>
   ```
3. Backup with file extension exclusion and manual start.
   ```
   <?php
    require __DIR__ . '/../vendor/autoload.php';
    use Backupgg\Backupg;

    $target = realpath("D:/path/to/some/folder");
    $params = [
      'BackupgTargetFolder' => $target,
      'excludeExtensions' => ['exe', 'dll'], // exclude dll and exe files from selection
      'autoStart' => false
    ];
    $b = new Backupg($params);
    
    $b->startBackup();
    ?>
   ```

## Accepted Parameters
|        Name         |   Type   |
|:...................:|:........:|
|'BackupTargetFolder' | `string` | directory location to be zipped. Required.
|'zipSaveLocation'    | `string` | directory location for the created zip to be saved.
|'customZipName'      | `string?`| custom name for the created zip file.
|'replace'            | `bool`   | flags for replace file. Default `false`.
|'useCompression'     | `bool`   | flags of the zip compression. Default `true`.
|'includeDotFile'     | `bool`   | flags for including files started with a dot. Default `false`.
|'excludeDir'         | `array`  | excluded directory path. **Case Sensitive**
|'excludeExtensions'  | `array`  | excluded file extension.
|'includeExtensions'  | `array`  | included file extension.
|'beforeDate'         | `string` | only files before this date will be read.
|'afterDate'          | `string` | only files after this date will be read.
|'memoryCap'          | `string` | memory threshold for the Zipping file process. To be converted with strtotime()
|'autoStart'          | `bool`   | flags for Backupg process autoStart. Default `false`.
`excludeExtensions` and `includeExtensions` cannot be used at the same time.
Other or wrong value will be **ignored**.

## Special Note
1. `.htaccess` inside the `/public` folder exist to prevent webaccess to the folder. Modify if needed.
2. The created script only need to be accessed with PHP so make sure the installation directory is reachable and readable by the PHP.

## History
Version 1.0
- Default Backupg operations.
- Add useCompression
- Fix :
  * memory cap for unexpected stopping while ziping files

Version 1.1
- Change parameter insertion from individual to array template.
- Add :
  * include dot file.
  * exclude dir.
  * exclude by file extension.
  * include by file extension.
  * file selection by date.
  * memory cap set.
  * auto or manual start.
