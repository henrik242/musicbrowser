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

   Copyright 2006, 2007 Henrik Brautaset Aronsen
-->
<html>
<head>
  <title>Music browser</title>
  <meta http-equiv="Content-Type" content="text/html; charset=iso-8859-1">
  <style type="text/css">
<!-- 
.topcolor { background-color: #EEEEBB; }
TH { background-color: #DDDDDD; }
.selector { font-size: 0.9em;  background-color: #DDDDDD; }
.error { color: red; }
.subtle { color: gray; font-size: 0.8em; text-decoration: none; }
a.subtle:hover { text-decoration: underline; }
-->
  </style>
</head>
<body>
  <p><div class=error>%error_msg%</div>
  <table align=center width="95%">
  <tr><td align=center colspan=%columns% class="topcolor">
    %cover_image%
    <br><p>%top_path%</p><br>
  </td></tr>
    %content%
  <tr><td>&nbsp;</td></tr>
  <tr><td colspan=%columns% class=selector align=center>
    <form action="%folder_path%" method="post" name="streamtype">%stream_options%</form>
  </td></tr>
  <tr><td colspan=%columns% align=right>
    <a class=subtle href="http://musicbrowser.sf.net/">musicbrowser.sf.net</a>
  </td></tr>
  </table>
</body></html>