<?php

/**
 *   This file is part of Music Browser.
 *
 *   Music Browser is free software: you can redistribute it and/or modify
 *   it under the terms of the GNU General Public License as published by
 *   the Free Software Foundation, either version 3 of the License, or
 *   (at your option) any later version.
 *
 *   Music Browser is distributed in the hope that it will be useful,
 *   but WITHOUT ANY WARRANTY; without even the implied warranty of
 *   MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 *   GNU General Public License for more details.
 *
 *   You should have received a copy of the GNU General Public License
 *   along with Music Browser.  If not, see <http://www.gnu.org/licenses/>.
 *
 *   Copyright 2006, 2007 Henrik Brautaset Aronsen
 */

$rootUrl = "http://" . $_SERVER['HTTP_HOST'] . "/mp3";
$rootPath = "/slottet/mp3";
$templateFile = "template.inc";

$musicBrowser = new MusicBrowser($rootUrl, $rootPath, $templateFile);
$musicBrowser->set_home("Hjem");
$musicBrowser->show_page();
exit(0);

class MusicBrowser {
  
  private $columns = 5;
  private $errorMsg = "";
  private $headingThreshold = 15;
  private $homeName = "Home";
  private $pathinfo, $rootPath, $rootUrl, $scriptName, $streamLib;
  private $suffixes = array("mp3", "ogg", "mp4", "m4a"); 
  private $streamType, $templateFile;
  
  /**
   * @param string $rootUrl Root URL
   * @param string $rootPath Root path on file system
   * @param string $templateFile The template file
   */  
  function __construct($rootUrl, $rootPath, $templateFile) {
    #ini_set("error_reporting", E_ALL);
    ini_set("display_errors", 0);
    require_once('streamlib.php');
    
    $this->rootUrl = $rootUrl;
    $this->rootPath = $rootPath;
    $this->templateFile = $templateFile;
    $this->streamLib = new StreamLib();
    $this->scriptName = $_SERVER["SCRIPT_NAME"];
    $this->pathinfo = $this->resolve_path($rootPath);
  }

  /**
   * Set name of root entry in header.
   */
  function set_home($homeName) {
    $this->homeName = $homeName;
  }

  /**
   * Display requested page.
   */
  function show_page() {
    $fullPath = $this->pathinfo['full'];
    
    # If streaming is requested, do it
    if ((is_dir($fullPath) || is_file($fullPath)) && isset($_GET['stream'])) {
      $this->stream_all($_GET['type']);
      exit(0);
    }

    # If the path is a file, download it
    if (is_file($fullPath)) {
      $this->streamLib->stream_file_auto($fullPath);
      exit(0);
    } 

    # List of files and folders
    $folder = $this->list_folder($fullPath);

    # Set stream type as pls or m3u from $_POST or $_COOKIE
    $this->streamType = $this->set_stream_type();

    # get all content for template
    $coverImage = $this->show_cover();
    $topPath = $this->show_header($folder['numfiles']);
    $content = $this->show_folder($folder['items']);
    $checked = $this->stream_selector();

    $search = array("/%top_path%/", "/%columns%/", "/%cover_image%/", "/%error_msg%/", 
                  "/%pls_checked%/", "/%m3u_checked%/", "/%content%/", "/%folder_path%/");
    $replace = array($topPath, $this->columns, $coverImage, $this->errorMsg, 
                   $checked['pls'], $checked['m3u'], $content, $_SERVER['REQUEST_URI']);

    $template = file_get_contents($this->templateFile);
    print preg_replace($search, $replace, $template);
    exit(0);
  }
  
