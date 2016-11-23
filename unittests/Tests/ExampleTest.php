<?php
# error reporting
ini_set('display_errors',1);
//error_reporting(E_ALL|E_STRICT);
error_reporting(E_ALL);

# include TestRunner
require_once 'PHPUnit/TextUI/TestRunner.php';

# our test class
class ExampleTest extends PHPUnit_Framework_TestCase
{
    public function testOne()
    {
        $this->assertTrue(FALSE);
    }
    public function testTwo()
    {
        $this->assertTrue(TRUE);
    }
}

# run the test
$suite = new PHPUnit_Framework_TestSuite('ExampleTest');
PHPUnit_TextUI_TestRunner::run($suite);
?>
