<?php

define('QLD_LIMIT_TIME_REGEXP', '/Last edited: (.+)\r/Usi');
define('WA_ENTERED_TIME_REGEXP', '#<b>Entered: (.+)</b>#Usi');
define('ACT_UPDATED_TIME_REGEXP', '#Updated: (.+)<br />#Usi');

class Traffic extends EventFeedAbs {

    public function getUrl() {
        if ($this->state == 'WA') {
            // Get page with feeds' URLS.
            $waLinksPage = $this->curlService->executeCurl($this->config['waTrafficLinksPageUrl'], $this->state);
            // Find current alert feed URL.
            preg_match_all($this->config['waTrafficUrlListPattern'], $waLinksPage, $waUrlListResults);
            // URL params.
            $waUrlList = implode("", $waUrlListResults[0]);
            // Full URL.
            $url = $this->config['waTrafficAlertUrl'] . $waUrlList;
        }
        else {
            $url = @$this->config['trafficUrls'][$this->state][$this->type];
        }

        if (is_null($url)) {
            $errorMessage = sprintf('Not proper data input. Event: traffic, state: `%s`, type: `%s`',
            $this->state, $this->type);
            $this->logger->LogError($errorMessage);
            throw new InputFeedException($errorMessage);
        }

        return $url;
    }

    public function loadFeed() {
        $url = $this->getUrl();
        $this->logger->LogDebug("Loading $url.");

        if ($this->state == 'WA') {
            $data = $this->curlService->executeCurl($url, 'WAT');
            $this->document = simplexml_load_string($data);
        }
        else if ($this->state == 'VIC') {
            $data = $this->curlService->executeCurl($url, 'VICT');
            $this->document = json_decode(utf8_encode($data), true);
        }
        else if ($this->state == 'NT' || $this->state == 'SA') {
            $html = new DOMDocument();
            libxml_use_internal_errors(true);
            if (!$html->loadHTMLFile($url)) {
                $errors = "";
                foreach (libxml_get_error() as $error) {
                    $errors .= $error->message . "<br/>";
                }
                libxml_clear_errors();
                $this->logger->LogDebug("libxml errors:<br/>". $errors . "<br/>End of errors.");
            }
            $loadResult = $html->loadHtmlFile($url);
            if ($loadResult) {
                $this->document = $html;
                $this->docXpath = new DOMXPath($this->document);
            }
            else {
                throw new NotLoadingFeedException(
                $this->state ." traffic URL was not loaded $url.");
            }
        }
        else if ($this->state == 'NSW' || $this->state == 'QLD'  || $this->state == 'ACT' || $this->state == 'TST') {
            $xmlObj = new AXP($this->logger);
            $xmlObj->setEncoding('UTF-8');
            $xmlObj->setXmlData($url,_AXP_URL);
            $xmlObj->parse();
            $this->document = $xmlObj->getArray();
        }
        else {
            $errorMessage = sprintf('Not proper data input. Event: traffic, state: `%s`, type: `%s`', $this->state, $this->type); 
            $this->logger->LogError($errorMessage);
            throw new InputFeedException($errorMessage);
        }
    }

