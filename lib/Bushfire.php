<?php
class Bushfire extends EventFeedAbs {
  public function getUrl() {
    if ($this->state == 'WA') {
        // Get page with feeds' URLS.
        $waLinksPage = $this->curlService->executeCurl($this->config['waLinksPageUrl'], $this->state);
        // Find current alert feed URL.
        preg_match_all($this->config['waUrlListPattern'], $waLinksPage, $waUrlListResults);
        // URL params.
        $waUrlList = $waUrlListResults[1][0];
        // Full URL.
        $url = $this->config['waAlertUrl'] . $waUrlList;
    }
    else {
      if ($this->type == 'incident') {
        $url = $this->config['incidentUrls'][$this->state];
      }
      else {
        $url = $this->config['alertUrls'][$this->state];
      }
    }
    return $url;
  }

  /*
   * $curTimeString variable is for testing.
   * It is used because different variants of $timeString and current
   * time should be tested, but we can't set system time.
   * In production this variable is always "now".
   */
  public function jsonQLDAlertTimeStringToUnixtimestamp($timeString, $curTimeString="now") {
    /*
    $pos = strpos($timeString, ' ');
    if ($pos === false) {
        var_dump($timeString);
        exit();
    }
    */
    // "17-Nov 21:31"
    list($dayMonth, $hourMinute) = explode(' ', $timeString);
    list($day, $month) = explode('-', $dayMonth);
    // Going to use following time format for conversion into
    //timestamp:
    // Common Log Format
    // dd "/" M "/" YY : HH ":" II ":" SS space tzcorrection
    // "10/Oct/2000:13:55:36 -0700"

    /*
     * We need to detect year of event.
     * date("Y") is not enough because it is possible that event
     * happened in previous year.
     * We can check if current month is the same as the one in
     * attribute of event.
     * Also we need current year in Queensland.
     */
    $dateTime = new DateTime($curTimeString, new DateTimeZone('Australia/Queensland'));
    $curMonth = $dateTime->format("M");

    // Possible months' names:
    // Jan Feb Mar Apr May Jun Jul Aug Sep Oct Nov Dec
    if ($curMonth == $month) {
          $year = $dateTime->format("Y");
    }
    else {
      if (($curMonth == 'Jan' || $curMonth == 'Feb') && ($month =='Nov' || $month == 'Dec')) {
        $year = (int)$dateTime->format("Y") - 1;
      }
      else {
       $year = $dateTime->format("Y");
      }
    }
    $offset = $this->getTimezoneOffset('UTC', 'Australia/Queensland');
    // Common Log Format
    // dd "/" M "/" YY : HH ":" II ":" SS space tzcorrection
    // "10/Oct/2000:13:55:36 -0700"
    $pubDate = sprintf('%s/%s/%s:%s:00 +%02d00', $day, $month, $year, $hourMinute, $offset/3600);
    $pubtimestamp = strtotime($pubDate);
    /*
    $this->logger->LogDebug(sprintf(
        "%s,%s time string `%s` was converted into Log Format `%s` and converted into timestamp `%s`",
        $this->state, $this->type == 'alert', $timeString, $pubDate, $pubtimestamp));
    */
    return $pubtimestamp;
  }

  public function getPubDateQLDAlert() {
    //"active" / "closed" / "permittedBurns"
    /*
      object(stdClass)#901 (11) {
      ["id"]=>
      string(13) "QF3-12-118096"
      ["displayType"]=>
      int(3)
      ["alertIcon"]=>
      string(13) "PermittedBurn"
      ["status"]=>
      string(5) "Going"
      ["alarmTime"]=>
      string(12) "19-Nov 20:17"
      ["lastUpdate"]=>
      string(12) "19-Nov 20:19"
      ["location"]=>
      string(18) "Pyramids Rd, Eukey"
      ["incidentType"]=>
      string(14) "PERMITTED BURN"
      ["latitude"]=>
      float(-28.768944)
      ["longitude"]=>
      float(152.006543)
      ["description"]=>
      string(87) "A fire or other emergency has started in the area however there is no immediate threat."
      */
    //var_dump($this->document);
    $maxUpdateTimestamp = 0;

    foreach ($this->document as $key=>$value) {
      foreach ($this->document->$key as $item) {
        $timestamp = $this->jsonQLDAlertTimeStringToUnixtimestamp($item->lastUpdate);
        if ($timestamp > $maxUpdateTimestamp) {
          $maxUpdateTimestamp = $timestamp;
        }
      }
    }
    return $maxUpdateTimestamp;
  }