  /**
   * Format music folder content as HTML.
   *
   * @return string Formatted HTML with folder content
   */
  private function show_folder(array $items) {

    $output = "";
    if (count($items) > 0) {
      $groupList = $this->group_items($items);
      
      foreach ($groupList as $first => $group) {
        $entry = "";
        if (count($groupList) > 1) {
          $entry .= "<tr><th colspan={$this->columns}>$first</th></tr>\n";
        }
        $rows = ceil(count($group) / $this->columns);
        for ($row = 0; $row < $rows; $row++) {
          $entry .= "<tr>";
          for ($i = 0; $i < $this->columns; $i++) {
            $cell = $row + ($i * $rows);
            $item = @ $group[$cell];
            $urlPath = rawurlencode("{$this->pathinfo['relative']}/$item");
            
            $entry .= '<td valign="top">';
            if (empty($item)) {
              $entry .= "&nbsp;";
            } elseif (is_dir("{$this->pathinfo['full']}/$item")) {
              # Folder link
              $item = htmlentities($item);
              $entry .= "<a href=\"{$this->scriptName}?path=$urlPath\">$item/</a>\n";
            } else {
              # File link
              $item = htmlentities($item);
              $entry .= "<a href=\"{$this->scriptName}?path=$urlPath\"><img 
                src=\"download.gif\" border=0 title=\"Download this song\" alt=\"[Download]\"></a>\n";
              $entry .= "<a title=\"Play this song\" "
                       ."href=\"{$this->scriptName}?path=$urlPath&amp;stream&amp;type={$this->streamType}\">$item</a>";
            }
            $entry .= "</td>\n";
          }
          $entry .= "</tr>\n";
        }      
        $output .= $entry;
      }
    }
    return $output;
  }

  /**
   * Group $items by initial, with a minimum amount in each group 
   * @see $this->headingThreshold
   */
  private function group_items(array $items) {
    natcasesort($items);
    $groupList = $group = array();
    $to = $from = "";
    foreach ($items as $item) {
      if (is_dir("{$this->pathinfo['full']}/$item") || 
          (is_file("{$this->pathinfo['full']}/$item") && $this->valid_suffix($item))) {
          
        $current = strtoupper($item{0});
        
        if (strlen($from) == 0) {
          $from = $current;
        }
        
        if ($to == $current || count($group) < $this->headingThreshold) {
          $group[] = $item;
        } else {
          $groupList = $this->add_group($groupList, $group, $from, $to);
          $group = array($item);
          $from = $current;
        }
        $to = $current;
      }
    }
    if (count($group) > 0) {
      $groupList = $this->add_group($groupList, $group, $from, $to);
    }
    return $groupList;
  }

  private function add_group(array $groupList, array $group, $from, $to) {
    if ($from == $to) {
      $groupList[$from] = $group;
    } else { 
      $groupList["$from&ndash;$to"] = $group;
    }
    return $groupList;  
  }

  /**
   * @return array Associative array with 'm3u' and 'pls', where one of the values are 'CHECKED'
   */
  private function stream_selector() {
    if ($this->streamType == "pls") {
      return array('pls' => 'CHECKED', 'm3u' => '');
    } else {
      return array('pls' => '', 'm3u' => 'CHECKED');
    }
  }

  /**
   * List folder content.
   * @return array An associative array with 'numfiles' (number of files only) and 'items' (all allowed file and folder names)
   */
  private function list_folder($fullPath) {
    $folderHandle = dir($fullPath);
    $items = array();
    $numFiles = 0;
    while (false !== ($entry = $folderHandle->read())) {
      if (!($entry{0} == ".")) {
        $items[] = $entry;
        if (is_file("$fullPath/$entry")) {
          $numFiles++;
        }
      }
    }
    $folderHandle->close();
    return array('numfiles' => $numFiles, 'items' => $items);
  }

  /**
   * Fetches streamtype from $_POST or $_COOKIE.
   * @return string streamtype as 'pls' or 'm3u'
   */
  private function set_stream_type() {
    $streamType = 'm3u';
    if (isset($_POST['streamtype'])) {
      if ($_POST['streamtype'] == 'pls') {
        $streamType = 'pls';
      }
      setcookie('streamtype', $streamType);
    } elseif (@ $_COOKIE['streamtype'] == 'pls') {
      $streamType = 'pls';
    }
    return $streamType;
  }

  /**
   * @return string Formatted HTML with cover image (if any)
   */
  private function show_cover() {
    
    $covers = array("cover.jpg", "Cover.jpg", "folder.jpg", "Folder.jpg", "cover.gif", "Cover.gif",
                  "folder.gif", "Folder.gif");
    $output = "";              
    foreach ($covers as $cover) {
      if (is_readable("{$this->pathinfo['full']}/$cover")) {
        $link = "{$this->scriptName}?path=" . urlencode("{$this->pathinfo['relative']}/$cover");
        $output .= "<a href=\"$link\"><img border=0 src=\"$link\" width=150 height=150 align=left></a>";
        break;
      }
    }
    return $output;
  }

