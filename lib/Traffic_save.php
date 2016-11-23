<?php

define('QLD_LIMIT_TIME_REGEXP', '/Last Reviewed: (.+)\./Usi');
define('WA_ENTERED_TIME_REGEXP', '#<b>Entered: (.+)</b>#Usi');

class Traffic extends EventFeedAbs{

    public function getUrl(){
        /*
         if($this->state == 'NSW'){
         $url = @$this->config['trafficUrls'][$this->state][$this->location];
         }else if($this->state == 'QLD'){
         $url = @$this->config['trafficUrls'][$this->state][$this->type];
         }else if($this->state == 'VIC' || $this->state == 'NT'){
         $url = @$this->config['trafficUrls'][$this->state];
         }
         */
        $url = @$this->config['trafficUrls'][$this->state][$this->type];


        if(is_null($url)){
            $errorMessage = sprintf('Not proper data input. Event: traffic, state: `%s`, type: `%s`',
            $this->state, $this->type);
            $this->logger->LogError($errorMessage);
            throw new InputFeedException($errorMessage);
        }
        return $url;
    }

    public function loadFeed(){
        $url = $this->getUrl();

        $this->logger->LogDebug("Loading $url.");

        if($this->state == 'WA'){
            $this->document = $this->curlService->executeCurl($url);
            $this->document = str_replace(chr(150), '-',  $this->document);
        }else if($this->state == 'VIC' || $this->state == 'NT'){
            $html = new DOMDocument();

            $loadResult = @$html->loadHtmlFile($url);
            if($loadResult){
                $this->document = $html;
                $this->docXpath = new DOMXPath($this->document);
            }else{
                throw new NotLoadingFeedException(
                $this->state ." traffic URL was not loaded $url.");
            }
        }else if($this->state == 'NSW' || $this->state == 'QLD'){
            $xmlObj = new AXP($debug = false);
            $xmlObj->setEncoding('UTF-8');
            $xmlObj->setXmlData($url,_AXP_URL);
            $xmlObj->parse();
            $this->document = $xmlObj->getArray();
        }else{
            $errorMessage = sprintf('Not proper data input. Event: traffic, state: `%s`, type: `%s`',
            $this->state, $this->type);
            $this->logger->LogError($errorMessage);
            throw new InputFeedException($errorMessage);
        }
    }

    public function getPubDate(){

        $feedPubDate = null;

        if($this->state == 'NSW'){
            $feedPubDate = $this->document[1]['updated']['value'];
        }

        if($this->state == 'QLD'){
            $feedPubDate = $this->getMaxUnixtimestampQLD();
        }

        if($this->state == 'VIC'){
            $nodeList = $this->docXpath->query(
                                               '/html/body/div/div/p/time');
            $feedPubDate = $nodeList->item(0)->getAttribute('datetime');
        }

        if($this->state == 'NT'){
            $nodeList = $this->docXpath->query('//*[@id="date"]');
            $feedPubDate = trim(str_replace(
                                         'This report is current as at: ', '',
            $nodeList->item(0)->nodeValue));
        }

        if($this->state == 'WA'){
            $feedPubDate = $this->getMaxUnixtimestampWA();
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

    protected function getMaxUnixtimestampWA(){
        preg_match_all(WA_ENTERED_TIME_REGEXP, $this->document, $regs);
        $unixtimestamps = array();
        foreach($regs[1] as $timeString){
            $unixtimestamps[] = $this->getUnixtimestampWA($timeString);
        }
        return max($unixtimestamps);
    }
    /**
     * Converts WA $timeString into unixtimestamp
     *
     * @param string $timeString
     * @return int or false
     */
    protected function getUnixtimestampWA($timeString){
        /*
         [1]=>
         array(2) {
         [0]=>
         string(21) "28/05/2012 8:38:03 AM"
         [1]=>
         string(21) "21/03/2011 8:03:12 AM"
         }
         */

        //lets convert to timestapms as SOAP
        //2008-07-01T22:35:17.03+08:00

        list($date, $time, $meridian) = explode(' ', $timeString);
        list($day, $month, $year) = explode('/', $date);
        list($hour, $minute, $second) = explode(':', $time);
        if($meridian == 'PM'){
            $hour = intval($hour) + 12;
            if($hour == 24){
                $hour = 12;
            }
        }
        $time = sprintf('%02d:%s:%s', $hour, $minute, $second);

        $offset = $this->getTimezoneOffset('UTC', 'Australia/West');

        $pubDate = sprintf('%s-%s-%sT%s.00+%02d:00',
        $year, $month, $day, $time, $offset/3600);
        return strtotime($pubDate);
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
        }
    }

    public function getInnerHTML($node){
        $innerHTML= '';
        $children = $node->childNodes;
        foreach ($children as $child) {
            $innerHTML .= $child->ownerDocument->saveXML($child);
        }
        return $innerHTML;
    }

    protected function getWAEvents(){
        $events = array();

        $items = explode('<HR>', $this->document);

        var_dump($items[0]);
        var_dump($items[1]);
        var_dump($items[count($items)]);

        exit();

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
        $offset = $this->getTimezoneOffset('UTC',
                                               'Australia/Queensland');
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
            $coordinates = false;

            $event = array('title' => $title,
                               'link' => $link,
                               'category' => $category,
                               'guid' => $guid,
            //'pubDate' => $pubDate,
                               'description' => $description,
                               'coordinates' => $coordinates,
                               'pubtimestamp' => $pubtimestamp,
                               'type' => $type);
            $events[] = $event;

        }
        return $events;
    }

