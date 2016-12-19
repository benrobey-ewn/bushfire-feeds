<?php
require_once('config/startup.php');
require_once('lib/APIService.php');
require_once('lib/App.php');
require_once('lib/Db.php');
require_once('lib/KLogger.php');
require_once ('models/Incident.php');

define('WRONG_NSW_BUSHFIRE_EVENT_PATTERN',
'#FIRE: No|TYPE: Pile Burn|TYPE: Motor Vehicle Accident|TYPE: School Fire|TYPE: Structure fire|TYPE: Vehicle fire#');
define('WRONG_TAS_BUSHFIRE_EVENT_PATTERN',
'#TYPE: FIRE INCIDENT|TYPE: ALARM|TYPE: STRUCTURE FIRE|TYPE: VEHICLE FIRE|TYPE: SMELL OF BURNING|TYPE: HAZMAT#');
define('WRONG_VIC_BUSHFIRE_EVENT_PATTERN',
'#FALSE ALARM|False Alarm|Medical Emergency|HAZMAT INCIDENT|Status:</b> Stop|Type:</b> Structure Fire|Type:</b> STRUCTURE|Type:</b> Hazardous Incident|Type:</b> ASSIST OTHER AGENCY|Type:</b> WASHAWAY|Type:</b> RESCUE|Type:</b> NON STRUCTURE|Non Structure Fire|Type:</b> INCIDENT|Type:</b> Fire<br /><b>Status:</b> Initiated|Status:</b> Stop#');
define('WRONG_SA_BUSHFIRE_EVENT_PATTERN',
'#\(Tree Down\)|\(Assist Other Agencies\)|\(Rubbish Fire\)|\(Private Alarm\)|\(Assist Resident\)|\(Severe Weather\)|\(Building Impact\)|\(Cleanup\)|\(Other\)|\(Flooding/Salvage\)|\(Rubbish Bin\)|\(Search\)|\(Rescue\)|\(Cleanup\)|\(Helicopter Landing\)|\(Storm Damage\)|\(Stop Call\)#');

$configPath = realpath(dirname(__FILE__) . '/config/config.php');
$startTime = microtime(true);

