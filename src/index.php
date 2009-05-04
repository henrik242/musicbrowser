<?php
require('streamlib.php');

$config = array(
  # Where your music is available on the file system (e.g. "/mnt/my_music/mp3")
  # Leave empty to use the current directory.  Using the current directory will 
  # also enable fwd/rwd in players like Winamp.
  'path' => "",

  # Public URL for this script. This URL will be used in the playlist files (.m3u/.pls).
  # Leave empty to auto detect.
  'url' => "",
  
  # The template file
  'template' => "template.inc",
  
  # array of allowed music file extensions
  'fileTypes' =>  array("mp3"),
  
  # Name of root entry in header
  'homeName' => "Music Browser",

  # Maximum number of entries in a playlist
  'maxPlaylistSize' => 100,

  # Which play modes to enable, and the order they are shown in.
  # May be one or more of FLASH, M3U, PLS, ASX, SLIM and SERVER.  SLIM and SERVER may be 
  # disabled for remote users by the allowLocal setting even if they are enabled here.
  'enabledPlay' => array(FLASH, M3U, PLS, ASX, XSPF, SLIM, SERVER, XBMC),
  
  # Slimserver URL.  The root URL for slimserver "Music folder" streaming, e.g.
  #  'slimserverUrl' => 'http://myserver:9000'.  Leave blank to disable.
  'slimserverUrl' => "",
  
  # Slimserver player ID. Either a MAC address or IP number.  See Player Settings - Player Information 
  # in the slimserver web interface, e.g. "12:34:8A:34:DE:11";
  #'slimserverPlayer' => '81.191.140.50',
  'slimserverPlayer' => "",

  # Url to the XBMC Media Center, e.g. 'xbmcUrl' => "http://10.0.0.20",
  'xbmcUrl' => "",
  
  # Base path within XBMC, e.g. 'smb://my.server/music/' or 'F:\\music'
  'xbmcPath' => "",

  # Array of regular expression (regexp) matches for hosts that are allowed to use 
  # server playback and slimserver playback, and to rebuild the search db, e.g.
  #  'allowLocal' => array("/^10\.0\.0\./")
  # Set to array() to disable.
  'allowLocal' => array("/^10\.0\.0\./"),
   
  # Array of regular expression (regexp) matches for files/directories to hide
  'hideItems' => array("/^lost\+found/", "/^\./"),
  
  # Play music on the server.  Full path to player with options, e.g.
  #  'serverPlayer' => "killall madplay; /usr/bin/madplay",
  # Leave blank to disable.
  'serverPlayer' => "",

  # Filename character set.  Usually utf-8 or iso-8859-1.
  'charset' => "utf-8",

  # Fetch and show cover images inside folders when listing folders?  This might be slow!
  'folderCovers' => false,

  # 'securePath' => false allows symlinks to folders outside the 'path' folder, but might 
  # be less secure
  'securePath' => true,    
  
  # Location of the search db text file. Leave empty to disable search.
  'searchDB' => "/tmp/musicbrowser-searchdb.txt",

  # Number of columns
  'columns' => 5,
  
  # Cover thumbnail size
  'thumbSize' => 100,
  
  # Set to true to display PHP errors and notices.  Should be set to false.
  'debug' => false,
);

$musicBrowser = new MusicBrowser($config);
$musicBrowser->show_page();
?>