  public function parseQldDocument() {
        /*
         * THis function should not be here, should have another class that extends ewnapplication / an abstract class
         *
         * this is a copy of getEventsQLDAlerts in this file. Parsing the XML format
         */

      $tmpEvents = array();
      /*
      object(stdClass)#901 (11) {
        ["id"]=>
        string(13) "QF3-12-118096"
        ["displayType"]=>
        int(3)
        ["alertIcon"]=>
        string(13) "PermittedBurn"
        ["status"]=>
        string(5) "Going"
        ["alarmTime"]=>
        string(12) "19-Nov 20:17"
        ["lastUpdate"]=>
        string(12) "19-Nov 20:19"
        ["location"]=>
        string(18) "Pyramids Rd, Eukey"
        ["incidentType"]=>
        string(14) "PERMITTED BURN"
        ["latitude"]=>
        float(-28.768944)
        ["longitude"]=>
        float(152.006543)
        ["description"]=>
        string(87) "A fire or other emergency has started in the area however there is no immediate threat."
        */
      //var_dump($this->document);



      foreach ($this->document as $key=>$value) {
          foreach ($this->document->$key as $item) {
              print_r($item);die;
              $title = $item->location;
              $link = null;

              if ($item->alertIcon == 'PermittedBurn') {
                  $category = 'Permitted Burns/' . $item->status;
              }
              else {
                  if ($item->alertIcon == 'WatchAndAct') {
                      $alertType = 'Watch and Act';
                  }
                  else {
                      $alertType = $item->alertIcon;
                  }
                  $category = $alertType . '/' . $item->incidentType . '/' . $item->status;
              }

              $guid = $item->id;
              $pubDate = $item->lastUpdate;
              $description = $item->description;
              $lat = $item->latitude;
              $lon = $item->longitude;
              $coordinates = $lon . ' ' . $lat;
              $alarmTime = $item->alarmTime;

              $event = array(
                  'title' => $title,
                  'link' => $link,
                  'category' => $category,
                  'guid' => $guid,
                  'pubDate' => $pubDate,
                  'description' => $description,
                  'coordinates' => $coordinates,
                  'geocoded' => 0,
                  //'pubtimestamp' will be set later
                  //'pubtimestamp' => $pubtimestamp,
                  'alarmTime' => $alarmTime,
                  'type' => $this->type
              );
              /*
              var_dump($event);
              exit();
              */
              //$this->logger->LogDebug("Dumping QLD values: $title $link $category $guid $pudDate $description $coordinates $alarmTime $this->type");
              $tmpEvents[] = $event;
          }
      }
      // Remove items with the same titles and older timestamp.
      $tmpEvents = $this->getLastUniqueEvents($tmpEvents);
      $catsByGuids = $this->getDBCategoriesByGuidsQLD($tmpEvents);


      /* Items must be
      array('title' => $title,
             'link' => $link,
             'category' => $category,
             'guid' => $guid,
             'pubDate' => $pubDate,
             'description' => $description,
             'coordinates' => $coordinates,
             'pubtimestamp' => $pubtimestamp,
             'type' => $this->type);
      */
      foreach ($tmpEvents as $i=>$value) {
          $guid = $tmpEvents[$i]['guid'];

          if (isset($catsByGuids[$guid])) {
              if ($catsByGuids[$guid] != $tmpEvents[$i]['category']) {
                  // pubDate here is lastUpdate
                  $pubtimestamp = $this->jsonQLDAlertTimeStringToUnixtimestamp($tmpEvents[$i]['pubDate']);
              }
              else{
                  $pubtimestamp = $this->jsonQLDAlertTimeStringToUnixtimestamp($tmpEvents[$i]['alarmTime']);
              }
          }
          else {
              $pubtimestamp = $this->jsonQLDAlertTimeStringToUnixtimestamp($tmpEvents[$i]['alarmTime']);
          }
          $tmpEvents[$i]['pubtimestamp'] = $pubtimestamp;
          unset($tmpEvents[$i]['alarmTime']);
      }
      return $tmpEvents;

    }

