<?php
require __DIR__ . '/../vendor/autoload.php';
use Backupg\Backup;
use Symfony\Component\Mailer\Transport;
use Symfony\Component\Mailer\Mailer;
use Symfony\Component\Mime\Email;

$target = realpath(__DIR__ . "/../../pom-usermanager");
$targetZip = realpath(__DIR__ . "/../../../../../backup");

$b = new Backup($target, $targetZip, "Backup pom-usermanager");
$logFilePath = $b->getLogFilePath();
$logFile = $b->getLogFileName();

/*
// Example: Gmail SMTP with TLS
$dsn = 'smtp://ga12wijaya@gmail.com:wkwvabtjoybuztar@smtp.gmail.com:587';
$transport = Transport::fromDsn($dsn);
$mailer = new Mailer($transport);

$email = (new Email())
    ->from('test@localhost.com')
    ->to('ga12wijaya@gmail.com')
    ->subject('Backup Files')
    ->text('Backup succeded')
    ->attachFromPath($logFilePath, $logFile);

$mailer->send($email);
*/
