<?php

define('MAX_FURURE_HOURS', 72);
define('MAX_PAST_HOURS', 24);

class Incident{
    private $table = null;
    public function __construct(){
        $this->table = App::$config['db']['incidents_table'];
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
        $lastFutureTime = $time + MAX_FURURE_HOURS * 3600;
        $whereArray[] = sprintf("`unixtimestamp` < %s", $lastFutureTime);
        if(isset($escapedParams['time'])){
            $whereTime = $time - 3600 * (int)$escapedParams['time'];
            $whereArray[] = sprintf("`unixtimestamp` > %s", $whereTime);
        }else{
            $firstPastTime = $time - MAX_PAST_HOURS * 3600;
            $whereArray[] = sprintf("`unixtimestamp` > %s", $firstPastTime);
        }

        if(isset($escapedParams['event'])){
            $event = $escapedParams['event'];
            if($event == 'traffic events'){
                $whereArray[] = "`event` = 'traffic'";
            }else if ($event == 'bushfire incidents'){
                $whereArray[] = "`event` = 'bushfire'";
                $whereArray[] = "`type` = 'incident'";
            }else if ($event == 'bushfire alerts'){
                $whereArray[] = "`event` = 'bushfire'";
                $whereArray[] = "`type` = 'alert'";
            }
        }

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
        $lastFutureTime = $time + MAX_FURURE_HOURS * 3600;
        $futureTimeCondition = sprintf("`unixtimestamp` < %s", $lastFutureTime);
        if(isset($escapedParams['time'])){
            $whereTime = $time - 3600 * (int)$escapedParams['time'];
            $pastTimeCondition = sprintf("`unixtimestamp` > %s", $whereTime);
        }else{
            $firstPastTime = $time - MAX_PAST_HOURS * 3600;
            $pastTimeCondition = sprintf("`unixtimestamp` > %s", $firstPastTime);
        }
        //print $time;
        $sqlParts = array();

        $statesWhereArray = array();
        $statesWhereArray[] = $futureTimeCondition;
        $statesWhereArray[] = 'point_str IS NOT NULL';
        $statesWhereArray[] = $pastTimeCondition;
        if($escapedParams){
            if(isset($escapedParams['state'])){
                $statesWhereArray[] = sprintf("`state` = '%s'", $escapedParams['state']);
            }
            if(isset($escapedParams['event'])){
                $event = $escapedParams['event'];
                if($event == 'traffic events'){
                    $statesWhereArray[] = "`event` = 'traffic'";
                }else if ($event == 'bushfire incidents'){
                    $statesWhereArray[] = "`event` = 'bushfire'";
                    $statesWhereArray[] = "`type` = 'incident'";
                }else if ($event == 'bushfire alerts'){
                    $statesWhereArray[] = "`event` = 'bushfire'";
                    $statesWhereArray[] = "`type` = 'alert'";
                }
            }
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
        $escapedParams['event'] == 'bushfire alerts'))){
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
        // We do not need future events in cumulative result.
        $timeWhereArray[1] = sprintf("`unixtimestamp` < %s", $time);
        $timeWhereArray[] = 'point_str IS NOT NULL';
        if($escapedParams){
            if(isset($escapedParams['state'])){
                $timeWhereArray[] = sprintf("`state` = '%s'", $escapedParams['state']);
            }
            if(isset($escapedParams['event'])){
                $event = $escapedParams['event'];
                if($event == 'traffic events'){
                    $timeWhereArray[] = "`event` = 'traffic'";
                }else if ($event == 'bushfire incidents'){
                    $timeWhereArray[] = "`event` = 'bushfire'";
                    $timeWhereArray[] = "`type` = 'incident'";
                }else if ($event == 'bushfire alerts'){
                    $timeWhereArray[] = "`event` = 'bushfire'";
                    $timeWhereArray[] = "`type` = 'alert'";
                }
            }
        }

        foreach($intervals as $interval){
            $timeWhereArray[0] = sprintf('`unixtimestamp` > %s', $time - $interval * 3600);

            // Here we need future events.
            if($interval == 0){
                $timeWhereArray[1] = $futureTimeCondition;
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
}