    public function getPubDate(){

        $feedPubDate = null;

        if($this->state == 'ACT'){
            $feedPubDate = $this->document[1]['channel'][0]['lastBuildDate']['value'];
        }

        if($this->state == 'NSW'){
            $feedPubDate = $this->document[1]['updated']['value'];
        }

        if($this->state == 'QLD'){
            $feedPubDate = $this->getMaxUnixtimestampQLD();
        }

        if($this->state == 'VIC'){
            $feedPubDate = md5(json_encode($this->document));
        }

        if($this->state == 'SA'){
            //$feedPubDate = $this->getMaxUnixtimestampSA();
            /*
             * Not possible to detect last update time from the document,
             * because there are dates of events and dates of planed events.
             * Get md5 of the document istead of data.
             * This approach may be good for all feeds.
             */
            $feedPubDate = md5($this->document->saveXML());
        }

        if($this->state == 'NT'){
            $nodeList = $this->docXpath->query('//*[@id="date"]');
            $feedPubDate = trim(str_replace(
                                         'This report is current as at: ', '',
            $nodeList->item(0)->nodeValue));
        }

        if($this->state == 'WA'){
            // WA feed has no pubDate tag, but has lastBuildDate tag.
            $feedPubDate = $this->document->xpath("/rss/channel/lastBuildDate");
            list( , $feedPubDate) = each($feedPubDate);
        }

        $this->logger->LogDebug(
               "$this->state, traffic feed pubDate in document $feedPubDate.");

        // If no pubDate in feed (Like QLD). We assume that feed is current and
        // set it unixtimestamp just to have value which has meaning.
        if($feedPubDate == null || $feedPubDate == false){
            $feedPubDate = time();
            $this->logger->LogDebug("Feed pubDate set as $feedPubDate.");
        }

        return $feedPubDate;
    }

    public function getEvents(){
        if($this->state == 'NSW'){
            return $this->getNSWEvents();
        }else if($this->state == 'QLD'){
            return $this->getQLDEvents();
        }else if($this->state == 'VIC'){
            return $this->getVICEvents();
        }else if($this->state == 'NT'){
            return $this->getNTEvents();
        }else if($this->state == 'WA'){
            return $this->getWAEvents();
        }else if($this->state == 'SA'){
            return $this->getSAEvents();
        }else if($this->state == 'ACT'){
            return $this->getACTEvents();
        }
    }

    public function getInnerHTML($node){
        $innerHTML= '';
        $children = $node->childNodes;
        foreach ($children as $child) {
            $innerHTML .= $child->ownerDocument->saveXML( $child );
        }
        return $innerHTML;
    }

    public function getWAEvents(){

        $items = $this->document->xpath("//item");

        $itemsCount = count($items);

        $this->logger->LogDebug(sprintf("%s events in %s %s feed.",
                                        $itemsCount,
                                        $this->state,
                                        $this->type));
        $events = array();

        foreach($items as $item){
            //description
            $description = @$item->xpath("./description");
            list( , $description) = each($description);

            $description = trim($description);
            //title
            $title = @$item->xpath("./title");

            list( , $title) = each($title);
            //link
            $link = @$item->xpath("./link");
            list( , $link) = each($link);

            //guid
            $guid = @$item->xpath("./guid");
            list( , $guid) = each($guid);
            //pubDate
            $pubDate = @$item->xpath("./pubDate");
            list( , $pubDate) = each($pubDate);

            $pubtimestamp = strtotime($pubDate);

            $coordinates = null;

            $category = null;

            $event = array('title' => $title,
                            'link' => $link,
                            'category' => $category,
                            'guid' => $guid,
                            'pubDate' => $pubDate,
                            'description' => $description,
                            'coordinates' => $coordinates,
                            'geocoded' => 0,
                            'pubtimestamp' => $pubtimestamp,
                            'type' => 'alert');

            $events[] = $event;
        }

        return $events;
    }

/*
    public function getMaxUnixtimestampSA(){
        $maxUnixtimestamp = -1;

        $nodelist = $this->docXpath->query( '//table[@id="criimson"]/tbody/tr' );

        if($nodelist->length == 0){
            $this->logger->LogDebug('No entries if feed SA bushfires/traffic. No update time.');
            return $maxUnixtimestamp;
        }

        foreach ($nodelist as $nodeItem){
            $cells = $nodeItem->getElementsByTagName('td');
            // Table header.
            if($cells->length == 0){
                continue;
            }
            // "26/11/2012"
            $date = $cells->item(0)->nodeValue;
            // "00:57"
            $time = $cells->item(1)->nodeValue;
            $pubtimestamp = $this->getUnixtimestampSA($date, $time);
            if($pubtimestamp > $maxUnixtimestamp){
                $maxUnixtimestamp = $pubtimestamp;
            }
        }
        return $maxUnixtimestamp;
    }
*/
    public function getUnixtimestampSA($dateString, $timeString){
        // Input example: "26/11/2012" "00:57"

        // Going to convert into Common Log Format
        // dd "/" M "/" YY : HH ":" II ":" SS space tzcorrection
        // "10/Oct/2000:13:55:36 -0700"
        $offset = $this->getTimezoneOffset('UTC', 'Australia/South');
        // Replace month number with short month name.
        list($day, $month, $year) = explode('/', $dateString);

        $month = $this->shortMonths[(int)$month];
        $dateString = implode('/', array($day, $month, $year));

        // %02d30 shows only int part of time offset and 30 minutes.
        $pubDate = sprintf('%s:%s:00 +%02d30',
                           $dateString, $timeString, $offset/3600);
        return strtotime($pubDate);
    }

