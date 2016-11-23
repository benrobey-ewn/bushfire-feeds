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
        header('Content-type: application/json; charset=utf-8');
        print json_encode($data);
    }
}


$log = new KLogger ($config['logPath'],
$config['logLevel']);

$conditions = array('point_str IS NULL');
$limit = 120; //Default limit
$conditionsString = 'WHERE ';
$a = false;
$i = false;

if(isset($_GET['s'])){
    $state = strtoupper($_GET['s']);
    $conditions[] = sprintf("`state`='%s'", $db->escapeString($state));
}

if(isset($_GET['t'])){
    $t = $_GET['t'];

    if($t == 'a'){
        $conditions[] = "`type`='alert'";
    }else if($t == 'i'){
        $conditions[] = "`type`='incident'";
    }
}

if(isset($_GET['x'])){
    $limit = $_GET['x'];
}

if(isset($_GET['lat']) && isset($_GET['lon']) && isset($_GET['r'])){
    $lat = $_GET['lat'];
    $lon = $_GET['lon'];
    $r = $_GET['r'];
    $conditions[] = sprintf("((2*asin(sqrt((sin(power(((%f*pi()/180)-(incidents.LAT *pi()/180))/2 ,2))
+cos(%f*pi()/180)*cos(incidents.LAT *pi()/180)
*power(sin(((%f*pi()/180)-(incidents.LON *pi()/180))/2),2)))))*180*60/pi())*1.852 < %f",
    $db->escapeString($lat), $db->escapeString($lat), $db->escapeString($lon), $db->escapeString($r));
}


//bbox=wlon,slat,elon,nlat
if(isset($_GET['bbox'])){
    $bboxArray = explode(',', $_GET['bbox']);

    if(count($bboxArray) == 4){
        list($wlon, $slat, $elon, $nlat) = $bboxArray;
        $conditions[] = sprintf("%f<=incidents.lon AND incidents.lon<=%f AND %f<=incidents.lat AND incidents.lat<=%f",
        $db->escapeString($wlon), $db->escapeString($elon), $db->escapeString($slat), $db->escapeString($nlat));
    }
}

if(isset($_GET['i'])){
    $interval = $_GET['i'];
    $intervalArray = explode(',', $interval);
    if(count($intervalArray) == 2){
        $end = time() + ((int)$intervalArray[0]) * 3600;
        $beginning = time() + ((int)$intervalArray[1]) * 3600;
    }else{
        $end = time();
        $beginning = time() + ((int)$intervalArray[0]) * 3600;
    }

    $conditions[] = sprintf("unixtimestamp>%d AND unixtimestamp<%d",
    $db->escapeString($beginning), $db->escapeString($end));
}

if(isset($_GET['e'])){
    if($_GET['e'] != 'all'){
        $conditions[] = sprintf("event ='%s'", $db->escapeString($_GET['e']));
    }
}else{
    $conditions[] = "event = 'bushfire'";
}

$conditionsString .= implode(' AND ', $conditions);

$sql = "SELECT unixtimestamp, state, title, category, description, type
    FROM  `" . $config['db']['base'] . "`.`" . $config['db']['incidents_table'] . "`
    $conditionsString
    ORDER BY `unixtimestamp` DESC
    LIMIT $limit";


$rows = $db->getArrayRows($sql);

generate_jsonp($rows);