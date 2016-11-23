<?php
require_once('send_error_email.php');
/*
 Request JSON example

{
"AlertKey": 0,
"AlertGroupKey": null,
"Subject": "API Update Existing Alert Test",
"TextForWeb": "Text for Web",
"TextForSMS": "EWN Test Message - Updated",
"TextForFax": "",
"CreatedDate": "2014-01-22 05:03:38Z",
"Expires": "2012-12-28 13:38:00Z",
"TopicKey": 1,
"IsDraft": false,
"Sent": null,
"IsBadMessage": false,
"IsDenied": false,
"IsApproved": false,
"FeedMonitor_UserKey": null,
"FeedMonitor_ChangeDate": null,
"AlertURL": "2012-11-15-033800-29221-435",
"AlertGUID": "cd127a0e-e6a0-4ddb-9aa1-93a2d2733f35",
"SendToAllInGroup": true,
"AlertType": "GIS",
"TextForVOIP": "",
"IsExpired": false,
"DoNotSendToExisting": false,
"HasExpired": true,
"ExternalID": "",
"ExternalDate": null,
"ShortURL": "",
"ShortURLTwitter": "",
"QuickAlertRecipients": [],
"DeliveryLocations": [
{
"PrimaryKey": 0,
"AlertDeliveryLocationKey": 0,
"AlertKey": 0,
"LocalityKey": null,
"LocationKey": null,
"DeliveryHtmlEmail": false,
"DeliveryTextEmail": false,
"DeliveryDesktopAlert": false,
"DeliverySms": true,
"DeliveryMms": false,
"DeliveryVoipToPhone": false,
"DeliveryVoipToIp": false,
"DeliveryPager": true,
"DeliveryWebsites": true,
"Priority": false,
"Polygon": null,
"CreatedDate": "2014-01-22 05:03:38Z",
"Severity": 0,
"Centroid": null
},
{
"PrimaryKey": 0,
"AlertDeliveryLocationKey": 0,
"AlertKey": 0,
"LocalityKey": null,
"LocationKey": null,
"DeliveryHtmlEmail": false,
"DeliveryTextEmail": false,
"DeliveryDesktopAlert": false,
"DeliverySms": true,
"DeliveryMms": false,
"DeliveryVoipToPhone": false,
"DeliveryVoipToIp": false,
"DeliveryPager": true,
"DeliveryWebsites": true,
"Priority": false,
"Polygon": null,
"CreatedDate": "2014-01-22 05:03:38Z",
"Severity": 0,
"Centroid": null
}
]
}
*/

define('REQUEST_TMPL',
    json_encode(array(
            "AlertKey"=> null,
            "AlertGroupKey"=> null,
            "Subject"=> null,
            "TextForWeb"=> null,
            "TextForSMS"=> null,
            "TextForFax"=> null,
            "CreatedDate"=> null,
            "Expires"=> null,
            "TopicKey"=> 1,
            "IsDraft"=> false,
            "Sent"=> null,
            "IsBadMessage"=> false,
            "IsDenied"=> false,
            "IsApproved"=> false,
            "FeedMonitor_UserKey"=> null,
            "FeedMonitor_ChangeDate"=> null,
            "AlertURL"=> null,
            "AlertGUID"=> null,
            "SendToAllInGroup"=> true,
            "AlertType"=> "GIS",
            "TextForVOIP"=> "",
            "IsExpired"=> false,
            "DoNotSendToExisting"=> false,
            "HasExpired"=> false,
            "ExternalID"=> "",
            "ExternalDate"=> null,
            "ShortURL"=> "",
            "ShortURLTwitter"=> "",
            "QuickAlertRecipients"=> array(),
            "DeliveryLocations"=> array()
        )
    )
);

define('REQUEST_ITEM_TMPL',
    json_encode(array(
            "PrimaryKey"=> 0,
            "AlertDeliveryLocationKey"=> 0,
            "AlertKey"=> null,
            "LocalityKey"=> null,
            "LocationKey"=> null,
            "DeliveryHtmlEmail"=> true,
            "DeliveryTextEmail"=> true,
            "DeliveryDesktopAlert"=> true,
            "DeliverySms"=> true,
            "DeliveryMms"=> false,
            "DeliveryVoipToPhone"=> false,
            "DeliveryVoipToIp"=> false,
            "DeliveryPager"=> true,
            "DeliveryWebsites"=> true,
            "Priority"=> false,
            "Polygon"=> null,
            "CreatedDate"=> null,
            "Severity"=> 4,
            "Centroid"=> null
        )
    )
);



class APIService{

    private $logger = null;
    private $globalData = null;
    private $apiRoot = null;
    private $apiKey = null;
    private $timeLimit = null;
    private $requestTmpl = null;
    private $requestItemTmpl = null;

