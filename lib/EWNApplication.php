<?php
require_once('lib/KLogger.php');

class EWNApplication {
    private $config = null;
    private $db = null;
    private $logger = null;
    private $htmlPurifier = null;

    public function __construct($config) {
        $this->config = $config;
        $this->db = new Db($this->config['db']['host'],
                           $this->config['db']['user'],
                           $this->config['db']['pass'],
                           $this->config['db']['base']);
        $this->logger = new KLogger ($this->config['logPath'], $this->config['logLevel']);
        $this->htmlPurifier = new HTMLPurifier(HTMLPurifier_Config::createDefault());
        $this->logger->LogDebug('EWNApplication app initialized.');
    }

    public function in_array_case_insensitive($needle, $haystack) {
        return in_array(strtolower($needle), array_map('strtolower', $haystack));
    }

    public function getExistingRecordsTitles($values) {
        $fieldNames = array('guid', 'title', 'state', 'description', 'category',
                        'link', 'unixtimestamp', 'lon', 'lat', 'point_str', 'geocoded',
                        'point_geom', 'type', 'event', 'update_ts', 'feed_type', 'posted', 'geometries');

        $skipNames = array('unixtimestamp', 'guid', 'point_geom', 'lon', 'lat', 'geocoded', 'update_ts', 'posted');

        $rowConditions = array();
        foreach ($values as $row) {
            $fieldConditions = array();
            for ($i=0; $i<count($fieldNames); $i++) {
                if (in_array($fieldNames[$i], $skipNames)) {
                    continue;
                }
                if ($row[$i] === 'NULL') {
                    $fieldConditions[] = sprintf("`%s` IS NULL", $fieldNames[$i]);
                }
                else if(in_array($fieldNames[$i], array('point_str', 'geometries'))) {
                    $fieldConditions[] = sprintf("`%s` = %s", $fieldNames[$i], $row[$i]);
                }
                else {
                    $fieldConditions[] = sprintf("`%s` = '%s'", $fieldNames[$i], $row[$i]);
                }
            }
            $rowConditions[] = sprintf("(%s)", implode(' AND ', $fieldConditions));
        }
        $tableName = sprintf("`%s`.`%s`", $this->config['db']['base'], $this->config['db']['incidents_table']);
        $sql = sprintf("SELECT `title` FROM %s WHERE %s", $tableName, implode(' OR ', $rowConditions));
        //$this->logger->LogDebug($sql);
        $values = $this->db->getValues($sql);

        return $values;
    }

    public function dbPrepareDescription($description) {
        $len = $this->config['max_db_description_legth'];

        if (strlen($description) > $len - 100) {
            $this->logger->LogError(sprintf(
                           'Description longer than %s. Abridgeing...', $len));
            $description = substr($description, 0, $len - 100);
        }
        $description = $this->db->escapeString($this->htmlPurifier->purify($description));
        $description = $this->stringCleaner($description);

        return $description;
    }

    public function stringCleaner($description) {
        //Reference: http://stackoverflow.com/questions/7419302/converting-microsoft-word-special-characters-with-php
        $search = [                 // www.fileformat.info/info/unicode/<NUM>/ <NUM> = 2018
            "\xC2\xAB",     // « (U+00AB) in UTF-8
            "\xC2\xBB",     // » (U+00BB) in UTF-8
            "\xE2\x80\x98", // ‘ (U+2018) in UTF-8
            "\xE2\x80\x99", // ’ (U+2019) in UTF-8
            "\xE2\x80\x9A", // ‚ (U+201A) in UTF-8
            "\xE2\x80\x9B", // ‛ (U+201B) in UTF-8
            "\xE2\x80\x9C", // “ (U+201C) in UTF-8
            "\xE2\x80\x9D", // ” (U+201D) in UTF-8
            "\xE2\x80\x9E", // „ (U+201E) in UTF-8
            "\xE2\x80\x9F", // ‟ (U+201F) in UTF-8
            "\xE2\x80\xB9", // ‹ (U+2039) in UTF-8
            "\xE2\x80\xBA", // › (U+203A) in UTF-8
            "\xE2\x80\x93", // – (U+2013) in UTF-8
            "\xE2\x80\x94", // — (U+2014) in UTF-8
            "\xE2\x80\xA6"  // … (U+2026) in UTF-8
        ];

        $replacements = [
            "<<", 
            ">>",
            "'",
            "'",
            "'",
            "'",
            '"',
            '"',
            '"',
            '"',
            "<",
            ">",
            "-",
            "-",
            "..."
        ];

        //if (preg_match($search, $description)) {
            //replace ms word special characters with ascii compliant characters (0 - 127).
            str_replace($search, $replacements, $description);
            //$this->logger->LogDebug("Invalid ms word characters have been swapped.");
        //}

        //if any values out of the ascii table range (32 - 126), it should be okay to remove everything else that isn't in after running through everything else.
        if (preg_match('/[\x00-\x1F\x80-\xFF]/', $description)) {
            //Remove invalid characters
            //$this->logger->LogDebug("Before: " . $description);
            $description = preg_replace('/[\x00-\x1F\x80-\xFF]/', '', $description);
            //$this->logger->LogDebug("After: " . $description);
            //$this->logger->LogDebug("Invalid ascii characters have been removed.");
        }
        //$this->logger->LogDebug($description);
        return $description;
    }

