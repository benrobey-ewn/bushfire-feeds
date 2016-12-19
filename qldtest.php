<?php

require_once('lib/CurlService.php');

require 'vendor/autoload.php';

use Carbon\Carbon;

ob_start();
$curlService = new CurlService();
$data = $curlService->executeCurl('https://www.qfes.qld.gov.au/data/alerts/bushfireAlert.xml', 'QLD');
ob_end_clean();

if(false) {
    list($var, $value) = explode('=', $data, 2);
    $document = json_decode(utf8_encode(substr_replace($value, '', -1)));
}

if(true) {
    $document = new SimpleXMLElement($data);
}

/*
 * `unixtimestamp`=VALUES(`unixtimestamp`),
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
 */


/*
 * we need
 *
 * 'coordinates'
 * 'guid'
 * 'title'
 * 'category'
 * 'geometries'
 * 'geocoded'
 * 'pubtimestamp'
 * 'link'
 *
 */


$parseXml = function($document)  {
    $return = array();
    foreach($document->entry as $element) {
        //Use Namespace
        $namespaces = $element->getNameSpaces(true);
        $geo = $element->children($namespaces['georss']);
        $item = [];
        foreach($element as $key=>$value) {
            switch ($key) {
                case 'category':
                    $item['category'] = isset($value['term']) ? (string)  $value['term'] : null;
                    break;

                case 'content':
                    //$item['description'] = (string) $value;
                    break;

                case 'id':
                    $item['guid'] = (string) $value;
                    break;

                case 'published':
                    $item['unixtimestamp'] = $item['pubtimestamp'] = Carbon::parse($value)->timestamp;
                    $item['pubDate'] = Carbon::parse($value)->timezone('Australia/Brisbane')->format('l jS \\of F Y h:i:s A');
                    break;

                case 'title':
                    $item['title'] = (string) $value;
                    break;

                case 'updated':

                    break;

            }

        }
        $item['coordinates'] = (string) $geo->point;
        $item['link'] = 'https://www.qfes.qld.gov.au/data/alerts/bushfireAlert.xml';
        $item['geometries'] = 1;
        $return[]= $item;
    }
    return $return;
};

$test = $parseXml($document);

var_dump($test);
die;


//$document = simplexml_load_string(preg_replace('/(<\?xml[^?]+?)utf-16/i', '$1utf-8', $data));
