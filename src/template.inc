<!--[if lte IE 6]><!-- quirksmode --><![endif]-->
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
  <title>Music Browser</title>
  <meta http-equiv="Content-Type" content="text/html; charset=utf-8">
  <style type="text/css">
    <!--
    body { margin: 0; padding: 150px 0 25px 0; color: #000; background: #fff; 
        	 font-family: 'Times New Roman', Times, serif;  font-size: 1em; }
    div#header { width: 100%; height: 150px; position: fixed; top: 0; left: 0; color: #000; background:  #91AA9D; padding: 0px; }
    div#footer { overflow: auto; width: 100%; height: 25px; position: fixed; bottom: 0; left: 0; 
        	 color: #000; background: #91AA9D; padding: 1px; font-size: 0.8em; }
    div#content { padding: 0; }
    div#leftheader { text-align: left;  margin-right: 500px;  height: 150px; overflow: auto; padding: 0px; }
    div#rightheader { position: fixed; height: 150px; top: 0; right: 0; }
    div#leftfooter { float: left; text-align: left; }
    div#rightfooter { float: right; text-align: right; }
    div#breadcrumb { margin: 10px 10px 10px 10px; }
    div#cover { margin: 0px 10px 10px 10px; }
    #box { display: block; color: black; position: fixed; z-index: 100; padding: 1em; top: 30px; left: 70px; }

    .box { padding: 10px; background-color: #CCC; }
    .boxbutton { padding: 1px 5px; vertical-align: top; background-color: gray; color: white;  }
    .boxbutton:link { text-decoration: none;  }
    th { background-color: #3E606F; color: #FCFFF5; text-align: center; }
    a { color: #2222AA; text-decoration: none; }
    a:hover { text-decoration: underline; }
    form { display: inline; }
    .folder { font-size: 0.8em; }
    .file { font-size: 0.8em; }
    .cell { vertical-align: top; text-align: left; }
    .odd { background-color: #FCFFF5; }
    .even { background-color: #D1DBBD; }
    .title { font-weight: bold; }
    .path { font-weight: bold; }
    .loading { text-decoration: blink; text-align: center; font-style: italic; }
    .error { color: red; }
    .feet { text-decoration: none; padding-right: 10px; padding-left: 10px; }
    .searchresult { list-style-type: none; padding-top: 10px; padding-right: 20px; margin: 0px; }
    #batplay { padding: 5px; background-color: #ffffff; margin-top: 10px; }
    -->
  </style>

  <!-- Workaround for IE5 and IE6 -->
  <!--[if lte IE 6]>
  <style type="text/css">
    body { height: 100%; overflow: hidden; font-size: 100%; }
    div#content { width: 100%; height: 100%; overflow: auto; }
    div#header, div#footer, div#rightheader, #box { position: absolute; }
  </style>
  <script type="text/javascript">onload = function() { content.focus() }</script>
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
  <div id="rightheader"><div id="player"></div></div>
</div>

<div id="content">
  <div class="loading">initializing...</div>
</div>

<div id='footer'>
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

</body>
</html>