# backupg
PHP Zipping Class for Backup. Provided with symfony/mailer for notification.
This class is to help creating automated/not script to backup all files within a folder. 
Probably will add some feature like file filter selection etc later.

## Case Point
1. To build 1 execute to backup some folder.
2. To create a cronjob / task scheduled backup.

## Requirement
1. Running PHP >= 7.4
2. Composer

## Installation
Pull / Download all the files and install using composer :
```
composer install
```

## Usage
1. Build the script inside the `/public` folder.
2. Execute the script via `terminal` or `command line`, ex:
   ```
   php -f "path/to/your/script.php"
   ```

## Example
1. Normal backup to some folder
   ```
    $target = realpath("D:/path/to/some/folder");
    $saveLocation = realpath("D:/path/to/save/location");
    
    $b = new Backup($target, $saveLocation, false, false);
    $logFilePath = $b->getLogFilePath();
    $logFile = $b->getLogFileName();
   ```
2. Backup with replace flags
   ```
    $target = realpath("D:/path/to/some/folder");
    $saveLocation = realpath("D:/path/to/save/location");
    
    $b = new Backup($target, $saveLocation, "Backup files", true);
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
`.htaccess` inside the `/public` folder is to prevent webaccess to the folder.
