<?php
require_once('config/startup.php');
require_once('lib/AXP.php');
require_once('lib/EventFeedAbs.php');
require_once('lib/Bushfire.php');
require_once('lib/Clearer.php');
require_once('lib/CurlService.php');
require_once('lib/Db.php');
require_once('lib/EWNApplication.php');
require_once('lib/FeedsExceptions.php');
require_once('lib/Geocoder.php');
require_once('lib/Traffic.php');
require_once('lib/htmlpurifier/HTMLPurifier.auto.php');
  
$config_path = realpath(dirname(__FILE__) . '/config/config.php');
$config = require($config_path);

$event = 'bushfire';
if (defined('EVENT')) {
    $event = EVENT;
}
$location = null;
if (defined('LOCATION')) {
    $location = LOCATION;
}
$type = null;
if (defined('TYPE')) {
    $type = TYPE;
}

$ewnApplication = new EWNApplication($config);

if ($event == 'bushfire') {
		//$this->$logger->LogDebug('EWNApplication() initialised - getFeed() - Event: ' . $event .' Type: ' . $type . ' Location: ' . $location);
    $ewnApplication->getFeed($event, STATE, $type);
}
else if ($event == 'traffic') {
    foreach($config['trafficUrls'][STATE] as $type=>$url) {
    		//$this->$logger->LogDebug('EWNApplication() initialised - getFeed() - Event: ' . $event .' Type: ' . $type . ' Location: ' . $location);
        $ewnApplication->getFeed($event, STATE, $type);
    }
}
else if ($event == 'clear') {
		//$this->$logger->LogDebug('EWNApplication() initialised - clear()');
    $ewnApplication->clear();
}