    public function __construct($apiRoot, $apiKey, $timeLimit=600,
                                $requestTmpl=REQUEST_TMPL,
                                $requestItemTmpl=REQUEST_ITEM_TMPL){
        $this->apiRoot = $apiRoot;
        $this->apiKey = $apiKey;
        $this->timeLimit = $timeLimit;
        $this->requestTmpl = json_decode($requestTmpl, true);
        $this->requestItemTmpl = json_decode($requestItemTmpl, true);
        $this->globalData = null;

        $this->logger = new KLogger ("/var/www/html/dev/bushfire-traffic/log/log_output.log",
                                     1);
    }

    public function init($uri, $data=null){
        $handler = curl_init($uri);

        $httpHeaders = array('Accept: application/json',
                             sprintf('APIKey: %s', $this->apiKey));

        if($data){
            $httpHeaders[] = 'Content-Type: application/json';
            $httpHeaders[] = 'Content-Length: ' . strlen($data);
            $this->globalData = $data;
            curl_setopt($handler, CURLOPT_POSTFIELDS, $data);
        }

        curl_setopt($handler, CURLOPT_HTTPHEADER, $httpHeaders);
        curl_setopt($handler, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($handler, CURLOPT_HEADER, 0);
        curl_setopt($handler, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($handler, CURLOPT_MAXREDIRS, 10);

        curl_setopt($handler, CURLINFO_HEADER_OUT, true);

        return $handler;
    }
    /*
     * Timestamp to GMT time in needed format
     *
     * $timestamp - int timestamp, if null or not set current timestamp is used.
     *
     * return string "2014-12-28 13:38:00Z"
     */
    public function timeStampToGMT($timestamp=null){
        if(!$timestamp){
            $timestamp = time();
        }
        return gmdate("Y-m-d H:i:s\Z", $timestamp);
    }

    public function formatRequestItemJSON($alertKey, $centroid, $createdDate,
                                          $alertDeliveryLocationKey, $severity=4){
        $item = $this->requestItemTmpl;

        $item['AlertKey'] = $alertKey;
        $item['Centroid'] = $centroid;
        $item['CreatedDate'] = $createdDate;
        $item['AlertDeliveryLocationKey'] = $alertDeliveryLocationKey;
        $item['Severity'] = $severity;
        //App::$logger->LogDebug("severity in array: " . $item['Severity']);
        return $item;
    }

    public function formatRequestJSON($alertGroupKey, $subject, $textForWeb, $textForSMS,
                                      $textForFax, $createdDate, $expires,
                                      $alertURL, $alertGUID, $externalId,  $centroids=null,
                                      $alertKey=0, $alertDeliveryLocationKey=0, $severity=4, $forceDeliveryLocations=false){
        $event = $this->requestTmpl;
        
        if(strlen($textForSMS) > 160){
            $textForSMS = substr($textForSMS, 0, 159);
        }
        $event['AlertGroupKey'] = $alertGroupKey;
        $event['Subject'] = $subject;
        $event['TextForWeb'] = $textForWeb;
        $event['TextForSMS'] = $textForSMS;
        $event['TextForFax'] = $textForFax;
        $event['CreatedDate'] = $this->timeStampToGMT($createdDate);
        $event['Expires'] = $this->timeStampToGMT($expires);
        $event['AlertURL'] = $alertURL;
        $event['AlertGUID'] = $alertGUID;
        $event['ExternalID'] = $externalId; 
        //$this->logger->LogDebug('ExternalID:' . $alertGUID);



        $event['AlertKey'] = $alertKey;
        if ($createdDate != null) {
            $event['ExternalDate'] = (string)date("Y-m-d h:i:s", $createdDate);
            //$this->logger->LogDebug("External Date:" . $event['ExternalDate']);
        }
        else {
            App::$ogger->LogDebug('No created date available. ExternalDate set to "".');
        }
        if ($alertKey === 0 || $forceDeliveryLocations) { //  $latLngChnged || $severityChanged
            if ($centroids) {
                foreach ($centroids as $centroid) {
                    $event['DeliveryLocations'][] = $this->formatRequestItemJSON($alertKey, $centroid, $this->timeStampToGMT($createdDate), 0, $severity);
                }
            }else {
                $event['DeliveryLocations'][] = $this->formatRequestItemJSON($alertKey, null, $this->timeStampToGMT($createdDate), 0, $severity);
            }
        }

        //App::$logger->LogDebug("event array formatRequestJson: " . print_r($event, true));
       // App::$logger->LogDebug("severity: " . $severity);

        return json_encode($event);
    }
        public function formatRequestJSONTraffic($alertGroupKey, $subject, $textForWeb, $textForSMS,
                                      $textForFax, $createdDate, $expires, $topicKey,
                                      $alertURL, $alertGUID, $externalId,  $centroids=null,
                                      $alertKey=0, $alertDeliveryLocationKey=0){
        $event = $this->requestTmpl;
        
        if(strlen($textForSMS) > 160){
            $textForSMS = substr($textForSMS, 0, 159);
        }
        
        $event['AlertGroupKey'] = $alertGroupKey;
        $event['Subject'] = $subject;
        $event['TextForWeb'] = $textForWeb;
        $event['TextForSMS'] = $textForSMS;
        $event['TextForFax'] = $textForFax;
        $event['CreatedDate'] = $this->timeStampToGMT($createdDate);
        $event['Expires'] = $this->timeStampToGMT($expires);
        $event['TopicKey'] = $topicKey;
        $event['AlertURL'] = $alertURL;
        $event['AlertGUID'] = $alertGUID;
        $event['ExternalID'] = $externalId; 
        //$this->logger->LogDebug('ExternalID:' . $alertGUID);
        $event['AlertKey'] = $alertKey;
       
        $severity = 4;
        if ($createdDate != null) {
            $event['ExternalDate'] = (string)date("Y-m-d h:i:s", $createdDate);
            //$this->logger->LogDebug("External Date:" . $event['ExternalDate']);
        }
        else {
            App::$logger->LogDebug('No created date available. ExternalDate set to "".');
        }
        if($alertKey === 0){
            if($centroids){
                foreach($centroids as $centroid){
                    $event['DeliveryLocations'][] = $this->formatRequestItemJSON(
                         $alertKey, $centroid, $this->timeStampToGMT($createdDate),
                         0, $severity);
                }
            }else{
                $event['DeliveryLocations'][] = $this->formatRequestItemJSON(
                         $alertKey, null, $this->timeStampToGMT($createdDate), 0, $severity);
            }
        }

        return json_encode($event);
    }

    protected function execute($handler){
        $maxEcetutionTime = ini_get('max_execution_time');
        set_time_limit($this->timeLimit);


        
        $urlRequest = curl_getinfo($handler, CURLOPT_URL );
        //App::$logger->LogDebug($urlRequest);
        //App::$logger->LogDebug('APIService app initialized.');


        $curlError = curl_error($handler);
        // verbose debugging
        curl_setopt($handler,CURLOPT_VERBOSE,true);
        // output stderror to klog
        curl_setopt($handler,CURLOPT_STDERR ,$this->logger->GetLogFileHandle());



        set_time_limit($maxEcetutionTime);
        $data = curl_exec($handler);

        

        $httpCode = curl_getinfo($handler, CURLINFO_HTTP_CODE);
        $httpResponse = curl_getinfo($handler);

        //App::$logger->LogDebug("HTTP code: $httpCode");
        //App::$logger->LogDebug("HTTP response: " . print_r($httpResponse, true));


        curl_close($handler);
        $debug = var_export($this->globalData, true);

        App::$logger->LogDebug('~~~~~~ Curl Request ~~~~~~~~~');
        App::$logger->LogDebug("Request: " . $debug);

        $reply = json_decode($data, true);
        $reply = var_export($reply, true);
        App::$logger->LogDebug('~~~~~~ Curl Response ~~~~~~~~~');
        App::$logger->LogDebug("Response:" . $reply);


        

        if ($httpCode == 400 && preg_match('/(AlertGroupKey)/', $data)) {
            App::$logger->LogDebug("Alert group key changed, posting new alert, new alert group key: " . $event['AlertGroupKey2']);
        }
        else if($httpCode != 200) {
            $errorMsg = sprintf(".\nAPI root: %s \nCurl error: %s \nHTTP Code: %s \nReply: %s \nData: %s ", $this->apiRoot, $curlError, $httpCode, $data, $debug);
            //Add email error here
            mailError("Can't post latest bushfire/traffic alert" . $errorMsg);
            throw new Exception($errorMsg);
        }

        return $data;
    }

    public function post($alertGroupKey, $subject, $textForWeb, $textForSMS,
                         $textForFax, $createdDate, $expires,
                         $alertURL, $alertGUID, $externalId, $centroids=null, $severity=4){
        $uri = $this->apiRoot;
        $data = $this->formatRequestJSON($alertGroupKey, $subject, $textForWeb,
                                         $textForSMS, $textForFax, $createdDate,
                                         $expires, $alertURL, $alertGUID, $externalId,
                                         $centroids, 0, 0, $severity);
        
        //App::$logger->LogDebug("alert group key: " . $alertGroupKey);
        /*App::$logger->LogDebug("subject: " . $subject);
        App::$logger->LogDebug("text for web: " . $textForWeb);
        App::$logger->LogDebug("text for SMS: " . $textForSMS);
        App::$logger->LogDebug("text for fax: " . $textForFax);
        App::$logger->LogDebug("created date: " . $createdDate);
        App::$logger->LogDebug("expires: " . $expires);
        App::$logger->LogDebug("alert URL: " . $alertURL);
        App::$logger->LogDebug("alert GUID: " . $alertGUID);
        App::$logger->LogDebug("external ID: " . $externalId);
        App::$logger->LogDebug("data array dump: " . var_dump($data));
        App::$logger->LogDebug("Post text for web: " . $textForWeb);
        */

        $handler = $this->init($uri, $data);
        curl_setopt($handler, CURLOPT_CUSTOMREQUEST, 'POST');

        return $this->execute($handler);
    }
    public function postTraffic($alertGroupKey, $subject, $textForWeb, $textForSMS,
                         $textForFax, $createdDate, $expires, $topicKey,
                         $alertURL, $alertGUID, $externalId, $centroids=null){
        $uri = $this->apiRoot;
        $data = $this->formatRequestJSONTraffic($alertGroupKey, $subject, $textForWeb,
                                         $textForSMS, $textForFax, $createdDate,
                                         $expires, $topicKey, $alertURL, $alertGUID, $externalId,
                                         $centroids);

        //App::$logger->LogDebug("Post text for web: " . $textForWeb);

        $handler = $this->init($uri, $data);
        curl_setopt($handler, CURLOPT_CUSTOMREQUEST, 'POST');

        return $this->execute($handler);
    }

    public function get($alertKey){
        $uri = $this->apiRoot . '/' . $alertKey;
        $handler = $this->init($uri);
        curl_setopt($handler, CURLOPT_CUSTOMREQUEST, 'GET');

        return $this->execute($handler);
    }

    public function put($alertGroupKey, $subject, $textForWeb, $textForSMS,
                        $textForFax, $createdDate, $expires,
                        $alertURL, $alertGUID, $externalId, $centroids,
                        $alertKey, $alertDeliveryLocationKey, $severity=4, $forceDeliveryLocations){
        $uri = $this->apiRoot . '/' . $alertKey;
        $data = $this->formatRequestJSON($alertGroupKey, $subject, $textForWeb,
                                         $textForSMS, $textForFax, $createdDate,
                                         $expires, $alertURL, $alertGUID, $externalId,
                                         $centroids, $alertKey,
                                         $alertDeliveryLocationKey, $severity, $forceDeliveryLocations);
        
        App::$logger->LogDebug("alert group key: " . $alertGroupKey);
        /*App::$logger->LogDebug("subject: " . $subject);
        App::$logger->LogDebug("text for web: " . $textForWeb);
        App::$logger->LogDebug("text for SMS: " . $textForSMS);
        App::$logger->LogDebug("text for fax: " . $textForFax);
        App::$logger->LogDebug("created date: " . $createdDate);
        App::$logger->LogDebug("expires: " . $expires);
        App::$logger->LogDebug("alert URL: " . $alertURL);
        App::$logger->LogDebug("alert GUID: " . $alertGUID);
        App::$logger->LogDebug("external ID: " . $externalId);
        App::$logger->LogDebug("data array dump: " . var_dump($data));
        */

        $handler = $this->init($uri, $data);
        curl_setopt($handler, CURLOPT_CUSTOMREQUEST, 'PUT');

        return $this->execute($handler);
    }
    public function putTraffic($alertGroupKey, $subject, $textForWeb, $textForSMS,
                        $textForFax, $createdDate, $expires, $topicKey,
                        $alertURL, $alertGUID, $externalId, $centroids,
                        $alertKey, $alertDeliveryLocationKey){
        $uri = $this->apiRoot . '/' . $alertKey;
        $data = $this->formatRequestJSONTraffic($alertGroupKey, $subject, $textForWeb,
                                         $textForSMS, $textForFax, $createdDate,
                                         $expires, $topicKey, $alertURL, $alertGUID, $externalId,
                                         $centroids, $alertKey,
                                         $alertDeliveryLocationKey);
        $handler = $this->init($uri, $data);
        curl_setopt($handler, CURLOPT_CUSTOMREQUEST, 'PUT');

        return $this->execute($handler);
    }

    public function delete($alertKey){
        $uri = $this->apiRoot . '/' . $alertKey;
        $handler = $this->init($uri);
        curl_setopt($handler, CURLOPT_CUSTOMREQUEST, 'DELETE');

        return $this->execute($handler);
    }
}