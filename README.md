# backupg
PHP Zipping Class for Backup. Provided with symfony/mailer for notification.

# Example
1. Normal backup to some folder
   ```
    $target = realpath("D:/path/to/some/folder");
    $saveLocation = realpath("D:/path/to/save/location");
    
    $b = new Backup($target, $saveLocation, false, false);
    $logFilePath = $b->getLogFilePath();
    $logFile = $b->getLogFileName();</code>
   ```
3. Backup with replace flags
   ```
    $target = realpath("D:/path/to/some/folder");
    $saveLocation = realpath("D:/path/to/save/location");
    
    $b = new Backup($target, $saveLocation, "Backup files", true);
    $logFilePath = $b->getLogFilePath();
    $logFile = $b->getLogFileName();</code>
   ```
