## About

Music Browser is a light-weight web-based browser and streamer for you music collection. It is runs on most operating systems, and is light enough to run flawlessly on NAS devices like the Linksys NSLU2 or Freecom FSG3.

## Features

*   Streams folders recursively as m3u, pls or asx playlists 
*   All folders are available as podcasts/rss 
*   Cover images (folder.jpg, cover.jpg etc.) are shown in folders and podcasts if available. 
*   Embedded flash player ([JW FLV Media Player][6]) 
*   Basic playback via [Slimserver][7] & [XBMC Media Center][8] 
*   Very basic playback via sound card card on the server 
*   Configurable, but no configuration is needed 
*   Host access control from slimserver and server side playback. 
*   Small size (70K download) 
*   Open source ([GPL][9]): Feel free to edit the program to fit your needs. 

## Requirements

*   A webserver with PHP4.2%2B
*   Most platforms (Windows, Linux, BSD, MacOSX, ...) are supported
*   A 200mhz CPU and 32MB of RAM works great

## Installation

1.  Extract the distribution archive to a web accessible path (e.g. /var/www/musicbrowser)
2.  Optional: Edit *path* in index.php to match your site (e.g. /mnt/media/mp3)
3.  Enjoy your music collection through a browser (e.g. http://myserver/musicbrowser)

## Templates 

Extra templates are available as optional downloads from the [Git repository][10]. Just replace your current template.inc with one of the new ones. 

## Troubleshooting

*   *I only get a blank screen!*  
    Set 'debug' => true in index.php, you might see some helpful info. Also look into your web server's error.log.
*   * Only 130 seconds of my songs are played!*  
    Set max\_execution\_time = 0 in php.ini.
*   *I need password controlled access*  
    Your web server can provide this. Google for ".htaccess" if you are using Apache, and "auth.backend.plain.userfile" if you are using Lighttpd.
*   *Unicode characters (asian etc.) look weird*  
    Try replacing "iso-8859-1" with "utf-8" in the 'charset' configuration in index.php 
*   *PLS playlists with unicode names (asian etc.) doesn't play in WinAmp*  
    Somehow WinAmp doesn't like unicode PLS playlist names, I assume it is a bug. Switch to M3U playlist, WinAmp will read this as it should. Also, PLS playlists will play just fine when opened in iTunes. 
*   *I get "Could not open URL - Error reaching slimserver" when trying to play on Squeezebox*  
    Try opening the URL from the error message into a new browser window. You might see that you must lower your CSRF Protection Level in SqueezeCenter » Settings » Advanced » Security.

## Verified configurations

*   Linksys NSLU2 / Unslung, lighttpd, fastcgi, PHP4.2 
*   Linutop V2 / Debian, Apache 2.2, PHP5.1 
*   D-Link DNS323 or DSMG600 / fun_plug, lighttpd, fastcgi, PHP4.4 
*   Windows 2000 Server, Apache 2.2.3, PHP 5 
*   Ubuntu, Apache 2.2.3, PHP 5.2.1 
*   FreeNAS 0.69, FreeBSD 6.4, Lighttpd 1.4.20, PHP 5.2.8 


 [6]: http://www.jeroenwijering.com/?item=JW_FLV_Media_Player
 [7]: http://slimdevices.com
 [8]: http://xbmc.org/
 [9]: http://www.gnu.org/copyleft/gpl.html
 [10]: https://github.com/henrik242/musicbrowser/tree/master/templates
