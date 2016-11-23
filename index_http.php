<?php
require_once('config/startup.php');
require_once ('lib/FeedsExceptions.php');
require_once ('lib/KLogger.php');
require_once ('lib/ShellExecutor.php');

$config_path = realpath(dirname(__FILE__) . '/config/config.php');
$config = require($config_path);

/*
global $argc;

if (isset($_SERVER['SCRIPT_URI'])) {
    $rootFolder = implode("/", (explode('/', $_SERVER["SCRIPT_URI"], -1))).'/';
}
else {
    //$rootFolder = basename(__DIR__) . '/';
    //$rootFolder = $_SERVER["HTTP_HOST"] . implode("/", (explode('/', $_SERVER['PHP_SELF'], -1))).'/';
    if ($argc && $argc > 0) {
        $rootFolder = '52.65.53.150/bushfire-traffic/';
        //There is possibly a better way to do this?
    }
    else {
        $rootFolder = $_SERVER["HTTP_HOST"] . '/' . basename(dirname($_SERVER['PHP_SELF'])) . '/';
    }
    //Need to figure out if file called from shell.
}
*/
$logger = new KLogger ($config['processlogPath'], $config['processlogLevel']);
$shellExecutor = new ShellExecutor();
$jobs = $config['jobs'];
$pids = array();
$startTime = microtime_float();
$logger->LogDebug("Starting jobs...");

foreach ($jobs as $job) {
    //$command = sprintf('/web/cgi-bin/php5 "$HOME/html/bushfires/feeds/%s.php"', $job);
    //$command = sprintf('curl %s%s.php', $rootFolder, $job);
    $command = sprintf('php %s.php', $job);
    //one at a time.
    $logger->LogDebug(sprintf("Shell Command: %s", $command));
    // production : run all jobs in parallel
    $pid = $shellExecutor->background($command, $job);
    // debug: run jobs one at a time
    //$pid = $shellExecutor->andWait($command, $job);
    $pids[$job] = $pid;
    $logger->LogDebug("Started process: $job with pid: $pid.");
}

while(true) {
    $jobsFinished = true;
    foreach ($pids as $job=>$pid) {
        if ($pid != null) {
            $curJobTime = microtime_float();
            $executionJobTime = round($curJobTime - $startTime, 3);
            if ($executionJobTime > $config['max_fetch_execution_time']) {
                $logger->LogDebug("Process $job with pid $pid was killed due to timeout.");
                $killed = $shellExecutor->kill($pid);
            }
            else {
                if ($shellExecutor->is_running($pid)) {
                    $jobsFinished = false;
                }
                else {
                    $logger->LogDebug("Process $job with pid $pid finished.");
                    unset($pids[$job]);
                }
           }
        }
    }

    if ($jobsFinished) {
        $logger->LogDebug('All jobs finished. Exiting...');
        exit();
    }
    else {
        $curTime = microtime_float();
        $executionTime = round($curTime - $startTime, 3);
        if ($executionTime > $config['max_fetch_execution_time']) {
            $logger->LogError('Some jobs did not finish in ' . $config['max_fetch_execution_time'] . ' seconds. Trying to kill ...');
            foreach ($pids as $job=>$pid) {
                $killed = $shellExecutor->kill($pid);
                if ($killed) {
                    $logger->LogError("Process $job with pid $pid was killed.");
                }
                else {
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

function microtime_float () {
    list ($msec, $sec) = explode(' ', microtime());
    $microtime = (float)$msec + (float)$sec;
    return $microtime;
}