<?php
$config = array(
  # Where your music is available on the file system
  'path' => "/mnt/my_music/",

  # Public URL for this script.  Leave blank to auto-detect.
  # This URL will be used in the playlist files (.m3u/.pls).
  'url' => "",
  
  # The template file
  'template' => "template.inc",
  
  # array of allowed music file extensions
  'fileTypes' =>  array("mp3", "ogg", "mp4", "m4a"),
  
  'homeName' => "Home",

  # Maximum number of entries in a playlist
  'maxPlaylistSize' => 100,
    
  # Play music on the server.  Full path to player with options.  Leave blank to disable.
  'player' => "",
);

require('streamlib.php');
$musicBrowser = new MusicBrowser($config);
$musicBrowser->show_page();
?>