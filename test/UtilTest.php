<?php
require_once dirname(__FILE__) . '/../src/musicbrowser.php';
require_once 'PHPUnit/Framework.php';


class UtilTest extends PHPUnit_Framework_TestCase {

  private $util;

  protected function setUp() {
    $this->util = new Util();
  }

  public function testJsUrl() {
    $this->assertEquals("http://testing/123/%2526%252Ahei", $this->util->js_url("http://testing/123/%26%2Ahei"));
  }

  public function testPathEncode() {
    $this->assertEquals("he+isann/%C3%A6hei", $this->util->path_encode("/he isann/æhei"));
  }

  /**
   * @dataProvider playUrlProvider
   */
  public function testPlayUrl($streamtype, $shuffle, $expected) {
    $this->assertEquals($expected, $this->util->play_url("some/path", $streamtype, $shuffle));
  }

  public function playUrlProvider() {
    return array(
      array("flash", "true", "javascript:play('some/path')"),
      array("xbmc", "true", "javascript:play('some/path')"),
      array("m3u", "true", "index.php?path=some/path&amp;shuffle=true&amp;stream=m3u"),
      array("pls", "false", "index.php?path=some/path&amp;shuffle=false&amp;stream=pls"),
    );
  }

  public function testStrip() {
    $this->assertEquals("testtest\x5btest", $this->util->strip("test\\test\x5b\x5ctest"));
  }

  public function testWordWrap() {
    $this->assertEquals("abcdefghijklmnopqrstuvwxyzæøåabcdefghiæø åæøåæøåjklmnopqrstuvwxyzæøåabcdefghijklm nopqrstuvwxyz",
      $this->util->word_wrap("abcdefghijklmnopqrstuvwxyzæøåabcdefghiæøåæøåæøåjklmnopqrstuvwxyzæøåabcdefghijklmnopqrstuvwxyz", "utf-8"));
  }

  public function testConvertToUtf8() {
    $this->assertEquals("Æ", $this->util->convert_to_utf8("\xC6", "iso-8859-1"));
    $this->assertEquals("Æ", $this->util->convert_to_utf8("Æ", "utf-8"));
  }

  public function testPathinfoBasename() {
    $this->assertEquals("file.mp3", $this->util->pathinfo_basename("/testing/123/file.mp3"));
  }
}
?>
