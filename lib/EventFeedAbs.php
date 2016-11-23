<?php
abstract class EventFeedAbs{
    protected $config = null;
    protected $state = null;
    protected $type = null;
    protected $curlService = null;
    protected $document = null;
    protected $db = null;
    protected $logger = null;
    protected $location = null;
    protected $shortMonths = array(1 =>'Jan', 'Feb', 'Mar', 'Apr', 'May',
                             'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec');

    abstract public function getUrl();
    abstract public function getPubDate();
    abstract public function getEvents();

    public function __construct($config, $curlService, $state, $type, $db, $logger){
        $this->config = $config;
        $this->curlService = $curlService;
        $this->state = $state;
        $this->type = $type;
        $this->db = $db;
        $this->logger = $logger;
        $this->logger->LogDebug("Event Feed object initialized ($state, $type).");
    }

    /**Returns the offset from the origin timezone to the remote timezone, in seconds.
     *
     *    @param $remote_tz;
     *    @param $origin_tz; If null the servers current timezone is used as the origin.
     *    @return int;
     */
    public function getTimezoneOffset($remote_tz, $origin_tz = null) {
        if($origin_tz === null) {
            if(!is_string($origin_tz = date_default_timezone_get())) {
                return false; // A UTC timestamp was returned -- bail out!
            }
        }
        $origin_dtz = new DateTimeZone($origin_tz);
        $remote_dtz = new DateTimeZone($remote_tz);
        $origin_dt = new DateTime("now", $origin_dtz);
        $remote_dt = new DateTime("now", $remote_dtz);
        $offset = $origin_dtz->getOffset($origin_dt) - $remote_dtz->getOffset($remote_dt);
        return $offset;
    }

    public function geocode($location){
        $this->logger->LogDebug(
             "Getting coordinates by title '$location', state '$this->state'");

        $coordinates = null;

        try{
            $geocoder = new Geocoder($this->config['geocoding_api_uri_tmpl'],
                                     //$this->config['maps_host'],
                                     //$this->config['maps_key'],
                                     $this->logger,
                                     $this->db,
                                     $this->config['db']['geocoder_cache_table']);
            $coordinates = $geocoder->getCoordinatesByTitle($location,
                                                            $this->state);

        }catch(GeocodingFailedFeedException $e){
            $this->logger->LogDebug($e->getMessage());
        }

        return $coordinates;
    }

    public function loadFeed() {
        $url = $this->getUrl();
        $this->logger->LogDebug("Loading feed for $this->state from $url");
        $data = $this->curlService->executeCurl($url, $this->state);
        // http://osom.cfa.vic.gov.au/public/osom/websites.rss has undeclared
        // "&ndash" entities and that is why the feed can't be parsed as xml.
        $data = str_replace('&ndash', '-', $data); 

        if ($this->state == 'QLD' && $this->type == 'alert') {
            list($var, $value) = explode('=', $data, 2);
            $document = json_decode(utf8_encode(substr_replace($value, '', -1)));
        }
        else if ($this->state == 'SA' && $this->type == 'alert') {
            $rootDir = basename(dirname($_SERVER['PHP_SELF']));
            $directory = $rootDir . "/temp/";
            $fileToUnzip = $rootDir . "/temp/SA.kmz";
            $zipFileName = "";
            $zip = new ZipArchive;
            $result = $zip->open($fileToUnzip);

            if ($result == true) {
                $zip->extractTo($directory);
                $zipFileName = $zip->getNameIndex(0);
                $zip->close();
                $this->logger->LogDebug("Unzip for state $this->state file $fileToUnzip successful.");
                $document = false;
                $contents = file_get_contents($directory . $zipFileName);
                $document = new SimpleXMLElement($contents);
            }
            else {
                $this->logger->LogError("Unzip for state $this->state file $fileToUnzip unsuccessful.");
            }
        }
        else {
            $data = str_replace('&middot;', '', $data);
            //$document = @simplexml_load_string($data); ORIGINAL
            /*Has to be in UTF-8 and not UTF-16, is converted to or it is UTF-16 for no apparent reason, and needs to be converted, 
            that's why simplexml_load_string returns a false value, because it errors and doesn't report corretcly when it has the @ value infront of it. (this took way too long to figure out, thanks Arek)*/
            $document = simplexml_load_string(preg_replace('/(<\?xml[^?]+?)utf-16/i', '$1utf-8', $data));
        }
        if (!$document) {
            $this->logger->LogError("No propper data was curled ($this->state, $url).");
            throw new NotLoadingFeedException("No propper data was curled ($this->state, $url).");
        }
        $this->logger->LogDebug("Feed was curled from $url.");
        $this->document = $document;
    }
}
