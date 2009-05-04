<?php
require_once dirname(__FILE__) . '/../src/musicbrowser.php';
require_once 'PHPUnit/Framework.php';

class PathTest extends PHPUnit_Framework_TestCase {

  protected function setUp() {
    chdir(dirname(__FILE__));
  }
  
  /**
   * @dataProvider constructorProvider
   */
  public function testConstructor($rootPath, $securePath, $getPath, $root, $full, $relative) {
    if ($getPath !== null) {
      $_GET['path'] = $getPath;
    }
    $path = new Path($rootPath, $securePath);

    $this->assertEquals($root, $path->root);
    $this->assertEquals($full, $path->full);
    $this->assertEquals($relative, $path->relative);
  }

  public function constructorProvider() {
    $cwd = dirname(__FILE__);
    return array(
      array("does/not/exist", false, null, null, null, null),
      array("../src", false, null, "../src", "../src/", ""),
      array("", false, null, $cwd, "$cwd/", ""),
      array("", false, "does/not/exist", $cwd, "$cwd/", ""),
      array("", false, "../src", $cwd, "$cwd/", ""),
      array("../test", false, "test_folder", "../test", "../test/test_folder", "test_folder"),
      array("", false, "test_folder", $cwd, "$cwd/test_folder", "test_folder"),
      array("", false, "../te\x5cst_f%5colder/..", $cwd, "$cwd/test_folder", "test_folder"),
    );
  }
}

?>