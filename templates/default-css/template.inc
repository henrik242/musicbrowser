<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.01 Transitional//EN" "http://www.w3.org/TR/html4/loose.dtd">
<!--
   This file is part of Music Browser.

   Music Browser is free software: you can redistribute it and/or modify
   it under the terms of the GNU General Public License as published by
   the Free Software Foundation, either version 3 of the License, or
   any later version.

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
  <title></title>
  <meta http-equiv="Content-Type" content="text/html; charset=utf-8">
  <style type="text/css">
<!--
#header { background-color: #91AA9D; position:fixed; top: 0px; left: 0px; padding: 0px; height: 150px; width: 100%; z-index:10; }
#footer { background-color: #91AA9D; font-size: 0.8em; overflow: auto; width: 100%; height: 25px; 
          position: fixed; bottom: 0; left: 0; padding: 1px 0px; }
#leftheader { text-align: left; margin-right: 500px; height: 150px; overflow: auto; padding: 0px; }
#rightheader { text-align: right; padding: 0px; float: right;	position: fixed; top: 0; right: 0; }
#leftfooter { text-align: left; float: left; }
#rightfooter { text-align: right; float: right; }
#content { text-align: left; top: 150px; width: 100%; position: relative; padding-bottom: 25px; }
#breadcrumb { text-align: left; margin: 10px 10px 10px 10px; }
#cover { margin: 0px 10px 10px 10px; }
#box { display: block; color: black; position: fixed; z-index: 100; padding: 1em; top: 30px; left: 70px; }

body { padding: 0px; margin: 0px; }
.box { padding: 10px; background-color: #C0C0C0; }
.boxbutton { padding: 1px 5px; vertical-align: top; background-color: gray; color: white;  }
.boxbutton:link { text-decoration: none;  }
th { background-color: #3E606F; color: #FCFFF5; text-align: center; }
a { color: #2222AA; text-decoration: none; }
a:hover { text-decoration: underline; }
form { display: inline; }
.folder { font-size: 0.8em; }
.file { font-size: 0.8em; }
.cell { vertical-align: bottom; text-align: left; }
.odd { background-color: #FCFFF5; }
.even { background-color: #D1DBBD; }
.title { font-weight: bold; }
.path { font-weight: bold; }
.loading { text-decoration: blink; text-align: center; font-style: italic; }
.error { color: red; }
.feet { text-decoration: none; padding-right: 10px; padding-left: 10px; }
.searchresult {list-style-type: none; padding-top: 10px; padding-right: 20px; margin: 0px; }
	-->
  </style>

<!--[if lte IE 6]>
<style type="text/css">
  #rightheader { overflow: visible; display: inline; }
  #leftheader { overflow: visible; display: inline; }
  #content { top: 0px; padding-bottom; 0px; }
  #header { position: relative; }
  #box { position: relative; display: inline; }
  #cover { display: inline;
  #footer { position: relative; overflow: visible; }
</style>
<![endif]-->

  <script type="text/javascript" src="musicbrowser.js"></script>
</head>
<body>

<div id="box"></div>
<div id="header">
	<div id="leftheader">
		<div id="breadcrumb"></div>
		<div id="cover"></div>
	</div>
	<div id="rightheader">%flash_player%</div>
</div>

<div id="content">
    <div class="loading">initializing...</div>
</div>

<div id="footer">
   <div id="leftfooter">
      %searchfield%
      <form method="post" action="" name=streamtype><span id="options"></span></form>
  </div>
  <div id="rightfooter">
      <a class="feet" href="#" id="podcast">podcast</a>|
      <a class="feet" href="javascript:showHelp()">hotkeys</a>|
      %admin%
      <span class="feet"><a href="http://musicbrowser.sf.net/">musicbrowser.sf.net</a> v%version%</span>
  </div>
</div>
</body></html>