  public function getPubDate() {
    if ($this->state == 'WA') {
      // WA feed has no pubDate tag, but has lastBuildDate tag.
      $feedPubDate = @$this->document->xpath("/rss/channel/lastBuildDate");
      list( , $feedPubDate) = each($feedPubDate);
    }
    else if($this->state == 'QLD' && $this->type == 'alert') {
        $feedPubDate = $this->getPubDateQLDAlert();
    }
    else {
      $feedPubDate = @$this->document->xpath("/rss/channel/pubDate");
      list( , $feedPubDate) = each($feedPubDate);
    }
    $this->logger->LogDebug("Feed pubDate in document $feedPubDate.");
    // If no pubDate in feed. We assume that feed is current and
    // set it unixtimestamp just to have value which has meaning.
    if ($feedPubDate == null) {
      $feedPubDate = time();
      $this->logger->LogDebug("Feed pubDate set as $feedPubDate.");
    }
    return $feedPubDate;
  }

  public function getEventsQLDAlerts() {
    $tmpEvents = array();
    /*
    object(stdClass)#901 (11) {
      ["id"]=>
      string(13) "QF3-12-118096"
      ["displayType"]=>
      int(3)
      ["alertIcon"]=>
      string(13) "PermittedBurn"
      ["status"]=>
      string(5) "Going"
      ["alarmTime"]=>
      string(12) "19-Nov 20:17"
      ["lastUpdate"]=>
      string(12) "19-Nov 20:19"
      ["location"]=>
      string(18) "Pyramids Rd, Eukey"
      ["incidentType"]=>
      string(14) "PERMITTED BURN"
      ["latitude"]=>
      float(-28.768944)
      ["longitude"]=>
      float(152.006543)
      ["description"]=>
      string(87) "A fire or other emergency has started in the area however there is no immediate threat."
      */
    //var_dump($this->document);

    foreach ($this->document as $key=>$value) {
      foreach ($this->document->$key as $item) {
        $title = $item->location;
        $link = null;

        if ($item->alertIcon == 'PermittedBurn') {
          $category = 'Permitted Burns/' . $item->status;
        }
        else {
          if ($item->alertIcon == 'WatchAndAct') {
              $alertType = 'Watch and Act';
          }
          else {
              $alertType = $item->alertIcon;
          }
          $category = $alertType . '/' . $item->incidentType . '/' . $item->status;
        }

        $guid = $item->id;
        $pubDate = $item->lastUpdate;
        $description = $item->description;
        $lat = $item->latitude;
        $lon = $item->longitude;
        $coordinates = $lon . ' ' . $lat;
        $alarmTime = $item->alarmTime;

        $event = array(
          'title' => $title,
          'link' => $link,
          'category' => $category,
          'guid' => $guid,
          'pubDate' => $pubDate,
          'description' => $description,
          'coordinates' => $coordinates,
          'geocoded' => 0,
          //'pubtimestamp' will be set later
          //'pubtimestamp' => $pubtimestamp,
          'alarmTime' => $alarmTime,
          'type' => $this->type
        );
        /*
        var_dump($event);
        exit();
        */
        //$this->logger->LogDebug("Dumping QLD values: $title $link $category $guid $pudDate $description $coordinates $alarmTime $this->type");
        $tmpEvents[] = $event;
      }
    }
    // Remove items with the same titles and older timestamp.
    $tmpEvents = $this->getLastUniqueEvents($tmpEvents);
    $catsByGuids = $this->getDBCategoriesByGuidsQLD($tmpEvents);
    /* Items must be
    array('title' => $title,
           'link' => $link,
           'category' => $category,
           'guid' => $guid,
           'pubDate' => $pubDate,
           'description' => $description,
           'coordinates' => $coordinates,
           'pubtimestamp' => $pubtimestamp,
           'type' => $this->type);
    */
    foreach ($tmpEvents as $i=>$value) {
      $guid = $tmpEvents[$i]['guid'];

      if (isset($catsByGuids[$guid])) {
        if ($catsByGuids[$guid] != $tmpEvents[$i]['category']) {
          // pubDate here is lastUpdate
          $pubtimestamp = $this->jsonQLDAlertTimeStringToUnixtimestamp($tmpEvents[$i]['pubDate']);
        }
        else{
          $pubtimestamp = $this->jsonQLDAlertTimeStringToUnixtimestamp($tmpEvents[$i]['alarmTime']);
        }
      }
      else {
        $pubtimestamp = $this->jsonQLDAlertTimeStringToUnixtimestamp($tmpEvents[$i]['alarmTime']);
      }
      $tmpEvents[$i]['pubtimestamp'] = $pubtimestamp;
      unset($tmpEvents[$i]['alarmTime']);
    }
    return $tmpEvents;
  }

