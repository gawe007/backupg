<?php
declare(strict_types=1);
namespace Backupg;

use ZipArchive;
use Throwable;
use FilesystemIterator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

/**
 * Backupg class.
 * version 1.1
 * by gawe007@github.com.
 * This class is designed to be run on both CLI and webviews. Doesn't accept inline input.
 * Accept an assosiative array of parameters: 
 * @param backupTargetdirectory' directory to be zipped. A realpath is preferred. Required.
 * @param zipSaveLocation directory for the created zip to be saved. A realpath is preferred. If not spesified, the file will be saved at the same dir with the executed script.
 * @param customZipName custom name for the created zip file.
 * @param replace boolean flags for replace file if the generated zip has same filename with a file within the zip save location.
 * @param useCompression boolean flags of the zip compression.
 * @param includeDotFile boolean flags for including files started with a dot.
 * @param excludeDir array of excluded directory path. Caution! [Case Sensitive]
 * @param excludeExtensions array of excluded file extension. Cannot be used with includeExtensions.
 * @param includeExtensions array of included file extension. Cannot be used with excludeExtensions.
 * @param beforeDate string of date for file filtering. Only file before this date will be read.
 * @param afterDate string of date for file filtering. Only file after this date will be read.
 * @param memoryCap string of memory threshold for the Zipping file process. Cannot be set higher than the .ini limit.
 * @param autoStart boolean flags for backup process autoStart. Default is true.
 */
class Backupg{
    // System Parameter
    private float $safetyFactor = 0.8;
    private string $defaultCap = "256M";
    private string $memoryCap;
    private string $iniLimit;
    private bool $processStarted = false;

    // Backup Parameter
    private array $arrayFiles = [];
    private string $logLocation = __DIR__ . DIRECTORY_SEPARATOR ."log";
    private string $logFile = "";
    private string $logfilename = "";
    private string $zipPath = "";
    private int $dateMode = 0;

    // Allowed Parameter
    private array $allowedParam = [
        'backupTargetdirectory' => 'string',
        'zipSaveLocation' => 'string',
        'customZipName' => 'string?',
        'replace' => 'bool',
        'useCompression' => 'bool',
        'includeDotFile' => 'bool',
        'excludeDir' => 'array',
        'excludeExtensions' => 'array',
        'includeExtensions' => 'array',
        'beforeDate' => 'string',
        'afterDate' => 'string',
        'memoryCap' => 'string',
        'autoStart' => 'bool'
        ];

    // Default Parameter value
    private array $data = [
        'backupTargetdirectory' => '',
        'zipSaveLocation' => '',
        'customZipName' => null,
        'replace' => false,
        'useCompression' => true,
        'includeDotFile' => false,
        'excludeDir' => [],
        'excludeExtensions' => [],
        'includeExtensions' => [],
        'beforeDate' => "",
        'afterDate' => "",
        'memoryCap' => "",
        'autoStart' => true
    ];

    // Counter
    private $excludedDir = 0;
    private $excludedFile = 0;

    public function __construct(array $params)
    {
        $this->setup_params($params);   
        $this->setup_log();
        if($this->data['autoStart']) $this->start();
    }

    /**
     * @method startBackup() start the backup process manually.
     */
    public function startBackup(): void {
        if(!$this->processStarted){
            $this->start();
        }else{
            $this->write_log("Invalid. Backup Process already started.");
        }
    }

    // Setups
    /**
     * Setup class parameters
     */
    private function setup_params(array $params): void 
    {
        foreach ($params as $key => $value) {
            if (!array_key_exists($key, $this->allowedParam)) {
                continue;
            }

            $expected = $this->allowedParam[$key];

            // validate type
            if ($this->isValidType($value, $expected)) {
                if($key === 'excludeExtensions' || $key === 'includeExtensions'){
                    $this->data[$key] = array_map(fn ($item) => strtolower($item), $value);
                }
                $this->data[$key] = $value;
            } else {
                $this->write_log("Param ". $value . " is not supported.");
            }
        }
        
        $this->iniLimit = ini_get('memory_limit');
    }

