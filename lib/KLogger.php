<?php

/* Finally, A light, permissions-checking logging class.
 *
 * Author   : Kenneth Katzgrau < katzgrau@gmail.com >
 * Date : July 26, 2008
 * Comments : Originally written for use with wpSearch
 * Website  : http://codefury.net
 * Version  : 1.0
 *
 * Usage:
 *      $log = new KLogger ( "log.txt" , KLogger::INFO );
 *      $log->LogInfo("Returned a million search results"); //Prints to the log file
 *      $log->LogFATAL("Oh dear.");             //Prints to the log file
 *      $log->LogDebug("x = 5");                    //Prints nothing due to priority setting
 */

class KLogger
{

    const DEBUG     = 1;    // Most Verbose
    const INFO      = 2;    // ...
    const WARN      = 3;    // ...
    const ERROR     = 4;    // ...
    const FATAL     = 5;    // Least Verbose
    const OFF       = 6;    // Nothing at all.

    const OPEN_SUCCEEDED  = 1;
    const OPEN_FAILED   = 2;
    const LOG_CLOSED    = 3;

    /* Public members: Not so much of an example of encapsulation, but that's okay. */
    public $Log_Status  = KLogger::LOG_CLOSED;
    public $DateFormat  = "Y-m-d G:i:s";
    public $MessageQueue;

    private $log_file;
    private $priority = KLogger::INFO;

    private $file_handle = null;

    public function __construct($filepath , $priority){
        if ( $priority == KLogger::OFF ) return;

        $this->log_file = $filepath;
        $this->MessageQueue = array();
        $this->priority = $priority;

        if ( file_exists( $this->log_file ) )
        {
            if ( !is_writable($this->log_file) )
            {
                $this->Log_Status = KLogger::OPEN_FAILED;
                $this->MessageQueue[] = "The file exists, but could not be opened for writing. Check that appropriate permissions have been set.";
                echo "The file exists, but could not be opened for writing. Check that appropriate permissions have been set.";
                return;
            }
        }

        if ( $this->file_handle = fopen( $this->log_file , "a" ) )
        {
            $this->Log_Status = KLogger::OPEN_SUCCEEDED;
            $this->MessageQueue[] = "The log file was opened successfully.";
            if ( $this->file_handle ) {
               fclose( $this->file_handle );
            }
            $this->file_handle = null;
        }
        else
        {
            $this->Log_Status = KLogger::OPEN_FAILED;
            $this->MessageQueue[] = "The file could not be opened. Check permissions.";
        }

        return;
    }

    public function __destruct()
    {
        if ( $this->file_handle ) {
            fclose( $this->file_handle );
            $this->file_handle = null;
        }
    }

    public function LogInfo($line)
    {
        $this->Log( $line , KLogger::INFO );
    }

    public function LogDebug($line)
    {
        $this->Log( $line , KLogger::DEBUG );
    }

    public function LogWarn($line)
    {
        $this->Log( $line , KLogger::WARN );
    }

    public function LogError($line){

        $this->Log($line , KLogger::ERROR);
    }

    public function LogFatal($line)
    {
        $this->Log( $line , KLogger::FATAL );
    }

    public function Log($line, $priority)
    {
        if ( $this->priority <= $priority )
        {
            $status = $this->getTimeLine($priority);
            $debugBacktrace = debug_backtrace();
            $line = sprintf("%s %s, %s: %s",
            $status,
            $debugBacktrace[1]['file'],
            $debugBacktrace[1]['line'],
            $line);
            $this->WriteFreeFormLine ("$line\n");
        }
    }
    public function GetLogFileHandle()
    {
        return $this->file_handle;
    }
    public function WriteFreeFormLine( $line )
    {
        if ( $this->Log_Status == KLogger::OPEN_SUCCEEDED && $this->priority != KLogger::OFF )
        {
            // do we need to rotate?
            if (filesize( $this->log_file ) > 500000)
            {
                $f5 = str_replace('.log', '_5.log', $this->log_file);
                $f4 = str_replace('.log', '_4.log', $this->log_file);
                $f3 = str_replace('.log', '_3.log', $this->log_file);
                $f2 = str_replace('.log', '_2.log', $this->log_file);
                $f1 = str_replace('.log', '_1.log', $this->log_file);

                if (file_exists($f5))
                    unlink($f5);

                if (file_exists($f4))
                   rename($f4, $f5);
                if (file_exists($f3))
                   rename($f3, $f4);
                if (file_exists($f2))
                   rename($f2, $f3);
                if (file_exists($f1))
                   rename($f1, $f2);


                if (file_exists($this->log_file))
                   rename($this->log_file, $f1);
            }

            $this->file_handle = fopen( $this->log_file , "a" );
            if (fwrite( $this->file_handle , $line ) === false) {
                $this->MessageQueue[] = "The file could not be written to. Check that appropriate permissions have been set.";
            }  
            if ( $this->file_handle ) {
               fclose( $this->file_handle );
               $this->file_handle = null;
            }
        }
    }

    private function getTimeLine($level){
        $time = date($this->DateFormat);

        switch($level){
            case KLogger::INFO:
                $levelStr = 'INFO';
                break;
            case KLogger::WARN:
                $levelStr = 'WARN';
                break;
            case KLogger::DEBUG:
                $levelStr = 'DEBUG';
                break;
            case KLogger::ERROR:
                $levelStr = 'ERROR';
                break;
            case KLogger::FATAL:
                $levelStr = 'FATAL';
                break;
            default:
                $levelStr = 'LOG';
                break;
        }
        return sprintf("%s - %s -->", $time, $levelStr);
    }
}
?>