    public function getACTEvents(){
        $events = array();
        $items = $this->document[1]['channel'][0]['item'];

        if(count($items) == 0){
            $this->logger->LogDebug('No entries for feed ACT bushfires/traffic.');
            return $events;
        }

        foreach($items as $item){
            $title = $item['title']['value'];
            $guid = $item['guid']['value'];
            $description = $item['description']['value'];
            $link = $item['link']['value'];
            $coordinates = implode(' ',
                                   array_reverse(explode(' ',
                                             $item['georss:point']['value'])));
            preg_match(ACT_UPDATED_TIME_REGEXP,
                       $description,
                       $regs);
            $dateTime = trim($regs[1]);
            /* 'Updated: 17 Dec 2012 07:25:44<br />'
                We will convert it into Common Log Format
                dd "/" M "/" YY : HH ":" II ":"
                SS space tzcorrection

                "10/Oct/2000:13:55:36 -0700"
            */

            $remote_tz = 'Australia/ACT';
            $remote_dtz = new DateTimeZone($remote_tz);
            $remote_dt = new DateTime("now", $remote_dtz);
            $offset = $remote_dtz->getOffset($remote_dt);

            list($day, $month, $year, $time) = explode(' ', $dateTime);

            $dateTime = sprintf('%02d/%s/%s:%s +%02d00',
                               $day, $month, $year, $time, $offset/3600);

            $pubtimestamp = strtotime($dateTime);

            if(stristr($title, 'MOTOR') || stristr($title, 'VEHICLE') || stristr($title, 'CAR')){
                $event_type = 'traffic';
            }else if(stristr($title, 'GRASS AND BUSH FIRE') || stristr($title, 'GRASS FIRE') || stristr($title, 'BUSH FIRE')){
                $event_type = 'bushfire';
            }else{
                continue;
            }

            $category = null;
            $type = 'incident';

            $event = array('title' => $title,
                               'link' => $link,
                               'category' => $category,
                               'guid' => $guid,
                                //'pubDate' => $pubDate,
                               'description' => $description,
                               'coordinates' => $coordinates,
                               'geocoded' => 0,
                               'pubtimestamp' => $pubtimestamp,
                               'type' => $type,
                               'event_type' => $event_type);
            $events[] = $event;
        }
        return $events;
    }

    protected function getUnixtimestampSAPrescribed($timeString){
        //May 10 2013 06:00 PM
        list($month, $day, $year, $time, $meridian) = explode(' ', $timeString);
        list($hour, $minute) = explode(':', $time);

        $hour = intval($hour);
        if($meridian == 'PM' && $hour != 12){
            $hour = $hour + 12;
        }

        $timezoneOffset = $this->getTimezoneOffset('UTC', 'Australia/South');

         /*
         * We will use "Common Log Format"
         * dd "/" M "/" YY : HH ":" II ":" SS space tzcorrection
         * "10/Oct/2000:13:55:36 -0700"
         */
        $offsetHours = (int)($timezoneOffset / 3600);

        $offsetMinutes = $timezoneOffset % 3600 / 60;

        $pubDate = sprintf('%02d/%s/%s:%02d:%02d:00 +%02d%02d',
                           $day, $month, $year, $hour, $minute, $offsetHours, $offsetMinutes);
        return strtotime($pubDate);
    }

