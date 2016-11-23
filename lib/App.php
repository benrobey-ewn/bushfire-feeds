<?php
class App{

    public static $config = null;
    public static $db = null;
    public static $logger = null;

    private function __construct() {}

    private function __clone(){}

    public static function createApp($configPath){
        self::$config = require($configPath);

        self::$db = new Db(self::$config['db']['host'],
        self::$config['db']['user'],
        self::$config['db']['pass'],
        self::$config['db']['base']);
        self::$logger = new KLogger (self::$config['logPath'],
        self::$config['logLevel']);
    }
}