<?php
require_once('send_error_email.php');
class Db {

    private $host = null;
    private $user = null;
    private $pass = null;
    private $base = null;
    private $connection = null;

    public function __construct($host, $user, $pass, $base) {
        $this->host = $host;
        $this->user = $user;
        $this->pass = $pass;
        $this->base = $base;
    }

    private function connect(){
        $connection = @mysql_connect($this->host, $this->user, $this->pass);
        if(!$connection){
            mailError("Can't connect to Database Server.");
            throw new AppFeedException("Can't connect to Database Server");
            //Bushfire /traffic feed can't connect to db server error email
            
        }
        if(!mysql_select_db($this->base, $connection)){
            mailError("Can't connect to Database, but can connect to the Database Server.");
            throw new AppFeedException("Can't connect to Database");
            
            //Bushfire /traffic feed can't connect to database but can connect to the server error email.
        }
        $this->connection = $connection;
    }

    public function escapeString($string){
        if(!$this->connection){
            $this->connect();
        }
        return mysql_real_escape_string($string, $this->connection);
    }

    public function executeQuery ($sql){
        if(!$this->connection){
            $this->connect();
        }
        $result = mysql_query ($sql, $this->connection);
        if(!$result){
            throw new Exception('DB error:' . mysql_error($this->connection));
        }else{
            return $result;
        }
    }

    public function getValue($sql){
        $q=$this->executeQuery($sql);

        $row=mysql_fetch_array($q);

        $num=mysql_num_rows($q);

        if ($num==0){
            return false;
        }else{
            return $row[0];
        }
    }

    public function getValues($sql){
        //echo $sql.'<br />';
        //exit();
        $q = $this->executeQuery($sql);
        //echo $q;
        $values = array();
        while($row=mysql_fetch_array($q)){
            array_push($values,$row[0]);
        }
        return $values;
    }

    public function getLastInsertId(){
        $sql = "SELECT LAST_INSERT_ID()";
        return $this->getValue($sql);
    }

    public function getRow($sql){
        $q=$this->executeQuery($sql);
        $row=mysql_fetch_assoc($q);
        $num=mysql_num_rows($q);

        if ($num==0){
            return false;
        }else{
            return $row;
        }
    }

    public function getRows($sql){
        $q=$this->executeQuery($sql);
        $rows = array();
        while($row=mysql_fetch_assoc($q)){
            array_push($rows, $row);
        }
        return $rows;
    }

    public function getArrayRows($sql){
        $q=$this->executeQuery($sql);
        $rows = array();
        while($row = mysql_fetch_array($q, MYSQL_NUM)){
            array_push($rows, $row);
        }
        return $rows;
    }
}