    protected function getSAEvents(){
        $events = array();

        /*
         * Get data from "Prescribed Burns" table.
         */
        $nodelist = $this->docXpath->query('//table[@id="TableList"]/tbody/tr');

        if($nodelist->length > 0){
            $len = $nodelist->length -1;
        }else{
            $len = 0;
        }

        $this->logger->LogDebug(sprintf("%s events in %s, %s %s bushfires/traffic \"Prescribed Burns\".",
                                        $len,
                                        $this->state,
                                        $this->location,
                                        $this->type));

        foreach ($nodelist as $nodeItem){
            $link = null;

            $cells = $nodeItem->getElementsByTagName('td');

            // Table header.
            if($cells->length == 0){
                continue;
            }

            $startTimestamp = $this->getUnixtimestampSAPrescribed($cells->item(0)->nodeValue);
            $endTimestamp = $this->getUnixtimestampSAPrescribed($cells->item(1)->nodeValue);

            $ignoreBeforeTimeStamp = time() - $this->config['clear_before_hours'] * 60 * 60;

            /*
             * Start of "Prescribed Burns" event may be older that clear_before_hours,
             * though end may be in future.
             * To avoid deleting of such events from database, we use current timestamp.
             *
             * If event finished we use $endTimestamp to keep event in database for a while.
             */
            if($startTimestamp < $ignoreBeforeTimeStamp && $endTimestamp > time()){
                $pubtimestamp = time();
            }else if($endTimestamp < time()){
                $pubtimestamp = $endTimestamp;
            }else{
                $pubtimestamp = $startTimestamp;
            }

            $no = trim(str_replace(array("\xC2", "\xA0"), " ", $cells->item(2)->nodeValue));
            $street = trim(str_replace(array("\xC2", "\xA0"), " ", $cells->item(3)->nodeValue));
            $locality = trim(str_replace(array("\xC2", "\xA0"), " ", $cells->item(4)->nodeValue));
            $burnType = trim(str_replace(array("\xC2", "\xA0"), " ", $cells->item(5)->nodeValue));

            $address = '';

            if($no != ''){
                $address = "$no, ";
            }

            if($street != ''){
                $address .= "$street, ";
            }

            $address .= $locality;

            $coordinates = $this->geocode($address);

            if($coordinates){
                $geocoded = 1;
            }else{
                $geocoded = 0;
            }

            // Generate guid.
            $guid = uniqid ('', true);

            $event_type = 'bushfire';

            $category = "Permitted Burns";
            $type = 'alert';

            $title = sprintf('%s (%s)', $address, "Prescribed Burns");

            $description = sprintf('%s (%s)', "Prescribed Burns", $burnType);

            $event = array('title' => $title,
                               'link' => $link,
                               'category' => $category,
                               'guid' => $guid,
                                //'pubDate' => $pubDate,
                               'description' => $description,
                               'coordinates' => $coordinates,
                               'geocoded' => $geocoded,
                               'pubtimestamp' => $pubtimestamp,
                               'type' => $type,
                               'event_type' => $event_type);
            $events[] = $event;
        }

        /*
         * Get data from "Current Incidents" table.
         */

        $nodelist = $this->docXpath->query('//table[@id="criimson"]/tbody/tr');


        $this->logger->LogDebug(sprintf("%s events in %s, %s %s bushfires/traffic feed.",
                                        $nodelist->length,
                                        $this->state,
                                        $this->location,
                                        $this->type));
        foreach ($nodelist as $nodeItem){
            $link = null;

            $cells = $nodeItem->getElementsByTagName('td');

            // Table header.
            if($cells->length == 0){
                continue;
            }

            // "26/11/2012"
            $date = $cells->item(0)->nodeValue;
            // "00:57"
            $time = $cells->item(1)->nodeValue;
            $pubtimestamp = $this->getUnixtimestampSA($date, $time);

            /*
             * If event started before clear_before_hours we update $pubtimestamp with
             * current timestamp to avoid deletion from database.
             */
            $ignoreBeforeTimeStamp = time() - $this->config['clear_before_hours'] * 60 * 60;
            if($pubtimestamp < $ignoreBeforeTimeStamp){
                $pubtimestamp = time();
            }

            /*
             * Converting "COLLABY HILL ROAD <br> WARNERTOWN" to
             * "COLLABY HILL ROAD, WARNERTOWN"
             */

            $children = $cells->item(3)->childNodes;
            $title = '';

            foreach ($children as $child) {
                $childText = $child->ownerDocument->saveXML($child);

                if($childText == '<br />'){
                    $title .= ', ';
                }else{
                    $title .= $childText;
                }
            }

            $type = $cells->item(5)->nodeValue;
            $status = ucwords(strtolower($cells->item(6)->nodeValue));
            $description = sprintf('%s/%s', $type, $status);

            $as = $cells->item(2)->getElementsByTagName('a');
            if($as->length){
                $imgs = $as->item(0)->getElementsByTagName('img');
                $flag = $imgs->item(0)->getAttribute('src');
            }else{
                $flag = null;
            }


            switch ($flag) {
                case '/custom/criimson/flag_blue_active.gif':
                    $category = 'Advice';
                    break;
                case '/custom/criimson/flag_green_active.gif':
                    $category = 'Watch And Act';
                    break;
                case '/custom/criimson/flag_red_active.gif':
                    $category = 'Emergency Warning';
                    break;
                default:
                    $category = 'No Alert Level';
                    break;
            }

            $as = $cells->item(8)->getElementsByTagName('a');
            if($as->item(0)){
                $onClick = $as->item(0)->getAttribute('onclick');
                preg_match("/\((.+)\)/", $onClick, $result);
                $coordinates = $result[1];
                list($lat, $lon) = explode(',', $coordinates);
                $coordinates = sprintf('%s %s', $lon, $lat);
            }else{
                $coordinates = null;
            }

            $guid = uniqid ('', true);

            if(stristr($type, 'vehicle')){
                $event_type = 'traffic';
            }else{
                $event_type = 'bushfire';
            }

            /*
             * We also get events fire from http://www.cfs.sa.gov.au/site/news_media/current_incidents.jsp
             * which may repeat here.
             * Here we format title in exactly the same way as on current_incidents.jsp
             * so that events are overwritten (Not added to database).
             */
            if($event_type == 'bushfire'){
                $title = sprintf('%s (%s)', $title, $type);
            }

            $type = 'incident';

            $event = array('title' => $title,
                               'link' => $link,
                               'category' => $category,
                               'guid' => $guid,
                                //'pubDate' => $pubDate,
                               'description' => $description,
                               'coordinates' => $coordinates,
                               'geocoded' => 0,
                               'pubtimestamp' => $pubtimestamp,
                               'type' => $type,
                               'event_type' => $event_type);
            $events[] = $event;

        }
        return $events;
    }

