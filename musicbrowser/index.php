<?php
$config = array(
  # Public URL for this script.  Leave blank to auto-detect.
  # This URL will be used in the playlist files (.m3u/.pls).
  'url' => "",
  
  # Where your music is available on the file system
  'path' => "/mnt/my_music/",

  # The template file
  'template' => "template.inc",
  
  # array of allowed music file extensions
  'fileTypes' =>  array("mp3", "ogg", "mp4", "m4a"),
  
  'homeName' => "Home",
  
  # Play music on the server.  Full path to player with options.  Leave blank to disable.
  'player' => "",
);

require('streamlib.php');
$musicBrowser = new MusicBrowser($config);
$musicBrowser->show_page();
?>