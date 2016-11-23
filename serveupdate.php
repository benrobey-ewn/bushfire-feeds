<?php

require_once('config/startup.php');
require_once ('lib/FeedsExceptions.php');
require_once ('lib/Db.php');
require_once ('lib/KLogger.php');

$config_path = realpath(dirname(__FILE__) . '/config/config.php');
$config = require($config_path);

$log = new KLogger ($config['logPath'],
                    $config['logLevel']);
try{
    $db = new Db($config['db']['host'],
                 $config['db']['user'],
                 $config['db']['pass'],
                 $config['db']['base']);

    // t - selected records shoud be bigger or equal the timestamp
    // e - exclude guids

    $conditions = array();

    if(isset($_POST['t'])){
        $log->LogDebug(sprintf('Request timestamp parameter: `%s`.', $_POST['t']));
        $escapedTS = $db->escapeString($_POST['t']);
        $conditions[] = sprintf(" WHERE update_ts >= %s", $escapedTS);
    }

    if(isset($_POST['e'])){
        $log->LogDebug(sprintf('Request exclude parameter: `%s`.', $_POST['e']));
        $titles = explode("','", rawurldecode($_POST['e']));
        $escapedTitles = array_map(function($title){
                                    global $db;
                                    return sprintf("'%s'",
                                                   $db->escapeString($title));
                                  },
                                  $titles);

        $conditions[] = sprintf('title NOT IN (%s)',
                                implode(',', $escapedTitles));
    }

    $sql_t = "SELECT `guid`,  `title`, `state`, `description`, `category`, `link`," .
             "       `unixtimestamp`, `lon`, `lat`, `point_str`, `geocoded`, `type`," .
             "       `event`, `update_ts`, `geometries`" .
             "FROM  `%s`.`%s` %s";

    $sql = sprintf($sql_t, $config['db']['base'],
                           $config['db']['incidents_table'],
                           implode(' AND ', $conditions));

    $log->LogDebug(sprintf('SQL: `%s`.', $sql));
    $rows = $db->getArrayRows($sql);
    $log->LogDebug(sprintf('SQL result records number: %s.', count($rows)));

    echo json_encode($rows);

    $log->LogDebug(sprintf('Records were served.'));

}catch (Exception $e){
    $log->LogError($e->getMessage());
}