    protected function getNTEvents(){
        $events = array();

        $nodelist = $this->docXpath->query( "/html/body/div[2]/div/div/div/div[2]/table/tbody/tr" );

        if($nodelist->length == 0){
            $this->logger->LogDebug('No entries if feed NT traffic.');
            return $events;
        }

        $this->logger->LogDebug(sprintf("%s events in %s, %s %s traffic feed.",
                                        $nodelist->length,
                                        $this->state,
                                        $this->location,
                                        $this->type));

        $time = $this->getPubDate();
        // Mon May 21 03:24:31 CST 2012 CST
        list($dayOfWeek, $month, $day, $time,
        $zone, $year, $zone2) = explode(' ', $time);

        // Going to use following time format for conversion into
        //timestamp:
        // Common Log Format
        // dd "/" M "/" YY : HH ":" II ":" SS space tzcorrection
        // "10/Oct/2000:13:55:36 -0700"

        // We expect that time zone in time sting is always CST.
        if(!$zone2 == 'CST'){
            $this->logger->LogError('Unexpected time zone for NT, traffic');
        }
        // We expect that Australia/Darwin is in CST time zone.
        $offset = $this->getTimezoneOffset('UTC', 'Australia/Queensland');
        $pubDate = sprintf('%s/%s/%s:%s +%02d00',
                       $day, substr($month, 0, 3), $year, $time, $offset/3600);
        $pubtimestamp = strtotime($pubDate);
        //print to check
        //echo date('c', $pubtimestamp) . "\n";

        foreach ($nodelist as $nodeItem){
            $trClass = $nodeItem->getAttribute('class');
            // No data in row
            if($trClass == 'group'){
                continue;
            }

            $cells = $nodeItem->getElementsByTagName('td');

            //0 Restriction - 1 Section Affected - 2 Type of Restriction - 3 Details - 4 Comments
            // Lets do:
            // Restriction + Type of Restriction = $title
            // Type of Restriction = $type
            // Details + Comments = $description;

            $as = $cells->item(0)->getElementsByTagName('a');
            $href = $as->item(0)->getAttribute('href');

            preg_match("/'([^']+)'/", $href, $result);
            $pageUrl = $result[1];

            $link = $this->config['ntTrafficEventBaseUrl'] . $pageUrl;

            //list($tmpUrlPart, $guid) = explode('=', $pageUrl);
            $guid = $link;

            $restriction = $cells->item(0)->nodeValue;
            $affected = $cells->item(1)->nodeValue;
            $type = $cells->item(2)->nodeValue;
            $details = $cells->item(3)->nodeValue;
            $comments = trim($cells->item(4)->nodeValue);
            if($comments == '-'){
                $comments = '';
            }

            $title = sprintf('%s, %s.', $restriction, $type);
            $category = 'No Alert Level';
            $description = sprintf('%s. %s', $details, $comments);


            $coordinates = $this->geocode($restriction);

            if($coordinates){
                $geocoded = 1;
            }else{
                $geocoded = 0;
            }

            $event = array('title' => $title,
                               'link' => $link,
                               'category' => $category,
                               'guid' => $guid,
            //'pubDate' => $pubDate,
                               'description' => $description,
                               'coordinates' => $coordinates,
                               'geocoded' => $geocoded,
                               'pubtimestamp' => $pubtimestamp,
                               'type' => $type);
            $events[] = $event;

        }
        return $events;
    }

