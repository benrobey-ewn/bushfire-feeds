<?php

define('MAX_FURURE_HOURS', 72);
define('MAX_PAST_HOURS', 24);

class Incident{
    private $table = null;
    public function __construct(){
        $this->table = App::$config['db']['incidents_table'];
    }

    private function getEventConditions($params){
        $eventConditions = array();

        if(isset($params['event'])){
            $event = $params['event'];

            if($event == 'traffic events'){
                $eventConditions[] = "`event` = 'traffic'";
            }else if($event == 'bushfire'){
                /* Here we come if request came from only bushfire page
                 * and "bushfire incidents"/"bushfire alerts" was not selected.
                */
                $eventConditions[] = "`event` = 'bushfire'";
            }else if ($event == 'bushfire incidents'){
                $eventConditions[] = "`event` = 'bushfire'";
                $eventConditions[] = "`type` = 'incident'";
            }else if ($event == 'bushfire alerts'){
                $eventConditions[] = "`event` = 'bushfire'";
                $eventConditions[] = "`type` = 'alert'";
            }
        }
        return $eventConditions;
    }

    public function getData($params=array()){
        /*
         $sql = sprintf("SELECT *
         FROM `%s`
         WHERE point_str IS NOT NULL",
         $this->table);
         */

        $escapedParams = array();
        foreach($params as $name=>$value){
            $name = App::$db->escapeString($name);
            $value = App::$db->escapeString($value);
            $escapedParams[$name] = $value;
        }

        $whereArray = array();

        $whereArray[] = "point_str IS NOT NULL";

        $time = time();

        if(isset($escapedParams['time'])){
            if($escapedParams['time'] == 0){
                $lastFutureTime = $time + MAX_FURURE_HOURS * 3600;
            }else{
                $lastFutureTime = $time;
            }

            $whereTime = $time - 3600 * (int)$escapedParams['time'];
            $whereArray[] = sprintf("`unixtimestamp` > %s", $whereTime);
        }else{
            $lastFutureTime = $time + MAX_FURURE_HOURS * 3600;

            $firstPastTime = $time - MAX_PAST_HOURS * 3600;
            $whereArray[] = sprintf("`unixtimestamp` > %s", $firstPastTime);
        }
        $whereArray[] = sprintf("`unixtimestamp` < %s", $lastFutureTime);

        $eventConditions = $this->getEventConditions($escapedParams);
        $whereArray = array_merge($whereArray, $eventConditions);

        if(isset($escapedParams['state'])){
            $whereArray[] = sprintf("`state` = '%s'", $escapedParams['state']);
        }

        $where = implode(' AND ', $whereArray);

        $sql = sprintf("SELECT *
                       FROM `%s`
                       WHERE %s",
                       $this->table, $where);

        $rows = App::$db->getRows($sql);

        return $rows;
    }

