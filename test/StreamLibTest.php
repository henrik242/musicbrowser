<?php
require_once dirname(__FILE__) . '/../src/musicbrowser.php';
require_once 'PHPUnit/Framework.php';

class StreamLibTest extends PHPUnit_Framework_TestCase {

  protected $streamlib;

  protected function setUp() {
    $this->streamlib = new StreamLib;
  }

  public function testPlaylist_asx() {
    $this->markTestIncomplete('This test has not been implemented yet.');
  }

  public function testPlaylist_pls() {
    $this->markTestIncomplete('This test has not been implemented yet.');
  }

  public function testPlaylist_m3u() {
    $this->markTestIncomplete('This test has not been implemented yet.');
  }

  public function testPlaylist_rss() {
    $this->markTestIncomplete('This test has not been implemented yet.');
  }

  public function testPlaylist_xspf() {
    $this->markTestIncomplete('This test has not been implemented yet.');
  }

  public function testStream_show_entries() {
    $this->markTestIncomplete('This test has not been implemented yet.');
  }

  public function testStream_content() {
    $this->markTestIncomplete('This test has not been implemented yet.');
  }

  public function testStream_mp3() {
    $this->markTestIncomplete('This test has not been implemented yet.');
  }

  public function testStream_gif() {
    $this->markTestIncomplete('This test has not been implemented yet.');
  }

  public function testStream_jpeg() {
    $this->markTestIncomplete('This test has not been implemented yet.');
  }

  public function testStream_png() {
    $this->markTestIncomplete('This test has not been implemented yet.');
  }

  public function testStream_file_auto() {
    $this->markTestIncomplete('This test has not been implemented yet.');
  }

  public function testStream_file() {
    $this->markTestIncomplete('This test has not been implemented yet.');
  }

  public function testReadfile_chunked() {
    $this->markTestIncomplete('This test has not been implemented yet.');
  }
}
?>