try {
    App::createApp($configPath);
    if ((microtime(true) - $startTime) > App::$config['apiInterval']) {
        App::$logger->LogInfo('Execution takes to much time. Exiting...');
        exit();
    }

    $incident = new Incident();
    /* TODO: After bushfires are tested on test server the line should be replaced
       with $records = $incident->getUpdated(); in api.php
       to get all events.

       This file should be deleted and cron job should be removed.
    */
    $records = $incident->getUpdated('bushfire');
    $records = filterBushfireEvents($records);

    if (count($records) == 0) {
        App::$logger->LogDebug(sprintf('No events for update. Exiting...'));
        exit();
    }

    App::$logger->LogDebug(sprintf('Going to post/update %s events using API.', count($records)));
    $apiService = new APIService(App::$config['apiRoot'], App::$config['bushfireApiKey']);
    $maxExecutionTime = ini_get('max_execution_time');

    foreach ($records as $record) {
        //App::$logger->LogDebug("Posting record" .  print_r($record, true));
        set_time_limit(300);

        if (!$record['point_str']) {
            continue;
        }

        $title = $record['title'];
        $state = $record['state'];
        $description = removeImgAndStyle($record['description']);
        $category = strtolower($record['category']);
        $last_category = strtolower($record['last_category']);

        if ($state == 'NSW') {
            // Remove from description table row "Location: View on map".
            // We have to search from the end to remove only one row.
            $descriptionR = strrev($description);
            $patternR = '@' . strrev('<tr*.><td*.>Location:</td><td*.><a*.>View on map</a></td></tr>') . '@Usi';
            $description = strrev(preg_replace($patternR, '', $descriptionR));
        }
        if ($state == 'VIC') {
            //$description = preg_replace('/()/g', '', $description)
        }
        if ($state == 'WA') {
            $description = preg_replace('/(style=\\"font-family:arial,.*?)>/', '', $description, 1);
        }
        $subject = sprintf('Bushfire %s: %s, %s', ucname($category), $title, $state);

        $textForWeb = sprintf('%s %s, %s', $state, $title, $description);
        $textForSMS = substr(strip_tags($textForWeb), 0, App::$config['smsLength']);
        //Strip carriage returns from sms
        $textForSMS = str_replace(array("\n", "\t", "\r", "\l"), '', $textForSMS);
        $textForFax = '';
        $createdDate = $record['unixtimestamp'];
        $expires = $record['unixtimestamp'] + 6 * 60 * 60;
        $alertURL = null; //$record['link'];
        $alertGUID = null; //$record['guid'];
        $centroids = array($record['point_str']);
        $alertKey = $record['alert_key'];
        $alertKey2 = $record['alert_key2'];
        $alertDeliveryLocationKey = $record['delivery_location_key'];
        $alertDeliveryLocationKey2 = $record['delivery_location_key2'];
        $externalId = $record['guid'];


        //unique case
        $SACategory = preg_match('/(fire)/i', $description);
        //$SANotCategory = preg_match('/(vehicle|tree)/i', $description);
        $severity = 4;
        $postboth = false;
        $alertGroupKey2 = 0;
        $forceDeliveryLocations = true;
        $lastSeverity = null;

        $alertGroupKey = App::$config['bushfireAdviceAlertGroupKey']; //getAlertGroup($category);
        $alertGroupKey2 = getAlertGroup2($category); // default to 0
        
        $severity = getseverity($category);
        $lastSeverity = getseverity($last_category);

        
        App::$logger->LogDebug("this category: $category");
        App::$logger->LogDebug("last category: $last_category");
        

        if ($category !=  'advice' && $category != 'watch and act' && $category != 'warning' && $category != 'alert' && $category != 'emergency warning' && $category != 'emergency') {
            App::$logger->LogError("Wrong category `$category` for `$textForWeb`, skipping.");
            continue;
        }
        if (latLngChanged($record['lat'], $record['lon'], $record['last_lat'], $record['last_lon']) || severityChanged($severity, $lastSeverity)) {
            $forceDeliveryLocations = true;
        }
        
        App::$logger->LogDebug("records lat long: " .$record['lat'] .",". $record['lon'] .",". $record['last_lat'] .",". $record['last_lon']);
        $latLngChangedResult = latLngChanged($record['lat'], $record['lon'], $record['last_lat'], $record['last_lon']);

        App::$logger->LogDebug("lat long changed: " . ($latLngChangedResult ? 'true' : 'false'));

        App::$logger->LogDebug("severity: $severity lastSeverity: $lastSeverity");
        $severityChangedResult = severityChanged($severity, $lastSeverity);

        App::$logger->LogDebug("severity changed: " . ($severityChangedResult ? 'true' : 'false'));

        App::$logger->LogDebug("force delivery location: " . ($forceDeliveryLocations ? 'true' : 'false'));
        if ($alertGroupKey2 > 0 && getAlertGroup($category) == 0) {
            $postboth = true;
        }
        App::$logger->LogDebug("Post both?: " . ($postboth ? 'true' : 'false'));
        if ($alertKey != 0) {
            App::$logger->LogDebug("Going to update data for `$title`.");
            try {
                $reply = $apiService->put($alertGroupKey, $subject, $textForWeb, $textForSMS, $textForFax,
                                      $createdDate, $expires, $alertURL, $alertGUID, $externalId, $centroids,
                                      $alertKey, $alertDeliveryLocationKey, $severity, $forceDeliveryLocations, $topicId);
            }
            catch (Exception $e) {
                $errorMsg = $e->getMessage();
                App::$logger->LogError($errorMsg);
                if (strpos($errorMsg, 'AlertGroupKey cannot be changed for an alert') !== false) {
                     App::$logger->LogDebug("Could not put `$textForWeb`. Will try to post.");
                     try {
                        $reply = $apiService->post($alertGroupKey, $subject, $textForWeb, $textForSMS, $textForFax,
                                                   $createdDate, $expires, $alertURL, $alertGUID, $externalId, $centroids, $severity);
                        App::$logger->LogDebug(" reply api_bushfire $reply");
                     }
                     catch (Exception $e) {
                          $errorMsg = $e->getMessage();
                          App::$logger->LogError($errorMsg);
                          continue;
                     }
                }
                else {
                    continue;
                }
            }
        }
        else {
            App::$logger->LogDebug("Going to post data for `$title`.");
            $reply = $apiService->post($alertGroupKey, $subject, $textForWeb, $textForSMS, $textForFax,
                                       $createdDate, $expires, $alertURL, $alertGUID, $externalId, $centroids, $severity);
        }
        if ($postboth == true)
        {
            if ($alertKey2 != 0) {
                App::$logger->LogDebug("Going to update data for `$title`.");
                try {
                    $reply2 = $apiService->put($alertGroupKey2, $subject, $textForWeb, $textForSMS, $textForFax,
                                          $createdDate, $expires, $alertURL, $alertGUID, $externalId, $centroids,
                                          $alertKey2, $alertDeliveryLocationKey2, $severity, $forceDeliveryLocations);
                }
                catch (Exception $e) {
                    $errorMsg = $e->getMessage();
                    App::$logger->LogError($errorMsg);
                    if (strpos($errorMsg, 'AlertGroupKey cannot be changed for an alert') !== false) {
                         App::$logger->LogDebug("Could not put `$textForWeb`. Will try to post.");
                         try {
                            $reply2 = $apiService->post($alertGroupKey2, $subject, $textForWeb, $textForSMS, $textForFax,
                                                       $createdDate, $expires, $alertURL, $alertGUID, $externalId, $centroids, $severity);
                            App::$logger->LogDebug(" reply api_bushfire $reply");
                         }
                         catch (Exception $e) {
                              $errorMsg = $e->getMessage();
                              App::$logger->LogError($errorMsg);
                              continue;
                         }
                    }
                    else {
                        continue;
                    }
                }
            }
            else {
                App::$logger->LogDebug("Going to post data for `$title`.");
                $reply2 = $apiService->post($alertGroupKey2, $subject, $textForWeb, $textForSMS, $textForFax,
                                           $createdDate, $expires, $alertURL, $alertGUID, $externalId, $centroids, $severity);
            }
        }
        
        if (!$reply) {
            App::$logger->LogDebug("Error posting/updating data for `$title`.");
            continue;
        }
        else {
            $l = strlen($textForSMS);
            App::$logger->LogDebug("Posted/updated data for `$title`. SMS: `$textForSMS`. SMS length: $l.");
        }

        $reply = json_decode($reply, true);
        //App::$logger->LogDebug("Reply: " . var_dump($reply));
        $updateResult = $incident->setPosted($record['guid'], $reply['AlertKey'], $reply['DeliveryLocations'][0]['AlertDeliveryLocationKey']);
        if ($postboth == true)
        {
            $reply2 = json_decode($reply2, true);
            //App::$logger->LogDebug("Reply2: " . var_dump($reply2));
            $updateResult = $incident->setPosted2($record['guid'], $reply2['AlertKey'], $reply2['DeliveryLocations'][0]['AlertDeliveryLocationKey']);
        }
        if ($updateResult) {
            App::$logger->LogDebug("Set posted for `$title` in DB.");
        }
        else {
            App::$logger->LogDebug("Error setting posted for `$title`.");
        }
    }    
    App::$logger->LogDebug("Finished executing file api_bushfires.php");
    set_time_limit($maxExecutionTime);
}
catch (Exception $e) {
    App::$logger->LogError($e->getMessage());
    exit();
}

