<?php
require_once dirname(__FILE__) . '/../src/streamlib.php';
require_once 'PHPUnit/Framework.php';
 
class UrlTest extends PHPUnit_Framework_TestCase {

  protected function setUp() {
    $_SERVER['HTTP_HOST'] = 'test.host';
    $_SERVER['SCRIPT_NAME'] = "/our/script/name.php";
  }

  public function testConstructorWithSSL() {
    $_SERVER["HTTPS"] = "https";
    $url = new Url("");

    $this->assertEquals('https://test.host/our/script/name.php', $url->full);
    $this->assertEquals('https://test.host/our/script', $url->root);
    $this->assertEquals('name.php', $url->relative);
  }

  /**
   * @dataProvider constructorProvider
   */
  public function testConstructor($link, $full, $root, $relative) {
    $url = new Url($link);

    $this->assertEquals($full, $url->full);
    $this->assertEquals($root, $url->root);
    $this->assertEquals($relative, $url->relative);
  }

  public function constructorProvider() {
    return array(
      array("htttp://no/way", null, null, null),
      array(null, 'http://test.host/our/script/name.php', 'http://test.host/our/script', 'name.php'),
      array("http://testing/123/", 'http://testing/123/name.php', 'http://testing/123', 'name.php'),
    );
  }

}

?>