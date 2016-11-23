<?php
require_once(dirname(__FILE__).'/config/startup.php');
require_once(dirname(__FILE__).'/lib/App.php');
require_once(dirname(__FILE__).'/lib/KLogger.php');
require_once(dirname(__FILE__).'/lib/Db.php');
require_once(dirname(__FILE__).'/models/Incident.php');

$configPath = realpath(dirname(__FILE__) . '/config/config.php');

try{
    //print json_encode($_GET);
    //exit();
    App::createApp($configPath);

    $incident = new Incident();
    $groups = $incident->getGroups($_GET);
    $data = $incident->getData($_GET);
    print json_encode(array('groups' => $groups,
                            'data' => $data));

}catch (Exception $e){
    App::$logger->LogError($e->getMessage());
    exit();
}
?>