    public function checkCoordinates($lon, $lat) {
        if ($lat >= $this->config['bbox']['minLat'] && $lat <= $this->config['bbox']['maxLat'] && $lon >= $this->config['bbox']['minLon'] && $lon <= $this->config['bbox']['maxLon']) {
            return true;
        }
        else {
            return false;
        }
    }

    /**
     * Updates database.
     *
     * @param $events - array with keys: 'title', 'link', 'category', 'guid',
     * 'pubDate', 'description', 'coordinates', pubtimestamp
     * @param $type - string (alert/insident)
     * @param $event_type string (bushfire/traffic)
     */
    public function updateIncidents($events, $state, $event_type, $feedType){

        //$this->logger->LogDebug("database values" . print_r($events, true));

        $result = true;
        $dbTable = sprintf("`%s`.`%s`", $this->config['db']['base'],
                                       $this->config['db']['incidents_table']);

        $sqlTemplate = "INSERT INTO %s (`guid`,  `title`, `state`,
                            `description`, `category`, `link`, `unixtimestamp`,
                            `lon`,`lat`,`point_str`, `point_geom`, `geocoded`, `type`,
                            `event`, `update_ts`, `feed_type`, `posted`, `geometries`)
                        VALUES %s ON DUPLICATE KEY UPDATE
                            `unixtimestamp`=VALUES(`unixtimestamp`),
                            `description`=VALUES(`description`),
                            `category`=VALUES(`category`),
                            `link`=VALUES(`link`),
                            `update_ts`=VALUES(`update_ts`),
                            `lon`=VALUES(`lon`),
                            `lat`=VALUES(`lat`),
                            `point_str`=VALUES(`point_str`),
                            `point_geom`=VALUES(`point_geom`),
                            `posted`=0,
                            `geometries`=VALUES(`geometries`),
                            `last_lon`=`lon`,
                            `last_lat`=`lat`,
                            `last_category`= `category`
                            ";

