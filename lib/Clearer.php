<?php

class Clearer{
    private $config = null;

    public function __construct($db, $config){
        $this->db = $db;
        $this->config = $config;
    }
    public function clear(){

        $clearBeforeTimeStamp = time() - $this->config['clear_before_hours'] * 60 * 60;

        $sql = "DELETE
                FROM  `" . $this->config['db']['base'] . "`.`" . $this->config['db']['incidents_table'] . "`
                WHERE `unixtimestamp` < $clearBeforeTimeStamp";
        $this->db->executeQuery($sql);

        $sql = "DELETE
                FROM  `" . $this->config['db']['base'] . "`.`" . $this->config['db']['geocoder_cache_table'] . "`
                WHERE `insert_time` < $clearBeforeTimeStamp";
        $this->db->executeQuery($sql);

    }
}