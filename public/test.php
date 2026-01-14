<?php
require __DIR__ . '/../vendor/autoload.php';
use Backupg\Backupg;
use Symfony\Component\Mailer\Transport;
use Symfony\Component\Mailer\Mailer;
use Symfony\Component\Mime\Email;

$target = realpath("D:/path/to/some/folder");
$saveLocation = realpath("D:/path/to/save/location");

$b = new Backupg([
    'backupTargetFolder' => $target, 
    'zipSaveLocation' => $saveLocation, 
    'customZipName' => "Backup 1",
    'replace' => true,
    'useCompression' => true,
    'excludeDir' => [
            'dir2'
        ],
    'excludeExtensions' => [
        'jfif',
        'exe'
    ],
    'beforeDate' => date('Y-m-d')
    ]);

$logFilePath = $b->getLogFilePath();
$logFile = $b->getLogFileName();

/*
// Example: Gmail SMTP with TLS
$dsn = 'smtp://someguy@gmail.com:yourappapssword@smtp.gmail.com:587';
$transport = Transport::fromDsn($dsn);
$mailer = new Mailer($transport);

$email = (new Email())
    ->from('test@localhost.com')
    ->to('someguy@gmail.com')
    ->subject('Backup Files')
    ->text('Backup succeded')
    ->attachFromPath($logFilePath, $logFile);

$mailer->send($email);
*/