function latLngChanged($lat, $lng, $last_lat, $last_lng) {
    if ($lat != $last_lat || $lng != $last_lng) {
        return true;
    }
    else {
        return false;
    }
}

function severityChanged($severity, $lastSeverity) {
    //Not sure if just Java convention return ($severity != $lastSeverity);
    if ($severity != $lastSeverity) {
        return true;
    }
    else {
        return false;
    }
}

function getAlertGroup($category) {
    if ($category == 'advice') {
        return App::$config['bushfireAdviceAlertGroupKey'];
    }
    return 0;
}

function getAlertGroup2($category) {
    if ($category == 'watch and act' || $category == 'warning' || $category == 'alert') {
        return App::$config['bushfireWatchActEmergencyAlertGroupKey'];
    }
    else if ($category == 'emergency warning' || $category == 'emergency') {
        return App::$config['bushfireWatchActEmergencyAlertGroupKey'];
    }
    else {
        return 0;
    }
}

function getSeverity($category) {
    if ($category == 'emergency warning' || $category == 'emergency') {
        return 0;
    }
    else {
        return 4;
    }
}


function ucname($string) {
    $string =ucwords(strtolower($string));

    foreach (array('-', '\'', '/', '(', ')') as $delimiter) {
        if (strpos($string, $delimiter)!==false) {
            $string =implode($delimiter, array_map('ucfirst', explode($delimiter, $string)));
        }
    }
    return $string;
}

