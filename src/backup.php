<?php
declare(strict_types=1);
namespace Backupg;

use ZipArchive;
use Throwable;
use InvalidArgumentException;
use RuntimeException;
use FilesystemIterator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

/**
 * Backup class
 * version 1
 * by Gawe007@github.com
 */
class Backup{
    private $targetDir;
    private $customName;
    private array $arrayFiles = [];
    private string $zipLocation = __DIR__ . DIRECTORY_SEPARATOR ."alternative_zip_folder";
    private string $logLocation = __DIR__ . DIRECTORY_SEPARATOR ."log";
    private string $logFile = "";
    private string $logfilename = "";
    private string $zipPath = "";

    private bool $useCompressionOnZip = true;

    public function __construct(string $targetPath, string $targetZipSaveLocation, string|null $customTargetFileName)
    {
        $this->customName = $customTargetFileName ?? "";
        $this->setup_log();
        if($targetZipSaveLocation){
            if(is_dir($targetZipSaveLocation)) {
                $this->zipLocation = $targetZipSaveLocation;
            }
        }
        $this->write_log("Backupg V.1.0 by gawe007@github.com");
        $this->write_log("-----------------------------------");
        $this->write_log("Starting Backup Process ".$this->customName);
        $this->write_log("Checking dir $targetPath ...");
        if(!is_dir($targetPath)) {
            $this->write_log("Dir : ".$targetPath ." unreadable");
            die("Stopping....");
        }
        $this->targetDir = $targetPath;
        $this->start();
    }

    public function setUseCompressionOnZip(bool $v = true): void{
        $this->useCompressionOnZip = $v;
    }

    private function start(): void {
        $this->getFiles();
        if($this->arrayFiles){
            $this->zipPath = $this->createZipFromFiles($this->arrayFiles, $this->zipLocation, $this->targetDir);
        }else{
            die("Files returned zero. Backup Stopped...");
        }

        $this->write_log("Backup Process Succeeded.");
        $this->write_log("ZIP File: " . $this->zipPath);
        $this->write_log("Log File: " . $this->logFile);
    }

    private function getFiles(): void {
       try {
            // Scan and get files with readability info
            $filesInfo = $this->getFilesWithReadability($this->targetDir, true, null, false, $this->logFile);

            // Display summary and a sample
            $total = count($filesInfo);
            $unreadableCount = 0;
            foreach ($filesInfo as $f) {
                if (!$f['readable']) {
                    $unreadableCount++;
                }
            }

            $this->write_log("Total files scanned: $total");
            $this->write_log("Unreadable files logged: $unreadableCount");
            $this->arrayFiles = $filesInfo;
        } catch (Throwable $e) {
            $this->write_log('Top-level exception: ' . $e->getMessage(), $this->logFile);
            $this->write_log("An error occurred while scanning directories. Check the log file for details." . PHP_EOL . "\n");
            die("Backup Stopped...\n");
        }
    }

