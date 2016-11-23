<?php

define('NOMINATIN_QUERY_TMPL',
       'http://nominatim.openstreetmap.org/search?format=json&limit=5' .
       '&accept-language=en&countrycodes=au&q=%s');

class Geocoder{

    private $map_host = null;
    private $key = null;
    private $logger = null;
    private $geocoderCache = null;
    private $db;

    public function __construct($uriTmpl, $logger, $db, $geocoderCache){
        $this->uriTmpl = $uriTmpl;
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


        $request_url = sprintf($this->uriTmpl,
                               urlencode($address));

        while (true) {

            $xml = @simplexml_load_file($request_url);

            if(!$xml){
                $this->logger->LogError("Google geocoding url not loading: $request_url.");
                break;
            }

            $status = $xml->status;

            if (strcmp($status, "OK") == 0 &&
                ($xml->result->geometry->location_type == 'ROOFTOP' ||
                 $xml->result->geometry->location_type == 'RANGE_INTERPOLATED' ||
                 $xml->result->geometry->location_type == 'GEOMETRIC_CENTER')) {
                // Successful geocode
                $lat = $xml->result->geometry->location->lat;
                $lng = $xml->result->geometry->location->lng;
                $result = $lng . ' ' . $lat;

                $this->updateCache($address, $result);
                return $result;

            }else if(strcmp($status, "OK") == 0){
                // Location type is not good enough
                $location_type = $xml->result->geometry->location_type;
                $this->logger->LogDebug("Address: `$address` was geocoded. But location_type - `$location_type`");
                break;
            }else if(strcmp($status, "OVER_QUERY_LIMIT") == 0 ||
                     strcmp($status, "ZERO_RESULTS") == 0 ||
                     strcmp($status, "UNKNOWN_ERROR") == 0){

                $delay += 1;
                $tryNumber += 1;
                if($tryNumber > $maxTriesNumber){
                    $errorMessage = "Address: `$address` was not geocoded using Google. Received status `$status` $maxTriesNumber times";
                    break;
                }
                $this->logger->LogDebug("$status when geocoding address `$address` $tryNumber times with Google. Will try again.");
                sleep($delay);
            }
        }

        $this->logger->LogInfo("Starting Nominatim.");

        $queryTmpl = NOMINATIN_QUERY_TMPL;

        $query = sprintf($queryTmpl, urlencode($address));

        $json = @file_get_contents($query);

        if(!$json){
            throw new GeocodingFailedFeedException("Geocoding url not loading: `$query`.");
        }
        $reply = json_decode($json, true);

        if(count($reply) == 0){
            $this->logger->LogDebug("Nominatim: no results for `$query`. Caching NULL coordinates.");
            $this->updateCache($address, null);
            throw new GeocodingFailedFeedException("Nominatim: no results for `$query`.");
        }else if (count($reply) > 1){
            $this->logger->LogDebug("Nominatim: many results for `$query`.  Caching NULL coordinates.");
            $this->updateCache($address, null);
            throw new GeocodingFailedFeedException("Nominatim: many results for `$query`.");
        }else{
            $lat = $reply[0]['lat'];
            $lng = $reply[0]['lon'];

            $result = $lng . ' ' . $lat;

            $this->updateCache($address, $result);
            return $result;
        }
    }

    public function updateCache($address, $coordinates){
        if($coordinates){
            $coordinates = sprintf("'%s'", $this->db->escapeString($coordinates));
        }else{
            $coordinates = 'NULL';
        }

        $sql_t = "INSERT INTO `%s`(`insert_time`, `address`, `coordinates`)
                        VALUES (%s, '%s', %s)";

        $sql = sprintf($sql_t, $this->geocoderCache,
                time(),
                $this->db->escapeString($address),
                $coordinates);

        $this->db->executeQuery($sql);

        $this->logger->LogDebug("Saved in cache coordinates `$coordinates` for address `$address`.");
    }


    public function getCoordinatesByTitle($title, $state){

        if($state == 'WA'){

            $locationVariants = $this->getLocationVariants($title);

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