<?php
    define('DATE_TIME_ZONE', 'Australia/Canberra');

    require_once(realpath(dirname(__FILE__) . '/../config/startup.php'));

    require_once(realpath(dirname(__FILE__) .'/../lib/Db.php'));

    $config_path = realpath(dirname(__FILE__) . '/../config/config.php');
    $config = require($config_path);

    $db = new Db($config['db']['host'],
                 $config['db']['user'],
                 $config['db']['pass'],
                 $config['db']['base']);

    $tsql = "SELECT * FROM %s ORDER BY state";
    $sql = sprintf($tsql, $config['db']['lastUpdateTimeTable']);

    $records = $db->getRows($sql);

    $tableTpl = '<table border="1">
                    <tr>
                        <th>State</th>
                        <th>Type</th>
                        <th>Event</th>
                        <th>Publication Time<br />(time from document,<br />document md5 or<br />timestamp)</th>
                        <th>Last Update Time (%s)</th>
                        <th>Last Records</th>
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
               </tr>';

    $rows = array();

    foreach($records as $record){
        $feedLinkTpl = '<a href="feed.php?state=%s&type=%s&event=%s">last upadates</a>';

        if($record['type'] == 'all_events'){
           $event = 'all_events';
        }else{
            $event = $record['event'];
        }

        $feedLink = sprintf($feedLinkTpl, rawurlencode($record['state']),
                                          rawurlencode($record['type']),
                                          rawurlencode($event));

        $dt = new DateTime(sprintf('@%s', $record['update_timestamp']));
        $dt->setTimezone(new DateTimeZone(DATE_TIME_ZONE));
        $dtStr = $dt->format('l jS \of F Y h:i:s A');

        $rows[] = sprintf($rowTpl, $record['state'],
                                   $record['type'],
                                   $event,
                                   $record['time_string'],
                                   $dtStr,
                                   $feedLink);
    }

    $table = sprintf($tableTpl, DATE_TIME_ZONE, implode('', $rows));
?>

<!DOCTYPE html>
<html lang="en">
    <head>
        <link rel="stylesheet" type="text/css" href="../css/table.css">
    </head>
    <body>
        <div id="tb">
            <?php print $table; ?>
        </div>
    </body>
</html>