    /**
     * Recursively scan a directory and return an array of files with readability info
     *
     * Each returned item is an associative array:
     *  - path string
     *  - readable bool
     *
     * @param string $dir Directory to scan
     * @param bool $relative If true, return paths relative to $dir
     * @param array|null $extensions Optional array of lowercase extensions to include; null = all
     * @param bool $includeDotFiles Include files starting with a dot
     * @param string $logFile Path to the log file for writing runtime errors and unreadable files
     * @return array List of ['path' => string, 'readable' => bool]
     * @throws InvalidArgumentException If $dir is not a directory or not readable
     */
    private function getFilesWithReadability(
        string $dir,
        bool $relative = false,
        ?array $extensions = null,
        bool $includeDotFiles = false,
        string $logFile = ''
    ): array {
        $dir = rtrim($dir, DIRECTORY_SEPARATOR);
        if ($dir === '') {
            throw new InvalidArgumentException('Empty directory path provided');
        }
        if (!is_dir($dir) || !is_readable($dir)) {
            throw new InvalidArgumentException("Directory not found or not readable: $dir");
        }

        $results = [];

        try {
            $flags = FilesystemIterator::SKIP_DOTS;
            $rdi = new RecursiveDirectoryIterator($dir, $flags);
            $it  = new RecursiveIteratorIterator($rdi, RecursiveIteratorIterator::SELF_FIRST);

            foreach ($it as $item) {
                if (!$item->isFile()) {
                    continue;
                }

                $basename = $item->getBasename();

                if (!$includeDotFiles && isset($basename[0]) && $basename[0] === '.') {
                    continue;
                }

                if ($extensions !== null) {
                    $ext = strtolower(pathinfo($basename, PATHINFO_EXTENSION));
                    if ($ext === '' || !in_array($ext, $extensions, true)) {
                        continue;
                    }
                }

                $path = $item->getPathname();
                $displayPath = $path;
                if ($relative) {
                    $displayPath = ltrim(substr($path, strlen($dir)), DIRECTORY_SEPARATOR);
                }

                // Check readability
                $isReadable = is_readable($path);

                // If not readable, log it
                if (!$isReadable && $logFile !== '') {
                    $this->write_log("Unreadable file detected: $path", $logFile);
                }

                $results[] = [
                    'path' => $displayPath,
                    'readable' => $isReadable
                ];
            }

            // Sort by path for predictable order
            usort($results, function ($a, $b) {
                return strcasecmp($a['path'], $b['path']);
            });

        } catch (Throwable $t) {
            if ($logFile !== '') {
                $this->write_log('Exception during directory scan: ' . $t->getMessage(), $logFile);
            }
            throw $t;
        }

        return $results;
    }

    private function setup_log(): void {
        $this->logfilename = $this->customName ? $this->customName."_".time().".txt" : "Log_backup_".time().".txt";
        $this->logFile = $this->logLocation . DIRECTORY_SEPARATOR . $this->logfilename ;

        if (!is_dir($this->logLocation)) {
            if (!mkdir($this->logLocation, 0755, true) && !is_dir($this->logLocation)) {
                // If we cannot create the log directory, fallback to sys_get_temp_dir
                $this->logLocation  = sys_get_temp_dir();
                $this->logFile = $this->logLocation . '/log_file_'.time().'.txt';
            }
        }
    }

    private function write_log(string $message): void
    {
        $logFile = $this->logFile;
        $timestamp = date('Y-m-d H:i:s');
        $line = "[$timestamp] $message\n";
        @file_put_contents($logFile, $line, FILE_APPEND | LOCK_EX);
        if (PHP_SAPI === 'cli' || php_sapi_name() === 'cli') {
            // CLI: use PHP_EOL and plain text
            echo sprintf($line, PHP_EOL);
        } else {
            // Web: convert newlines to <br> or use HTML formatting
            echo nl2br(htmlspecialchars($line));
        }
    }