    protected function getVICEvents(){
        $events = array();

        $nodelist = $this->docXpath->query( "//ul[@id='alertList']//li" );

        if($nodelist->length == 0){
            $this->logger->LogDebug('No entries if feed VIC traffic.');
            return $events;
        }

        $this->logger->LogDebug(sprintf("%s events in %s, %s %s traffic feed.",
        $nodelist->length,
        $this->state,
        $this->location,
        $this->type));

        foreach ($nodelist as $nodeItem){
            $id = $nodeItem->getAttribute('id');
            list($tmpPart, $pageUrl) = explode('_', $id);

            $guid = $this->config['vicTrafficEventBaseUrl'] . $pageUrl;
            $link = $guid;

            $a = $nodeItem->getElementsByTagName('a');
            $lon = $a->item(0)->getAttribute('data-long');
            $lat = $a->item(0)->getAttribute('data-lat');

            $aDivs = $a->item(0)->getElementsByTagName('div');

            // Find content div.
            foreach($aDivs as $aDiv){
                $class = $aDiv->getAttribute('class');
                if($class == 'alertContent'){
                    $alertContentDiv = $aDiv;
                    break;
                }
            }

            $cDivs = $alertContentDiv->getElementsByTagName('div');

            // Find content div.
            foreach($cDivs as $cDiv){

                $class = $cDiv->getAttribute('class');

                if($class == 'alertTimeline'){
                    $ps = $cDiv->getElementsByTagName('p');
                    foreach($ps as $p){
                        if($p->getAttribute('class') =='startTime'){
                            $time = $p->getElementsByTagName('time');
                            // 2012-05-20T03:15:50+1000
                            //
                            // Going to use for conversion following format:
                            // SOAP     YY "-" MM "-" DD "T" HH ":" II ":"
                            // SS frac tzcorrection?
                            // "2008-07-01T22:35:17.02",
                            //"2008-07-01T22:35:17.03+08:00"
                            $dateTime = $time->item(0)->getAttribute('datetime');
                            list($dateTime, $offset) = explode('+', $dateTime);
                            $offset = intval(substr($offset, 0, 2));
                            $pubDate = sprintf("%s.00+%02d:00", $dateTime, $offset);
                            $pubtimestamp = strtotime($pubDate);
                            //print to check
                            //echo date('c', $pubtimestamp) . "\n";
                            break;
                        }
                    }
                }else if($class == 'alertStatus'){
                    $ps = $cDiv->getElementsByTagName('p');
                    foreach($ps as $p){
                        // closureType: Road Closed, Traffic Alert(? only 2.)
                        // incidentType: Road Damage, Flood
                        // Lets use closureType as $type
                        // and closureType and incidentType as part of title
                        $pClass = $p->getAttribute('class');
                        if($pClass == 'closureType'){
                            $closureType = $p->nodeValue;
                        }else if($pClass == 'incidentType'){
                            $incidentType = $p->nodeValue;
                        }
                    }
                }else if($class == 'alertTitle'){
                    /*
                     * alertTitle example:
                     <h3>Flynns Road</h3>
                     <p class="locale">Pelluebla</p>
                     <p class="incidentFrom"><strong>From:</strong> Almonds-Wilby Road, Pelluebla</p>
                     <p class="incidentTo"><strong>To:</strong> Cemetery Road, Pelluebla</p>
                     */
                    $hs = $cDiv->getElementsByTagName('h3');
                    $road = $hs->item(0)->nodeValue;

                    $ps = $cDiv->getElementsByTagName('p');
                    foreach($ps as $p){
                        $pClass = $p->getAttribute('class');
                        if($pClass == 'locale'){
                            $locale = $p->nodeValue;
                        }else if($pClass == 'incidentFrom'){
                            $incidentFrom = $p->nodeValue;
                        }else if($pClass == 'incidentTo'){
                            $incidentTo = $p->nodeValue;
                        }
                    }
                }
            }

            $title = sprintf('%s, %s - %s - %s.',
            $road, $locale, $closureType, $incidentType);
            $category = 'No Alert Level';
            $description = $incidentFrom . '<br />' . $incidentTo;
            $coordinates = $lon . ' ' . $lat;
            $type = $closureType;

            $event = array('title' => $title,
                               'link' => $link,
                               'category' => $category,
                               'guid' => $guid,
            //'pubDate' => $pubDate,
                               'description' => $description,
                               'coordinates' => $coordinates,
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
                preg_match(QLD_LIMIT_TIME_REGEXP,
                $item['description']['value'],
                $regs);
                $unixtimestamps[] = $this->getUnixtimestampQLD($regs[1]);
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
            list($day, $month, $year, $at, $time) = explode(' ',
            $dateString);
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
                $title = sprintf('%s, %s',
                $item['title']['attr']['street'],
                $item['title']['attr']['suburb']);
            }

            if($this->type == 'limit'){
                preg_match(QLD_LIMIT_TIME_REGEXP,
                $item['description']['value'],
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

            $event = array('title' => $title,
                               'link' => $link,
                               'category' => $category,
                               'guid' => $guid,
            //'pubDate' => $pubDate,
                               'description' => $description,
                               'coordinates' => $coordinates,
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
            $type = strtolower(array_shift(explode('_', end(explode('#', $item['link']['attr']['href'])))));

            $description = trim($item['content']['value']);
            $title = trim($item['title']['value']);
            $link = $item['link']['attr']['href'];

            $category = null;
            $guid = trim($item['id']['value']);
            $pubDate = trim($item['updated']['value']);


            // 2012-05-02T09:35:22Z
            $pubDate = str_replace('Z',
                                       '',
            str_replace('T', ' ', $pubDate));

            $pubtimestamp = strtotime($pubDate);
            if($pubtimestamp == false){
                $this->logger->LogError(
                    "NSW, traffic: could not convert to timestamp `$pubDate`.");
                $pubtimestamp = time();
            }


            list($titleEvent, $titleLocation) = explode('-', $title, 2);
            $coordinates = $this->geocode($titleLocation);

            $event = array('title' => $title,
                               'link' => $link,
                               'category' => $category,
                               'guid' => $guid,
                               'pubDate' => $pubDate,
                               'description' => $description,
                               'coordinates' => $coordinates,
                               'pubtimestamp' => $pubtimestamp,
                               'type' => $type);
            $events[] = $event;
        }
        return $events;
    }
}