        $valuesTemplate = "('%s', '%s', '%s', '%s',
                                '%s', '%s', '%s',
                                %s, %s, %s, %s, %s, '%s', '%s', '%s', '%s', %s, %s)";
        $update_ts = time();
        $valuesArray = array();
        $this->logger->LogDebug("Records to insert before filter for ".$state."." . count($events));
        foreach ($events as $event) {
            $ignoreBeforeTimeStamp = time() - $this->config['clear_before_hours'] * 60 * 60;
            if ($event['pubtimestamp'] < $ignoreBeforeTimeStamp) {
                continue;
            }
            /*
            if ($state == 'SA' || $state == 'NSW') {
                $this->logger->LogDebug("$state array values: $event_type" . print_r($event, true));
            }
            */
            $coordinates = 'NULL';
            $lon = 'NULL';
            $lat = 'NULL';
            $pointGeom = 'NULL';

            if ($event['coordinates']) {
                if(strstr(',', $event['coordinates'])){

                }else{
                    if($state == 'QLD') {
                        list($latTmp, $lonTmp) = explode(" ", $event['coordinates']);
                    }
                    else {
                        list($lonTmp, $latTmp) = explode(" ", $event['coordinates']);
                    }
                    if($this->checkCoordinates($lonTmp, $latTmp)){
                        $pointGeom = sprintf("GeomFromText('POINT(%s)')",
                                             $event['coordinates']);
                        $lon = "'$lonTmp'";
                        $lat = "'$latTmp'";
                        $coordinates = "'" . $event['coordinates']. "'";
                    }else{
                        $errorMsg = sprintf(
                         'Wrong coordinates: `%s` for event guid `%s`, `%s`, `%s`',
                                                            $event['coordinates'],
                                                            $event['guid'],
                                                            $event['title'],
                                                            $state);
                        $this->logger->LogError($errorMsg);
                    }
                }
            }

            $titleEscaped = $this->db->escapeString(
                            $this->htmlPurifier->purify($event['title']));

            $descriptionEscaped = $this->dbPrepareDescription($event['description']);

            //$this->logger->LogDebug("Description $state $event_type: " . $event['description']);
            //$this->logger->LogDebug("Description $state $event_type: " . $descriptionEscaped);

            if(is_null($event['category'])){
                $categoryEscaped = 'No Alert Level';
            }else{
                $categoryEscaped = $this->db->escapeString($event['category']);
            }

            $categoryEscaped = strtolower($categoryEscaped);

            $typeEscaped = $this->db->escapeString($event['type']);

            if(array_key_exists('event_type' ,$event)){
                $insertType = $event['event_type'];
            }else{
                $insertType = $event_type;
            }

            if(array_key_exists('geometries', $event)
               && (!is_null($event['geometries']))){
                $geometries = sprintf("'%s'", $event['geometries']);
            }else{
                $geometries = 'NULL';
            }

            $valuesArray[] = array($event['guid'],  $titleEscaped,
                                $state, $descriptionEscaped, $categoryEscaped,
                                $event['link'], $event['pubtimestamp'],
                                $lon, $lat, $coordinates, $pointGeom, $event['geocoded'],
                                $typeEscaped, $insertType, $update_ts, $feedType, 0, $geometries);
        }

        if(!$valuesArray){
            $this->logger->LogDebug("Nothing to insert for $state, $event_type.");
            return true;
        }

        // We have to check if there are exactly the same records in the
        // table to avoid rewritting.


        $existingTitles = $this->getExistingRecordsTitles($valuesArray);

        $filteredValuesArray = array();

        foreach($valuesArray as $values){
            $title = $values[1];
            if(!$this->in_array_case_insensitive($title, $existingTitles)){
                $filteredValuesArray[] = $values;
            }
        }

        if(!$filteredValuesArray){
            $this->logger->LogDebug("Nothing new to insert for $state, $event_type.");
            return true;
        }

        $values = array();
        foreach($filteredValuesArray as $params){
            array_unshift($params, $valuesTemplate);
            $values[] = call_user_func_array('sprintf', $params);
        }

        $sql = sprintf($sqlTemplate, $dbTable, implode(', ', $values));

        $this->logger->LogDebug($sql);

        if(!$this->db->executeQuery($sql)){
            $this->logger->LogError(sprintf('Error executing query "%s".', $sql));
            $result = false;
        }else{
            $valuesNumber = count($values);

            ob_start();                    // start buffer capture
            var_dump( $values );           // dump the values
            $contents = ob_get_contents(); // put the buffer into a variable
            ob_end_clean();                // end capture


            $this->logger->LogDebug("inserted values for ".$state.": " . $contents);
            $this->logger->LogDebug("Inserted or updated $valuesNumber records for $state, $event_type.");
        }

        return $result;
    }
    /*
     * Update timestamp and update feed publication time if is passed.
     */
    public function updateTimestamp($state, $type, $event, $feedPubDate=null){
        $timestamp = time();

        $setValues = "`update_timestamp`=$timestamp ";
        if($feedPubDate){
            $setValues .= sprintf(", `time_string`='%s'", $feedPubDate);
        }

        $tsql = "UPDATE `%s`
                        SET %s
                        WHERE `state`='%s'
                        AND `type`='%s'
                        AND `event`='%s'";
        $sql = sprintf($tsql, $this->config['db']['lastUpdateTimeTable'],
                              $setValues,
                              $state,
                              $type,
                              $event);

        $updateTsResult = $this->db->executeQuery($sql);

        if($updateTsResult){
            $this->logger->LogDebug(
            "Timestamps table updated for $state, $type, $event.");
        }else{
            $this->logger->LogError(
            "Error updating timestamps table for $state, $type, $event.");
        }

        return $updateTsResult;
    }

    public function getLastUpdateTime($state, $type, $event){
        $this->logger->LogDebug("getLastUpdateTime() $state, $type, $event");
        $tsql = "SELECT `time_string`
                        FROM  `%s`
                        WHERE `state`='%s'
                        AND `type`='%s'
                        AND `event`='%s'";

        $sql = sprintf($tsql,
                        $this->config['db']['lastUpdateTimeTable'],
                        $state,
                        $type,
                        $event);

        $lastUpdated = $this->db->getValue($sql);

        return $lastUpdated;
    }

    public function endsWith($FullStr, $EndStr) {
        // Get the length of the end string
        $StrLen = strlen($EndStr);
        // Look at the end of FullStr for the substring the size of EndStr
        $FullStrEnd = substr($FullStr, strlen($FullStr) - $StrLen);
        // If it matches, it does end with EndStr
        return $FullStrEnd == $EndStr;
    }

    public function deleteOldLogs() {
        $files=array();
        $handle = dir($this->config['logFolder']);
        while ($entry = $handle->read()) {
            if ($entry == '.' || $entry == '..') {
                continue;
            }
            if ($this->endsWith($entry, '.log')) {
                $oldestDate = date('Ymd', time() - $this->config['keepLogsDays'] * 86400);
                $fileDate = substr($entry, count($entry) - 13 ,8);
                if ($fileDate < $oldestDate) {
                    $deletePath = $this->config['logFolder'] . '/' . $entry;
                    if (@unlink($deletePath)) {
                        $this->logger->LogDebug("Deleted file `$deletePath`.");
                    }
                    else {
                        $this->logger->LogError("Couldn't deleted file `$deletePath`.");
                    }
                }
            }
        }
    }

    public function clear() {
        $this->logger->LogDebug('Starting to clear.');
        $clearer = new Clearer($this->db, $this->config);
        $clearer->clear();
        $this->logger->LogDebug("Cleared old db records.");
        $this->deleteOldLogs();
        $this->logger->LogDebug("Deleted old log files.");
        $this->logger->LogDebug('Finished to clear.');
    }

    public function getFeed($event, $state, $type) {
        try {
            $this->logger->LogDebug('Starting to get Feed.');
            // Default type if event is bushfire
            $this->logger->LogDebug("Event: $event, state: $state, type: $type.");
            $feedLoader = null;
            $curlService = new CurlService();

            if ($event == 'bushfire') {
                $feedLoader = new Bushfire($this->config,
                                           $curlService,
                                           $state,
                                           $type,
                                           $this->db,
                                           $this->logger);
            }else if ($event == 'traffic') {
                $feedLoader = new Traffic($this->config,
                                          $curlService,
                                          $state,
                                          $type,
                                          $this->db,
                                          $this->logger);
            }
            else {
                throw new InputFeedException(sprintf('Wrong event input: `%s`', $event));
            }

            $feedLoader->loadFeed();
            $pubTime = $feedLoader->getPubDate();
            $lastUpdateTime = $this->getLastUpdateTime($state, $type, $event);
            $this->logger->LogDebug("pubTime: $pubTime, lastUpdateTime: $lastUpdateTime.");

            if ($pubTime == $lastUpdateTime) {
                $this->logger->LogDebug("pubTime is equal to lastUpdateTime, exiting ...");
                // Only update timestamp.
                $updateTsResult = $this->updateTimestamp($state, $type, $event);
                return;
            }


            //get events from $feedLoader->document
            //see Bushfire.php - for each state's document processing

            $events = $feedLoader->getEvents();

            ob_start();                    // start buffer capture
            var_dump( $events );           // dump the values
            $contents = ob_get_contents(); // put the buffer into a variable
            ob_end_clean();                // end capture


            $this->logger->LogDebug("events for ".$state.": " . $contents);

            //$this->logger->LogDebug("Got to here for $state, $type" . print_r($events, true) . "");

            if ($events) {
                //$this->logger->LogDebug("Going to post/update incidents for $state, $type, $event.");
                if ($this->updateIncidents($events, $state, $event, $type)) {
                    $this->logger->LogDebug("Incidents table updated for $state, $type, $event.");
                    // Save new $pubTime and update timestamp.
                    $updateTsResult = $this->updateTimestamp($state, $type, $event, (string)$pubTime);
                }
                else {
                    $this->logger->LogError("Error updating incidents table for $state, $type, $event.");
                }
            }else {
                // Only update timestamp.
                $updateTsResult = $this->updateTimestamp( $state, $type, $event);
            }

        }
        catch (Exception $e) {
            $this->logger->LogError(sprintf('%s:%s - %s', $e->getFile(), $e->getLine(), $e->getMessage()));
        }
    }
}