function removeImgAndStyle($html) {
    // remove images
    $html = preg_replace("/<img[^>]+\>/i", "", $html);
    // remove style
    $html = preg_replace('/(<[^>]+) style=".*?"/i', '$1', $html);
    return $html;
}

function validateBushfireEventNSW($record) {
    return !preg_match(WRONG_NSW_BUSHFIRE_EVENT_PATTERN, $record['description']);
}

function validateBushfireEventTAS($record) {
    return !preg_match(WRONG_TAS_BUSHFIRE_EVENT_PATTERN, $record['description']);
}

function validateBushfireEventVIC($record) {
    if($record['type'] == 'alert'){
        return true;
    }
    return !preg_match(WRONG_VIC_BUSHFIRE_EVENT_PATTERN, $record['description']);
}

/**
 * This is a stub. The feed is not updated.
 * So we do not need filtering.
 *
 * @param array $record
 * @return boolean
 */
function validateBushfireEventNT($record) {
    return true;
}

/**
 * This is a stub. No approach to filter the feed at the moment.
 *
 * @param array $record
 * @return boolean
 */
function validateBushfireEventQLD($record) {
    return true;
}

/**
 * Check if WA event is really bushfire event.
 *
 * @param array $record
 * @return boolean
 */
function validateBushfireEventWA($record) {
    // All events look good.
    return true;
}

/**
 * Check if ACT event is really bushfire event.
 *
 * @param array $record
 * @return boolean
 */
function validateBushfireEventACT($record) {
    // Events are studied at the moment of parsing.
    return true;
}

/**
 * Check if SA event is really bushfire event.
 *
 * @param array $record
 * @return boolean
 */
function validateBushfireEventSA($record) {
    return !preg_match(WRONG_SA_BUSHFIRE_EVENT_PATTERN, $record['title']);
}

function filterBushfireEvents($records) {
    $filteredRecords = array();

    foreach ($records as $record) {
        $category = $record['category'];
        $description = removeImgAndStyle($record['description']);
        if ($category == 'advice' || $category == 'watch and act' || $category == 'emergency warning' || $category == 'warning' || $category == 'emergency' || $category == 'alert') {
            $isValid = call_user_func(
            'validateBushfireEvent' . strtoupper($record['state']),
            $record);
            if ($isValid) {
                $filteredRecords[] = $record;
                $filtMsg = sprintf('%s, %s, %s is valid bushfire event', $record['title'], $record['description'], $record['state']);
            }
            else {
                $filtMsg = sprintf('%s, %s, %s is not valid bushfire event', $record['title'], $record['description'], $record['state']);
            }
        }
        else {
            $filtMsg = sprintf('Bushfire event %s, %s is skipped because of category: `%s`.', $record['title'], $record['state'], $record['category']);
        }
    }
    return $filteredRecords;
}