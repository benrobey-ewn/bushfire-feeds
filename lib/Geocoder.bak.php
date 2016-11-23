<?php

class Geocoder{

    private $map_host = null;
    private $key = null;
    private $logger = null;
    private $geocoderCache = null;
    private $db;

    public function __construct($maps_host, $maps_key, $logger, $db, $geocoderCache){
        $this->maps_host = $maps_host;
        $this->maps_key = $maps_key;
        $this->logger = $logger;
        $this->db = $db;
        $this->geocoderCache = $geocoderCache;
    }

    public function geocodeFromCache($fullAddress){

        $sql_t = "SELECT `coordinates`
                FROM  `%s`
                WHERE `address` = '%s'";

        $sql = sprintf($sql_t, $this->db->escapeString($this->geocoderCache),
                               $this->db->escapeString($fullAddress));

        return $this->db->getValue($sql);
    }

    public function geocode($address, $state){

        if(trim($address) == ''){
            throw new GeocodingFailedFeedException("Empty address supplied for state $state.");
        }

        $address = sprintf("%s, %s, Australia", $address, $state);

        $this->logger->LogDebug("Checking if address `$address` is in geocoder cache.");
        $cachedCoordinates = $this->geocodeFromCache($address);

        if(!($cachedCoordinates === false)){
            $this->logger->LogDebug("Got coordinated for address `$address` `$cachedCoordinates` from cache.");
            return $cachedCoordinates;
        }else{
            $this->logger->LogDebug("No coordinated for address `$address` in cache. Trying to get coordinates from Google");
        }

        // Initialize delay in geocode speed
        $delay = 0;
        $tryNumber = 1;
        $maxTriesNumber = 3;

        $base_url = sprintf("http://%s/maps/geo?output=xml&key=%s",
                            $this->maps_host, $this->maps_key);

        $request_url = $base_url . "&q=" . urlencode($address);

        /*
         $r_default_context = stream_context_get_default (array
         ('http' => array(
         'proxy' => 'proxy.softservecom.com:8080',
         'request_fulluri' => True,
         )));
         libxml_set_streams_context($r_default_context);
         */
        while (true) {

            $xml = @simplexml_load_file($request_url);

            if(!$xml){
                throw new GeocodingFailedFeedException("Geocoding url not loading: $request_url.");
            }

            $status = $xml->Response->Status->code;

            if (strcmp($status, "200") == 0) {
                // Successful geocode
                $coordinates = $xml->Response->Placemark->Point->coordinates;
                list($lng, $lat, $alt) = explode(",", $coordinates);

                $result = $lng . ' ' . $lat;

                $sql_t = "INSERT INTO `%s`(`insert_time`, `address`, `coordinates`)
                        VALUES (%s, '%s', '%s')";

                $sql = sprintf($sql_t, $this->geocoderCache,
                                       time(),
                                       $this->db->escapeString($address),
                                       $this->db->escapeString($result));

                $this->db->executeQuery($sql);

                $this->logger->LogDebug("Saved in cache coordinates `$result` for address `$address`.");

                return $result;

            }else if(strcmp($status, "602") == 0 || strcmp($status, "603") == 0){
                // 602  G_GEO_UNKNOWN_ADDRESS
                // 603  G_GEO_UNAVAILABLE_ADDRESS
                $sql_t = "INSERT INTO `%s`(`insert_time` ,`address`, `coordinates`)
                          VALUES (%s, '%s', NULL)";

                $sql = sprintf($sql_t, $this->geocoderCache,
                                       time(),
                                       $this->db->escapeString($address));

                $this->db->executeQuery($sql);

                $this->logger->LogDebug("Saved in cache coordinates NULL for address `$address`.");

                $errorMessage = "Address: `$address` was not geocoded. Received status `$status`";
                throw new GeocodingFailedFeedException($errorMessage);

            }else if (strcmp($status, "620") == 0) {
                //sent geocodes too fast
                $delay += 1;
                $tryNumber += 1;
                if($tryNumber > $maxTriesNumber){
                    $errorMessage = "Address: `$address` was not geocoded. Received status `$status` $maxTriesNumber times";
                    throw new GeocodingFailedFeedException($errorMessage);
                }
            }else{
                $errorMessage = "Address: `$address` was not geocoded. Received status `$status`";
                throw new GeocodingFailedFeedException($errorMessage);
            }
            sleep($delay);
        }
    }

    public function getCoordinatesByTitle($title, $state){

        if($state == 'WA'){

            $locationVariants = getLocationVariants($title);

            if(count($locationVariants) == 0){
                $errorMessage = "Failed geocoding for WA. No location variants found in title: `$title`.";
                throw new GeocodingFailedFeedException($errorMessage);
            }

            $this->logger->LogDebug("WA location variants: " . implode(",", $locationVariants));

            $longtitudes = array();
            $latitudes = array();

            foreach($locationVariants as $locationVariant){
                try{
                    $coordinates = $this->geocode($locationVariant, $state);
                    list($longtitude, $latitude) = explode(' ', $coordinates);
                    $longtitudes[] = (float) $longtitude;
                    $latitudes[] = (float) $latitude;
                    $this->logger->LogDebug("Location variant `$locationVariant, WA, Australia` was geocoded: $coordinates.");
                }catch (GeocodingFailedFeedException $e){
                    $this->logger->LogDebug("Location variant `$locationVariant, WA, Australia` was not geocoded.");
                }
            }

            $coordinatesNumber = count($longtitudes);

            if($coordinatesNumber > 0){
                $averageLongtitude = array_sum($longtitudes) / $coordinatesNumber;
                $averageLatitude = array_sum($latitudes) / $coordinatesNumber;
                $averageCoordinatesString = numberToString($averageLongtitude) . ' ' . numberToString($averageLatitude);
                return $averageCoordinatesString;
            }else{
                $errorMessage = "None of location variants was geocodes: " . implode(",", $locationVariants);
                $this->logger->LogDebug($errorMessage);
                throw new GeocodingFailedFeedException($errorMessage);
            }
        }else{
            // Currently VIC and SA.
            $pos = strpos($title, '(');

            if($pos === false){
                $location = $title;
            }else{
                $location = trim(substr($title, 0, $pos));
            }
            $coordinates = $this->geocode($location, $state);
            $this->logger->LogDebug("Location `$location, $state, Australia` was geocoded: $coordinates.");
            return $coordinates;
        }
    }

    public function getLocationVariants($phrase){
        $locationVariants = array();

        $strParts = explode(' ', $phrase);

        $partsNumber = count($strParts);

        for($i=1; $i<$partsNumber; $i++){
            //@ - because sometimes the string is empty
            if(ctype_upper(@$strParts[$i][0])){
                $locationVariant = $strParts[$i];
                $i++;
                while($i < $partsNumber){
                    if(ctype_upper($strParts[$i][0])){
                        $locationVariant .= ' ' . $strParts[$i];
                        $i++;
                    }else{
                        break;
                    }
                }
                $locationVariants[] = $locationVariant;
            }
        }
        return $locationVariants;
    }
}