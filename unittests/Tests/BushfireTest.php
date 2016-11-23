<?php
# error reporting
ini_set('display_errors',1);
//error_reporting(E_ALL|E_STRICT);
error_reporting(E_ALL);

# include TestRunner
require_once 'PHPUnit/TextUI/TestRunner.php';

require_once realpath(dirname(__FILE__) . '/../../lib/EventFeedAbs.php');
require_once realpath(dirname(__FILE__) . '/../../lib/Bushfire.php');
require_once realpath(dirname(__FILE__) . '/../../lib/FeedsExceptions.php');
require_once realpath(dirname(__FILE__) . '/../../lib/KLogger.php');

# our test class
class BushfireTest extends PHPUnit_Framework_TestCase{
    public function setUp(){
        // Most Verbose - 1 (DEBUG), ERROR - 4
        $logger = new KLogger ('php://stderr', 4);
        $this->bushfire = new Bushfire(null, null, null, null, $logger);
    }

    public function testJsonQLDAlertTimeStringToUnixtimestamp(){
        // Check conversion of time string to timestamp when supplied time string
        // is for the same year as current year.
        $timestamp = $this->bushfire->jsonQLDAlertTimeStringToUnixtimestamp(
                                          '17-Nov 21:31', '@1353756278');
        $this->assertEquals(1353151860, $timestamp);

        // Check conversion of time string to timestamp when supplied time string
        // is for the previous year.
        $timestamp = $this->bushfire->jsonQLDAlertTimeStringToUnixtimestamp(
                                          '17-Nov 21:31', '@1357023661');
        $this->assertEquals(1353151860, $timestamp);
    }
    public function tearDown(){}
}

# run the test
$suite = new PHPUnit_Framework_TestSuite('BushfireTest');
PHPUnit_TextUI_TestRunner::run($suite);
?>
