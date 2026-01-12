# backupg
PHP Zipping Class for Backup. Provided with symfony/mailer for notification.
This class is to help creating automated/not script to backup all files within a folder. 
Probably will add some feature like file filter selection etc later.

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
Or, just download the `backup.php` an use it directly :
```
require("./parent/dir/backup.php");
$backup = new backup($target, $saveLocation, false, false);
```

## Usage
1. Build the script inside the `/public` folder.
2. Execute the script via `terminal` or `command line`, ex:
   ```
   php -f "path/to/your/script.php"
   ```

## Example
Create the script inside '/public' folder.
1. Normal backup to some folder
   ```
    $target = realpath("D:/path/to/some/folder");
    $saveLocation = realpath("D:/path/to/save/location");
    
    $b = new Backup($target, $saveLocation, false, false);
    $logFilePath = $b->getLogFilePath();
    $logFile = $b->getLogFileName();
   ```
2. Backup with replace flags and custom name
   ```
    $customName = "Custom ZIP Name";
    $target = realpath("D:/path/to/some/folder");
    $saveLocation = realpath("D:/path/to/save/location");
    
    $b = new Backup($target, $saveLocation, $customName, true);
    $logFilePath = $b->getLogFilePath();
    $logFile = $b->getLogFileName();
   ```
Don't forget to include the autoload and the class inside the script:
```
<?php
require __DIR__ . '/../vendor/autoload.php';
use Backupg\Backup;
```

## Special Note
1. `.htaccess` inside the `/public` folder is to prevent webaccess to the folder.
2. The created script only need to be accessed with PHP so make sure the installation directory is reachable and readable by the PHP.