    protected function getVICEvents(){
        $events = array();
        $usedTitles = array();

        $items = $this->document['incidents'];

        if(count($items) == 0){
            $this->logger->LogDebug('No entries if feed VIC traffic.');
            return $events;
        }

        $this->logger->LogDebug(sprintf("%s events in %s, %s %s traffic feed.",
                                        count($items),
                                        $this->state,
                                        $this->location,
                                        $this->type));

        $ignoreBeforeTimeStamp = time() - $this->config['clear_before_hours'] * 60 * 60;

        foreach ($items as $item){
            $guid = $item['id'];
            $link = $item['description_url'];
            $lon = $item['long'];
            $lat = $item['lat'];

            $alertDescription = $item['description'];
            $incidentTo = $item['to'];
            $incidentFrom = $item['from'];

            $incidentType = $item['incident_type'];
            $closureType = $item['closure_type'];
            $road = $item['closed_road_name'];
            $locale = $item['locale'];

            $title = sprintf('%s, %s - %s',
                             $road, $locale, $closureType);
            if($incidentType != 'Nil'){
                $title .= " - $incidentType";
            }

            if(in_array($title, $usedTitles)){
                for($i=0; $i<count($events); $i++){
                    if($events[$i]['title'] == $title . '.'){
                        $events[$i]['title'] = sprintf('%s (%s).',
                                                       $title,
                                                       $events[$i]['guid']);
                        break;
                    }
                }
                $title .= " ($guid)";
            }

            $usedTitles[] = $title;
            $title .= '.';

            $category = $item['alert_type'];

            if($alertDescription){
                $description = $alertDescription;
                $description .= '<br />' . $incidentFrom;
            }else{
                $description = $incidentFrom;
            }

            if($incidentTo){
                $description .= '<br />' . $incidentTo;
            }

            $coordinates = null;
            // $lon and $lat may be empty strings here
            if($lon && $lat){
                $coordinates = $lon . ' ' . $lat;
            }

            $type = $closureType;
            $pubtimestamp = $locale = $item['updated'];

            if($pubtimestamp < $ignoreBeforeTimeStamp){
                $pubtimestamp = time();
            }

            $event = array('title' => $title,
                               'link' => $link,
                               'category' => $category,
                               'guid' => $guid,
                               //'pubDate' => $pubDate,
                               'description' => $description,
                               'coordinates' => $coordinates,
                               'geocoded' => 0,
                               'pubtimestamp' => $pubtimestamp,
                               'type' => $type);
            $events[] = $event;
        }
        return $events;
    }
    /**
     * Gets biggets unixtimestamp of QLD items
     *
     * @return int or false
     */
    protected function getMaxUnixtimestampQLD(){
        $unixtimestamps = array();
        foreach($this->document[1]['channel'][0]['item'] as $item){
            if($this->type == 'limit'){
                $match = preg_match(QLD_LIMIT_TIME_REGEXP, $item['description']['value'], $regs);
                if ($match) {
                    $unixtimestamps[] = $this->getUnixtimestampQLD($regs[1]);
                }
            }else{
                $unixtimestamps[] = $this->getUnixtimestampQLD(
                $item['title']['attr']['dateFrom'],
                $item['title']['attr']['timeFrom']);
            }
        }
        return max($unixtimestamps);
    }
    /**
     * Converts $dataString and $timeString from QLD item into unixtimestamp
     *
     * @param string $dateString
     * @param string $timeString
     * @return int or false
     */
    protected function getUnixtimestampQLD($dateString, $timeString=null){
        // Going to use following time format for conversion into
        //timestamp:
        // Common Log Format
        // dd "/" M "/" YY : HH ":" II ":" SS space tzcorrection
        // "10/Oct/2000:13:55:36 -0700"

        $offset = $this->getTimezoneOffset('UTC', 'Australia/Queensland');

        if($this->type == 'limit'){
            //02 Jun 2014 15:36
            $elements = explode(' ', $dateString);

            if(count($elements) == 4){
                list($day, $month, $year, $time) = $elements;
            }else{
                // count == 3
                list($day, $month, $year) = $elements;
                $time = '00:00';
            }


            $pubDate = sprintf('%s/%s/%s:%s:00 +%02d00',
                                $day, $month, $year, $time, $offset/3600);
        }else{

            list($dayOfWeek, $monthDay, $year) = explode(',', $dateString);
            list($month, $day) = explode(' ', trim($monthDay));

            $month = substr($month, 0, 3);
            $year = trim($year);

            list($time, $meridian) = explode(' ', $timeString);


            if($meridian == 'PM'){
                list($h, $m, $s) = explode(':', $time);
                $h = intval($h) + 12;
                if($h == 24){
                    $h = 12;
                }
                $time = sprintf('%02d:%s:%s', $h, $m, $s);
            }
            $pubDate = sprintf('%s/%s/%s:%s +%02d00',
            $day, $month, $year, $time, $offset/3600);
        }

        $unixtimestamp = strtotime($pubDate);

        if($unixtimestamp === false){
            $this->logger->LogError(
                            "Could not convert $pubDate into unixtimestamp.");
        }
        return $unixtimestamp;
    }

