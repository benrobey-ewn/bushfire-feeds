<?php

require_once('config/startup.php');
require_once ('lib/FeedsExceptions.php');
require_once ('lib/CurlService.php');
require_once ('lib/Db.php');
require_once ('lib/KLogger.php');


$config_path = realpath(dirname(__FILE__) . '/config/config.php');
$config = require($config_path);

//$config['logPath'] = 'php://stderr';
//$config['logLevel'] = 1;

$log = new KLogger ($config['logPath'],
$config['logLevel']);

$db = new Db($config['db']['host'],
$config['db']['user'],
$config['db']['pass'],
$config['db']['base']);

function prepareSqlValue($value){

    if(is_null($value)){
        return 'NULL';
    }
    global $db;
    return $db->escapeString($value);
}

try{
    /*
     * There is hight probability that during last update not all records with the
     * biggest timestamp were received.
     * So we have to check if there are more records with the timestamp, which is the
     * biggest in destination database, in the source database.
     */

    // Get biggets upadet_ts, and guids of such records.

    $uriParams = array();

    $sql_t = "SELECT `title`, `update_ts`
              FROM  `%s`.`%s`
              WHERE `update_ts` = (SELECT MAX(update_ts) FROM `%s`.`%s`)";

    $sql = sprintf($sql_t, $config['db']['base'],
                           $config['db']['incidents_table'],
                           $config['db']['base'],
                           $config['db']['incidents_table']);
    $rows = $db->getArrayRows($sql);

    $titles = array();
    if($rows){
        $timestamp = $rows[0][1];
        $log->LogDebug(sprintf('Max timestamp - %s', $timestamp));
        foreach($rows as $row){
            $titles[] = $row[0];
        }
        $log->LogDebug(sprintf('Titles: %s', implode(',', $titles)));

        $uriParams = array(
            't'=> $timestamp,
            'e'=> rawurlencode(implode("','", $titles))
        );
    }

    $uri = $config['update_uri'];
    $log->LogDebug(sprintf('URI: %s', $uri));

    $log->LogDebug(sprintf('Params: %s', json_encode($uriParams)));

    $curlService = new CurlService();

    /*
      Array of arrays with keys

     `guid`, `title`, `state`, `description`, `category`, `link`, `unixtimestamp`,
     `lon`, `lat`, `point_str`, `type`, `event`, `update_ts`
     */
    $updateJSON = $curlService->executeCurl($uri, $uriParams);

    $updateArray = json_decode($updateJSON);

    $log->LogDebug(sprintf('Received %s elements', count($updateArray)));

    //var_dump($updateArray);
    //exit();

    if($updateArray){
        $dbTable = sprintf("`%s`.`%s`", $config['db']['base'],
                                        $config['db']['incidents_table']);

        $sqlTemplate = "INSERT INTO %s (`guid`, `title`, `state`,
                                `description`, `category`, `link`, `unixtimestamp`,
                                `lon`,`lat`,`point_str`, `point_geom`, `geocoded`, `type`,
                                `event`, `update_ts`, `geometries`)
                                VALUES %s
                                ON DUPLICATE KEY UPDATE
                                `guid`=VALUES(`guid`),
                                `description`=VALUES(`description`),

                                `lon`=VALUES(`lon`),
                                `lat`=VALUES(`lat`),
                                `point_str`=VALUES(`point_str`),
                                `point_geom`=VALUES(`point_geom`),
                                `geocoded`=VALUES(`geocoded`),

                                `unixtimestamp`=VALUES(`unixtimestamp`),
                                `category`=VALUES(`category`),
                                `update_ts`=VALUES(`update_ts`),
                                `geometries`=VALUES(`geometries`)";

        $valuesTemplate = "('%s', '%s', '%s','%s',
                                '%s', '%s', '%s',
                                %s, %s, %s, %s, %s, '%s', '%s', '%s', %s)";
        $values = array();
        foreach($updateArray as $updateRow){
            // Escape strings and replace null values with 'NULL' values
            $updateRow = array_map('prepareSqlValue', $updateRow);

            $pointStr = $updateRow[9];
            $pointGeom = 'NULL';
            // If point string exist
            if($pointStr != 'NULL'){
                $pointGeom = sprintf("GeomFromText('POINT(%s)')", $pointStr);
                $updateRow[9] = sprintf("'%s'", $pointStr);
            }
            // Insert $pointGeom into values array at index 10.
            array_splice($updateRow, 10, 0, array($pointGeom));

            $geometries = $updateRow[15];
            if($geometries != 'NULL'){
                $updateRow[15] = sprintf("'%s'", $geometries);
            }

            // Prepare and pass parameters to sprintf function.
            array_unshift($updateRow, $valuesTemplate);
            $values[] = call_user_func_array('sprintf', $updateRow);
        }
        $sql = sprintf($sqlTemplate, $dbTable, implode(', ', $values));

        if(strlen($sql) > $config['max_allowed_packet']){
            $log->LogDebug(sprintf('Insert/update SQL lenght: %s. Data will be inserted in chunks.', strlen($sql)));

            $startIndex = 0;
            $endIndex = 0;

            $valuesCount = count($values);

            while(true){
                $sql = sprintf($sqlTemplate, $dbTable, implode(', ', array_slice($values, $startIndex, $endIndex + 1)));
                if($endIndex == $valuesCount - 1 && strlen($sql) <= $config['max_allowed_packet']){
                    if($db->executeQuery($sql)){
                        $log->LogDebug('Last set of records was inserted.');
                    }
                    break;
                }

                if(strlen($sql) > $config['max_allowed_packet']){
                    $sql = sprintf($sqlTemplate, $dbTable, implode(', ', array_slice($values, $startIndex, $endIndex)));
                    // Execute query
                    if($db->executeQuery($sql)){
                        $log->LogDebug('Part of records was inserted.');
                    }
                    $startIndex = $endIndex;
                    continue;
                }
                $endIndex++;
            }
        }else{
            //$log->LogDebug(sprintf('SQL: %s', $sql));
            if($db->executeQuery($sql)){
                $log->LogDebug('Database was updated.');
            }
        }
    }
}catch (Exception $e){
    $log->LogError($e->getMessage());
}

