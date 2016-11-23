<?php

require_once('config/startup.php');
require_once ('lib/FeedsExceptions.php');
require_once ('lib/Db.php');
require_once ('lib/KLogger.php');

$config_path = realpath(dirname(__FILE__) . '/config/config.php');
$config = require($config_path);
$db = new Db($config['db']['host'],
$config['db']['user'],
$config['db']['pass'],
$config['db']['base']);

$log = new KLogger ($config['logPath'],
$config['logLevel']);


function generate_jsonp($data) {
    if(isset($_GET['callback'])){
        if (preg_match('/\W/', $_GET['callback'])) {
            // if $_GET['callback'] contains a non-word character,
            // this could be an XSS attack.
            header('HTTP/1.1 400 Bad Request');
            exit();
        }
        header('Content-type: application/javascript; charset=utf-8');
        print sprintf('%s(%s);', $_GET['callback'], json_encode($data));
    }else{
        header('Content-type: application/javascript; charset=utf-8');
        print json_encode($data);
    }
}

if(isset($_GET['e'])){
    if($_GET['e'] == 'all'){
        $eventCondition = '';
    }else{
        $eventCondition = sprintf("AND event ='%s'", $db->escapeString($_GET['e']));
    }
}else{
    $eventCondition = "AND event = 'bushfire'";
}


/*

$sql = "SELECT *
    FROM  `" . $config['db']['base'] . "`.`" . $config['db']['incidents_table'] . "`
    WHERE `point_str` IS NOT NULL $eventCondition
    ORDER BY `unixtimestamp` DESC
    LIMIT 50";

*/

$sql = "SELECT *
    FROM  `" . $config['db']['base'] . "`.`" . $config['db']['incidents_table'] . "`
    WHERE `point_str` IS NOT NULL
    AND (category='Advice'
    OR category='Watch and Act'
    OR category='Emergencies'
    OR category='Emergency'
    OR category='Emergency Warning'
    OR category='Advice/Incidents/Open'
    OR category='Emergencies/Incidents/Open'
    OR category='Watch And Act/Incidents/Open'
    OR category LIKE 'Advice/%/Going'
    OR category LIKE 'Watch And Act/%/Going') $eventCondition
    ORDER BY `unixtimestamp` DESC
    LIMIT 50";

$rows = $db->getRows($sql);


$number = count($rows);
$output = array();

for($i=0; $i<$number; $i++){
    $row = $rows[$i];

    $date = date('Y-m-d H:i \G\M\T', $row['unixtimestamp']);

    $output[] = array('date'=>$date,
                      'state'=>$row['state'],
                      'title'=>$row['title'],
                      'category'=>$row['category'],
                      'coordinates'=>$row['point_str']);
}


generate_jsonp($output);