  private function getDBCategoriesByGuidsQLD($events) {
    $sqlTpl = "SELECT `guid`, `category`
              FROM `%s`
              WHERE state = 'QLD'
              AND (%s)";
    $conditions = array();

    foreach ($events as $event) {
      $conditions[] = sprintf("`guid`='%s'", $event['guid']);
    }
    $sql = sprintf($sqlTpl, $this->config['db']['incidents_table'], implode(' OR ', $conditions));
    $guidsCategories = $this->db->getRows($sql);
    $catsByGuids = array();

    foreach($guidsCategories as $guidCategory) {
      $catsByGuids[$guidCategory['guid']] = $guidCategory['category'];
    }
    return $catsByGuids;
  }

  /**
  * Removes from events array items that give info about the same event.
  *
  * Checks if array of events contains items with the same titles and
  * removes items with the same titles but older timestamp.
  *
  * @param $events - array of events.
  * @return array of events.
  */
  public function getLastUniqueEvents($events) {
    $eventsTmp = array();
    foreach ($events as $event) {
      if (array_key_exists($event['title'], $eventsTmp)) {
        if($eventsTmp[$event['title']]['pubDate'] < $event['pubDate']) {
          $eventsTmp[$event['title']] = $event;
        }
      }
      else {
        $eventsTmp[$event['title']] = $event;
      }
    }
    return $eventsTmp;
  }