    /**
     * Setup Log
     */
    private function setup_log(): void 
    {
        $this->logfilename = $this->data['customZipName'] ? $this->data['customZipName']."_".time().".txt" : "Log_backup_".time().".txt";
        $this->logFile = $this->logLocation . DIRECTORY_SEPARATOR . $this->logfilename ;

        if (!is_dir($this->logLocation)) {
            if (!mkdir($this->logLocation, 0755, true) && !is_dir($this->logLocation)) {
                // If we cannot create the log directory, fallback to sys_get_temp_dir
                $this->logLocation  = sys_get_temp_dir();
                $this->logFile = $this->logLocation . '/log_file_'.time().'.txt';
            }
        }
    }

    /**
     * Setup Excluded Dir
     */
    private function setup_excluded_dir(): void
    {
        if(!empty($this->data['excludeDir'])) {
            $this->write_log("Exclude Dir count: ". count($this->data['excludeDir']));
        }   
    }

    /**
     * Setup files filter
     */
    private function setup_file_filter(): void 
    {
        $e = count($this->data['excludeExtensions']);
        $i = count($this->data['includeExtensions']);
        if( $e > 0 && $i > 0 ){
            $this->write_log("Detected exclusion:$e inclusion:$i");
            $this->write_log("File extensions exclusion and inclusion cannot be used together.");
            $this->write_log("Backup process stopped...");
            die();
        }
    }

    /**
     * Setup date filter
     */
    private function setup_date_filter(): void
    {
        
        $this->data['beforeDate'] = !empty($this->data['beforeDate']) ? strtotime($this->data['beforeDate']) : null;
        $this->data['afterDate']  = !empty($this->data['afterDate'])  ? strtotime($this->data['afterDate'])  : null;

        // Normalize: swap if user gave them reversed
        if ($this->data['beforeDate'] !== null && $this->data['afterDate'] !== null && $this->data['beforeDate'] < $this->data['afterDate']) {
            [$this->data['beforeDate'], $this->data['afterDate']] = [$this->data['afterDate'], $this->data['beforeDate']];
        }

        if ($this->data['beforeDate'] === null && $this->data['afterDate'] !== null) {
            $this->dateMode = 1; // beforeDate mode
            $mode = "Upperbound";
        } elseif ($this->data['afterDate'] === null && $this->data['beforeDate'] !== null) {
            $this->dateMode = 2; // afterDate mode
            $mode = "Lowerbound";
        } elseif ($this->data['beforeDate'] !== null && $this->data['afterDate'] !== null) {
            $this->dateMode = 3; // between mode
            $mode = "Between";
        } else {
            $this->dateMode = 0; // no filter
            $mode = "All";
        }

        $this->write_log("Date mode is set to $mode");        
        if ($this->data['beforeDate'] !== null) {
            $this->write_log("Before date is set to : [".date("Y-m-d", $this->data['beforeDate'])."]");
        }
        if ($this->data['afterDate'] !== null) {
            $this->write_log("After date is set to : [".date("Y-m-d", $this->data['afterDate'])."]");
        }
    }

    /**
     * Start the backup process
     */
    private function start(): void 
    {
        $this->processStarted = true;
        $startAuto = ($this->data['autoStart']) ? 'started with autoStart' : 'started';
        $this->write_log("Backupg V.1.0 by gawe007@github.com");
        $this->write_log("-----------------------------------");
        $this->write_log("Backup Process ".$this->data['customZipName']." ".$startAuto);
        $this->write_log("Checking dir ". $this->data['backupTargetdirectory'] ."...");
        if(!is_dir($this->data['backupTargetdirectory'])) {
            $this->write_log("Dir : ".$this->data['backupTargetdirectory'] ." unreadable");
            $this->write_log("Backup Process Stopped...");
            die();
        }
        $this->setup_excluded_dir();
        $this->setup_file_filter();
        $this->setup_date_filter();
        $this->readFiles();
        if($this->arrayFiles){
            if(!$this->zipPath = $this->createZipFromFiles($this->arrayFiles, $this->data['zipSaveLocation'], $this->data['backupTargetdirectory'])){
                $this->write_log("Error when zipping files. Backup Process Stopped...");
                die();
            }
        }else{
            $this->write_log("Files returned zero. Backup Process Stopped...");
            die();
        }

        $this->write_log("Backup Process Succeeded.");
        $this->write_log("ZIP File: " . $this->zipPath);
        $this->write_log("Log File: " . $this->logFile);
    }

