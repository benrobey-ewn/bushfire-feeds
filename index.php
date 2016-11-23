<?php
require_once('config/startup.php');

require_once ('lib/FeedsExceptions.php');
require_once ('lib/KLogger.php');
require_once ('lib/ShellExecutor.php');

$config_path = realpath(dirname(__FILE__) . '/config/config.php');
$config = require($config_path);


chdir(dirname(realpath(__FILE__)));

function microtime_float () {
    list ($msec, $sec) = explode(' ', microtime());
    $microtime = (float)$msec + (float)$sec;
    return $microtime;
}

$logger = new KLogger ($config['processlogPath'],
$config['processlogLevel']);

$logger->LogDebug("Executing process from " . __FILE__ . "...");

$shellExecutor = new ShellExecutor();

$jobs = $config['jobs'];

$pids = array();

$startTime = microtime_float();

$logger->LogDebug("Starting jobs...");

foreach($jobs as $job){
    //$command = sprintf('/web/cgi-bin/php5 "$HOME/html/bushfires/feeds/%s.php"', $job);
    $command = sprintf('php %s.php', $job);

    $pid = $shellExecutor->background($command);

    $pids[$job] = $pid;

    $logger->LogDebug("Started process $job with pid $pid.");
}

while(true){
    $jobsFinished = true;

    foreach($pids as $job=>$pid){
        if($shellExecutor->is_running($pid)){
            $jobsFinished = false;
        }else{
            $logger->LogDebug("Process $job with pid $pid finished.");
            unset($pids[$job]);
        }
    }

    if($jobsFinished){
        $logger->LogDebug('All jobs finished. Exiting...');
        exit();
    }else{
        $curTime = microtime_float();
        $executionTime = round($curTime - $startTime, 3);
        if($executionTime > $config['max_fetch_execution_time']){
            $logger->LogError('Some jobs did not finish in ' .
            $config['max_fetch_execution_time'] .
                                  ' seconds. Trying to kill ...');
            foreach($pids as $job=>$pid){
                $killed = $shellExecutor->kill($pid);
                if($killed){
                    $logger->LogError("Process $job with pid $pid was killed.");
                }else{
                    $logger->LogDebug("Process $job with pid $pid finished.");
                }
                /*
                 * We do not exit here and let the script to check if jobs
                 * are running.
                 */
            }
        }
    }
}