  public function getEventsSAAlerts() {
    //http://172.31.3.244/exo-test/testSAALertLevel.xml
    //http://www.cfs.sa.gov.au/custom/criimson/CFS_Fire_Warnings.xml
    $dataWarning = $this->curlService->executeCurl('http://www.cfs.sa.gov.au/custom/criimson/CFS_Fire_Warnings.xml', 'SA2');
    $dataWarning = str_replace('&middot;', '', $dataWarning);
    //$dataWarning = preg_replace('/(<\?xml[^?]+?)utf-16/i', '$1utf-8', $dataWarning);
    $documentWarning = simplexml_load_string($dataWarning);
    $childrenXmlWarning = $documentWarning->channel->item;
    $tmpEvents = array();
    $childrenXml = $this->document->Document->Folder->Placemark;

    $curlArray = array();
    foreach ($childrenXmlWarning as $child) {
      $link = $child->link;
      $guid = $child->identifier;
      $curledWarning = $this->curlService->executeCurl($link, 'SA3');
      if (preg_match('/<center>(.*?)<.center>/i', $curledWarning, $returnLink)) {
        $curlArray[] = $returnLink[1];
      }
    }
    $this->logger->LogDebug("Curl array value: " . print_r($curlArray, true));

    foreach ($childrenXml as $child) {
      $name = $child->name;
      $coords = $child->Point->coordinates;
      $description = $child->description;
      $description = preg_replace('/(<br>|&lt;br&gt;)/i', ' ', $description);
      preg_match('/First Reported:\s(.*)Status:/i', $description, $firstReported);
      preg_match('/Status:\s(.*)Region:/i', $description, $status);
      preg_match('/Region:\s(.*)/i', $description, $region);
      preg_match('/(.*)\sFirst\sReported:/i', $description, $category);
      preg_match('/(.*),/i', $coords, $coords);

      $link = null;
      $pubtimestamp = $this->getSATimestamp($firstReported[1]);
      $title = $name;
      $category = $category[1];
      $guid = uniqid("SA");
      $pubDate = $firstReported[1];
      $description = $description;
      $type = $this->type;
      $coords = $coords[1];
      $coords = preg_replace("/(,)/", " ", $coords);
      //$this->logger->LogDebug("SA coords: $coords");
      $event = array(
        'title' => $title,
        'link' => $link,
        'category' => $category,
        'guid' => $guid,
        'pubDate' => $pubDate,
        'description' => $description,
        'coordinates' => $coords,
        'geocoded' => 0, 
        'pubtimestamp' => $pubtimestamp,
        'type' => $type,
        'geometries' => 0
      );
      $event = $this->getValidSAWarnings($event, $childrenXmlWarning, $curlArray);
      $tmpEvents[] = $event;
      $this->logger->LogDebug("SA Alert category: " . $event['category']);
    }
    return $tmpEvents;
  }

  //example input Sunday, 15 Nov 2015 12:05:00
  private function getSATimestamp($timestampIn) {
    //$this->logger->LogDebug("SA method timestamp: $timestampIn");
    date_default_timezone_set('Australia/South');
    return strtotime($timestampIn);
  }

  private function getValidSAWarnings($events, $childrenXmlWarning, $curlArray) {
    $count = 0;
    foreach ($childrenXmlWarning as $child) {
      $description = $child->description;
      $isFirstReported = preg_match('/First Reported:\s(.*)Status:/i', $description, $firstReported);
      $title = $child->title;
      $title = preg_replace('(\s\(.*\))', '', $title);
      $guid = $child->identifier;
      //$this->logger->LogDebug("SA timestamp before: " . $timestamp);
      if ($isFirstReported) {
        $timestamp = preg_replace('/(<br>)/i', "", $firstReported[1]);
        $timestamp = $this->getSATimestamp($timestamp);
        //$this->logger->LogDebug("SA timestamp after: " . $timestamp);
        //$this->logger->LogDebug("Found SA alert values, war-timestamp: $timestamp war-title: $title " . "event timestamp: " . $events['pubtimestamp'] . " event title: " . $events['title']);
        if ($events['pubtimestamp'] == $timestamp && $events['title'] == $title) {
          $this->logger->LogDebug("Found SA alert Match, timestamp: $timestamp title: $title");
          $events['guid'] = "SA" . $guid;
          $this->logger->LogDebug("SA returnLink:" . $curlArray[$count]);
          if (preg_match('/(advice)/i', $curlArray[$count])) {
            $events['category'] = 'advice';
          }
          else if (preg_match('/(watch)/i', $curlArray[$count])) {
            $events['category'] = 'watch and act';
          }
          else if(preg_match('/(emergency|warning)/i', $curlArray[$count])) {
            $events['category'] = 'emergency warning';
          }
        }
      }
      $count++;
    }
    $this->logger->LogDebug("SA array dump: " . print_r($events, true));
    return $events;
  }

