<?php
$applicationRoot = realpath(dirname(__FILE__) . '/../');
$logFolder = $applicationRoot . '/log';
//$logPath = sprintf('%s/logger-%s.log', $logFolder, date('Ymd'));
//$logPath = sprintf('%s/klog_output.log', $logFolder);
$logPath = $logFolder . '/klog_output.log';
//$logPath = 'php://stderr';
//$processlogPath = sprintf('%s/process-%s.log', $logFolder, date('Ymd'));
//$processlogPath = 'php://stderr';
$processLogPath = $logFolder . '/log_output.log';
//$processLogPath = $logPath;
return array(
    'applicationRoot' => $applicationRoot,
    // Most Verbose - 1 (DEBUG), ERROR - 4
    'logFolder' => $logFolder,
    'logLevel' => 1,
    'logPath' => $logPath,
    'processlogLevel' => 1,
    'processlogPath' =>$processLogPath,
    'keepLogsDays' => 30,

    // Should be smaller than interval used in cron for posting event with API
    // to avoid situation when more than one job is run.
    'apiInterval' => 599,

    'db' => array(
        'host' => 'localhost',
        'base' => 'BushfireTraffic',
        'user' => 'root',
        'pass' => 'Maddy3322',
        'lastUpdateTimeTable' => 'last_update_time',
        'incidents_table' => 'incidents',
        'geocoder_cache_table' => 'geocoder_cache'
    ),

    'max_db_description_legth' => '10000',
     /*
     * This value must be equal to max_allowed_packet configuration value of MySQL server
     * for weatherwatch.bsch.au.com max_allowed_packet value is 1048576
     */
    'max_allowed_packet' => 1048576,
    'timezoneIdentifier' => 'GMT',
    'geocoding_api_uri_tmpl' => 'http://maps.googleapis.com/maps/api/geocode/xml?address=%s&sensor=false',
    #'maps_host' => 'maps.google.com',
    #'maps_key' => "ABQIAAAA_z3cwAtG4_2x7-W-kcaQqBTWiHSUiUWE0kvzU32cuEeuOpZe2hRNQCns5bsnFnlNyd72z0qjsRgLdA",
    // bounding box for coordinates check
    'bbox' =>array(
        'minLat' => -54.640301,
        'maxLat' => -9.228830,
        'minLon' => 112.921112,
        'maxLon' => 159.278717
    ),
    // For server which receives data from another server
    //'update_uri' => 'http://www.hazmon.org/Dev/ewn2/serveupdate.php',
    /*
    * Only one list of jobs should be uncommented
    */
    // For server which receives data from another server
    //'jobs' => array('getupdate', 'clear'),
    // For server which gets data directly from feeds
    // 'sat' includes data for 'sa', 'sai'
    // non test
    'jobs' => array('clear', 'nsw', 'tas', 'vic', 'vici', 'wa', 'sa', //'sat',
                    'actt', 'nt', 'qld', 'nswt', 'qldt', 'vict', 'ntt', 'wat'),
    /* test
    'jobs' => array('clear', 'bushfiretest', 'traffictest'), */
    'alertUrls'=>array(
        'NSW' => 'http://www.rfs.nsw.gov.au/feeds/majorIncidents.xml', //https://maps.cfs.sa.gov.au/kmls/nsw
        'TAS' => 'http://www.fire.tas.gov.au/Show?pageId=colBushfireSummariesRss',
        //'VIC' => 'http://osom.cfa.vic.gov.au/public/osom/websites.rss', //https://maps.cfs.sa.gov.au/kmls/victoria
        'VIC' => 'https://data.emergency.vic.gov.au/Show?pageId=getIncidentRSS', //https://maps.cfs.sa.gov.au/kmls/victoria
        'SA' => 'https://maps.cfs.sa.gov.au/kmls/incidents', //http://www.cfs.sa.gov.au/custom/criimson/CFS_Fire_Warnings.xml
        'NT' => 'http://www.lrm.nt.gov.au/applications/bushfiresnt-alerts/watch-and-act', //'http://www.nretas.nt.gov.au/applications/bushfiresnt-alerts/watch-and-act',
        //'QLD' => 'http://www.ruralfire.qld.gov.au/bushfirealert/bushfireAlert.xml',
        //'QLD' => 'http://www.ruralfire.qld.gov.au/bushfirealert/bushfireAlert.js',
        'QLD' => 'https://www.qfes.qld.gov.au/data/alerts/bushfireAlert.xml',
        'WA' => null, // This state info is processed in a different way, //https://maps.cfs.sa.gov.au/kmls/wa
        /* TEST based off NSW
        'TST' => 'http://scrape.dev.ewn.com.au/Bushfire-Traffic/bushfiretestHTML.xml' */
    ),

    'incidentUrls' => array(
        'VIC' => 'http://osom.cfa.vic.gov.au/public/osom/IN_COMING.rss',
        'SA' => 'http://www.cfs.sa.gov.au/custom/criimson/CFS_Fire_Warnings.xml'
        //http://www.cfs.sa.gov.au/custom/criimson/CFS_Current_Incidents.xml
        //https://maps.cfs.sa.gov.au/kmls/incidents?t=1448498802532
    ),

    'trafficUrls' => array(
        // This link is both for bushfires and traffic events
        'ACT' => array('all_events' => 'http://www.esa.act.gov.au/feeds/currentincidents.xml'),
        // This link is both for bushfires and traffic events
        'SA' => array('all_events' => 'http://www.cfs.sa.gov.au/site/news_media/current_incidents.jsp'),
        'NSW' => array(
            'north' => 'http://livetraffic.rta.nsw.gov.au/traffic/rss/reg-north.atom',
            'south' => 'http://livetraffic.rta.nsw.gov.au/traffic/rss/reg-south.atom',
            'west' => 'http://livetraffic.rta.nsw.gov.au/traffic/rss/reg-west.atom',
            'sydneyinner' => 'http://livetraffic.rta.nsw.gov.au/traffic/rss/syd-metro.atom',
            'sydneynorth' => 'http://livetraffic.rta.nsw.gov.au/traffic/rss/syd-north.atom',
            'sydneysouth' => 'http://livetraffic.rta.nsw.gov.au/traffic/rss/syd-south.atom',
            'sydneywest' => 'http://livetraffic.rta.nsw.gov.au/traffic/rss/syd-west.atom'
        ),
        'QLD' => array(
            'incident' => 'http://131940.qld.gov.au/DMR.Modules/TTIEvents/RSS/RSS.aspx?regionid=0&eventcause=Incident',
            'roadwork' => 'http://131940.qld.gov.au/DMR.Modules/TTIEvents/RSS/RSS.aspx?regionid=0&eventcause=RoadWorks',
            'event' => 'http://131940.qld.gov.au/DMR.Modules/TTIEvents/RSS/RSS.aspx?regionid=0&eventcause=SpecialEvent',
            'limit' => 'http://131940.qld.gov.au/DMR.Modules/TTIEvents/RSS/RSS.aspx?regionid=0&eventcause=LoadLimits'
        ),
        'VIC' => array('http://traffic.vicroads.vic.gov.au/maps.json'),
        'NT' => array('http://www.ntlis.nt.gov.au/roadreport/obstructions-byregion.jsp'),
        'WA' => array(null) // This state info is processed in a different way
        /* TEST Based off ACT
        'TST' => array('all_events' => 'http://scrape.dev.ewn.com.au/Bushfire-Traffic/traffictest.xml') */
    ),

    // For processing WA bushfires
    'waLinksPageUrl' => 'http://www.dfes.wa.gov.au/pages/rss.aspx',// 'http://www.fesa.wa.gov.au/pages/rss.aspx',
    'waUrlListPattern' => '|<a href="/alerts/_layouts/fesa.sps2010.internet/fesalistfeed.aspx\?List=(.*)">Alerts and Warnings feed</a>|Usi',
    'waAlertUrl' => 'http://www.dfes.wa.gov.au/alerts/_layouts/fesa.sps2010.internet/fesalistfeed.aspx?List=',

    // we look for keys in titles and descriptions and save values in db
    'waBushfireCategories' => array(
        'ADVICE' => 'Advice',
        'WATCH AND ACT' => 'Watch and Act',
        'EMERGENCY WARNING' =>'Emergency Warning'
     ),

    // For processing WA traffic
    'waTrafficLinksPageUrl' => 'https://www.mainroads.wa.gov.au/Pages/RSS.aspx',
    'waTrafficUrlListPattern' => '|<a href="/_layouts/15/mrwa/rss.aspx\?list=(.*)">Alerts|Usi', //'|<a href="/_layouts/listfeed.aspx\?list=(.*)">Alerts|Usi',
    'waTrafficAlertUrl' => 'https://www.mainroads.wa.gov.au/_layouts/15/mrwa/rss.aspx?list=', //'https://www.mainroads.wa.gov.au/_layouts/listfeed.aspx?list=',

    'ntTrafficEventBaseUrl' => 'http://www.ntlis.nt.gov.au/roadreport/',
    'vicTrafficEventBaseUrl' => 'http://alerts.vicroads.vic.gov.au/incidents/',

    # Titles repeat, but " Update 1", " - filnal", " Final", " -Upda",
    # " - Update N", ", Final" ... can be added to them
    # we need to cut such endings off to be able to update data in database
    # to be updated
    'ntBushfireTitleEndingsToDelete' => array(
        "/ ?[-,]? ?update \d+.?$/i",
        "/ ?[-,]? ?final.?$/i"
    ),

    // Used to get title from description for VIC bushfire alerts.
    'vic_location_from_description_regexps' => array(
        '#<strong>Incident Name: (.*)[&,<]#Usi',
        '#<strong>Location: (.*)</strong>#Usi',
        '#<strong>Issued For: (.*)</strong>#Usi'
    ),

    'clear_before_hours' => 72,
    'max_fetch_execution_time' => 300,

    'apiRoot' => 'http://api.ewn.com.au/v1/rest/json/alert',
    //live 'http://api.ewn.com.au/v1/rest/json/alert',
    //DEV: 'http://api.ci.ewn.com.au:55555/v1/rest/json/alert'
    //TEST: 'http://api.test.ewn.com.au:55555/v1/rest/json/alert'
    'apiKey' => 'EAJJQ4KQXTNIQK0NW22E6OPYHSHA7AAZ0MYHEBRXFSBSK4DPWFX4ZN65KOFC18WA', 
    // live: 'EAJJQ4KQXTNIQK0NW22E6OPYHSHA7AAZ0MYHEBRXFSBSK4DPWFX4ZN65KOFC18WA'
    // DEV: 'NGIQ2O4AH2XBO3ZIELOCRYL42TLGIAIZ4SDRDU1XINIKFZDVSQ9LTXQK1PE5ZAOF'
    // TEST: 'NGIQ2O4AH2XBO3ZIELOCRYL42TLGIAIZ4SDRDU1XINIKFZDVSQ9LTXQK1PE5ZAOF'
    'trafficApiKey' => 'ZEYKQF8YTP5M4II5VIBUNHJWUBY1BHR3GMOH8MLFQHWEWCCVTRMTM73UFOKK7TZI',
    // live 'ZEYKQF8YTP5M4II5VIBUNHJWUBY1BHR3GMOH8MLFQHWEWCCVTRMTM73UFOKK7TZI',
    // dev: 'NGIQ2O4AH2XBO3ZIELOCRYL42TLGIAIZ4SDRDU1XINIKFZDVSQ9LTXQK1PE5ZAOF'
    // TEST: 'EAJJQ4KQXTNIQK0NW22E6OPYHSHA7AAZ0MYHEBRXFSBSK4DPWFX4ZN65KOFC18WA'
    'trafficAlertGroupKey' => 1568,
    'bushfireApiKey' => 'SZST8FITXAAQGAAZJVJKTBM7LYWTF2YYX17WTW3W1F2GYKKSKKTBOXAPKE7HCJMI',
    //live: 'SZST8FITXAAQGAAZJVJKTBM7LYWTF2YYX17WTW3W1F2GYKKSKKTBOXAPKE7HCJMI'
    //dev: 'KGE3UQYJLYHSIQZNRA3HUN6ZLGA1AGYOWYPKOQZH6FLHYOHU8KCBDF6DOZHXCTQS'
    //TEST: 'KGE3UQYJLYHSIQZNRA3HUN6ZLGA1AGYOWYPKOQZH6FLHYOHU8KCBDF6DOZHXCTQS'
    'bushfireAdviceAlertGroupKey' => 1628,  
    //live + test + ci: 1628
    'bushfireWatchActEmergencyAlertGroupKey' => 1724, 
    //live+ test + ci :1724
    'smsLength' => 160
);