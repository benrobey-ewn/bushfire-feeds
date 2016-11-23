<?php
require_once('config/startup.php');
require_once('lib/APIService.php');
require_once('lib/App.php');
require_once('lib/Db.php');
require_once('lib/KLogger.php');
require_once ('models/Incident.php');

$configPath = realpath(dirname(__FILE__) . '/config/config.php');
$startTime = microtime(true);

function removeImgAndStyle($html) {
    // remove images
    $html = preg_replace("/<img[^>]+\>/i", "", $html);
    // remove style
    $html = preg_replace('/(<[^>]+) style=".*?"/i', '$1', $html);

    return $html;
}
try {
    App::createApp($configPath);

    if ((microtime(true) - $startTime) > App::$config['apiInterval']){
        App::$logger->LogInfo('Execution takes to much time. Exiting...');
        exit();
    }

    $incident = new Incident();
    $records = $incident->getUpdated('traffic');

    if (count($records) == 0) {
        App::$logger->LogDebug(sprintf('No events for update. Exiting...'));
        exit();
    }

    App::$logger->LogDebug(sprintf('Going to post/update %s events using API.', count($records)));
    $apiService = new APIService(App::$config['apiRoot'], App::$config['trafficApiKey']);
    $maxExecutionTime = ini_get('max_execution_time');

    foreach ($records as $record) {
        set_time_limit(300);

        if (!$record['point_str']) {
            continue;
        }

        $title = $record['title'];
        $state = $record['state'];
        $description = removeImgAndStyle($record['description']);

        if ($state == 'NT') {
            $title = preg_replace('/(\.)/', '', $title, 1);
            $description = preg_replace('/(\.)/', '', $description, 1);
            $description = preg_replace('/(\.)/', '<br>', $description, -1);
        }

        else if ($state == 'VIC') {
            $title = preg_replace('/(\.)/', '', $title, 1);
            $description = preg_replace('/(\.)/', '', $description, 1);
            $description = preg_replace('/(\.)/', '<br>', $description, -1);
            $descriptionR = strrev($description);
            $description = strrev(preg_replace('/(^(.*?)>.\srb<)/i', '', $descriptionR, 1));
        }

        else if ($state == 'QLD') {
            $description = preg_replace('/(\.)/', '.<br>', $description, -1);
        }

        else if ($state == 'NSW') {
            // Remove from description table row "Location: View on map".
            // We have to search from the end to remove only one row.
            $descriptionR = strrev($description);
            $patternR = '@' . strrev('<tr*.><td*.>Location:</td><td*.><a*.>View on map</a></td></tr>') . '@Usi';
            $description = strrev(preg_replace($patternR, '', $descriptionR));
            //Remove timestamp and last checked.
            $timeRegExp = "/((started\stoday|startedtoday)\s(....(am|pm)|.....(am|pm)),\s(last\schecked|lastchecked)\stoday\s(....(am|pm)|.....(am|pm)))|(started\stoday|startedtoday)\s(....(am|pm)|.....(am|pm))/i";
            $description = preg_replace('/(<.span>)/i', ' ', $description);
            $description = preg_replace('/(<span>|<.span>|<div>|<.div>|<table cellpadding=.0. cellspacing=.0.><tr><td><.td><td>)/i', ' ', $description);
            $description = preg_replace('/(<.td>.?<.tr>)/i', '<br>', $description);
            $description = preg_replace('/(<.td>)/', ' ', $description);
            $description = preg_replace('/(<.tr>|<tr>|<td>|<.table>)/i', '', $description);
            $description = preg_replace($timeRegExp, '', $description);
            //Remove double line breaks
            $description = preg_replace('/(<br><br><br>|<br>\s<br>\s<br>|<br\s.><br\s.><br\s.>|<br\s.>\s<br\s.>\s<br\s.>\s|<br><br>|<br>\s<br>|<br\s.><br\s.>|<br\s.>\s<br\s.>)/i', '<br>', $description);
        }

        //Unessecary to put into method
        //Topic key to post (general if not chnaged).
        $topicKey = 1;
        //Topic keys related to EWN's topic keys.
        $TOPIC_KEY_ROAD_CLOSED = 115;
        $TOPIC_KEY_DEBRIS = 101;
        $TOPIC_KEY_ROAD_WORKS = 74;
        $TOPIC_KEY_CAUTION = 76;
        $TOPIC_KEY_SLIPPERY_ROAD = 76;
        $TOPIC_KEY_SIGNAL_FAULT = 116;
        $TOPIC_KEY_DEBRIS_ROCKS = 83;
        $TOPIC_KEY_CAR_ACCIDENT = 75;

        $keyWordRegexArrayAssoc = array(
            0 => array(
                'regExp' => '/(accident|crash|car\scrash|carcrash|bike\scrash|bikecrash|truck\scrash|truckcrash|vehicle\scollision|vehiclecollision)/i',
                'topicKeyValue' => $TOPIC_KEY_CAR_ACCIDENT,
                'titleVal' => 'Car Accident: '
            ),
            1 => array(
                'regExp' => '/(closed|closure|roadclosed|road\sclosed|diverted|road\sclosed.|roadclosed.)/i',
                'topicKeyValue' => $TOPIC_KEY_ROAD_CLOSED,
                'titleVal' => 'Road Closed: '
            ),
            2 => array(
                'regExp' => '/(rock|rocks|landslide|land\sslider)/i',
                'topicKeyValue' => $TOPIC_KEY_DEBRIS_ROCKS,
                'titleVal' => 'Debris Rocks: '
            ),
            3 => array(
                'regExp' => '/(debri|debris|rubbish|lost\sload|lostload|debris.|debri.)/i',
                'topicKeyValue' => $TOPIC_KEY_DEBRIS,
                'titleVal' => 'Debris: '
            ),
            4 => array(
                'regExp' => '/(roadworks|road\sworks|maintenance)/i',
                'topicKeyValue' => $TOPIC_KEY_ROAD_WORKS,
                'titleVal' => 'Road Works: '
            ),
            5 => array(
                'regExp' => '/(no\sblockage|noblockage|delaysexpected|delays\sexpected|breakdown|break\sdown|breakdown.|break\sdown.|car\sfire|carfire|road\sdamage|roaddamage)/i',
                'topicKeyValue' => $TOPIC_KEY_CAUTION,
                'titleVal' => 'Caution: '
            ),
            6 => array(
                'regExp' => '/(slippery|slide|spill)/i',
                'topicKeyValue' => $TOPIC_KEY_SLIPPERY_ROAD,
                'titleVal' => 'Slippery Road: '
            ),
            7 => array(
                'regExp' => '/(lights|lights\son\sflash|signal\sfault)/i',
                'topicKeyValue' => $TOPIC_KEY_SIGNAL_FAULT,
                'titleVal' => 'Signal Fault: '
            ),
        );

        foreach ($keyWordRegexArrayAssoc as $keyWord) {
            if (preg_match($keyWord["regExp"], $description)) {
                $topicKey = $keyWord["topicKeyValue"];
                $title = $keyWord["titleVal"] . $title;
                break;
            }
        }

        if ($topicKey == 1) {
            $title = 'General: ' . $title;
        }

        //App::$logger->LogDebug('Topic Key:' . $topicKey);
        $subject = sprintf('%s, %s', $title, $state);
        $textForWeb = sprintf('%s %s, %s', $state, $title, $description);
        $textForSMS = substr(strip_tags($textForWeb), 0, App::$config['smsLength']);
        //Strip Carriage returns from sms
        $textForSMS = str_replace(array("\n", "\t", "\r", "\l"), '', $textForSMS);
        $textForFax = '';
        $createdDate = $record['unixtimestamp'];
        $expires = $record['unixtimestamp'] + 6 * 60 * 60;
        $alertURL = null; //$record['link'];
        $alertGUID = null; //$record['guid'];
        $centroids = array($record['point_str']);
        $alertKey = $record['alert_key'];
        $alertDeliveryLocationKey = $record['delivery_location_key'];        
        $alertGroupKey = App::$config['trafficAlertGroupKey'];
        $externalId = $record['guid'];

        if ($alertKey != 0) {
            App::$logger->LogDebug("Going to update data for `$title`.");
            $reply = $apiService->putTraffic($alertGroupKey, $subject, $textForWeb, $textForSMS, $textForFax, $createdDate, $expires, $topicKey, $alertURL, $alertGUID, $externalId, $centroids, $alertKey, $alertDeliveryLocationKey);
        }
        else {
            App::$logger->LogDebug("Going to post data for `$title`.");
            $reply = $apiService->postTraffic($alertGroupKey, $subject, $textForWeb, $textForSMS, $textForFax, $createdDate, $expires, $topicKey, $alertURL, $alertGUID, $externalId, $centroids);
        }

        //App::$logger->LogDebug(App::$config['apiRoot'] . 
                                // App::$config['trafficApiKey']);

        if (!$reply) {
            App::$logger->LogDebug("Error posting/updating data for `$title`.");
            continue;
        }
        else {
            $l = strlen($textForSMS);
           // App::$logger->LogDebug("Posted/updated data for `$title`. SMS: `$textForSMS`. SMS length: $l.");
        }

        $reply = json_decode($reply, true);
        //App::$logger->LogDebug("Reply: " . var_dump($reply));
        $updateResult = $incident->setPosted($record['guid'], $reply['AlertKey'], $reply['DeliveryLocations'][0]['AlertDeliveryLocationKey']);

        if ($updateResult) {
            App::$logger->LogDebug("Set posted for `$title` in DB.");
        }
        else {
            App::$logger->LogDebug("Error setting posted for `$title`.");
        }
    }
    App::$logger->LogDebug("Finished executing file api_traffic.php");
    set_time_limit($maxExecutionTime);
}
catch (Exception $e) {
    App::$logger->LogError($e->getMessage());
    exit();
}