    protected function getQLDEvents(){

        $events = array();

        if(array_key_exists('item', $this->document[1]['channel'][0])){
            $items =  $this->document[1]['channel'][0]['item'];
        }else{
            $this->logger->LogDebug('No entries if feed.');
            return $events;
        }

        $itemsCount = count($items);

        $this->logger->LogDebug(sprintf("%s events in %s, %s %s traffic feed.",
                                        $itemsCount,
                                        $this->state,
                                        $this->location,
                                        $this->type));

        foreach($items as $item){

            $description = trim($item['description']['value']);

            //var_dump($item);
            //exit();

            if($this->type == 'limit'){
                $title = $item['title']['value'];
            }else{
                $title = sprintf('%s, %s', $item['title']['attr']['street'],
                                           $item['title']['attr']['suburb']);
            }

            if($this->type == 'limit'){
                preg_match(QLD_LIMIT_TIME_REGEXP, $item['description']['value'],
                                                 $regs);
                $pubtimestamp = $this->getUnixtimestampQLD($regs[1]);
            }else{
                $pubtimestamp = $this->getUnixtimestampQLD(
                                        $item['title']['attr']['dateFrom'],
                                        $item['title']['attr']['timeFrom']);
            }


            $link = $item['link']['value'];

            $category = null;
            // We set to id the same value as link
            $guid = $link;

            if($this->type == 'limit'){
                list($location, $happened) = explode(' - ', $title);
                $coordinates = $this->geocode($location);
            }else{
                $coordinates = $this->geocode($title);
            }

            if($coordinates){
                $geocoded = 1;
            }else{
                $geocoded = 0;
            }

            $event = array('title' => $title,
                               'link' => $link,
                               'category' => $category,
                               'guid' => $guid,
            //'pubDate' => $pubDate,
                               'description' => $description,
                               'coordinates' => $coordinates,
                               'geocoded' => $geocoded,
                               'pubtimestamp' => $pubtimestamp,
                               'type' => $this->type);
            $events[] = $event;
        }
        return $events;
    }