  /**
   * @return string Formatted HTML with bread crumbs for folder
   */
  private function show_header($numfiles) {
    $path = $this->pathinfo['relative'];
    $parts = $this->explode_modified($path);
    
    if (count($parts) > 0) {
      $items = array("<b><a href=\"{$this->scriptName}?path=\">{$this->homeName}</a></b>");
    } else {
      $items = array("<b>{$this->homeName}</b>");
    }
    $encodedPath = "";
    for ($i = 0; $i < count($parts); $i++) {
      $encodedPath .= rawurlencode("/" . $parts[$i]);
      if ($i < count($parts) - 1) {
        $items[] = "<b><a href=\"{$this->scriptName}?path=$encodedPath\">{$parts[$i]}</a></b>\n";
      } else {
        $items[] = "<b>{$parts[$i]}</b>";
      }
    }
    $output = implode(" &raquo; ", $items);

    # Output "play all" if there are files in this folder
    if ($numfiles > 0) {
      $output .= "&nbsp;&nbsp;<a href=\"{$this->scriptName}?path=$encodedPath&amp;stream&amp;type={$this->streamType}\"><img 
        src=\"play{$this->streamType}.gif\" border=0 title=\"Play all songs in this folder as {$this->streamType}\"
        alt=\"Play all songs in this folder as {$this->streamType}\"></a>";
    }
    return $output;
  }

  /**
   * Checks if $entry has any of the $suffixes
   *
   * @return boolean True if valid.
   */
  private function valid_suffix($entry) {

    foreach ($this->suffixes as $suffix) {
      if (preg_match("/\." . $suffix . "$/i", $entry)) {
         return true;
      }
    }
    return false;
  }

  /**
   * Stream folder or file.
   */
  private function stream_all($type) {
    $fullPath = $this->pathinfo['full'];
    $name = pathinfo($fullPath, PATHINFO_BASENAME);
    $items = array();
    
    if (is_dir($fullPath)) {
      # $fullPath is a folder with mp3's
      $folderHandle = dir($fullPath);
      while (false !== ($entry = $folderHandle->read())) {
        if ($this->valid_suffix($entry)) {
          $items[] = "{$this->pathinfo['relative']}/$entry";
          continue;
        }
      }
      natcasesort($items);
      $folderHandle->close();
    } else {
      # $fullPath is an mp3  
      $items[] = $this->pathinfo['relative'];
    }

    $entries = array();
    foreach ($items as $item) {
      $entries[] = $this->entry_info($item);
    }

    if ($type == "m3u") {
      $this->streamLib->stream_m3u($entries, $name);
    } else {
      $this->streamLib->stream_pls($entries, $name);
    }
  }

  /**
   * Info for entry in playlist.
   */
  private function entry_info($item) {
    $parts = $this->explode_modified($item);
    $fullUrl = "";
    foreach ($parts as $part) {
       $fullUrl .= "/" . rawurlencode($part);
    }
    $name = preg_replace("/\.[a-z0-9]{1,4}$/i", "", implode(" - ", $parts));
    return array('title' => $name, 'url' => "{$this->rootUrl}?path=$fullUrl");
  }

  /**
   * As explode with / as delimiter, but trims slashes and returns array() instead array with empty element.
   */
  private function explode_modified($thePath) {
    $parts = explode("/", trim($thePath, "/"));
    if (count($parts) == 1 && strlen($parts[0]) == 0) {
      return array();
    } else {
      return $parts;
    }
  }

  /**
   * Add message to be displayed as error.
   */
  private function add_error($msg) {
    $this->errorMsg .= "$msg<br>\n";
  }

  /**
   * Try to resolve safe path.
   */
  private function resolve_path($rootPath) {
    $relPath = "";
    if (isset($_GET['path'])) {
      # Most iso-8859-1 letters, minus " and \
      $getPath = preg_replace("/[^\x20\x21\x23-\x7e\xa0-\xff]/", "", $_GET['path']);
      $getPath = preg_replace('/\\\/', "", $getPath);
      
      if (is_readable("$rootPath/$getPath")) {
        $relPath = $getPath;
      } else {
        add_error("The path <i>$getPath</i> is not readable.");
      }
    }

    $fullPath = "$rootPath/$relPath";

    # Avoid funny paths
    $realFullPath = realpath($fullPath);
    $realRootPath = realpath($rootPath);
    if ($realRootPath != substr($realFullPath, 0, strlen($realRootPath)) || !(is_dir($fullPath) || is_file($fullPath))) {
       $relPath = "";
       $fullPath = $rootPath;
    }
    return array('full' => $fullPath, 'relative' => $relPath, 'root' => $rootPath);
  }
}
?>