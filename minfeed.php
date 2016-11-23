<?php

require_once('config/startup.php');
require_once ('lib/FeedsExceptions.php');
require_once ('lib/Db.php');
//require_once ('lib/KLogger.php');


function removeNewLine($str){
    $str = str_replace("\n\r", ' ', $str);
    $str = str_replace("\n", ' ', $str);
    $str = str_replace("\r", ' ', $str);
    return $str;
}



$config_path = realpath(dirname(__FILE__) . '/config/config.php');
$config = require($config_path);

//$log = new KLogger ($config['logPath'], $config['logLevel']);

$db = new Db($config['db']['host'],
$config['db']['user'],
$config['db']['pass'],
$config['db']['base']);


if(isset($_GET['e'])){
    if($_GET['e'] == 'all'){
        $eventCondition = '';
    }else{
        $eventCondition = sprintf("AND event ='%s'", $db->escapeString($_GET['e']));
    }
}else{
    $eventCondition = "AND event ='bushfire'";
}

$sql = "SELECT *
    FROM  `" . $config['db']['base'] . "`.`" . $config['db']['incidents_table'] . "`
    WHERE category='Advice'
    OR category='advice'
    OR category='Watch and Act'
    OR category='watch and act'
    OR category='Emergencies'
    OR category='Emergency Warning'
    OR category='emergency warning'
    OR category='Advice/Incidents/Open'
    OR category='Emergencies/Incidents/Open'
    OR category='Watch And Act/Incidents/Open'
    OR category LIKE 'Advice/%/Going'
    OR category LIKE 'Watch And Act/%/Going' $eventCondition
    ORDER BY `unixtimestamp` DESC
    LIMIT 100";
/*
 * Following lines will be useless (used for old QLD bushfires alerts feed.)
 *
 *    OR category='Advice/Incidents/Open'
 *    OR category='Emergencies/Incidents/Open'
 *    OR category='Watch And Act/Incidents/Open'
*/

$rows = $db->getRows($sql);
$number = count($rows);
$output = '';

for($i=0; $i<$number; $i++){
    $row = $rows[$i];

    $date = date('Y-m-d H:i \G\M\T', $row['unixtimestamp']);

    $tline = '%s,%s,"%s",%s';

    $line = sprintf($tline,
                    $date,
                    removeNewLine($row['state']),
                    removeNewLine(str_replace('"', '', strip_tags($row['title']))),
                    removeNewLine($row['category']));

    $output .= $line;

    if($i < $number - 1){
        $output .= "\n";
    }
}

echo $output;