    public function getGroups($params=array()){
        $escapedParams = array();
        foreach($params as $name=>$value){
            $name = App::$db->escapeString($name);
            $value = App::$db->escapeString($value);
            $escapedParams[$name] = $value;
        }

        $time = time();

        if(isset($escapedParams['time'])){
            if($escapedParams['time'] == 0){
                $lastFutureTime = $time + MAX_FURURE_HOURS * 3600;
            }else{
                $lastFutureTime = $time;
            }

            $whereTime = $time - 3600 * (int)$escapedParams['time'];
            $pastTimeCondition = sprintf("`unixtimestamp` > %s", $whereTime);
        }else{
            $lastFutureTime = $time + MAX_FURURE_HOURS * 3600;

            $firstPastTime = $time - MAX_PAST_HOURS * 3600;
            $pastTimeCondition = sprintf("`unixtimestamp` > %s", $firstPastTime);
        }
        $futureTimeCondition = sprintf("`unixtimestamp` < %s", $lastFutureTime);

        //print $time;
        $sqlParts = array();

        $statesWhereArray = array();
        $statesWhereArray[] = $futureTimeCondition;
        $statesWhereArray[] = 'point_str IS NOT NULL';
        $statesWhereArray[] = $pastTimeCondition;
        if($escapedParams){
            if(isset($escapedParams['state'])){
                $statesWhereArray[] = sprintf("`state` = '%s'",
                                              $escapedParams['state']);
            }

            $eventConditions = $this->getEventConditions($escapedParams);
            $statesWhereArray = array_merge($statesWhereArray, $eventConditions);
        }
        $statesWhere = implode(' AND ', $statesWhereArray);
        $sqlParts['states'] = sprintf("SELECT 'states' as `feature`, `state` as `group`, count(`state`) as `number`
                                      FROM `%s`
                                      WHERE %s
                                      GROUP BY `state`", $this->table, $statesWhere);

        if(!isset($escapedParams['event']) || (isset($escapedParams['event']) && $escapedParams['event'] == 'traffic events')){
            $trafficWhereArray = array();
            $trafficWhereArray[] = $futureTimeCondition;
            $trafficWhereArray[] = 'point_str IS NOT NULL';
            $trafficWhereArray[] = "`event`='traffic'";

            if(isset($escapedParams['state'])){
                $trafficWhereArray[] = sprintf("`state` = '%s'", $escapedParams['state']);
            }

            $trafficWhereArray[] = $pastTimeCondition;

            $trafficWhere = implode(' AND ', $trafficWhereArray);
            $sqlParts['traffic'] = sprintf("SELECT 'events' as `feature`, 'traffic events' as `group`, count(`event`) as `number`
                                           FROM `%s`
                                           WHERE %s", $this->table, $trafficWhere);
        }

        if(!isset($escapedParams['event']) || (isset($escapedParams['event']) && ($escapedParams['event'] == 'bushfire incidents' ||
            $escapedParams['event'] == 'bushfire alerts' || $escapedParams['event'] == 'bushfire'))){
            $bushfireWhereArray = array();
            $bushfireWhereArray[] = $futureTimeCondition;
            $bushfireWhereArray[] = "`event`='bushfire'";
            $bushfireWhereArray[] = 'point_str IS NOT NULL';

            if(isset($escapedParams['event'])){
                if ($escapedParams['event'] == 'bushfire incidents'){
                    $bushfireWhereArray[] = "`type` = 'incident'";
                }else if ($escapedParams['event'] == 'bushfire alerts'){
                    $bushfireWhereArray[] = "`type` = 'alert'";
                }
            }

            if(isset($escapedParams['state'])){
                $bushfireWhereArray[] = sprintf("`state` = '%s'", $escapedParams['state']);
            }

            $bushfireWhereArray[] = $pastTimeCondition;

            $bushfireWhere = implode(' AND ', $bushfireWhereArray);
            $sqlParts['bushfire'] = sprintf("SELECT 'events' as `feature`, CONCAT('bushfire ', `type`, 's') as `group`, count(`event`) as `number`
                                            FROM `%s`
                                            WHERE %s
                                            GROUP BY `type`", $this->table, $bushfireWhere);
        }

        $intervals = array(0, 1, 2, 5, 12, 24);

        if(isset($escapedParams['time'])){
            $intervals = array((int)$escapedParams['time']);
        }

        $timeWhereArray = array();
        // This element will be used later
        $timeWhereArray[0] = null;
        // This element will be used later
        $timeWhereArray[1] = null;
        $timeWhereArray[] = 'point_str IS NOT NULL';
        if($escapedParams){
            if(isset($escapedParams['state'])){
                $timeWhereArray[] = sprintf("`state` = '%s'", $escapedParams['state']);
            }
            $eventConditions = $this->getEventConditions($escapedParams);
            $timeWhereArray = array_merge($timeWhereArray, $eventConditions);
        }

        foreach($intervals as $interval){
            $timeWhereArray[0] = sprintf('`unixtimestamp` > %s', $time - $interval * 3600);

            // Here we need future events.
            if($interval == 0){
                $timeWhereArray[1] = $futureTimeCondition;
            }else{
                $timeWhereArray[1] = sprintf("`unixtimestamp` < %s", $time);
            }

            $timeWhere = implode(' AND ', $timeWhereArray);

            $sqlParts[$interval] = sprintf("SELECT 'times' as `feature`, '%s' as `group`, count(`event`) as `number`
                                            FROM `%s`
                                            WHERE %s",
            $interval, $this->table, $timeWhere);
        }

        $sql = implode(' UNION ALL ', $sqlParts);

        //echo $sql;

        $rows = App::$db->getRows($sql);

        $topGroups = array('states' => array(),
                           'events' => array(),
                           'times' => array());

        //json_encode($rows);

        foreach($rows as $row){
            if($row['number'] > 0){
                $topGroups[$row['feature']][$row['group']] = $row['number'];
            }
        }

        foreach($topGroups as &$topGroup){
            ksort($topGroup);
        }

        return $topGroups;
    }

    public function getUpdated($event=null){

        if($event){
            $where = sprintf("event = '%s'", App::$db->escapeString($event));
        }else{
            $where = 1;
        }

        $sql = sprintf("SELECT *
                       FROM `%s`
                       WHERE posted=0 AND point_str AND %s",
                       $this->table, $where);

        return App::$db->getRows($sql);
    }

    public function setPosted($GUID, $alertKey=null,
                                            $alertDeliveryLocationKey=null){
        $setKeys = '';
        if(!($alertKey === 0)){
            if($alertDeliveryLocationKey === 0){
                throw new Exception("AlertDeliveryLocationKey === 0 for `$title`");
            }

            $setKeys = sprintf(" , alert_key=%s, delivery_location_key=%s",
                                            App::$db->escapeString($alertKey),
                            App::$db->escapeString($alertDeliveryLocationKey));
        }

        $sql = sprintf("UPDATE `%s`
                        SET `posted`=1%s
                        WHERE `guid`='%s'", $this->table, $setKeys,
                                             App::$db->escapeString($GUID));
        return App::$db->executeQuery($sql);
    }

    public function setPosted2($GUID, $alertKey=null,
                                            $alertDeliveryLocationKey=null){
        $setKeys = '';
        if(!($alertKey === 0)){
            if($alertDeliveryLocationKey === 0){
                throw new Exception("AlertDeliveryLocationKey === 0 for `$title`");
            }

            $setKeys = sprintf(" , alert_key2=%s, delivery_location_key2=%s",
                                            App::$db->escapeString($alertKey),
                            App::$db->escapeString($alertDeliveryLocationKey));
        }

        $sql = sprintf("UPDATE `%s`
                        SET `posted`=1%s
                        WHERE `guid`='%s'", $this->table, $setKeys,
                                             App::$db->escapeString($GUID));
        return App::$db->executeQuery($sql);
    }
}