    /**
     * Read files from the target dir
     */
    private function readFiles(): void 
    {
       try {
            // Scan and get files with readability info
            $filesInfo = $this->getFilesWithReadability($this->data['backupTargetdirectory'], true, $this->data['includeExtensions'], $this->data['includeDotFile'], $this->logFile);

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
            $this->write_log("An error occurred while scanning directories. Check the log file for details.");
            $this->write_log("Backup Process Stopped...");
            die();
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
     * @param array|null $allowedExtensions Optional array of lowercase extensions to include; null = all
     * @param bool $includeDotFiles Include files starting with a dot
     * @param string $logFile Path to the log file for writing runtime errors and unreadable files
     * @return array List of ['path' => string, 'readable' => bool]
     */
    private function getFilesWithReadability(
        string $dir,
        bool $relative = false,
        ?array $allowedExtensions = null,
        bool $includeDotFiles = false,
        string $logFile = ''
    ): array {
        $dir = rtrim($dir, DIRECTORY_SEPARATOR);
        if ($dir === '') {
            $this->write_log('Empty directory path provided!');
            return [];
        }
        if (!is_dir($dir) || !is_readable($dir)) {
            $this->write_log("Directory not found or not readable: $dir");
            return [];
        }

        $results = [];

        try {
            $flags = FilesystemIterator::SKIP_DOTS;
            $rdi = new RecursiveDirectoryIterator($dir, $flags);
            $excludeDir      = isset($this->data['excludeDir']) ? $this->data['excludeDir'] : [];
            $excludeExtensions = isset($this->data['excludeExtensions']) ? $this->data['excludeExtensions'] : [];
            $it  = new RecursiveIteratorIterator($rdi, RecursiveIteratorIterator::SELF_FIRST);

            foreach ($it as $item) {
                $path = $item->getPathname();
                // Exclude directories
                if($excludeDir){
                    foreach ($excludeDir as $exclude) {
                        if(strpos($path, $exclude) !== false){
                            $this->write_log("$path excluded.");
                            $this->excludedDir++;
                            continue 2;
                        }
                    }
                }

                if (!$item->isFile()) {
                    continue;
                }

                $basename = $item->getBasename();

                if (!$includeDotFiles && isset($basename[0]) && $basename[0] === '.') {
                    continue;
                }

                //Exclusion by extension
                $ext = strtolower(pathinfo($basename, PATHINFO_EXTENSION));
                if ($ext === " " || in_array($ext, $excludeExtensions, true)) {
                    $this->write_log("".$path." excluded.");
                    $this->excludedFile++;
                    continue;
                }

                //Inclusion by extension
                if ($allowedExtensions) {
                    if ($ext === " " || !in_array($ext, $allowedExtensions, true)) {
                        $this->excludedFile++;
                        $this->write_log("File ".$path." is not included.");
                        continue;
                    }
                }

                // Filter by date
                if($this->dateMode != 0){
                    if (!$this->isWithinDateRange($item->getMTime(), $this->data['afterDate'], $this->data['beforeDate'])) {
                        $this->write_log("File ".$path." excluded. ".date("Y-m-d",$item->getMTime())." excluded");
                        $this->excludedFile++;
                        continue;
                    }
                }

                $path = $item->getPathname();
                $displayPath = $path;
                if ($relative) {
                    $displayPath = ltrim(substr($path, strlen($dir)), DIRECTORY_SEPARATOR);
                }

                $isReadable = is_readable($path);

                if (!$isReadable && $logFile !== '') {
                    $this->write_log("Unreadable file detected: $path", $logFile);
                }

                $results[] = [
                    'path' => $displayPath,
                    'readable' => $isReadable
                ];
            }

            usort($results, function ($a, $b) {
                return strcasecmp($a['path'], $b['path']);
            });

        } catch (Throwable $t) {
            if ($logFile !== '') {
                $this->write_log('Exception during directory scan: ' . $t->getMessage(), $logFile);
            }
            throw $t;
        }

        $this->write_log("Excluded dir:".$this->excludedDir.". Excluded files:".$this->excludedFile);
        return $results;
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
        if($this->data['replace']){
            $name = $this->data['customZipName'] ? $this->data['customZipName'] : $date;
        }else{
            $name = $this->data['customZipName'] ? $date . "_" . $this->data['customZipName'] : $date;
        }

        $this->write_log("Creating Zip with filename $name");
        $zipPath = $zipDir . DIRECTORY_SEPARATOR .$name . '.zip';

        if($this->data['replace']){
            if(file_exists($zipPath)){
                unlink($zipPath);
                $this->write_log("Old File Deleted");
            }
        }

        $this->write_log("ZIP process started. Target zip: $zipPath");
        $this->write_log("ZIP compression is set to ".($this->data['useCompression'] ? 'TRUE' : 'FALSE'));

        $zip = new ZipArchive();

        if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            $this->write_log("Failed to create zip archive at $zipPath");
            return false;
        }

        // Normalize baseDir
        if ($baseDir !== '') {
            $baseDir = rtrim($baseDir, DIRECTORY_SEPARATOR);
        }

        // Set memory threshold
        $iniLimit = $this->convertToBytes($this->iniLimit);
        if (!empty($this->memoryCap)) {
            $memoryCap = $this->convertToBytes($this->memoryCap);
            if($memoryCap === false || $memoryCap === '-1' || $memoryCap > $iniLimit){
                $this->write_log("Error: memoryCap ".$this->memoryCap." is invalid. Backup Process Stopped...");
                die();
            }
            $targetBytes = $memoryCap;
        } else {
            if ($iniLimit === false || $iniLimit === '-1') {
                $targetBytes = $this->convertToBytes($this->defaultCap);
            } else {
                $targetBytes = $iniLimit;
            }
        }
        $thresholdBytes = (int) ($targetBytes * $this->safetyFactor);

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
                if ($this->data['useCompression']) {
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
                    $this->write_log("Error occured on append operation. Please check Log for more detail.");
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

    //Public GET
    /**
     * getLogFilePath() return the log file path.
     */
    public function getLogFilePath(): string 
    {
        return $this->logFile;
    }

    /**
     * getLogFileName() return the name of the log file.
     */
    public function getLogFileName(): string 
    {
        return $this->logfilename;
    }

    /**
     * getZipFilePath() return the created ZIP file path after process end.
     */
    public function getZipFilePath(): string 
    {
        return $this->zipPath;
    }

    // Helper section
    /**
     * Helper for checking params data type
     */
    private function isValidType($value, string $expected): bool
    {
        // Split union types: "string|bool?"
        $types = explode('|', $expected);

        foreach ($types as $type) {

            $nullable = false;
            if (substr($type, -1) === '?') {
                $nullable = true;
                $type = substr($type, 0, -1);
            }

            if ($nullable && $value === null) return true;
            
            if ($this->matchType($value, $type)) return true;
        }

        return false;
    }

    /**
     * Helper for matching data type
     */
    private function matchType($value, string $type): bool
    {
        switch ($type) {
            case 'string': return is_string($value);
            case 'int':    return is_int($value);
            case 'bool':   return is_bool($value);
            case 'float':  return is_float($value);
            case 'array':  return is_array($value);
            case 'object': return is_object($value);
            case 'mixed':  return true; // accept anything
            default:       return false;
        }
    }

    /**
     * Helper for converting string to bytes
     */
    private function convertToBytes(string $val): int 
    {
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

    /**
     * Helper for date filtering
     */
    private function isWithinDateRange(int $mtime, ?int $afterDate, ?int $beforeDate): bool
    {
        switch ($this->dateMode) {
            case 1: 
                return $beforeDate !== null && $mtime <= $beforeDate;

            case 2: 
                return $afterDate !== null && $afterDate <= $mtime;

            case 3: 
                return $afterDate !== null && $beforeDate !== null
                   && $mtime >= $afterDate && $mtime <= $beforeDate;
            default:
                return false;
        }
    }

    /**
     * Helper for logging/displaying line of output
     */
    private function write_log(string $message): void
    {
        $logFile = $this->logFile;
        $timestamp = date('Y-m-d H:i:s');
        $line = "[$timestamp] $message\n";
        @file_put_contents($logFile, $line, FILE_APPEND | LOCK_EX);
        if (PHP_SAPI === 'cli' || php_sapi_name() === 'cli') {
            echo sprintf($line, PHP_EOL);
        } else {
            echo nl2br(htmlspecialchars($line));
        }
    }
}