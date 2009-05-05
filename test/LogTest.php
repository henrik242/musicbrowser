<?php
require_once dirname(__FILE__) . '/../src/musicbrowser.php';
require_once 'PHPUnit/Framework.php';


class LoggerTest extends PHPUnit_Framework_TestCase {

    public function testLogPop() {
      $logger = new Logger();
      $logger->log("test");
      $this->assertEquals("test", $logger->pop());

      $logger->log("trall");
      $logger->log("heiæøå");
      $this->assertEquals("trall<br>\nheiæøå", $logger->pop());
    }
}
?>
