<?php
require_once dirname(__FILE__) . '/../src/streamlib.php';
require_once 'PHPUnit/Framework.php';

class ItemTest extends PHPUnit_Framework_TestCase {

  private $item;
  
  protected function setUp() {
    chdir(dirname(__FILE__));
    $_SERVER['HTTP_HOST'] = 'test.host';
    $_SERVER['SCRIPT_NAME'] = "/our/script/name.php";
    $this->item = $this->getItem("test_folder", "æåe_test & file.mp3");
  }

  private function getItem($getPath, $name, $rootPath = "", $showFolderCover = false, $streamtype = "flash") {
    $url = new Url("http://testing/123/");
    $_GET['path'] = $getPath;
    $path = new Path($rootPath, false);
    return new Item($name, "utf-8", $showFolderCover, $path, $url, $streamtype, "true");
  }

  public function testUrlPath() {
    $this->assertEquals("test_folder/%C3%A6%C3%A5e_test+%26+file.mp3", $this->item->url_path());
  }

  public function testSortIndex() {
    $this->assertEquals("Æå", $this->item->sort_index());
  }

  public function testJsUrlPath() {
    $this->assertEquals("test_folder/%25C3%25A6%25C3%25A5e_test+%2526+file.mp3", $this->item->js_url_path());
  }

  public function testDisplayItem() {
    $item = $this->getItem("", "æåe_test & file, a veryveryveryveryveryveryveryvery long file name indeed.mp3");
    $this->assertEquals("æåe t est  & file, a veryveryveryveryver yveryveryvery long file name indeed.mp3",
      $item->display_item());
  }

  /**
   * @dataProvider showLinkProvider
   */
  public function testShowLink($getPath, $name, $result) {
    if (!defined('STREAMTYPE')) {
      define('STREAMTYPE', 'flash');
    }
    $item = $this->getItem($getPath, $name);
    $this->assertEquals($result, $item->show_link());
  }

  public function showLinkProvider() {
    return array(
      array("", "", "&nbsp;"),
      array("test_folder", "heiæøå", '<a href="test_folder/hei%C3%A6%C3%B8%C3%A5"><img src="download.gif" border=0 title="Download this song" alt="[Download]"></a>
<a class=file title="Play this song" href="javascript:play(\'test_folder/hei%25C3%25A6%25C3%25B8%25C3%25A5\')">heiæøå</a>
'),
      array("", "test_folder", '<a title="Play files in this folder" href="javascript:play(\'test_folder\')"><img border=0 alt="|&gt; " src="play.gif"></a>
<a class=folder href="javascript:changeDir(\'test_folder\')">test_folder</a>
'),
    );
  }

  public function testDirLink() {
    $this->assertEquals('<a title="Play files in this folder" href="javascript:play(\'test_folder/%25C3%25A6%25C3%25A5e_test+%2526+file.mp3\')"><img border=0 alt="|&gt; " src="play.gif"></a>
<a class=folder href="javascript:changeDir(\'test_folder/%25C3%25A6%25C3%25A5e_test+%2526+file.mp3\')">æåe_test & file.mp3</a>
', $this->item->dir_link());
  }

  public function testFileLink() {
    $this->assertEquals('<a href="test_folder/%C3%A6%C3%A5e_test%20%26%20file.mp3"><img src="download.gif" border=0 title="Download this song" alt="[Download]"></a>
<a class=file title="Play this song" href="javascript:play(\'test_folder/%25C3%25A6%25C3%25A5e_test+%2526+file.mp3\')">æåe_test & file.mp3</a>
', $this->item->file_link());
  }

  public function testDirectLink() {
    $item = $this->getItem("test_folder", "æåe_test & file.mp3");
    $this->assertEquals('tralala%C3%A6%C3%B8%C3%A5', $item->direct_link("tralalaæøå"));
  }

  public function testNonDirectLink() {
    $item = $this->getItem("test_folder", "æåe_test & file.mp3", "../test");
    $this->assertEquals('name.php?path=tralala%C3%A6%C3%B8%C3%A5', $item->direct_link("tralalaæøå"));
  }

  public function testShowFolderCover() {
    $item = $this->getItem("", "test_folder", "", true);
    $this->assertEquals('<a href="javascript:changeDir(\'test_folder\')"><img src="test_folder/folder.gif" border=0 width=100 height=100 alt=""></a><br>',
      $item->show_folder_cover("test_folder"));
  }

  public function testDontShowFolderCover() {
    $item = $this->getItem("", "test_folder", "", false);
    $this->assertEquals('', $item->show_folder_cover("test_folder"));
  }
}
?>
