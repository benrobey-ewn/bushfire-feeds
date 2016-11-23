<?php
header("Content-Type: text/plain");

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


function remove_single_quotes($a){
    return preg_replace('/\'/','', $a[0]);
}


$log = new KLogger ($config['logPath'],
                    $config['logLevel']);

$conditions = array('point_str IS NOT NULL');
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
    $limit = $db->escapeString($_GET['x']);
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
    }else if(count($intervalArray) == 1){
        if(substr($intervalArray[0], 0, 1) === '-'){
            $end = time();
            $beginning = time() + ((int)$intervalArray[0]) * 3600;
        }else{
            $beginning = time();
            $end = time() + ((int)$intervalArray[0]) * 3600;
        }
    }else{
        print 'Wrong data in param `i`';
        exit();
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

$sql = "SELECT *
    FROM  `" . $config['db']['base'] . "`.`" . $config['db']['incidents_table'] . "`
    $conditionsString
    ORDER BY `unixtimestamp` DESC
    LIMIT $limit";


    $rows = $db->getRows($sql);
    $number = count($rows);
    $output = '';

    for($i=0; $i<$number; $i++){
        $row = $rows[$i];

        $date = date('Y-m-d H:i \G\M\T', $row['unixtimestamp']);

        $tline = '%s,%s,"%s",%s,"%s",%s,%s,%s';

        $description = str_replace("\r", "", str_replace("\n", "", trim($row['description'])));
        $description =  preg_replace_callback('/style="[^"]+"/','remove_single_quotes', $description);
        $description =  preg_replace("/<([^<>]+)>/e", "'<' . str_replace('\\\\\"', \"'\", '$1') . '>'", $description);
        $description = str_replace('"', '', $description);

        $line = sprintf($tline, $date, $row['state'],
        str_replace('"', '', $row['title']),
        $row['category'],
        $description,
        //str_replace('"', '\"',str_replace("\r", "", str_replace("\n", "", trim($row['description'])))),
        $row['point_str'], $row['unixtimestamp'], $row['type']);

        $output .= $line;

        if($i < $number - 1){
            $output .= "\n";
        }
    }
    if($output == '') {
	$output = 'No output.';
    }

    echo $output;