    protected function getNSWEvents(){
        $events = array();

        if(array_key_exists('entry', $this->document[1])){
            $items = $this->document[1]['entry'];
        }else{
            $this->logger->LogDebug('No entries if feed.');
            return $events;
        }

        $itemsCount = count($items);

        $this->logger->LogDebug(sprintf("%s events in %s, %s %s traffic feed.",
                                        $itemsCount,
                                        $this->state,
                                        $this->location,
                                        $this->type));

        foreach($items as $item){
            # example of $item['link']['attr']['href']
            # "http://livetraffic.rta.nsw.gov.au/#INCIDENT_398218"
            # $type = strtolower(array_shift(explode('_', end(explode('#', $item['link']['attr']['href'])))));
            $tmpExplode = explode('#', $item['link']['attr']['href']);
            $tmpExplode = end($tmpExplode);
            $tmpLower = explode('_', $tmpExplode);
            $tmpLower = end($tmpLower);
            $type = strtolower($tmpLower);

            $description = trim($item['content']['value']);
            $title = trim($item['title']['value']);
            $link = $item['link']['attr']['href'];

            $category = null;
            $guid = trim($item['id']['value']);
            $pubDate = trim($item['updated']['value']);


            // 2012-05-02T09:35:22Z
            $pubDate = str_replace('Z', '', str_replace('T', ' ', $pubDate));

            $pubtimestamp = strtotime($pubDate);
            if($pubtimestamp == false){
                $this->logger->LogError(
                    "NSW, traffic: could not convert to timestamp `$pubDate`.");
                $pubtimestamp = time();
            }


            list($titleEvent, $titleLocation) = explode('-', $title, 2);

            $titleLocation = str_replace('near', 'at', $titleLocation);

            $coordinates = $this->geocode($titleLocation);

            if($coordinates){
                $geocoded = 1;
            }else{
                $geocoded = 0;
            }

            $event = array('title' => $title,
                               'link' => $link,
                               'category' => $category,
                               'guid' => $guid,
                               'pubDate' => $pubDate,
                               'description' => $description,
                               'coordinates' => $coordinates,
                               'geocoded' => $geocoded,
                               'pubtimestamp' => $pubtimestamp,
                               'type' => $type);
            $events[] = $event;
        }
        return $events;
    }
}
