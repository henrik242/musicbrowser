<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.01 Transitional//EN" "http://www.w3.org/TR/html4/loose.dtd">
<!--
   This file is part of Music Browser.

   Music Browser is free software: you can redistribute it and/or modify
   it under the terms of the GNU General Public License as published by
   the Free Software Foundation, either version 3 of the License, or
   (at your option) any later version.

   Music Browser is distributed in the hope that it will be useful,
   but WITHOUT ANY WARRANTY; without even the implied warranty of
   MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
   GNU General Public License for more details.

   You should have received a copy of the GNU General Public License
   along with Music Browser.  If not, see <http://www.gnu.org/licenses/>.

   Copyright 2006-2008 Henrik Brautaset Aronsen
-->
<html>
<head>
  <title>%display_title% %display_path%</title>
  <meta http-equiv="Content-Type" content="text/html; charset=%charset%">
  <link rel="alternate" title="%rss_title%" href="%rss_url%" type="application/rss+xml">  
  <style type="text/css">
<!-- 
.header { background-color: #91AA9D; }
.footer { background-color: #91AA9D; font-size: 0.8em; }
th { background-color: #3E606F; color: #FCFFF5; }
a { color: #2222AA; }
.error { color: red; }
.apphome { text-decoration: none; }
a.apphome:hover { text-decoration: underline; }
.folder { font-size: 0.8em; }
.file { font-size: 0.8em; }
.cell { vertical-align: bottom; }
.odd { background-color: #FCFFF5; }
.even { background-color: #D1DBBD; }
-->
  </style>
</head>
<body>

  <p><div class=error>%error_msg%</div>
  <table align=center width="95%"><tr><td colspan=%columns%>
    <!-- header -->
    <table class=header width="100%"><tr><td width=%column_width%>
        %cover_image%
      </td><td align=center>
        <br><p>%top_path%</p><br>
    </td><td align=right width=%column_width%>
      %flash_player%
    </td></tr></table>
    <!-- end header -->
  </td></tr>
    %content%
  <tr><td>&nbsp;</td></tr><tr><td colspan=%columns%>
    <!-- footer -->
    <table class=footer height=10 width="100%"><tr><td width=%thumb_size%></td><td align=center>
        <form action="%folder_path%" method=post name=streamtype>%stream_options%</form>
      </td><td width=%thumb_size% align=right>
        <a class=apphome href="http://musicbrowser.sf.net/">musicbrowser.sf.net</a>
    </td></tr></table>
    <!-- end footer -->
  </td></tr></table>
</body></html>