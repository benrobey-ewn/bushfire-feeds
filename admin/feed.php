<?php
    define('DATE_TIME_ZONE', 'Australia/Canberra');

    require_once(realpath(dirname(__FILE__) . '/../config/startup.php'));

    require_once(realpath(dirname(__FILE__) .'/../lib/Db.php'));

    $config_path = realpath(dirname(__FILE__) . '/../config/config.php');
    $config = require($config_path);


    // state=ACT&type=all_events&event=traffic

    if(isset($_GET['state']) && isset($_GET['type']) && isset($_GET['event'])){
        $state = $_GET['state'];
        $type = $_GET['type'];
        $event = $_GET['event'];
    }else{
        echo 'Wrong parametes';
        exit();
    }


    $db = new Db($config['db']['host'],
                 $config['db']['user'],
                 $config['db']['pass'],
                 $config['db']['base']);


    if($event == 'all_events'){
        $eventCondition = '';
    }else{
        $eventCondition = sprintf("AND event='%s'", $event);
    }

    $tsql = "SELECT *
             FROM %s
             WHERE state='%s'
             %s
             AND feed_type='%s'
             AND update_ts = (SELECT max(update_ts)
                              FROM %s
                              WHERE state='%s'
                              %s
                              AND feed_type='%s')";
    $sql = sprintf($tsql, $config['db']['incidents_table'],
                          $state,
                          $eventCondition,
                          $type,
                          $config['db']['incidents_table'],
                          $state,
                          $eventCondition,
                          $type);

    $records = $db->getRows($sql);

    /*
      'guid' => string '1422969' (length=7)
      'title' => string 'ALFREDTON, ' (length=11)
      'state' => string 'VIC' (length=3)
      'description' => string '<b>District/Region:</b> DISTRICT 15<br /><b>Location:</b> ALFREDTON<br /><b>Name:</b> <br /><b>Last Updated Date/Time:</b> 21/07/13 03:43:00 AM<br /><b>Type:</b> NON STRUCTURE<br /><b>Status:</b> SAFE<br /><b>Size:</b> SMALL<br /><b>Appliances:</b> 0<br /><b>Start Date/Time:</b> 21/07/13 03:42:00 AM<br />' (length=306)
      'category' => string 'no alert level' (length=14)
      'link' => string 'http://www.cfa.vic.gov.au/incidents/incident_summary.htm' (length=56)
      'unixtimestamp' => string '1374342180' (length=10)
      'lon' => string '143.789195344' (length=13)
      'lat' => string '-37.556359106' (length=13)
      'point_str' => string '143.78919534382126 -37.55635910625252' (length=37)
      'point_geom' => string '�������Еы—Aщa@sЖ6ЗBА' (length=25)
      'type' => string 'incident' (length=8)
      'event' => string 'bushfire' (length=8)
      'update_ts' => string '1374343593' (length=10)
      'feed_type' => string 'incident' (length=8)
     */


    $tableTpl = '<table border="1">
                    <tr>
                        <th>Title</th>
                        <th>State</th>
                        <th>Description</th>
                        <th>Category</th>
                        <th>Link</th>
                        <th>Event Time</th>
                        <th>Longtitude</th>
                        <th>Latitude</th>
                        <th>Type</th>
                        <th>Event</th>
                        <th>Update time (%s)</th>
                    </tr>
                    %s
                </table>';

    $rowTpl = '<tr>
                    <td>%s</td>
                    <td>%s</td>
                    <td>%s</td>
                    <td>%s</td>
                    <td>%s</td>
                    <td>%s</td>
                    <td>%s</td>
                    <td>%s</td>
                    <td>%s</td>
                    <td>%s</td>
                    <td>%s</td>
               </tr>';

    $rows = array();

    foreach($records as $record){
        //event time
        $et = new DateTime(sprintf('@%s', $record['unixtimestamp']));
        $et->setTimezone(new DateTimeZone(DATE_TIME_ZONE));
        $eStr = $et->format('l jS \of F Y h:i:s A');

        // update time
        $dt = new DateTime(sprintf('@%s', $record['update_ts']));
        $dt->setTimezone(new DateTimeZone(DATE_TIME_ZONE));
        $dtStr = $dt->format('l jS \of F Y h:i:s A');

        $rows[] = sprintf($rowTpl, $record['title'],
                                   $record['state'],
                                   $record['description'],
                                   $record['category'],
                                   $record['link'],
                                   $eStr,
                                   $record['lon'],
                                   $record['lat'],
                                   $record['type'],
                                   $record['event'],
                                   $dtStr);
    }

    $table = sprintf($tableTpl, DATE_TIME_ZONE, implode('', $rows));

    if($event == 'traffic' || $event == 'all_events'){ // all_events are ACT and SA. They are traffic configuration.
        if($state == 'WA'){
            $source = $config['waTrafficLinksPageUrl'];
            print '<br /><span style="color: red;">You will have to click "Alerts" link to view the source.</span>';
        }else{
            $source = $config['trafficUrls'][$state][$type];
        }
    }else{
        // bushfire
        if($type == 'alert'){
            if($state == 'WA'){
                $source = $config['waLinksPageUrl'];
                print '<br /><span style="color: red;">You will have to click "Alerts and Warnings feed" link to view the source.</span>';
            }else{
                $source = $config['alertUrls'][$state];
            }
        }else{
            // incident
            $source = $config['incidentUrls'][$state];
        }
    }
?>

<!DOCTYPE html>
<html lang="en">
    <head>
        <link rel="stylesheet" type="text/css" href="../css/table.css">
    </head>
    <body>
        <?php
            print $table;
            print sprintf('<br /><br /><a href="%s">Source</a>', $source);
        ?>
    </body>
</html>