    /**
     * Create a ZIP archive from an array of files produced by getFilesWithReadability.
     *
     * @param array $files: array of ['path' => string, 'readable' => bool]
     * @param string $zipDir: directory where the zip and its log will be created (must be writable or creatable)
     * @param string $baseDir: optional base directory used to resolve relative file paths in $files; if empty, paths are treated as absolute
     * @param bool $useRelativeNames: if true, files are stored in the zip using their path relative to $baseDir; otherwise stored using basename
     *
     * @return mixed path to the created zip on success, or false on failure.
     */
    function createZipFromFiles(array $files, string $zipDir, string $baseDir = '', bool $useRelativeNames = true)
    {
        // Ensure zip directory exists
        $zipDir = rtrim($zipDir, DIRECTORY_SEPARATOR);
        if ($zipDir === '') {
            $zipDir = getcwd();
        }
        if (!is_dir($zipDir)) {
            if (!mkdir($zipDir, 0755, true) && !is_dir($zipDir)) {
                // fallback to system temp dir
                $zipDir = sys_get_temp_dir();
            }
        }

        // Prepare filenames
        $date = date('Ymd_His');
        $name = $this->customName ? "_".$this->customName : "";
        $zipPath = $zipDir . DIRECTORY_SEPARATOR . $date . $name . '.zip';

        $this->write_log("ZIP process started. Target zip: $zipPath");

        $zip = new ZipArchive();

        if ($zip->open($zipPath, ZipArchive::CREATE) !== true) {
            $this->write_log("Failed to create zip archive at $zipPath");
            return false;
        }

        // Normalize baseDir
        if ($baseDir !== '') {
            $baseDir = rtrim($baseDir, DIRECTORY_SEPARATOR);
        }

        // Helper: convert shorthand memory string to bytes
        function convertToBytes(string $val): int {
            $val = trim($val);
            $last = strtolower($val[strlen($val)-1]);
            $num = (int) $val;
            switch ($last) {
                case 'g': return $num * 1024 * 1024 * 1024;
                case 'm': return $num * 1024 * 1024;
                case 'k': return $num * 1024;
                default: return (int) $val;
            }
        }

        // Parameters you can set
        $safetyFactor = 0.8; // start closing when usage > safetyFactor * target
        $defaultCap = '256M'; // used when memory_limit is -1 or not parseable

        // Determine target bytes
        if (!empty($memoryCap)) {
            $targetBytes = convertToBytes($memoryCap);
        } else {
            $iniLimit = ini_get('memory_limit');
            if ($iniLimit === false || $iniLimit === '-1') {
                $targetBytes = convertToBytes($defaultCap);
            } else {
                $targetBytes = convertToBytes($iniLimit);
            }
        }
        $thresholdBytes = (int) ($targetBytes * $safetyFactor);

        // Ensure script can run long
        set_time_limit(0);

        $addedCount = $skippedCount = 0;
        $iteration = 0;

        foreach ($files as $entry) {
            $iteration++;

            // Expecting ['path' => string, 'readable' => bool]
            if (!isset($entry['path'])) {
                $this->write_log('Skipping entry with missing path: ' . var_export($entry, true));
                $skippedCount++;
                continue;
            }

            $filePath = $entry['path'];

            // Resolve relative path if needed
            if ($baseDir !== '' && !preg_match('#^(?:[A-Za-z]:\\\\|/)#', $filePath)) {
                $filePath = $baseDir . DIRECTORY_SEPARATOR . ltrim($filePath, DIRECTORY_SEPARATOR);
            }

            // Skip unreadable
            if (empty($entry['readable']) || !is_readable($filePath)) {
                $this->write_log("Unreadable or missing file skipped: $baseDir/$filePath");
                $skippedCount++;
                continue;
            }

            // Determine local name inside zip
            if ($useRelativeNames && $baseDir !== '') {
                $localName = ltrim(substr($filePath, strlen($baseDir)), DIRECTORY_SEPARATOR);
                if ($localName === '') {
                    $localName = basename($filePath);
                }
            } else {
                $localName = basename($filePath);
            }

            // Add file
            $this->write_log("Adding file: $localName");
            if (!$zip->addFile($filePath, $localName)) {
                $this->write_log("Failed to add file to zip: $filePath as $localName");
                $skippedCount++;
                continue;
            }

            // Compression
            if (method_exists($zip, 'setCompressionName')) {
                if ($this->useCompressionOnZip) {
                    $zip->setCompressionName($localName, ZipArchive::CM_DEFLATE);
                } else {
                    $zip->setCompressionName($localName, ZipArchive::CM_STORE);
                }
            }

            $addedCount++;

            // Periodic memory check and flush
            if ($iteration % 500 === 0 || memory_get_usage(true) >= $thresholdBytes) {
                $this->write_log(sprintf(
                    "Memory check at iteration %d: usage=%d bytes, threshold=%d bytes. Flushing zip. Please wait..",
                    $iteration,
                    memory_get_usage(true),
                    $thresholdBytes
                ));

                // Close and free ZipArchive resources
                $zip->close();
                unset($zip);
                gc_collect_cycles();

                // Reopen in append mode (CREATE will open existing file and append)
                $zip = new ZipArchive();
                if ($zip->open($zipPath, ZipArchive::CREATE) !== true) {
                    $this->write_log("Unable to reopen zip archive for append: $zipPath");
                    die("Error occured on append operation. Please check Log for more detail.");
                }
            }
        }

        // Final close
        $zip->close();
        unset($zip);
        gc_collect_cycles();

        $this->write_log("ZIP process completed. Added: $addedCount; Skipped: $skippedCount");

        return $zipPath;
    }

    public function getLogFilePath(): string {
        return $this->logFile;
    }

    public function getLogFileName(): string {
        return $this->logfilename;
    }

    public function getZipFilePath(): string {
        return $this->zipPath;
    }
}