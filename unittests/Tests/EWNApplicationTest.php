<?php
# error reporting
ini_set('display_errors',1);
//error_reporting(E_ALL|E_STRICT);
error_reporting(E_ALL);

# include TestRunner
require_once 'PHPUnit/TextUI/TestRunner.php';

//require_once realpath(dirname(__FILE__) . '/../../lib/EventFeedAbs.php');
//require_once realpath(dirname(__FILE__) . '/../../lib/Bushfire.php');
//require_once realpath(dirname(__FILE__) . '/../../lib/FeedsExceptions.php');
require_once realpath(dirname(__FILE__) . '/../../lib/KLogger.php');
require_once realpath(dirname(__FILE__) . '/../../lib/htmlpurifier/HTMLPurifier.auto.php');
require_once realpath(dirname(__FILE__) . '/../../lib/Db.php');
require_once realpath(dirname(__FILE__) . '/../../lib/EWNApplication.php');


# our test class
class EWNApplicationTest extends PHPUnit_Framework_TestCase{
    public function setUp(){
        // Most Verbose - 1 (DEBUG), ERROR - 4
        // $logger = new KLogger ('php://stderr', 4);

        $config_path = realpath(dirname(__FILE__) . '/../../config/config.php');
        $config = require($config_path);

        $this->ewnApp = new EWNApplication($config);
    }

    public function test01(){
        $requestData = array(

            array(
                0=>1,
                1=>'Hello 1',
                2=>'NSW',
                3=>'Very bad',
                4=>'fire',
                5=>'daada',
                6=>1111111111,
                7=>'NULL',
                8=>'NULL',
                9=>'NULL',
                10=>'NULL',
                11=>'111',
                12=>'bushfire'
            ),

            array(
                0=>2,
                1=>'Hello 2',
                2=>'NSW',
                3=>'Very bad',
                4=>'fire',
                5=>'daada',
                6=>1111111111,
                7=>'NULL',
                8=>'NULL',
                9=>'NULL',
                10=>'NULL',
                11=>'111',
                12=>'bushfire'
            ),

            array(
                0=>3,
                1=>'Hello 3',
                2=>'NSW',
                3=>'Very bad',
                4=>'fire',
                5=>'daada',
                6=>1111111111,
                7=>'NULL',
                8=>'NULL',
                9=>'NULL',
                10=>'NULL',
                11=>'111',
                12=>'bushfire'
            )
        );

        print $this->ewnApp->getExistingRecordsIds($requestData);
    }

    public function tearDown(){
    }
}

# run the test
$suite = new PHPUnit_Framework_TestSuite('EWNApplicationTest');
PHPUnit_TextUI_TestRunner::run($suite);
?>
