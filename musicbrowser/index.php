<?php
require('streamlib.php');

$config = array(
  # Where your music is available on the file system (e.g. "/mnt/my_music/mp3")
  # Leave empty to use the current directory  (NB! This will 
  # also enable fwd/rwd in players like WinAmp.)
  'path' => "",

  # Public URL for this script. This URL will be used in the playlist files (.m3u/.pls).
  # Leave empty to auto detect.
  'url' => "",
  
  # The template file
  'template' => "template.inc",
  
  # array of allowed music file extensions in your collection
  'fileTypes' =>  array("mp3", "ogg"),
  
  # Name of root entry in header
  'homeName' => "Music Browser",

  # Maximum number of entries in a playlist
  'maxPlaylistSize' => 100,

  # Which play modes to enable, and the order they are shown in.
  # May be one or more of FLASH, M3U, PLS, ASX, SLIM and SERVER.  NB! SLIM and SERVER may be 
  # disabled for remote users by the allowLocal setting even if they are enabled here.
  'enabledPlay' => array(FLASH, M3U, PLS, ASX, SLIM, SERVER), 
  
  # Slimserver URL.  The root URL for slimserver "Music folder" streaming, e.g.
  #  'slimserverUrl' => "http://myserver:9000".  Leave blank to disable.
  'slimserverUrl' => "",
  
  # Slimserver player ID. Either a MAC address or IP number.  See Player Settings - Player Information 
  # in the slimserver web interface, e.g. "12:34:8A:34:DE:11";
  #'slimserverPlayer' => '81.191.140.50',
  'slimserverPlayer' => "",

  # Array of regular expression (regexp) matches for hosts that are allowed to use 
  # server playback and slimserver playback, e.g.
  #  'allowLocal' => array("/^10\.0\.0\./")
  # Leave as array() to disable.
  'allowLocal' => array(),
   
  # Array of regular expression (regexp) matches for files/directories to hide
  'hideItems' => array("/^lost\+found/", "/^\./"),
  
  # Play music on the server.  Full path to player with options, e.g.
  #  'serverPlayer' => "killall madplay; /usr/bin/madplay",
  # Leave blank to disable.
  'serverPlayer' => "",

  # Filename character set.  Usually utf-8 or iso-8859-1.
  'charset' => "iso-8859-1",

  # Fetch and show cover images inside folders when listing folders?  
  'folderCovers' => false,

  # Width of flash player
  'columnWidth' => 300,
    
  # Set to true to display PHP errors and notices.  Should be set to false.
  'debug' => false,
);

$musicBrowser = new MusicBrowser($config);
$musicBrowser->show_page();
?>