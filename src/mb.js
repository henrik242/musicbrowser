"use strict";

var currentFolder = '';
var currentHash = '###';
var jwPlayerId = 'jwp';
var prefix = 'mb.php/';
var hotkeyModifier = false;
var hotkeysDisabled = false;
var flashWidth = 500;
var flashHeight = 150;
var boxTimeout;

document.onkeydown = hotkey;

window.onload = function() {
  pollHash();
  setInterval(pollHash, 1000);
}

/**
 * Poll the url hash regularly for changes.
 */
var pollHash = function() {
  var locationHash = window.location.hash.replace(/^#/, '');
  if (locationHash == currentHash) {
    return; // Nothing's changed since last polled. 
  }

  // Firefox and Safari don't agree on hash encoding
  if (decodeURIComponent(currentHash) == locationHash) {
    return;
  }
  
  currentHash = locationHash;
  switch (currentHash.substr(0, 2)) {
      case 'p=':
        updateDirectory(currentHash.replace(/^p=/, ''));
        break;
      case 's=':
        search(currentHash.replace(/^s=/, ''));
        break;
      default:
        updateDirectory('');
  }
}

/**
 * Change to previous directory.
 */
var previousDir = function() {
  history.go(-1);
}

/**
 * Change directory.
 */
var changeDir = function(path) {
  updateDirectory(path);
  updateHash('p', path);
}

/**
 * Update content tag with specified content from specified path.
 */
var updateDirectory = function(path) {
  document.getElementById('content').innerHTML = "<div class=loading>loading...</div>";
  currentFolder = path;
  fetchContent(path.replace('&', '%26'));
  document.getElementById('podcast').href =  prefix + path + '&stream=rss';
  document.getElementById('podcast').title = prefix + path.replace(/\+/g, ' ') + ' podcast';
  document.getElementById('podcast').innerHTML = 'podcast';
}

/**
 * Set stream type.
 */
var setStreamType = function(path, streamType) {
  fetchContent(path.replace('&', '%26') +  '&streamType=' + streamType);
  
}

/**
 * Enable/disable shuffle.
 */
var setShuffle = function(path) {
  var shuffle = document.getElementById('shuffle').checked;
  fetchContent(path.replace('&', '%26') + '&shuffle=' + shuffle); 
}

/**
 * HTTP GET content from path.
 */
var fetchContent = function(path) {  
  var http = httpGet(prefix + path + ".json");
  http.onreadystatechange = function() {
    if (http.readyState == 4) {
      var result = jsonEval(http.responseText);
      if (!result) {
        document.getElementById('content').innerHTML = "<div class=error>Error.</div>";
      } else {
        document.title = path;
        document.getElementById('cover').innerHTML = createCoverImage(result.shift());
        document.getElementById('breadcrumb').innerHTML = createBreadcrumbs(path);
        //document.getElementById('options').innerHTML = result.options;
        var contentDiv = $('#content');
        contentDiv.html('');
        var table = createHtml(result);
        table.appendTo(contentDiv);

        if (document.getElementById('jwp') == null && result.streamType == 'flash') {
          jwObject().write('player'); // Only create flash player if it isn't there already
        } else if (document.getElementById('batplay') == null && result.streamType == 'native') {
          document.getElementById('player').innerHTML = '<div></div>';
        }
      }
    }
  }
  http.send(null);
}

var createBreadcrumbs = function(path) {
  var folders = path.split("/");
  var result = '<a href="#">Music</a>';
  for (var i = 0; i < folders.length; i++) {
    if (folders[i]) {
      result += ' » <a href="#p=' + folders[i] + '">' + folders[i] + '</a>';
    }
  }
  return result;
}

var createCoverImage = function(image) {
  if (!image) {
    return "";
  }
  return '<a href="javascript:showCover(\'' + image + '\')">' +
         '<img width="100" height="100" border="0" align="left" src="' + image + '" alt="" title="View enlarged cover"></a>';
}

var createHtml = function(entries) {
  var table = $('<table>');
  table.append($('<thead>')).append($('<tbody>'));

  var counter = 0;
  var tr;
  var rowclass;
  for (var i = 0; i < entries.length; i++) {
    if (counter === 0) {
      if (rowclass === 'even') {
        rowclass = 'odd';
      } else {
        rowclass = 'even';
      }
      tr = $('<tr>').addClass(rowclass).appendTo(table);
    }
    tr.append(createTd(entries[i]).addClass('cell'));

    counter++;
    if (counter === 5) {
      counter = 0;
    }
  }

  return table;
}

var createTd = function(value) {
    if (value === null || value === 'null') {
        value = '';
    }
    if (value.charAt(value.length-1) === "/") {
      return $('<td>').html(
      '<a href="javascript:play(\'' + value + '\')"><img border="0" alt="[Play]" title="Play this folder" src="play.gif"></img></a>' +
      '<a class="folder" href="javascript:changeDir(\'' + value + '\')" title="' + value + '">&nbsp;' + value + '</a>');
    }
    return $('<td>').html(
      '<a href="' + value + '"><img border="0" alt="[Download]" title="Download this song" src="download.gif"></img></a>' +
      '<a class="file" href="javascript:play(\'' + value + '\')" title="Play this song">&nbsp;' + value + '</a>');
};


/**
 * HTTP GET.
 * @return HTTP object
 */
var httpGet = function(fullPath) {
  var http = false;
  if (navigator.appName.indexOf('Microsoft') != -1) {
    http = new ActiveXObject("Microsoft.XMLHTTP");
  } else {
    http = new XMLHttpRequest();
  }
  http.open("GET", fullPath, true);
  return http;
}

/**
 * Enable search field, disable global hotkeys.
 */
var enableSearch = function() {
  hotkeysDisabled = true;
  document.getElementById('search').value = '';
}

/**
 * Disable search field, enable global hotkeys.
 */
 var disableSearch = function() {
  hotkeysDisabled = false;
  document.getElementById('search').value = 'search';
}

/**
 * Invoke search on keypress==return.
 */
var invokeSearch = function(e) {
  if (getKeyNum(e) == 13) {
    search();
  }
}

/**
 * Search for file or folder.
 * @param needle Use this needle.  Uses search field from page if empty.
 */
var search = function(needle) {
  var encodedNeedle = needle;
  if (!needle) {
    needle = document.getElementById('search').value;
    // Firefox and Safari don't agree on hash encoding
    encodedNeedle = encodeURIComponent(needle);
  }
  if (needle.length < 2) {
    showBox('<div class=error>Search term must be longer than 2 characters</div>', 3000);
    return;
  }
  updateHash('s', encodedNeedle);

  var startTime = new Date().getSeconds();
  showBox('<div class=error>Searching...</div>');
  var http = httpGet(prefix + "&search=" + needle);
  http.onreadystatechange = function() {
    if (http.readyState == 4) {
      var result = jsonEval(http.responseText);
      if (result && result.error) {
        showBox('<div class=error>' + result.error + '</div>');
      } else if (result) {
        if (result.numresults > 0) {
          document.title = result.title;
          document.getElementById('content').innerHTML = result.content;
          document.getElementById('breadcrumb').innerHTML = result.breadcrumb;
          document.getElementById('cover').innerHTML = '';

          // I should enable options and podcasts for searches as well:
          document.getElementById('options').innerHTML = '';
          document.getElementById('podcast').href = '#';
          document.getElementById('podcast').title = '';
          document.getElementById('podcast').innerHTML = '';
        }
        var seconds = new Date().getSeconds() - startTime;
        showBox('Found ' + result.numresults + ' results in ' + seconds + ' seconds.', 3000);
      }
    }
  }
  http.send(null);
}

/**
 * Update hash in url field.
 * @param func Function, either 's' (search) or 'p' (path)
 * @param content Value in hash
 */
var updateHash = function(func, content) {
  if (content) {
    var tempHash = func + '=' + content;
    // Firefox and Safari don't agree on hash encoding
    if (encodeURIComponent(tempHash) == currentHash) {
      return;
    }
    currentHash = tempHash;
    window.location.hash = '#' + currentHash;
  } else {
    currentHash = '';
    window.location.hash = '#';
  }
}

/**
 * Rebuild search database.
 */
var buildDB = function() {
  var answer = confirm("Are you sure you want to rebuild the search database?");
  if (!answer) {
    showBox('<div class=error>Aborted...</div>', 3000);
    return; 
  }
  showBox('Building search DB...');
  var http = httpGet(prefix + "&builddb");
  http.onreadystatechange = function() {
    if (http.readyState == 4) {
      var result = jsonEval(http.responseText);
      if (result) {
        showBox('<div class=error>' + result.error + '</div>', 3000);
      }
    }
  }
  http.send(null);
}

/**
 * Show album cover.
 */
var showCover = function(picture) {
  showBox('<img alt="" border=0 src="' + picture + '">');  
}

/**
 * Show flash player hotkeys.
 */
var showHelp = function() {
  showBox('Flash player hotkeys<br>'
      + '<b>p</b> - play or pause<br>'
      + '<b>b</b> - skip back<br>'
      + '<b>n</b> - skip next<br>'
      + '<b>a</b> - play everything in this folder<br>');  
}

/**
 * Show the dialogue.
 */
var showBox = function(content, timeout) {
  document.getElementById('box').innerHTML 
    = '<a class=boxbutton href="javascript:hideBox()">×</a><div class=box>' + content + '</div>';
  if (timeout) {
    clearTimeout(boxTimeout);
    boxTimeout = setTimeout(hideBox, timeout);
  }
}

/**
 * Hide the dialogue.
 * @see showBox()
 */
var hideBox = function() {
  document.getElementById('box').innerHTML = '';
}

/**
 * @return keynum from keypress
 */
var getKeyNum = function(e) {
  var keynum;
  if (window.event) { // IE
    keynum = e.keyCode;
  } else if (e.which) { // Netscape/Firefox/Opera
    keynum = e.which;
  }
  return keynum;
}

/**
 * Execute hotkey for flash player.
 */
var hotkey = function(e) {
  if (hotkeysDisabled) {
    return;
  }
  var keynum = getKeyNum(e);

  if (keynum == 224 || keynum == 16 || keynum == 17 || keynum == 18) {
    hotkeyModifier = true; // cmd, shift, ctrl, alt
  } else if (hotkeyModifier == true ) {
    hotkeyModifier = false; // modifier has been pressed
  } else {
    if (keynum == 80) jwPlayer().sendEvent('playpause'); // 'p'
    if (keynum == 66) jwPlayer().sendEvent('prev'); // 'b'
    if (keynum == 78) jwPlayer().sendEvent('next'); // 'n'
    if (keynum == 65) { // 'a'
      jwPlay(currentFolder, 'false');
      showBox("Playing all files in this folder", 3000);
    }
  }
}

/**
 * Crude json evaluator.  Returns false if input isn't json.
 */
var jsonEval = function(text) {
  if (text.substr(0, 1) != '[') {
    showBox('<div class=error>Could not parse content.<br>'
      + escapeHTML(text.substr(0,180)) + '...</div>');
    return false;
  } else {
    return eval("(" + text + ")");
  }
}

/**
 * Escape HTML string.
 */
var escapeHTML = function(str) {
   var div = document.createElement('div');
   var text = document.createTextNode(str);
   div.appendChild(text);
   return div.innerHTML;
}

/**
 * @return Instance of the JW Flash Player
 */
var jwPlayer = function() {
  if (navigator.appName.indexOf("Microsoft") != -1) {
    return window[jwPlayerId];
  } else {
    return document[jwPlayerId];
  }
}

/**
 * Play specified file or folder in the Flash Player.
 */
var jwPlay = function(path, shuffle) {
  var shuffleText = "";
  if (shuffle == 'true') { shuffleText = "&shuffle=true"; }
  var theFile = "{file:encodeURI('" + prefix + path + shuffleText + "&stream=flash')}";
  jwPlayer().loadFile(eval("(" + theFile + ")"));

  var http = httpGet(prefix + path + "&messages");
  http.onreadystatechange = function() {
    if (http.readyState == 4) {
      var result = jsonEval(http.responseText);
      if (result && result.error) {
        showBox('<div class=error>' + result.error + '</div>');
      }
    }
  }
  http.send(null);
}

/**
 * @return A JW Flash Player SWF object
 */
var jwObject = function() {
  var so = new SWFObject('mediaplayer.swf', jwPlayerId, flashWidth, flashHeight, '8', "#FFFFFF");
  so.addParam('allowscriptaccess', 'always');
  so.addParam('allowfullscreen', 'false');
  so.addVariable('height', flashHeight);
  so.addVariable('width', flashWidth);
  so.addVariable('file', '');
  so.addVariable('displaywidth', '0');
  so.addVariable('showstop', 'true');
  so.addVariable('autostart', 'true');
  so.addVariable('usefullscreen', 'false');
  so.addVariable('shuffle', 'false');
  so.addVariable('enablejs', 'true');
  so.addVariable('type', 'mp3');
  so.addVariable('repeat', 'list');
  so.addVariable('thumbsinplaylist', 'false');
  return so;
}


/**
 * Play specified file or folder.
 */
var play = function(path) {
  var http = httpGet(prefix + path + "?showstreamtype");
  http.onreadystatechange = function() {
    if (http.readyState == 4) {
      var result = jsonEval(http.responseText);
      if (result && result.error) {
        showBox('<div class=error>' + result.error + '</div>');
      } else if (result) {
        var type = result.streamType;
        var shuffle = result.shuffle;
        initiatePlay(path, type, shuffle);
      }
    }
  }
  http.send(null);
}

/**
 * Initiate playback.
 */
var initiatePlay = function(path, type, shuffle) {
  if (type == 'flash') {
    jwPlay(path, shuffle);
    return;
  }
  var shuffleText = "";
  if (shuffle) { shuffleText = "&shuffle=true"; }
  var http = httpGet(prefix + path + shuffleText + "&stream=" + type);
  http.onreadystatechange = function() {
    if (http.readyState == 4) {
      var result = jsonEval(http.responseText);
      if (result.error) {
        showBox('<div class=error>' + result.error + '</div>', 5000);
      } else if (result && type == 'native') {
        batPlay(result.title, result.url);
      }
    }
  }
  http.send(null);
}

/**
 * Modified version of Batmosphere Embedded Media Player, version 2006-05-31
 * Written by David Battino, www.batmosphere.com
 * OK to use if this notice is included
 */
var batPlay = function(title, url) {
  var objType = "audio/mpeg";  // The MIME type for Macs and Linux
  if (navigator.userAgent.toLowerCase().indexOf("windows") != -1) {
    objType = "application/x-mplayer2"; // The MIME type to load the WMP plugin in non-IE browsers on Windows
  }
  var player = "<div id='batplay'>Playing " + title + "<br>"
   + "<object width='" + flashWidth + "' height='100'><param name='type' value='" + objType + "'>"
   + "<param name='src' value='" + url + "'><param name='autostart' value='0'>"
   + "<param name='showcontrols' value='1'><param name='showstatusbar' value='1'>"
   + "<embed src ='" + url + "' type='" + objType + "' autoplay='true' autostart='1' width='" + flashWidth
   + "' bgcolor='#ffffff' height='100' controller='1' showstatusbar='1'></embed>"
   + "</object></div>";

  document.getElementById('player').innerHTML = player;
}

/**
 * SWFObject v1.5.1: Flash Player detection and embed - http://blog.deconcept.com/swfobject/
 *
 * SWFObject is (c) 2007 Geoff Stearns and is released under the MIT License:
 * http://www.opensource.org/licenses/mit-license.php
 *
 */
if(typeof deconcept=="undefined"){var deconcept={};}if(typeof deconcept.util=="undefined"){deconcept.util={};}if(typeof deconcept.SWFObjectUtil=="undefined"){deconcept.SWFObjectUtil={};}deconcept.SWFObject=function(_1,id,w,h,_5,c,_7,_8,_9,_a){if(!document.getElementById){return;}this.DETECT_KEY=_a?_a:"detectflash";this.skipDetect=deconcept.util.getRequestParameter(this.DETECT_KEY);this.params={};this.variables={};this.attributes=[];if(_1){this.setAttribute("swf",_1);}if(id){this.setAttribute("id",id);}if(w){this.setAttribute("width",w);}if(h){this.setAttribute("height",h);}if(_5){this.setAttribute("version",new deconcept.PlayerVersion(_5.toString().split(".")));}this.installedVer=deconcept.SWFObjectUtil.getPlayerVersion();if(!window.opera&&document.all&&this.installedVer.major>7){if(!deconcept.unloadSet){deconcept.SWFObjectUtil.prepUnload=function(){__flash_unloadHandler=function(){};__flash_savedUnloadHandler=function(){};window.attachEvent("onunload",deconcept.SWFObjectUtil.cleanupSWFs);};window.attachEvent("onbeforeunload",deconcept.SWFObjectUtil.prepUnload);deconcept.unloadSet=true;}}if(c){this.addParam("bgcolor",c);}var q=_7?_7:"high";this.addParam("quality",q);this.setAttribute("useExpressInstall",false);this.setAttribute("doExpressInstall",false);var _c=(_8)?_8:window.location;this.setAttribute("xiRedirectUrl",_c);this.setAttribute("redirectUrl","");if(_9){this.setAttribute("redirectUrl",_9);}};deconcept.SWFObject.prototype={useExpressInstall:function(_d){this.xiSWFPath=!_d?"expressinstall.swf":_d;this.setAttribute("useExpressInstall",true);},setAttribute:function(_e,_f){this.attributes[_e]=_f;},getAttribute:function(_10){return this.attributes[_10]||"";},addParam:function(_11,_12){this.params[_11]=_12;},getParams:function(){return this.params;},addVariable:function(_13,_14){this.variables[_13]=_14;},getVariable:function(_15){return this.variables[_15]||"";},getVariables:function(){return this.variables;},getVariablePairs:function(){var _16=[];var key;var _18=this.getVariables();for(key in _18){_16[_16.length]=key+"="+_18[key];}return _16;},getSWFHTML:function(){var _19="";if(navigator.plugins&&navigator.mimeTypes&&navigator.mimeTypes.length){if(this.getAttribute("doExpressInstall")){this.addVariable("MMplayerType","PlugIn");this.setAttribute("swf",this.xiSWFPath);}_19="<embed type=\"application/x-shockwave-flash\" src=\""+this.getAttribute("swf")+"\" width=\""+this.getAttribute("width")+"\" height=\""+this.getAttribute("height")+"\" style=\""+(this.getAttribute("style")||"")+"\"";_19+=" id=\""+this.getAttribute("id")+"\" name=\""+this.getAttribute("id")+"\" ";var _1a=this.getParams();for(var key in _1a){_19+=[key]+"=\""+_1a[key]+"\" ";}var _1c=this.getVariablePairs().join("&");if(_1c.length>0){_19+="flashvars=\""+_1c+"\"";}_19+="/>";}else{if(this.getAttribute("doExpressInstall")){this.addVariable("MMplayerType","ActiveX");this.setAttribute("swf",this.xiSWFPath);}_19="<object id=\""+this.getAttribute("id")+"\" classid=\"clsid:D27CDB6E-AE6D-11cf-96B8-444553540000\" width=\""+this.getAttribute("width")+"\" height=\""+this.getAttribute("height")+"\" style=\""+(this.getAttribute("style")||"")+"\">";_19+="<param name=\"movie\" value=\""+this.getAttribute("swf")+"\" />";var _1d=this.getParams();for(var key in _1d){_19+="<param name=\""+key+"\" value=\""+_1d[key]+"\" />";}var _1f=this.getVariablePairs().join("&");if(_1f.length>0){_19+="<param name=\"flashvars\" value=\""+_1f+"\" />";}_19+="</object>";}return _19;},write:function(_20){if(this.getAttribute("useExpressInstall")){var _21=new deconcept.PlayerVersion([6,0,65]);if(this.installedVer.versionIsValid(_21)&&!this.installedVer.versionIsValid(this.getAttribute("version"))){this.setAttribute("doExpressInstall",true);this.addVariable("MMredirectURL",escape(this.getAttribute("xiRedirectUrl")));document.title=document.title.slice(0,47)+" - Flash Player Installation";this.addVariable("MMdoctitle",document.title);}}if(this.skipDetect||this.getAttribute("doExpressInstall")||this.installedVer.versionIsValid(this.getAttribute("version"))){var n=(typeof _20=="string")?document.getElementById(_20):_20;n.innerHTML=this.getSWFHTML();return true;}else{if(this.getAttribute("redirectUrl")!=""){document.location.replace(this.getAttribute("redirectUrl"));}}return false;}};deconcept.SWFObjectUtil.getPlayerVersion=function(){var _23=new deconcept.PlayerVersion([0,0,0]);if(navigator.plugins&&navigator.mimeTypes.length){var x=navigator.plugins["Shockwave Flash"];if(x&&x.description){_23=new deconcept.PlayerVersion(x.description.replace(/([a-zA-Z]|\s)+/,"").replace(/(\s+r|\s+b[0-9]+)/,".").split("."));}}else{if(navigator.userAgent&&navigator.userAgent.indexOf("Windows CE")>=0){var axo=1;var _26=3;while(axo){try{_26++;axo=new ActiveXObject("ShockwaveFlash.ShockwaveFlash."+_26);_23=new deconcept.PlayerVersion([_26,0,0]);}catch(e){axo=null;}}}else{try{var axo=new ActiveXObject("ShockwaveFlash.ShockwaveFlash.7");}catch(e){try{var axo=new ActiveXObject("ShockwaveFlash.ShockwaveFlash.6");_23=new deconcept.PlayerVersion([6,0,21]);axo.AllowScriptAccess="always";}catch(e){if(_23.major==6){return _23;}}try{axo=new ActiveXObject("ShockwaveFlash.ShockwaveFlash");}catch(e){}}if(axo!=null){_23=new deconcept.PlayerVersion(axo.GetVariable("$version").split(" ")[1].split(","));}}}return _23;};deconcept.PlayerVersion=function(_29){this.major=_29[0]!=null?parseInt(_29[0]):0;this.minor=_29[1]!=null?parseInt(_29[1]):0;this.rev=_29[2]!=null?parseInt(_29[2]):0;};deconcept.PlayerVersion.prototype.versionIsValid=function(fv){if(this.major<fv.major){return false;}if(this.major>fv.major){return true;}if(this.minor<fv.minor){return false;}if(this.minor>fv.minor){return true;}if(this.rev<fv.rev){return false;}return true;};deconcept.util={getRequestParameter:function(_2b){var q=document.location.search||document.location.hash;if(_2b==null){return q;}if(q){var _2d=q.substring(1).split("&");for(var i=0;i<_2d.length;i++){if(_2d[i].substring(0,_2d[i].indexOf("="))==_2b){return _2d[i].substring((_2d[i].indexOf("=")+1));}}}return "";}};deconcept.SWFObjectUtil.cleanupSWFs=function(){var _2f=document.getElementsByTagName("OBJECT");for(var i=_2f.length-1;i>=0;i--){_2f[i].style.display="none";for(var x in _2f[i]){if(typeof _2f[i][x]=="function"){_2f[i][x]=function(){};}}}};if(!document.getElementById&&document.all){document.getElementById=function(id){return document.all[id];};}var getQueryParamValue=deconcept.util.getRequestParameter;var FlashObject=deconcept.SWFObject;var SWFObject=deconcept.SWFObject;