  public function getEvents() {
    if ($this->state == 'QLD' && $this->type == 'alert') {
        //parse the document which is stored in $this->document and return back a formatted array of events
        return $this->parseQldDocument();
    }
    else if ($this->state == 'SA' && $this->type == 'alert') {
      return $this->getEventsSAAlerts();
    }

      $items = @$this->document->xpath("//item");
      $itemsCount = count($items);
      $events = array();

    foreach ($items as $item) {
      $geometries = null;
      //description
      $description = @$item->xpath("./description");
      list( , $description) = each($description);

      if ($description == 'No Current Fire Warnings') {
        $log->LogDebug($this->state . ': No Current Fire Warnings');
        break;
      }

      $description = trim($description);
      //title
      $title = @$item->xpath("./title");
      list( , $title) = each($title);
      $title = trim($title);
      //link
      $link = @$item->xpath("./link");
      list( , $link) = each($link);
      //category
      $category = @$item->xpath("./category");
      list( , $category) = each($category);
      //guid
      $guid = @$item->xpath("./guid");
      list( , $guid) = each($guid);
      //pubDate
      $pubDate = @$item->xpath("./pubDate");
      list( , $pubDate) = each($pubDate);

      if ($pubDate == null) {
        $pubtimestamp = time();
      }
      else if($this->state == 'QLD' || $this->state == 'NT') {
        // Well formed RSS format.
        // Thu, 02 Aug 2012 14:40:20 +10
        $pubtimestamp = strtotime($pubDate);
      }
      else if ($this->state == 'TAS') {
        date_default_timezone_set('Australia/Tasmania');
        $pubtimestamp = strtotime($pubDate);
      }
      else if($this->state == 'SA') {
        date_default_timezone_set('Australia/South');
        $pubtimestamp = strtotime($pubDate);
      }
      else if($this->state == 'VIC') {
        // Sometimes there is local time though it has GMT identifier.
        // If time is bigger than current time we consider that it
        // should be local and add offset.
        $pubtimestamp = strtotime($pubDate);
        if ($pubtimestamp > time()) {
          $offset = $this->getTimezoneOffset('UTC', 'Australia/Victoria');
          $pubtimestamp -= $offset;
        }
      }
      else {
        //If they don't change formats we wouldn't come here.
        //if pubDate doesn't end with time zone identifier we add it
        if (!substr_compare($pubDate, $this->config['timezoneIdentifier'], - strlen($this->config['timezoneIdentifier']), strlen($this->config['timezoneIdentifier'])) == 0) {
          $this->logger->LogDebug('No `GMT` in time format for ' . $this->state);
          $pubDate = $pubDate . ' GMT';
        }
        $pubtimestamp = strtotime($pubDate);
      }

      $coordinates = null;
      $lng = null;
      $lat = null;
      $geocoded = 0;

      //coordinates
      if ($this->state == 'WA') {
        $lng = @$item->xpath(".//geo:long");
        $lat = @$item->xpath(".//geo:lat");
      }
      else if($this->state == 'NSW' || $this->state == 'TAS' || $this->state == 'QLD') {
        $coordinates = @$item->xpath(".//georss:point");
      }
      else if($this->state == 'VIC') {
        $pointCollection = @$item->xpath(".//georss:collection");
          /*
          if($pointCollection){
              list( , $pointCollectionItem) = each($pointCollection);
              $points = $pointCollectionItem->xpath(".//georss:point");

              $pointStrs = array();

              foreach($points as $point){
                  list(, $pointStr) = each($point);
                  $pointStrs[] = $pointStr;
              }

              $coordinates = implode(',', $pointStrs);

          }else{
              $this->logger->LogError(
              sprintf("Points collection not found for VIC, %s, %s.",
                      $this->type, $guid));
          }
          */
        list( , $pointCollectionItem) = each($pointCollection);
        $coordinates = $pointCollectionItem->xpath(".//georss:point");

        if ($this->type == 'alert') {
          $geometries = array();
          foreach ($coordinates as $point) {
            $values = explode(' ', $point);
            $geom_coordinates = array((float)$values[1], (float)$values[0]);
            $geometries[] = array('type' => 'Point', 'coordinates' => $geom_coordinates);
          }
          reset($coordinates);
        }
      }

      if ($coordinates) {
        list( , $coordinates) = each($coordinates);
        // Some TAS feeds have incorrect coordinates value `-90.0 147.0`.
        if ($coordinates == '-90.0 147.0') {
          $coordinates = null;
        }
        else {
          $values = explode(' ', $coordinates);
          $coordinates = $values[1] . ' ' . $values[0];
        }
      // WA
      }
      else if ($lng and $lat) {
        list( , $lng) = each($lng);
        list( , $lat) = each($lat);
        $coordinates = $lng . ' ' . $lat;
      }

      if (!$coordinates && $this->state != 'WA') {
        $this->logger->LogDebug("Getting coordinates by title '$title', state '$this->state'");
        try {
          //$this->config['maps_host'], $this->config['maps_key'],
          $geocoder = new Geocoder($this->config['geocoding_api_uri_tmpl'], $this->logger, $this->db, $this->config['db']['geocoder_cache_table']);
          $coordinates = $geocoder->getCoordinatesByTitle($title, $this->state);
          if ($coordinates) {
            $geocoded = 1;
          }
        }
        catch(GeocodingFailedFeedException $e) {
          $this->logger->LogDebug($e->getMessage());
        }
      }

      if ($this->state == 'NSW' && $this->type == 'alert') {
        $polygons = @$item->xpath(".//georss:polygon");
        if ($polygons) {
          $geometries = array();
          $multiPolygon = array('type' => 'MultiPolygon', 'coordinates' => array());
          foreach ($polygons as $polygon) {
            // sample:
            //<georss:polygon> -32.5527 152.2863 -32.5538 152.286 -32.5539 ... </georss:polygon>
            // or
            // <georss:polygon>-32.6226 149.5496 -32.6226 149.5496</georss:polygon>
            $values = explode(' ', $polygon);
            $poly_coordinates = array();

            for ($i=0; $i<count($values); $i+=2) {
              $poly_coordinates[] = array((float)$values[$i+1], (float)$values[$i]);
            }

            if (count($poly_coordinates) == 2 && $poly_coordinates[0][0] == $poly_coordinates[1][0] && $poly_coordinates[0][1] == $poly_coordinates[1][1]) {
              // Some polygons in this feed consist of 2 similar points.
              // We save them as a single point.
              $geometries[] = array('type' => 'Point', 'coordinates' => $poly_coordinates[0]);
            }
            else {
              $multiPolygon['coordinates'][] = $poly_coordinates;
            }
          } 
          if(count($multiPolygon['coordinates']) > 0) {
            $geometries[] = $multiPolygon;
          }
        }
      }
      # Titles repeat, but " Update 1", " - filnal", " Final", " -Upda",
      # " - Update N", ", Final" ... can added to them
      # we need to cut such endings off to be able to update data in database
      # to be updated
      if ($this->state == 'NT') {
        $title = preg_replace($this->config['ntBushfireTitleEndingsToDelete'], '', $title);
      }
      /* OLD CODE. This is done in different file now.
      // Set category of QLD.
      if($this->state == 'QLD'){
          $descriptionParts = explode('.', $description, 3);
          if(count($descriptionParts) < 3){
              $errorMsg = sprintf(
              'Could not detect location and alert level from QLD event %s description `%s`',
              $guid, $description);
              $this->logger->LogError($errorMsg);
          }else{
              //"Alert Level: Permitted Burn"
              $levelStringParts = explode (':', $descriptionParts[0]);

              if($category == '/Permitted Burns' && trim($levelStringParts[1]) == 'Permitted Burn'){
                  $category = 'Permitted Burns';
              }else{
                  $category = trim($levelStringParts[1]) . $category;
              }

              // Location: Nine Mile Creek Rd, Nine Mile Creek
              $locationStringParts = explode (':', $descriptionParts[1]);
              $title = trim($locationStringParts[1]);

              $description = trim($descriptionParts[2]);
          }
      }
      */
      // Set category and title of WA alerts an remove Bushfire ...
      // from title.
      if ($this->state == 'WA' && $this->type == 'alert') {
        // Remove special characters from a string
        $title = preg_replace('/[^(\x20-\x7F)]*/','', $title);

        foreach ($this->config['waBushfireCategories'] as $key => $value) {
          $searchString = 'Bushfire ' . $key . ' for ';
          if (substr($title, 0, strlen($searchString)) === $searchString) {
            $category = $value;
            $logStr = sprintf("Detected category for WA bushfire alerts " . "`%s`: '%s'.", $guid, $category);
            $this->logger->LogDebug($logStr);
            // remove 'Bushfire ' . $key . ' for ' from title.
            $title = ucfirst(str_replace($searchString, '', $title));
            break;
          }
        }
        if (!$category) {
          $this->logger->LogDebug("Category for WA bushfire alerts $guid was not detected.");
        }
      }
      // Set category and title of VIC alerts.
      if ($this->state == 'VIC' && $this->type == 'alert') {
        switch ($title) {
          case 'ADVICE':
            $category = 'Advice';
            break;
          case 'WATCH AND ACT':
            $category = 'Watch And Act';
            break;
          case 'EMERGENCY WARNING':
            $category = 'Emergency Warning';
            break;
        }
        // Get location from description.
        $regExps = $this->config['vic_location_from_description_regexps'];
        $titleFound = false;

        foreach ($regExps as $regExp) {
          $matchRes = preg_match_all($regExp, $description, $locationFromDescriptionResults);
          if ($matchRes) {
            $matchText = trim(strip_tags($locationFromDescriptionResults[1][0]));
            // Sometimes there is no text
            if ($matchText != '') {
              $title = $matchText;
              $titleFound = true;
              break;
            }
          }
        }
        if ($titleFound === false) {
          $this->logger->LogError("Could not get title from description for VIC alert, guid=$guid.");
        }
      }

      if ($geometries) {
        $geometries = json_encode($geometries);
        $geomLength = strlen($geometries);
        $logMsg = sprintf('Length of %s, %s geometries is %s (Max is 10000).', $title, $this->state, $geomLength);
        if ($geomLength <= 10000) {
          $this->logger->LogDebug($logMsg);
        }
        else {
          $this->logger->LogError($logMsg . ' Will set NULL.');
          $geometries = null;
        }
      }

      $event = array(
        'title' => $title,
        'link' => $link,
        'category' => $category,
        'guid' => $guid,
        'pubDate' => $pubDate,
        'description' => $description,
        'coordinates' => $coordinates,
        'geocoded' => $geocoded,
        'pubtimestamp' => $pubtimestamp,
        'type' => $this->type,
        'geometries' => $geometries
      );
      $events[] = $event;

      $this->logger->LogDebug("event array dump for ". $this->state);
      $this->logger->LogDebug(var_dump($event));
    }

      if($this->state == 'QLD') {
          $this->logger->LogDebug('Events for : '.$this->state. json_encode($events));
      }
    /*
     * NT titles repeat in one feed.
     * We sort out titles with the latest pubDates.
     */
    if ($this->state == 'NT') {
      $events = $this->getLastUniqueEvents($events);
    }
    return $events;
  }
}