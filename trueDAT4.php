<?
/*===============================================================================
	trueDAT4.php
	John Larson
	1/12/12
	
	license: MIT-style
	
===============================================================================*/

define('TRUEDAT4_VERSION', "4.0.5");
define('thisPage', $_SERVER['PHP_SELF']);
define('TRUEDAT4_BASEURL', '//www.truedat.us/baseResources/4_0_5/');
	
	$TDConfig = array();
	$trueDATBaseURL = TRUEDAT4_BASEURL;
	if(ConfigFileExists()) {
		require_once('trueDAT4Config.php');
		$trueDATBaseURL = $TDConfig['options']['baseURL'];
		global $TDConfig; // load our configuration as global variable
	}
	$accessKey = 'trueDAT4Access::' . currentPageURL();
	
	session_start();
	ini_set('display_errors', '1');
	$action = Request('a');
	
	BlockCSRFFailingRequest($action);
	
	// Special case actions:
	if($action == '')		{ DisplayTrueDAT4();			exit(); }
	if($action == "login")	{ LoginUser();					exit(); }
	if($action == "logout")	{ $_SESSION[$accessKey] = "";	exit(); }
	
	// At this point, we assume the action of an authenticated user:
	if(!(IsLoggedIn())) { header("HTTP/1.0 400 Not Authenticated."); exit(); } // guess not!
	
	switch($action) {
		case "executeSQL":						PerformSQLExecution();			break;
		case "exportToCSV":						ExportToCSV();					break;
		case "loadDBStructure":					LoadDBStructure();				break;
		case "getStoredProcedureDefinition":	GetStoredProcedureDefinition();	break;
		case "fetchTableField":					FetchTableField();				break;
		case "addTableRow":						AddTableRow();					break;
		case "updateTableField":				UpdateTableField();				break;
		case "deleteTableRow":					DeleteTableRow();				break;
		case "loadApp":							DisplayApp();					break;
		
		// Tools:
		case "tableTransferExport":				PerformTableTransferExport();	break;
		case "tableTransferUpload":				PerformTableTransferUpload();	break;
		case "loadTableTransferState":			DisplayTableTransferState();	break;
		case "deleteTableTransferFile":			DeleteTableTransferFile();		break;
		case "tableTransferImport":				PerformTableTransferImport();	break;
		case "CSVUpload":						PerformCSVUpload();				break;
		case "loadCSVState":					DisplayCSVState();				break;
		case "CSVQuery":						PerformCSVQuery();				break;
		case "deleteCSVFile":					DeleteCSVFile();				break;
		case "findValue":						PerformValueFind();				break;
		
		// Configuration:
		case "loadAutoDetectDBSettings":		LoadAutoDetectDBSettings();		break;
		case "firstConfig1":					ProcessFirstConfig1();			break;
		case "firstConfig2":					ProcessFirstConfig2();			break;
		case "firstConfig3":					ProcessFirstConfig3();			break;
		case "verifyConfigFileUpload":			VerifyConfigFileUpload();		break;
		case "loadSystemConfig":				DisplaySystemConfig();			break;
		case "sveSystemConfiguration":			SaveSystemConfiguration();		break;
	}
	exit();
	


function BlockCSRFFailingRequest($action) {
	if(strlen($action) == 0) return; // no problem
	$cookieToken = (isset($_COOKIE['CSRFToken']) ? $_COOKIE['CSRFToken'] : '');
	if(strlen($action) == 0  ||  Request('CSRFToken') != $cookieToken) exit(); // bad call, shut it down
	$_COOKIE['CSRFToken'] = ''; // zero out for protection against playback attacks
}


function LoginUser() {
	global $TDConfig, $accessKey;
	$username = StraightRequestText("username", 100);
	$password = StraightRequestText("password", 100);
	if(strlen($username . $password) == 0)
		$result = "Please enter your username and password.";
	else {
		$authParams = $TDConfig['authentication'];
		switch($authParams['authMode']) {
			case 'localUNPW':
				if($authParams['username'] == $username  &&  sha1($password) == $authParams['passwordHash']) {
					$_SESSION[$accessKey] = "granted";
					$result = 'granted';
				} else
					$result = "Username/password combination is incorrect.";
				break;
			case 'remoteUNPW': // Phone home to see if this user is welcome:
				$result = file_get_contents_post($authParams['authBaseURL'],
					"&username=" . urlencode($username) .
					"&password=" . urlencode($password) .
					"&URL=" . urlencode(currentPageURL()));
				
				if($result == "granted")
					$_SESSION[$accessKey] = "granted";
				else
					$result = "Username/password combination is incorrect.";
				break;
		}
	}
	echo $result;
}

function file_get_contents_post($URL, $postData) {
	if(!is_string($postData)) $postData = ArrayToQueryString($postData);
	return file_get_contents($URL, false, stream_context_create(array('http' => array(
		'method' => 'POST', 'header' => 'Content-type: application/x-www-form-urlencoded', 'content' => $postData
	))));
}

function IsLoggedIn() {
	global $accessKey, $TDConfig;
	if(Session($accessKey) == "granted") return true;
	
	if(!ConfigFileExists())  {
		if(Session($accessKey) == "installing") return true; // you are currently setting up, carry on!
		
		if(!file_exists(dirname(__FILE__) . '/trueDAT4InstallInProgress')) {
			$_SESSION[$accessKey] = "installing"; // you're first in, welcome!
			touch(dirname(__FILE__) . '/trueDAT4InstallInProgress'); // make our lock
			return true;
		}
		return false; // bugger off: install is in progress, and it's not with you!
	}
	
	if($TDConfig['authentication']['authMode'] == 'skip') { $_SESSION[$accessKey] = "granted"; return true; }
	if($TDConfig['authentication']['authMode'] == 'session') {
		eval("\$result = " . $TDConfig['authentication']['sessionExpression'] . ";"); // an expression like $_SESSION['someVariable'] == someValue
		if($result)
			$_SESSION[$accessKey] = "granted";
		return $result;
	}
	return false;
}



function DisplayTrueDAT4() {
	
	global $trueDATBaseURL;
	$configExists = ConfigFileExists();
//	if(!$configExists) { $trueDATBaseURL = TRUEDAT4_BASEURL; } // starter default!
		
	$isLoggedIn = IsLoggedIn();
?>
<!DOCTYPE HTML>
<!--[if lt IE 7 ]> <html class="ie ie6" lang="en"> <![endif]-->
<!--[if IE 7 ]>    <html class="ie ie7" lang="en"> <![endif]-->
<!--[if IE 8 ]>    <html class="ie ie8" lang="en"> <![endif]-->
<!--[if IE 9 ]>    <html class="ie ie9" lang="en"> <![endif]-->
<!--[if gt IE 9]><!--><html lang="en"><!--<![endif]-->
<html>
<head>
  <link rel="SHORTCUT ICON" HREF="<?=$trueDATBaseURL?>images/favicon.gif?x=1">
  <title>trueDAT4 by JPL Consulting</title>
  <meta http-equiv="Content-Type" content="text/html; charset=UTF-8" />
  <link href="<?=$trueDATBaseURL?>css/trueDAT4.css" media="screen" rel="Stylesheet" type="text/css" />
  <script type="text/javascript" src="<?=$trueDATBaseURL?>scripts/mootools-core-1.4.1.js"></script>
  <script type="text/javascript" src="<?=$trueDATBaseURL?>scripts/mootools-more-1.4.0.1.js"></script>
  <script type="text/javascript" src="<?=$trueDATBaseURL?>scripts/dbug.js"></script>
  <script type="text/javascript" src="<?=$trueDATBaseURL?>scripts/HistoryManager.js"></script>
  <script type="text/javascript" src="<?=$trueDATBaseURL?>scripts/TabSwapper.js"></script>
  <script type="text/javascript" src="<?=$trueDATBaseURL?>scripts/PopUpWindow.js"></script>
  <script type="text/javascript" src="<?=$trueDATBaseURL?>scripts/TableSorter.js"></script>
  <script type="text/javascript" src="<?=$trueDATBaseURL?>scripts/InlineSuggest.js"></script>
  <script type="text/javascript" src="<?=$trueDATBaseURL?>scripts/Roar.js"></script>
  <script type="text/javascript" src="<?=$trueDATBaseURL?>scripts/trueDAT4.js"></script>
</head>
<body>
  <div id="loadingMessage"><div id="loadingMessageInner">Loading...</div></div>
  <div id="versionMessage">version<?=TRUEDAT4_VERSION?></div>

  <div id="container">
<?	if($configExists) { ?>
    <div id="loginPage" style="display: <?=($isLoggedIn ? 'none' : 'block')?>;"><? DisplayLoginForm() ?></div>
    <div id="mainPage"  style="display: <?=($isLoggedIn ? 'block' : 'none')?>;"><? if($isLoggedIn) { DisplayApp(); } ?></div>
    <div id="configurePage" style="display: none;"></div>
<?	} elseif($isLoggedIn) { ?>
    <div id="configurePage"><?=DisplaySystemConfigFirstTime()?></div>
<?	} else { ?>
    <div id="configurePage"><?=DisplayConfigInProgressMessage()?></div>
<?	} ?>
  </div>
</body>
</html>
<?
}

function LoginIsRelevant() { global $TDConfig; return in_array($TDConfig['authentication']['authMode'], array('localUNPW', 'remoteUNPW')); }
function DisplayLoginForm() {
?>
  <div class="center" style="margin-left: 150px;">
<?
	BeginRolledScrollPane("Welcome to trueDAT4", "550px");
	global $TDConfig;
	switch($TDConfig['authentication']['authMode']) {
		case 'session': ?>
	<p>
      You must login via the host application in order to access trueDAT.
    </p>
<?			break;
		case 'skip': ?>
	<p>Come on in.</p>
    <div class="center nextSection">
      <?=DrawButton('Enter trueDAT', 'window.location.reload();')?>
    </div>
<?			break;
		default: ?>
   <form action="JavaScript:void(0);" id="loginForm" onsubmit="JavaScript: loginUser(this);">
	<table class="verticalMiddle">
	  <tr>
	    <td width="200">Username</td>
	    <td><input type="text" name="username" style="width: 200px;"></td>
	  </tr>
	  <tr>
	    <td>Password</td>
	    <td><input type="password" name="password" style="width: 200px;"></td>
	  </tr>
	  <tr>
	    <td></td>
	    <td><div id="loginMessage"></div></td>
	  </tr>
	  <tr>
	    <td></td>
	    <td><input type="submit" value="Log In"></td>
	  </tr>
	</table>
   </form>
<?
	}
	EndScrollPane();
?>
  </div>
<?
}


function BeginRolledScrollPane($caption, $width) { BeginScrollPane($caption, $width, "rolledScroll"); }
function BeginScrollPane($caption, $width, $class="scroll") { ?>
<div class="<?=$class?>" style="width:<?=$width?>">
  <div class="header"><span class="title"><?=$caption?></span></div>
  <div class="bodyBottomRightEdge"><div class="body"><?
}
function EndScrollPane($caption = '') { ?>
  </div></div>
  <div class="footer"><span class="footerTitle"><?=$caption?></span></div>
</div><?
}

function DrawButton($label, $onClick, $class = '', $ID = '') {
?><div class="button <?=$class?>" id="<?=$ID?>" onclick="JavaScript: if(!$(this).hasClass('buttonDisabled')) {<?=$onClick?>}"><?=$label?></div><?
}

function DrawHiddenButton($label, $onClick, $class = '', $ID = '') {
?><div class="button <?=$class?>" style="display: none;" id="<?=$ID?>" <?
?>onclick="JavaScript: if(!$(this).hasClass('buttonDisabled')) {<?=$onClick?>}"><?=$label?></div><?
}




function DisplaySystemConfig() {
	
	BeginRolledScrollPane('Configure trueDAT', '800px');
?>
<form action="JavaScript: void(0);" onsubmit="JavaScript: saveConfiguration(this);">
  <p>This feature is not yet built.</p>
  <p>For now you can manaully edit trueDAT4Config.php if you need to change settings.</p>
  <?=DrawButton('Ok, cool', "swapSections('configurePage', 'mainPage');")?>
</form>
<?
	EndScrollPane();
}

function DisplayConfigInProgressMessage() {
	BeginRolledScrollPane('Welcome to trueDAT', '800px');
?>
<div class="centered" style="width: 450px;">
  <p>A configuration of this installation is currently in progress.  Please try back later.</p>
  <p>If you're the one trying to configure this installation, you can delete the file named <code>trueDAT4InstallInProgress</code>
  	in this directory to start again.</p>
</div>
<?
	EndScrollPane();
}

function DisplaySystemConfigFirstTime() {
	BeginRolledScrollPane('Welcome to trueDAT', '800px');
?>
<form action="JavaScript: void(0);" onsubmit="JavaScript: saveFirstConfiguration(this);" id="configForm" class="centered" style="width: 482px;">
  <input type="hidden" name="step" value="1" />
  <p>Looks like you're just getting started with trueDAT.</p>
  <p>To set up, all we need do to is establish the database you'll be connecting to and how you want to authorize users to use this installation.</p>
  <div id="configStep1" class="configPanel">
  	<p>First, the database connection:</p>
   <table id="DBInputTable" class="top">
  	<tr><td></td>
  	  <td class="verticalMiddle">
  	    <select name="autodetect" style="width: 272px;" id="autodetectSelect" onchange="JavaScript: manageDBInputState(this);">
  	      <option value="">Auto-detect settings for...</option>
  	      <option value="WordPress">WordPress</option>
  	      <option value="Drupal">Drupal</option>
  	      <option value="Joomla">Joomla</option>
  	      <option value="Magento">Magento</option>
  	      <option value="JPL">JPL Settings</option>
  	    </select>
  	    <?=DrawButton('Go', "loadAutoDetectDBSettings($('configForm'));")?>
  	</td></tr>
  	<tr><td>Database Type:</td><td><select name="db_type" style="width: 308px;"><option value="MySQL">MySQL</option></select></td></tr>
  	<tr><td>Database Host:</td><td><input type="text" name="db_host" value="localhost" style="width: 300px;" /></td></tr>
  	<tr><td>Username:</td><td><input type="text" name="db_username" value="" style="width: 300px;" /></td></tr>
  	<tr><td>Password:</td><td><input type="text" name="db_password" value="" style="width: 300px;" /></td></tr>
  	<tr><td>Database Name:</td><td><input type="text" name="db_schema" value="" style="width: 300px;" /></td></tr>
  	<tr><td></td><td><input type="submit" value="Go" /></td></tr>
   </table>
   <div id="configStep1Result" style="opacity: 0;">&nbsp;</div>
  </div>
  <div id="configStep2" class="configPanel" style="display: none;">
  	<p>So far so good!</p>
  	<p>Now indicate how you would like this installation to authenticate users:</p>
   <table class="top">
  	<tr>
  	  <td>
  	  	<input type="radio" name="authMode" value="localUNPW" id="authModeRadio1" />
  	  	<label for="authModeRadio1">A single username/password for this installation</label>:<br />
  	  	<div class="nextElement" style="padding-left: 23px;">
   	      Username: <input type="text" name="username" style="width: 120px;" maxlength="50" /> &nbsp;
  	      Password: <input type="password" name="password" style="width: 120px;" maxlength="50" />
  	    </div>
  	    <br />
  	  </td>
  	</tr>
  	<tr>
  	  <td>
  	  	<input type="radio" name="authMode" value="session" id="authModeRadio2" />
  	  	<label for="authModeRadio2">Via comparison with a $_SESSION variable</label>:<br />
  	  	<div class="nextElement" style="padding-left: 23px;">
  	      A user is authenticated whenever<br />
  	      <code style="font-size: 21px;">$_SESSION['<input type="text" name="sessionName" style="width: 100px;" maxlength="50" />']
  	      <select name="sessionCompare">
  	      	<option value="<">&lt;</option>
  	      	<option value="<=">&lt;=</option>
  	      	<option value="==" selected>==</option>
  	      	<option value=">">&gt;</option>
  	      	<option value=">=">&gt;=</option>
  	      </select>
  	      <input type="text" name="sessionValue" style="width: 40px;" maxlength="10" />
  	      </code>
  	    </div>
  	    <br />
  	  </td>
  	</tr>
  	<tr>
  	  <td>
  	  	<input type="radio" name="authMode" value="skip" id="authModeRadio3" />
  	  	<label for="authModeRadio3">Skip authentication</label><br />
  	  	<div style="padding-left: 23px;">
  	  	  <i style="font-size: 11px;">(recommended only for localhost installations that are not publicly accessible)</i>
  	    </div>
  	    <br />
  	  </td>
  	</tr>
  	<tr>
  	  <td>
  	  	<input type="radio" name="authMode" value="remoteUNPW" id="authModeRadio4" />
  	  	<label for="authModeRadio4">Via remote authentication</label>:<br />
  	  	<div class="nextElement" style="padding-left: 23px;">
  	      Remote URL: <input type="text" name="authBaseURL" style="width: 320px;" maxlength="200" /><br />
  	  	  <i style="font-size: 11px;">(make sure this URL points to a script which expects <code>username</code>, <code>password</code>, and
  	  	  	optionally <code>URL</code>, and returns <code>granted</code> if the credentials are valid)</i>
  	    </div>
  	    <br />
  	  </td>
  	</tr>
  	<tr><td style="padding-left: 20px;"><input type="submit" value="Go" /></td></tr>
   </table>
   <div id="configStep2Result" style="opacity: 0;">&nbsp;</div>
  </div>
  <div id="configStep3" class="configPanel" style="display: none;">
    <p>Last step.</p>
    <p>trueDAT can be installed as a single stand-alone file, and fetch its images, CSS, and JS from another source.</p>
    <p>The arrangement makes maintenance easier and deployment super light.</p>
    <p>You may (optionally) indicate a base URL for fetching these resources.  You can use the public stash of JS/CSS/images.
       Otherwise, leave it as is and trueDAT will
       expect to find the images/JS/CSS all in the same directory as this trueDAT4.php file.</p>
  	<div class="nextElement">
      Resource Base URL: <input type="text" name="baseURL" style="width: 320px;" maxlength="200" /><br />
      <input type="checkbox" id="usePublicResourceBase"
        onclick="JavaScript: var f = this.form; if(this.checked) { f.baseURL.value = '<?=TRUEDAT4_BASEURL?>'; }" />
      <label for="usePublicResourceBase">Use the public stash of resources</label>
    </div>
  	<div class="nextSection">
      <input type="submit" value="Finish" />
    </div>
  </div>
  <div id="configStep4" class="configPanel" style="display: none;"></div>
</form>
<script>
  $('configForm').step.value = '1'; // helpful browsers get overzealous sometimes
  $('DBInputTable').getElements('input[type=text]').addEvent('change', function() { $('autodetectSelect').selectedIndex = 0; }); // typing means not an auto detect!
</script>
<?	
	EndScrollPane();
}


function LoadAutoDetectDBSettings() {
	$result = GetAutoDetectDBSettings(Request('which'));
	if($result) { // mask particulars for security
		$result['password'] = 'XXXXXXXXXX';
		$result['username'] = substr($result['username'], 0, 2) . str_repeat('X', strlen($result['username'])-2);
	}
	echo json_encode($result);
}

function GetAutoDetectDBSettings($which) {
	
	// Regexp short cuts to make things more readable:
	$w = "\s*";				// optional block of whitespace
	$s = "\w[\w|\d|\.|-|_|#]*";	// symbol: function or variable name
	switch($which) {
		case 'WordPress':
			$theFile = 'wp-config.php';
			$regExpSet = array(
				'host'		=> "/define\('DB_HOST',$w'([^']+)'\);/",
				'username'	=> "/define\('DB_USER',$w'([^']+)'\);/",
				'password'	=> "/define\('DB_PASSWORD',$w'([^']+)'\);/",
				'schema'	=> "/define\('DB_NAME',$w'([^']+)'\);/",
			);
			break;
		case 'Drupal': // db_url = 'mysqli://LocalDevUser:ABC123@localhost/drupal_play';
			$theFile = 'sites/default/settings.php';
			$regExpSet = array(
				'host'		=> "/\n\\\$db_url = '.+@($s)\//",
				'username'	=> "/\n\\\$db_url = '.+\/\/($s):/",
				'password'	=> "/\n\\\$db_url = '.+:([^\)]+)@/",
				'schema'	=> "/\n\\\$db_url = '.+\/($s)';/",
			);
			break;
		case 'Joomla':
			$theFile = 'configuration.php';
			$regExpSet = array(
				'host'		=> "/public \\\$host = '($s)'/",
				'username'	=> "/public \\\$user = '($s)'/",
				'password'	=> "/public \\\$password = '([^']+)'/",
				'schema'	=> "/public \\\$db = '($s)'/",
			);
			break;
		case 'Magento':
			$theFile = 'app/etc/local.xml';
			$regExpSet = array(
				'host'		=> "/<host><!\[CDATA\[($s)\]\]><\/host>/",
				'username'	=> "/<username><!\[CDATA\[($s)\]\]><\/username>/",
				'password'	=> "/<password><!\[CDATA\[([^\]]+)\]\]><\/password>/",
				'schema'	=> "/<dbname><!\[CDATA\[($s)\]\]><\/dbname>/",
			);
			break;
		case 'JPL': // this is how I roll
			$theFile = 'includes/universalJPLPackageSettings.php';
			$regExpSet = array(
				'host'		=> "/define\('DB_HOST',$w'([^']+)'\);/",
				'username'	=> "/define\('DB_USERNAME',$w'([^']+)'\);/",
				'password'	=> "/define\('DB_PASSWORD',$w'([^']+)'\);/",
				'schema'	=> "/define\('DB_NAME',$w'([^']+)'\);/",
			);
			break;
	}
	return $theFile ? FindDBConfigSettings($theFile, $regExpSet) : false;
}
function FindDBConfigSettings($theFile, $regExpSet) {
	$fileText = FindFileText($theFile);
	if(!$fileText) return false;
	
	$resultSet = array();
	foreach($regExpSet as $field => $pattern) {
		if(preg_match($pattern, $fileText, $matchSet))
			$resultSet[$field] = $matchSet[1];
	}
	return (count($resultSet) > 0 ? $resultSet : false); // return if we got any matches... better than nothing!
}
function FindFileText($theFile) {
	$levelsUp = 0;
	for($levelsUp = 0; $levelsUp < 5; $levelsUp++) {
		$fileTarget = dirname(__FILE__) . str_repeat("/..", $levelsUp) . "/$theFile";
		if(file_exists($fileTarget)) return GetFileText($fileTarget);
	}
	return false; // couldn't find up our directory tree, bummer!
}

function ProcessFirstConfig1() {
	
	global $TDConfig; $TDConfig = BuildTDConfigFromFirstConfigRequest();
	
	$connData = $TDConfig['connections'][GetCurrentDBConnection()];
	if($connData['host'] == '') {
		echo "Please indicate the database host.";
	} elseif($connData['username'] == '') {
		echo "Please indicate the username.";
	} elseif($connData['password'] == '') {
		echo "Please indicate the password.";
	} elseif($connData['schema'] == '') {
		echo "Please indicate the database name.";
	} elseif(OpenTDDBConnection(false)) {
		if(GetSQLValueTD("SELECT SCHEMA_NAME FROM INFORMATION_SCHEMA.SCHEMATA WHERE SCHEMA_NAME=" . SQLValue($connData['schema'])) == false)
			echo "The database name you indicated does not exist.";
		else
			echo "ok";
	} else {
		echo "<p>Connecting to your database using the parameters provided failed.  Please try again!</p>";
	}
}

function ProcessFirstConfig2() {
	switch(Request('authMode')) {
		case "localUNPW":
			if(Request('username') == '')
				echo "Please enter a username.";
			elseif(Request('password') == '')
				echo "Please enter a password.";
			else
				echo "ok";
			break;
		case "session":
			if(Request('sessionName') == '')
				echo "Please enter the name of the session variable to base authentication on.";
			elseif(Request('sessionValue') == '')
				echo "Please enter the value for session-based authentication.";
			else 
				echo "ok";
			break;
		case "skip":
			echo "ok";
			break;
		case "remoteUNPW":
			$targetURL = Request('authBaseURL');
			$result = @file_get_contents($targetURL);
			if($result === false)
				echo "Our HTTP request to <a href=\"$targetURL\" target=\"blank\">$targetURL</a> seems to have failed, " .
					"could not reach that script for authentication.";
			else
				echo "ok";
			break;
		default:
			echo "Please chooose an authentication method.";
	}
}

function ProcessFirstConfig3() {
	
	$TDConfig = BuildTDConfigFromFirstConfigRequest();
	
	// First ensure (crudely) that the indicated base is going to work:
	$baseURL = $TDConfig['options']['baseURL'];
	if(@file_get_contents(HTTPifyURL($baseURL) . "scripts/trueDAT4.js") === false) {
?>
    <p>Problem.</p>
    <p>The resource base URL you indicated,<br />
      <code><a href="<?=$baseURL?>" target="_blank"><?=$baseURL?></a></code><br />
      doesn't seem to have what we need.
    </p>
    <p><a href="<?=$baseURL . "scripts/trueDAT4.js"?>" target="_blank">trueDAT4.js</a> can't be found where it should be relative to that URL.</p>
    <p>Either make sure you've installed trueDAT's supporting files at that URL (such that the above link works for you), or indicate another URL.</p>
    <p><?=DrawButton('Try again', "swapSections('configStep4', 'configStep3');")?></p>
<?
		return;
	}
	
	// Ok, we're on our way!
	$TDData = '<? $TDConfig = ' . printArrayAsPhpCode($TDConfig) .';';
	
	$fh = @fopen('trueDAT4Config.php', 'w');
	if(!$fh) { // looks like writing ourselves will not work, so...
?>
  <h3>Small snag.</h3>
  <p>It looks like your server settings are such that we cannot write the configuration file out to the directory, so we need you to do it.</p>
  <p>Take the following code, paste it into a new file.  Save it as <code>trueDAT4Config.php</code>, and then upload it to the same directory.</p>
  <pre class="configPanel" style="width: 400px;"><?=htmlspecialchars($TDData)?></pre>
  <br />
  <br />
  <?=DrawButton('All done.', 'verifyConfigFileUpload();')?>
  <div id="configStep4Result" class="nextSection">&nbsp;</div>
<?
		
	} else {
		fwrite($fh, $TDData);
		fclose($fh);
		if(file_exists('trueDAT4InstallInProgress'))
			unlink('trueDAT4InstallInProgress'); // we're done with this lock
			
		global $accessKey;
		$_SESSION[$accessKey] = "granted"; // log in the user right away
?>
  <h3>Success</h3>
  <p>Congratulations, trueDAT is all configured and ready to go.</p>
  <?=DrawButton('Use trueDAT', 'window.location.reload();')?>
<?
	}
}

function VerifyConfigFileUpload() {
	if(!ConfigFileExists()) {
?>
  <p>Still not there.</p>
  <p>Try again!</p>
<?	} else {
		unlink('trueDAT4InstallInProgress'); // we're done with this lock
 ?>
  <h3>Success</h3>
  <p>Congratulations, trueDAT is all configured and ready to go.</p>
  <?=DrawButton('Use trueDAT', 'window.location.reload();')?>
<?
	}
}

function ConfigFileExists() {
	if(!file_exists(dirname(__FILE__) . '/trueDAT4Config.php')) return false;
	return true;  // To do: something more clever like verify that the config file parses correctly.  Maybe.  We're all grown adults here.
}

function BuildTDConfigFromFirstConfigRequest() {
	// Build our options data structure from the request parameters + suitable defaults:
	if(Request("autodetect") != '') {
		$connection = GetAutoDetectDBSettings(Request('autodetect'));
		$connection['type'] = Request('db_type');
	} else {
		$connection = array(
			'type' => Request('db_type'),
			'host' => Request('db_host'),
			'username' => Request('db_username'),
			'password' => Request('db_password'),
			'schema' => Request('db_schema'),
		);
	}
	$TDConfig = array(
		'connections' => array( // multiple connections configurable, but just one for now
			$connection
		),
		'authentication' => array(
			'authMode' => Request('authMode'),
			'username' => Request('username'),
			'passwordHash' => sha1(Request('password')),
			'sessionExpression' => "Session('" . Request('sessionName') . "') " . Request('sessionCompare') . " " . ProperPHPLiteral(Request('sessionValue')),
			'authBaseURL' => EnsureEndsWith(Request('authBaseURL'), '/'),
		),
		'options' => array(
			'currentConnection' => 0,
			'baseURL' => EnsureEndsWith(Request('baseURL'), '/'),
			'suggestItems' => "tables&SPs&columns",
			'statementDelimiter' => "\nGO\n",
			'timeElapsedDisplayThreshold' => 1,
			'enableForeignKeySurfing' => false,
		),
	);
	return $TDConfig;
}
function ProperPHPLiteral($valueString) {
	if(ProperNumber($valueString, 'nan') !== 'nan') return $valueString; // is a number, no problem.
	if(in_array(strtolower($valueString), array('true', 'false'))) return $valueString; // is a boolean, no problem
	return "\"". str_replace(array("\\", '"'), array("\\\\", '\"'), $valueString) . "\"";
}

function printArrayAsPhpCode($array, $depth = 1) {
	if(count($array) == 0) return "array()"; // empty, super simple!
	
	$hasKeys = !(array_values($array) === $array);
	$result = "array(" . ($hasKeys ? "\n" : '');
	foreach ($array as $key => $value) {
		if(is_int($value)  ||  is_float($value)) {
			$phpValue = $value;
		} elseif(is_null($value)) {
			$phpValue = 'null';
		} elseif(is_array($value)) {
			$phpValue = printArrayAsPhpCode($value, $depth+1);
		} elseif(is_string($value)) {
			$phpValue = "\"" . str_replace(array("\\", '"', "\n"), array("\\\\", '\"', '\n'), $value) . "\"";
		} elseif(is_bool($value)) {
			$phpValue = ($value ? 'true' : 'false');
		} else {
			trigger_error("Unsupported type of \$value, in index $key. gettype() =" . gettype($value));
		}
		$result .= $hasKeys ? str_repeat("\t", $depth) . "\"$key\" => $phpValue,\n" : "$phpValue, ";
	}
	$result = substr($result, 0, strlen($result) - 2); // Remove last comma.
	$result .= ($hasKeys ? "\n" . str_repeat("\t", $depth-1) : '') . ")"; // close out the array
	return $result;
}


function DisplayApp() {
	global $trueDATBaseURL;
?>
<? if(LoginIsRelevant()) { ?><div id="logOutButton"><? DrawButton("Log Out", "logoutUser();"); ?></div><? } ?>
<script type="text/javascript">
window.addEvent('domready', function() {
	initializeTrueDAT4();
	loadSQLCheatSheet(<?=JSValue(file_get_contents(HTTPifyURL($trueDATBaseURL) . "scripts/trueDATCheatSheet" . GetCurrentDBType() . ".txt"))?>);
});
</script>
<form method="post" action="<?=thisPage?>" id="SQLExportForm">
  <input type="hidden" name="a" value="exportToCSV">
  <input type="hidden" name="SQL" value="">
</form>
<form action="JavaScript: void(0);" id="SQLForm">
<?	if(false) { ?>
<div class="rolledScroll" style="width:800px;">
  <div class="header">
  	<div class="controls">
  	</div>
    <span class="title">Operations Menu</span>
  </div>
<?	} ?>
<? BeginScrollPane('', '800px') ?>
<table class="verticalMiddle" width="100%">
  <tr>
    <td><img src="<?=$trueDATBaseURL?>images/textTables.gif"></td>
    <td class="verticalMiddle">
      <div class="right verticalMiddle">
        <? DrawButton("ID=", "getIDEqualsRecord();"); ?>
        <input type="text" name="IDEquals" value="" style="width: 45px;" onkeypress="JavaScript: return captureEnter(event, getIDEqualsRecord);">
      </div>
      <select name="table" style="width: 250px;"></select>
      <? DrawButton("Schema", "loadSchema();"); ?>
      <? DrawButton("Triggers", "loadTriggers();"); ?>
      <? DrawButton("All", "selectAll();"); ?>
      <? DrawButton("Count", "getCount();"); ?> &nbsp; &nbsp;
      <? DrawButton("Top", "getTopRecords();"); ?>
      <input type="text" name="topCount" value="10" style="width: 30px;"
         onkeypress="JavaScript: return captureEnter(event, getTopRecords);"><span style="font-size: 10px;"></span>
      <input type="checkbox" name="isDesc" checked><span style="font-size: 8px;">DESC</span>
    </td>
  </tr>
  <tr>
    <td><img src="<?=$trueDATBaseURL?>images/textSPs.gif"></td>
    <td>
      <div class="right">
        <? DrawButton("Configure", "loadTrueDATConfigure();"); ?>
        <? DrawButton("Reload Schema", "loadDBStructure();"); ?>
      </div>
      <select name="storedProcedure" style="width: 250px;"></select>
      <? DrawButton("Get Definition", "getStoredProcedureDefinition();"); ?>
    </td>
  </tr>
</table>
<?	EndScrollPane(); ?>

<ul id="tabHolder" class="tabSet"><li class="tab">Tab 1</li></ul>

<div class="scroll" id="SQLPanel">
  <div class="header"><span class="title"></span></div>
  <div class="bodyBottomRightEdge"><div class="body">
    <select id="cheatSheetSelect" tabindex="1" onchange="JavaScript: loadSelectQuery(this);"></select>
    <select id="recentQuerySelect" tabindex="2"
            onchange="JavaScript: loadSelectQuery(this);"
            onblur="JavaScript: loadSelectQuery(this);">
    </select>
    <textarea name="SQL" id="SQLTextArea" tabindex="3"></textarea>
    <div class="right verticalMiddle">
      <input type="checkbox" name="showHTMLWhiteSpace" id="HTMLWhiteSpaceCheckbox"
        ><label for="HTMLWhiteSpaceCheckbox">HTML whitespace</label> &nbsp; &nbsp;
      Truncate to
      <input type="text" name="truncateLength" value="0" style="width: 30px;" tabindex="7"> chars. &nbsp;
      <input type="submit" class="button" value="Execute" tabindex="4" onclick="JavaScript: executeSQL();  return false;">
      <input type="submit" class="button" value="Export"  tabindex="5" onclick="JavaScript: exportToCSV(); return false;">
    </div>
    <div class="verticalMiddle">
      <? DrawButton("Beautify", "beautifyTheSQL();"); ?>
      <? DrawButton("Favorites", "this.toggleClass('pushed'); $('favoritesDiv').toggleClass('hidden')"); ?>
      <select id="toolSelect" onchange="JavaScript: swapToSection('toolSections', this.selectedIndex);
      	if(this.selectedIndex > 0) { this.getNext().fade('in'); }">
      	<option> - Select a tool - </option>
      	<option>Table Transfer</option>
      	<option>CSV Query Generator</option>
      	<option>Value Finder</option>
      </select>
      <div class="button" onclick="JavaScript: swapToSection('toolSections', 0); this.fade('out'); $('toolSelect').selectedIndex = 0;" style="opacity: 0;">dismiss</div>
    </div>
  </form><!--#SQLForm-->
    <div class="clear"></div>
    <div class="nextElement"></div>
    <div id="favoritesDiv" class="hidden" >
      <div id="favoriteQuerySet"></div>
      <div class="nextElement verticalMiddle">
      	Save current query as:
      	<input type="text" name="favoriteQueryName" style="width: 300px;" maxlength="100" onkeypress="JavaScript: return captureEnter(event, addFavoriteQuery);" />
      	<input type="submit" value="Save" onclick="JavaScript: addFavoriteQuery(this); return false;" />
      </div>
      <br />
      <hr />
    </div>
    <div id="toolSections" class="nextElement">
      <div></div>
      <div style="display: none;"><? DrawTableTransferInterface()?></div>
      <div style="display: none;"><? DrawCSVQueryInterface()?></div>
      <div style="display: none;"><? DrawValueFinderInterface(); ?></div>
    </div>
  </div></div>
  <div class="footer"><span class="footerTitle"></span></div>
</div>



<br />
<div class="rolledScroll" id="resultPanels">
  <div class="header"><span class="title"></span></div>
  <div class="bodyBottomRightEdge"><div class="body">
   <div id="resultPanelHolder">
     <div class="resultPanel">
       <h2 class="resultMessage">Welcome to trueDAT4</h2>
       <div class="queryResult">
         <table class="data">
           <tr><th>ID</th><th>name</th><th>sortOrder</th><th>isActive</th><th>isAwesome</th></tr>
           <tr><td>1</td><td>Mr. T</td><td>Always #1, yo.</td><td>True</td><td>True</td></tr>
           <tr><td>2</td><td>John</td><td>2</td><td>True</td><td>True</td></tr>
           <tr><td>4</td><td>Lee</td><td>3</td><td>True</td><td>True</td></tr>
           <tr><td>5</td><td>Rob</td><td>4</td><td>True</td><td>True</td></tr>
           <tr><td>3</td><td>Tom</td><td>5</td><td>False</td><td>True</td></tr>
         </table>
       </div>
       <div class="clear"></div>
     </div>
   </div>
    <? EndScrollPane(); ?>
    
    
    <form action="JavaScript:void(0);" id="tableEditForm" style="display: none;" onsubmit="JavaScript: updateTableField();">
      <input type="hidden" name="dataType" value="">
      <input type="hidden" name="tableName" value="">
      <input type="hidden" name="theID" value="">
      <input type="hidden" name="columnName" value="">
      <input type="hidden" name="activeInput" value="">
      <div id="editorTextBoxControls" class="verticalMiddle">
        <input type="text" name="textbox" value="" style="width: 230px;" tabindex="100">
        <? DrawButton("!", "showCellEditorTextArea();"); ?>
      </div>
      <textarea name="textarea" style="width: 256px; height: 80px;" tabindex="100"></textarea>
      <br />
      <input type="submit" value="Save" tabindex="101">
      <input type="submit" value="Cancel" onclick="JavaScript: App.editPopUp.close(); return false;" tabindex="102" />
    </form>
    
    
    <form action="JavaScript:void(0);" id="columnHideForm" style="display: none;" onsubmit="JavaScript: hideSelectedColumns(this);">
      <div>
        <select name="columnSet" style="width: 150px;" multiple></select><br />
        <div class="nextSection">
          <input type="submit" value="Hide Selected" />
          <input type="checkbox" name="persistShowHide" /> Persist
        </div>
        <?=JSLink('Manage Hidden Columns', 'swapNext(this.getParent())')?>
      </div>
      <div style="display: none;">
      	Persistenly Hidden Columns:<br />
      	<select name="hiddenColumnSet" style="width: auto; min-width: 150px;" multiple></select><br />
        <input type="submit" value="Remove Selected" onclick="JavaScript: removeHiddenColumns(this.form); return false;" class="nextSection" /><br />
        <?=JSLink('Back', 'swapPrevious(this.getParent())')?>
      </div>
    </form>
<?
}




/*============================================================================
SECTION :: Database Interactions                                            */
$TDDB_connectionIsOpen	= false;
$TDDB_connection		= null;
function OpenTDDBConnection($dieOnFail = true) {
	global $TDDB_connectionIsOpen, $TDDB_connection, $TDConfig;
	if($TDDB_connectionIsOpen) return true;
	$connData = $TDConfig['connections'][GetCurrentDBConnection()];
	switch($connData['type']) {
		case "MSSQL":
			$connectionInfo = array('UID' => $connData['username'], 'PWD' =>$connData['password'], 'Database' => $connData['schema']);
			$connectionInfo = array('Database' => $connData['schema']);
			$TDDB_connection = sqlsrv_connect($connData['host'], $connectionInfo);
			if(!$TDDB_connection) {
				if($dieOnFail) {
					var_dump($connData);
					echo('Connection to MSSQL at ' . $connData['host'] . ' failed!');
					die( print_r( sqlsrv_errors(), true));
				}
				else
					return false;
			}
			break;
		case "MySQL":
			$TDDB_connection = mysqli_connect($connData['host'], $connData['username'], $connData['password'], $connData['schema']);
			if(!$TDDB_connection) {
				if($dieOnFail)
					die('Connection to MySQL at ' . $connData['host'] . ' failed!');
				else
					return false;
			}
			
			mysqli_query($TDDB_connection, "SET sql_mode='NO_BACKSLASH_ESCAPES'"); // to avoid \' shenanigans
			break;
		default: // unsupported DB type!
			return false;
	}
	$TDDB_connectionIsOpen = true;
	return true;
}
function CloseTDDBConnection() {
	global $TDDB_connectionIsOpen, $TDDB_connection;
	if(!$TDDB_connectionIsOpen) return;
	switch(GetCurrentDBType()) {
		case "MySQL": mysqli_close($TDDB_connection); break;
		case "MSSQL": break;  // later.
	}
	$TDDB_connectionIsOpen = false;	
}

function ExecuteSQLTD2($SQL, $dieOnFail = true) { echo "</hr>$SQL"; return ExecuteSQLTD($SQL, $dieOnFail); }
function ExecuteSQLTD($SQL, $dieOnFail = true) {
	OpenTDDBConnection();
	global $TDDB_connection;
	switch(GetCurrentDBType()) {
		case "MySQL":
			$xRS = mysqli_query($TDDB_connection, $SQL);
			if(!$xRS  &&  $dieOnFail) { die("<hr />Invalid SQL:<br />$SQL<br /><br />" . mysqli_error($TDDB_connection)); } 
			break;
		case "MSSQL":
			$xRS = sqlsrv_query($TDDB_connection, $SQL, array(), array("Scrollable" => 'static'));
			if(!$xRS  &&  $dieOnFail) { die("<hr />Invalid SQL:<br />$SQL<br /><br />" . mysqli_error($TDDB_connection)); }
			break;
	}
	return $xRS;
}
function rs_num_rows($xRS) {
	switch(GetCurrentDBType()) {
		case "MySQL": return mysqli_num_rows($xRS);  break;
		case "MSSQL": return sqlsrv_num_rows($xRS); break;
	}
}
function rs_num_fields($xRS) {
	switch(GetCurrentDBType()) {
		case "MySQL": return mysqli_num_fields($xRS);  break;
		case "MSSQL": return sqlsrv_num_fields($xRS); break;
	}
}
function rs_get_field_names($xRS) {
	$resultSet = array(); 
	switch(GetCurrentDBType()) {
		case "MySQL":
			$fieldCount = mysqli_num_fields($xRS);
			for($fLoop = 0; $fLoop < $fieldCount; $fLoop++) {
				$theField = mysqli_fetch_field($xRS);
				$resultSet[] = $theField->name;
			}
			break;
		case "MSSQL":
			$fieldSet = sqlsrv_field_metadata($xRS);
			foreach($fieldSet  as $field) {
			//	var_dump($field);
				$resultSet[] = $field['Name'];
			}
			break;
	}
//	var_dump($resultSet);
	return $resultSet;
}
function rs_fetch_array($xRS) {
	switch(GetCurrentDBType()) {
		case "MySQL": return mysqli_fetch_array($xRS);  break;
		case "MSSQL": return sqlsrv_fetch_array($xRS, SQLSRV_FETCH_BOTH); break;
	}
}
function rs_fetch_fields($xRS) {
	$result = array();
	switch(GetCurrentDBType()) {
		case "MySQL":
			$fieldCount = mysqli_num_fields($xRS);
			for($fLoop = 0; $fLoop < $fieldCount; $fLoop++) {
				$result[] = mysqli_fetch_field($xRS);
			}
			break;
		case "MSSQL":
			$result = sqlsrv_field_metadata($xRS);
			break;
	}
	return $result;
}
function GetTableColumnRS($tableName, $extraWhere = '1=1') {
	switch(GetCurrentDBType()) {
		case "MySQL": return ExecuteSQLTD("SHOW COLUMNS FROM $tableName"); break;
		case "MSSQL": return ExecuteSQLTD(
			"SELECT syscolumns.name AS column_name
			   FROM sysobjects
			  INNER JOIN syscolumns ON sysobjects.id = syscolumns.id 
			  WHERE sysobjects.xtype = 'U'
				AND $extraWhere
				AND sysobjects.name=" . SQLValue($tableName) . "
			  ORDER BY syscolumns.colid");
			break;
	}
}
function GetRSFieldSet($xRS) {
	$fieldSet = array();
	switch(GetCurrentDBType()) {
		case "MySQL":
			$fieldCount = mysqli_num_fields($xRS);
			for($fLoop = 0; $fLoop < $fieldCount; $fLoop++) {
				$field = mysqli_fetch_field($xRS);
				$fieldSet[] = array(
					'type' => ConvertMySQLiTypeCode($field->type),
					'name' => $field->name,
					'table'=> $field->table);
			}
			break;
		case "MSSQL":
			$rawFields = sqlsrv_field_metadata($xRS);
			foreach($rawFields as $field) {
				$fieldSet[] = array(
					'type' => GetTypeLabelForMSSQLTypeCode($field['Type']),
					'name' => $field['Name'],
					'table'=> null);
			}
			break;
	}
	return $fieldSet;
}
function ConvertMySQLiTypeCode($typeCode) { // return $typeCode;
	switch($typeCode) {
		case 16: return 'boolean';
		case 1: case 2: case 3: case 6: case 8: case 9:		return 'int';
		case 4: case 5: case 246:							return 'number';
		case 10: case 12: case 7: case 11: case 13:			return 'datetime';
		case 252: case 253: case 254:						return 'string';
	}
}
function GetNextResultRecordSet(&$xRS) {
	switch(GetCurrentDBType()) {
		case "MySQL": return false;  break;
		case "MSSQL": return sqlsrv_next_result($xRS); break;
	}
}
function GetTypeLabelForMSSQLTypeCode($type) {
	if($type == -7) return 'boolean';
	if(in_array($type, array(1, -8, -10, -9, -1, 12, -152))) return 'text';
	if(in_array($type, array(91, 92, 93, -154))) return 'datetime';
	if(in_array($type, array(3, 6, 4, 3, 2, 7, 5, 3, -2, -6))) return 'number';
	return 'text';
}
function SetLimitSyntax($SQL, $limit) {
	if($limit <= 0) return $SQL;
	switch(GetCurrentDBType()) {
		case "MySQL": return "$SQL LIMIT $limit";  break;
		case "MSSQL": $result = str_replace("SELECT ", "SELECT TOP $limit ", $SQL); return $result; break;
	}
}

function FormatMSSQLErrors($errors) {
    foreach ($errors as $error) {
		echo "SQLSTATE: ".$error['SQLSTATE']."<br/>";
		echo "Code: ".$error['code']."<br/>";
		echo "Message: ".$error['message']."<br/>";
	}
}

function FormatRSDate($xR, $index) {
	
	switch(GetCurrentDBType()) {
		case "MySQL": $result = MySQLDateToString($xR[$index]);  break;
		case "MSSQL":
			if(is_null($xR[$index])) return '';
		//	var_dump($xR[$index]);  // FUNKY: when a var_dump is done, $xR[$index]->date) formats and outputs... otherwise blank?!?
			$dateValue = ProperDate($xR[$index]->date);
			$result = date($dateValue % 60*60*24 == 0 ? 'm/d/Y' : ($dateValue % 60 == 0 ? 'm/d/Y g:ia' : 'm/d/Y g:i:sa'), $dateValue);
			break;
	}
	return str_replace(' ', '&nbsp;', $result);
}
function GetSQLValueTD2($SQL, $f = '', $ordinal = 0) { echo "</hr>$SQL"; return GetSQLValueTD($SQL, $f, $ordinal); }
function GetSQLValueTD($SQL, $fallback = '', $ordinal = 0) {
	$xR = rs_fetch_array(ExecuteSQLTD($SQL));
	return $xR ? $xR[$ordinal] : $fallback;
}
function GetCurrentDBConnection() { global $TDConfig; return ProperInt($TDConfig['options']['currentConnection']); }
function GetCurrentDBName() { global $TDConfig; return $TDConfig['connections'][GetCurrentDBConnection()]['schema']; }
function GetCurrentDBType() { global $TDConfig; return $TDConfig['connections'][GetCurrentDBConnection()]['type']; }
/*==== End SECTION :: Database Interactions ================================*/


function ExportToCSV() { WriteAndDeliverRSAsCSV(ExecuteSQLTD($_REQUEST["SQL"]), "trueDATExport.csv"); }

function PerformSQLExecution() {
	
	// Split on our statementDelimiter (approximate SQL multi-query behaviour)
	global $TDConfig;
	$statementDelimiter = $TDConfig['options']['statementDelimiter'];
	$rawSQLSet = explode($statementDelimiter, str_replace("\'", "'", $_POST["SQL"]));
	$SQLSet = array();
	foreach($rawSQLSet as $SQL) { // filter out empties by hand... array_filter doesn't reindex, ugh!
		if(strlen(trim($SQL)) > 0)
			$SQLSet[] = $SQL;
	}
	
	$resultSet = array();
	for($sLoop = 0; $sLoop < sizeof($SQLSet); $sLoop++) {
		tic();
		$tRS = ExecuteSQLTD($SQLSet[$sLoop]);
		$timeElapsed = toc();
		$hasResultRows = (rs_num_rows($tRS) !== false);
		
	  do {
			
		if($sLoop == 0) {
			if($hasResultRows) {
				$recordCount = rs_num_rows($tRS);
				$resultCountMessage = ($recordCount <= 0 ? '' : " : $recordCount record" . ConditionalMark($recordCount != 1, "s") . " returned");
				$resultMessage = "Execution Result $resultCountMessage";
			} else
				$resultMessage = "Execution Result";
			echo "<h2 class=\"resultMessage\">$resultMessage</h2>";
		}
		echo "<div class=\"queryResult\">";
		
		if($sLoop > 0)
			echo "<hr><div style=\"margin-bottom: 10px;\"><strong>Next Query:</strong> " . TruncatedString($SQLSet[$sLoop], 100) . "</div>";
		
		if($hasResultRows) {
			DrawButton('Hide', 'loadShowHideColumnForm(this);', 'right');
			DrawButton('Sort', 'makeColumnsSortable(this);', 'right');
			if(rs_num_rows($tRS) > 0) DrawHiddenButton("Edit", "toggleEditMode(this);", 'edit');
			DrawHiddenButton("Add New", "enterAddMode(this);", 'edit');
			echo "<div class=\"clear\"></div>";
		}
		
		$columnDataTypeSet = array();
		$columnTableSet = array(); // the names of the tables that each column belongs to
		echo "<table class=\"data\" style=\"margin-top: 10px;\">";
		if($hasResultRows) {
			$fieldSet = GetRSFieldSet($tRS);
			$fieldCount = count($fieldSet);
			
			// Render the header row:
			echo "<tr>";
			foreach($fieldSet as $field) {
				$columnDataTypeSet[] = $field['type'];
				$columnTableSet[] = $field['table'];
				echo "<th>{$field['name']}</th>";
			}
			echo "</tr>";
			
			$showHTMLWhiteSpace = RequestCheckbox("showHTMLWhiteSpace")  ||  BeginsWith(strtoupper($SQLSet[$sLoop]), 'SHOW CREATE TABLE ');
			
			// Render the data rows:
			while($tR = rs_fetch_array($tRS)) {
				echo "<tr>\n";
				for($fLoop = 0; $fLoop < $fieldCount; $fLoop++) {
					if($columnDataTypeSet[$fLoop] == 'boolean'  ||  $tR[$fLoop] == chr(0x01)  ||  $tR[$fLoop] == chr(0x00)) {
						if(is_null($tR[$fLoop]))
							$displayValue = '';
						elseif($tR[$fLoop] == chr(0x01)  ||  $tR[$fLoop] == chr(0x00))
							$displayValue = ($tR[$fLoop] == chr(0x01) ? "True" : "False");
						else
							$displayValue = (ProperInt($tR[$fLoop]) == 1 ? 'True' : 'False');
						if($columnDataTypeSet[$fLoop] != 'boolean')
							$columnDataTypeSet[$fLoop] = 'boolean'; // correct for "unknown" datatype, ugh!
					}
					elseif($columnDataTypeSet[$fLoop] == 'datetime') {
						$displayValue = FormatRSDate($tR, $fLoop);
					}
					else
						$displayValue = htmlspecialchars(TruncatedString($tR[$fLoop], RequestInt("truncateLength", 0)));
					
					if($showHTMLWhiteSpace)
						$displayValue = HTMLWhiteSpace($displayValue);
					
					echo "<td>$displayValue</td>";
				}
				echo "</tr>\n";
			}
		}
		else { // display number of rows affected
			$ar = mysqli_affected_rows();
			echo "<tr><td>" . (ProperInt($ar, 'x') != 'x' ? $ar . " record" . PluralS($ar) . " affected" : "Execution Successful") . "</td></tr>";
		}
?>
</table>
<? if($timeElapsed > $TDConfig['options']['timeElapsedDisplayThreshold']) { ?><?=$timeElapsed?> seconds elapsed.<? } ?>
<br/>
<input type="submit" class="button add" style="display: none;" value="Add Record" onclick="JavaScript: addNewRow(this);  return false;">
</div><div class="clear"></div>
<?
		$resultSet[] = array(
			'SQL' => $SQLSet[$sLoop],
			'columnDataTypeSet' => $columnDataTypeSet,
			'columnTableSet' => $columnTableSet);
		
	  } while($hasMore = GetNextResultRecordSet($tRS));
	} // next item in the SQLSet, in case split on $statementDelimiter yielded multiple queries
	
?>	
<script type="text/javascript">
	App.currentQueryState = {
		resultSet: <?=json_encode($resultSet)?>
	};
</script>
<?
}


function DeleteTableRow() {
	$deleteSQL = "DELETE FROM " . Request("tableName") . "
		  WHERE " . GetTablePrimaryKey(Request("tableName")) . "=" . RequestInt("theID");
	global $TDDB_connection;
	ExecuteSQLTD($deleteSQL, false);
	if(mysqli_errno($TDDB_connection) != 0) // uh oh, we'll display what went wrong
		echo "Error: " . mysqli_error($TDDB_connection) . ".<hr />$deleteSQL";
	else
		echo 'ok';
}

function FetchTableField() {
	echo GetSQLValueTD("SELECT " . Request("columnName") . " FROM " . Request("tableName") .
		" WHERE " . GetTablePrimaryKey(Request("tableName")) . '=' . RequestInt("theID", 0));
}

function UpdateTableField() {
	
	$theNewValue = Request(Request("activeInput"));
	$theSQLValue = $theEchoValue = '';
	if($theNewValue == "*NULL*")  // it's like magic, yo.
		$theSQLValue = "NULL";
	else {
		switch(strtolower(Request("dataType"))) {
			case "boolean":
				$theSQLValue  = SQLBit(strlen($theNewValue) > 0);
				$theEchoValue = (strlen($theNewValue) > 0 ? 'True' : 'False');
				break;
			case "int":
				$theSQLValue  = ProperInt($theNewValue, "null");
				$theEchoValue = ProperInt($theNewValue, "");
				break;
			case "float": case "currency": case "real":
				$theSQLValue  = ProperNumber($theNewValue, "null");
				$theEchoValue = ProperNumber($theNewValue, "");
				break;
			case "date":
				$theSQLValue  = SQLDate(ProperDate($theNewValue, "null"));
				$theEchoValue = ProperDate($theNewValue, "");
				break;
			case "datetime":  // MySQL
				$theSQLValue  = SQLDate(ProperDate($theNewValue, "null"));
				$theEchoValue = MySQLDateToString(trim($theSQLValue, "\'"));
				break;
			case "string": case "blob":
				$theSQLValue  = SQLValue($theNewValue);
				$theEchoValue = htmlspecialchars($theNewValue);
				break;
		}
	}
	$updateSQL =
		"UPDATE " . Request("tableName") . "
		    SET " . Request("columnName") . "=$theSQLValue
		  WHERE " . GetTablePrimaryKey(Request("tableName")) . "=" . RequestInt("theID", 0);
	ExecuteSQLTD($updateSQL, false);
	
	global $TDDB_connection;
	if(mysqli_error($TDDB_connection) != 0) // uh oh, we'll display what went wrong
		echo "Error: " . mysqli_error($TDDB_connection) . "<hr />...no UPDATE occurred.<br />" . $updateSQL;
	else
		echo $theEchoValue;
}

function AddTableRow() {
	
	$tableName = Request("tableName");
	$columnNameList = Request('columnNameList');
	$columnNameSet = explode(', ', $columnNameList);
	$columnDataTypeSet = explode(', ', Request('columnDataTypeList'));
	
	$valueSet = array();
	$displayValueSet = array();
	for($i = 1; $i < count($columnDataTypeSet); $i++) { // $i=1 to skip ID column, per the convention
		$rawValue = Request("newField$i");
		switch($columnDataTypeSet[$i]) {
			case 'boolean':		$thisValue = SQLBit($rawValue);		$displayValueSet[] = ProperBoolean($rawValue) ? 'True' : 'False';	break;
			case 'datetime':	$thisValue = SQLDate($rawValue);	$displayValueSet[] = $rawValue;										break;
			default:			$thisValue = SQLValue($rawValue);	$displayValueSet[] = $rawValue;										break;
		}
		$valueSet[] = $thisValue;
	}
	
	$insertSQL = "INSERT INTO $tableName($columnNameList) VALUES(" . implode(', ', $valueSet) . ")";
	ExecuteSQLTD($insertSQL, false);
	
	global $TDDB_connection;
	if(mysqli_errno($TDDB_connection) != 0) {
		// uh oh, we'll display what went wrong and then quit
?>
  <td colspan="<?=(count($columnNameSet) + 1)?>" class="clickable" onclick="JavaScript: $(this).getParent().dispose();">
    Error in the SQL that was generated, no INSERT occurred:<hr />
    <?=$insertSQL?><br />
</td>
<?
		return;
	} else { // render the new row:
		echo "<td>" . mysqli_insert_id($TDDB_connection) . "</td>";
		for($c = 0; $c < sizeof($columnNameSet); $c++) {
			echo "<td>" . htmlspecialchars($displayValueSet[$c]) . "</td>";
		}
	}
}




/****************************************************************************
//	SECTION::Table Transfer
*/
function DrawTableTransferInterface() {
?>
<table width="540" align="center" class="top">
 <tr>
  <td width="250">
    <b>Export Tables</b>
    <form method="post" action="<?=thisPage?>" onsubmit="JavaScript: return beginTableTransferExport(this);">
      <input type="hidden" name="a" value="tableTransferExport">
      <select id="tableTransferExportSelect" name="tableSet[]" multiple style="width: 250px;"></select><br/>
      Limit: <input type="text" name="limit" value="0" style="width: 50px;" /> (zero for all)<br />
      <div class="nextElement">
        <input type="submit" value="Download Table Export File">
      </div>
    </form>
  </td>
  <td width="40"></td>
  <td width="250">
    <b>Import Tables</b>
  	<div id="uploadedTableTransferState"><? DisplayTableTransferState(); ?></div>
    <form method="post" action="<?=thisPage?>" target="TableTransferUploadIFrame" id="tableTransferUploadForm"
           enctype="multipart/form-data" onsubmit="JavaScript: return beginTableTransferUpload(this);">
      <input type="hidden" name="a" value="tableTransferUpload" />
      Upload an export .zip file:
      <input type="file" name="theFile">
      <input type="submit" value="Upload" class="button" />
    </form>
    <iframe name="TableTransferUploadIFrame" style="display: none; width: 400px; height: 300px;"></iframe>
  </td>
 </tr>
</table>
<iframe name="tableTransferImportIFrame" id="tableTransferImportIFrame" class="toolIFrame" style="display: none;"></iframe>
<?
}

function DisplayTableTransferState() {
	
	// First establish if we have an uploaded file:
	$uploadFileName = GetUploadedTableTransferFileName();
    if($uploadFileName) {
    	
		$importTableSet = array();
		$zip = zip_open($uploadFileName);
		if(is_int($zip)) { // not even a valid ZIP file... not a good sign!
			DeleteTableTransferFile();
			echo "<script>App.roar.alert('Please upload a .zip file.');</script>";
			return;
		}
		while($zipFile = zip_read($zip)) {
			$importTableSet[] = TrimTrailing(zip_entry_name($zipFile), ".csv");
		}
?>
<form method="post" action="<?=thisPage?>" target="tableTransferImportIFrame" 
	  onsubmit="JavaScript: return beginTableTransferImport(this);">
  <input type="hidden" name="a" value="tableTransferImport">
  <select name="tableSet[]" multiple size="<?=count($importTableSet)?>" style="width: 250px;">
<?	foreach($importTableSet as $tableName) { ?>
    <option value="<?=$tableName?>"><?=$tableName?></option>
<?	} ?>
  </select><br/>
  <div class="nextElement">
    <input type="submit" value="Truncate then Import Tables" />
    <input type="submit" value="Clear File" onclick="JavaScript: deleteTableTransferFile(); return false;" />
  </div>
</form>
<br />
<?	}
}

function PerformTableTransferExport() {
	$tableSet = $_REQUEST["tableSet"];
	if(!is_array($tableSet)) return;
	
	set_time_limit(999);
	$limit = RequestInt('limit');
	$zip = new ZipArchive;
	$zipFileName = '.trueDATTableTransfer' . makeRandomHash(10) . '_exportZip.zip';
	$tempFileName = 'tableTransferTemp' . makeRandomHash(10) . '_.csv';
	$zip->open($zipFileName, ZIPARCHIVE::OVERWRITE);
	foreach($tableSet as $tableName) {
		$xRS = ExecuteSQLTD(SetLimitSyntax("SELECT * FROM $tableName", $limit));
		WriteRecordSetAsCSV($xRS, $tempFileName);
		clearstatcache();
		$zip->addFromString("$tableName.csv", GetFileText($tempFileName));
	}
	$zip->close();
	unlink($tempFileName);
//	exit();
	DeliverFileAsInlineDownload($zipFileName, 'trueDATTableTransfer.zip');
	unlink($zipFileName);
}
function PerformTableTransferUpload() {
	DeleteTableTransferFile(); // only 1 uploaded at a time!
	$filename = '.trueDATTableTransfer_' . makeRandomHash(10) . '_uploadFile';
	$success = move_uploaded_file($_FILES['theFile']['tmp_name'], $filename);		
?><script>window.top.completeTableTransferUpload(<?=($success ? 'true' : 'false')?>);</script>
<?
}

function PerformTableTransferImport() {
	$oldForeignKeyChecks = GetSQLValueTD('SELECT @@FOREIGN_KEY_CHECKS');
	ExecuteSQLTD('SET FOREIGN_KEY_CHECKS=0');
	
	$tableSet = $_REQUEST["tableSet"];
	if(!is_array($tableSet)) return;
	
	$zip = zip_open(GetUploadedTableTransferFileName());
	set_time_limit(9999);
	BeginIFrame();
	echo "<h4>Commence Table Transfer Import</h4>";
	
	while($zipFile = zip_read($zip)) {
		$tableName = substr(zip_entry_name($zipFile), 0, strlen(zip_entry_name($zipFile))-4); // trim off ".csv"
	//	echo "$tableName<br />";
		if(in_array($tableName, $tableSet)) {
			ImportTableFromCSV($tableName, zip_entry_read($zipFile, zip_entry_filesize($zipFile)));
		}
	}
	zip_close($zip);
	ExecuteSQLTD("SET FOREIGN_KEY_CHECKS=$oldForeignKeyChecks");
	echo "<br />Table Transfer Import complete! " . JSLink('dismiss', "window.top.completeTableTransferImport();", 'button');
}

function ImportTableFromCSV($tableName, $CSVData) {
	
	echo date('g:i:sa') . ": Importing $tableName... ";
	
	// Save out our CSV data to file so we can take advantage of fgetcsv:
	// (there's probably less roundabout way to do this!)
	$tempFileName = "tableTransferInputTemp_" . makeRandomHash(10) . ".csv";
	$fh = fopen($tempFileName, 'w');
	fwrite($fh, $CSVData);
	fclose($fh);
	
	// Get the columns for this table:
	$cRS = ExecuteSQLTD("SHOW COLUMNS FROM $tableName");
	$dataTypeSet = array();
	while($cR = rs_fetch_array($cRS)) {
		$dataTypeSet[$cR['Field']] = $cR['Type'];
	}
	
	$fh = fopen($tempFileName, 'r');
	$fieldNameSet = fgetcsv($fh, 0);
	$fieldNameList = implode(', ', $fieldNameSet);
	ExecuteSQLTD("TRUNCATE TABLE $tableName");
	$rowCount = 0;
	while(($data = fgetcsv($fh, 0)) !== FALSE) {
		$valueSet = array();
		foreach($data as $index => $value) {
			$dataType = $dataTypeSet[$fieldNameSet[$index]];
			if(BeginsWith($dataType, 'bit'))
				$theSQLValue = ($value == '' ? 'NULL' : SQLBit(StringProperBoolean($value)));
			elseif(BeginsWith($dataType, 'int')  ||  BeginsWith($dataType, 'decimal'))
				$theSQLValue = ($value == '' ? 'NULL' : $value);
			elseif(BeginsWith($dataType, 'date'))
				$theSQLValue = ($value == '' ? 'NULL' : SQLDate($value));
			else
				$theSQLValue = SQLValue($value);
			$valueSet[] = $theSQLValue;
		}
		ExecuteSQLTD("INSERT INTO $tableName ($fieldNameList) VALUES(" . implode(', ', $valueSet) . ")");
		$rowCount++;
	}
	fclose($fh);
	unlink($tempFileName);
	echo $rowCount . " record" . PluralS($rowCount) . " done!<br />";
}


function DeleteTableTransferFile() {
	if($uploadFileName = GetUploadedTableTransferFileName())
		unlink($uploadFileName);
}
function GetUploadedTableTransferFileName() { return GetUploadedFileName("/\\.trueDATTableTransfer_.{10}_uploadFile/"); }


/*
//	End SECTION::Table Transfer
****************************************************************************/



/****************************************************************************
//	SECTION::CSV Queries
*/
function DrawCSVQueryInterface() {
?>
<div style="width: 540px; margin: 0 auto;">
  <form method="post" action="<?=thisPage?>" target="CSVUploadIFrame" id="CSVUploadForm"
        enctype="multipart/form-data" onsubmit="JavaScript: return beginCSVUpload(this);">
    <input type="hidden" name="a" value="CSVUpload" />
    Upload a .csv file:
    <input type="file" name="theFile">
    <input type="submit" value="Upload" class="button" />
  </form>
  <iframe name="CSVUploadIFrame" style="display: none; width: 400px; height: 300px;"></iframe>
  <div id="uploadedCSVState"><? DisplayCSVState(); ?></div>
</div>
<?	
}
function PerformCSVUpload() {
	DeleteCSVFile(); // only 1 uploaded at a time!
	$filename = '.trueDATCSV_' . makeRandomHash(10) . '_uploadFile';
	$success = move_uploaded_file($_FILES['theFile']['tmp_name'], $filename);		
?><script>window.top.completeCSVUpload(<?=($success ? 'true' : 'false')?>);</script><?
}

function DisplayCSVState() {
	
	// First establish if we have an uploaded file:
	if(!$uploadFileName = GetUploadedCSVFileName()) return;
	
	$fh = fopen($uploadFileName, 'r');
	$fieldNameSet = fgetcsv($fh, 0);
	
	echo "<div class=\"nextSection\">Insertable CSV fields:<br />";
	foreach($fieldNameSet as $fieldName) {
		if(strlen($fieldName) < 40) // ensure that our CSV was legit/this is a plausible field name:
			echo JSLink("<$$fieldName>", 'insertCSVField(' . JSValue($fieldName) . ')', 'button');
	}
?>
    </div>
<form method="post" action="<?=thisPage?>" target="CSVQueryIFrame" class="nextElement"
	  onsubmit="JavaScript: return beginCSVQuery(this);">
  <input type="hidden" name="a" value="CSVQuery">
  <input type="hidden" name="mode" value="preview">
  CSV Query:<br />
  <textarea name="CSVSQL" id="CSVSQL" style="width: 540px; height: 100px;"></textarea><br/>
  <div class="nextElement verticalMiddle">
  	For first <input type="text" name="limit" style="width: 40px" /> rows
    <input type="submit" value="Execute CSV" onclick="JavaScript: this.form.mode.value = 'execute';" />
    <input type="checkbox" name="verbose" id="CSVVerboseCheckbox">
    <label for="CSVVerboseCheckbox">print queries</label> &nbsp; &nbsp; OR
    <input type="submit" value="Preview CSV" onclick="JavaScript: this.form.mode.value = 'preview';"  />
    <input type="submit" value="Clear File" class="right" onclick="JavaScript: deleteCSVFile(); return false;" />
  </div>
  <iframe name="CSVQueryIFrame" id="CSVQueryIFrame" class="toolIFrame" style="display: none;"></iframe>
</form>
<br />
<?
}

function PerformCSVQuery() {
	if(!$uploadFileName = GetUploadedCSVFileName()) return;
	
	$execute = (Request('mode') == 'execute');
	$verbose = RequestCheckbox('verbose');
	$limit = RequestInt('limit');
	$CSVSQL = Request("CSVSQL");
	$fh = fopen($uploadFileName, 'r');
	$fieldNameSet = fgetcsv($fh, 0);
	$tagSet = array();
	foreach($fieldNameSet as $fieldName) { $tagSet[] = "<$$fieldName>"; }
	
	set_time_limit(9999);
	BeginIFrame();
	echo "<h4>CSV Query " . ($execute ? "Execution" : "Preview") . "</h4>";
	
	$rowCount = 0;
	$failCount = 0;
	global $TDDB_connection;
	while(($CSVSet = fgetcsv($fh, 0)) !== FALSE  &&  ($limit <= 0  ||  $rowCount < $limit)) {
		// Generate this SQL as a plug-and-chug substitution from the CSV row data:
		$SQL = $CSVSQL;
		foreach($tagSet as $index => $tag) {
			$SQL = str_replace($tag, SQLSafe($CSVSet[$index]), $SQL);
		}
		$failure = false;
		$rowCount++;
		if($execute) {
			if(!ExecuteSQLTD($SQL, false)) {
				$failure = true;
				$failCount++;
			}
		}
		
		if($verbose  ||  !$execute  ||  $failure) {
			if($failure) {
				echo "<span class=\"alert\">$SQL<br />Error: <code>" . mysqli_error($TDDB_connection) . "</code></span><hr />";
			} else {
				echo "$SQL<hr />";
			}
		}
		if($failCount == $rowCount  &&  $rowCount >= 3) {
			echo "This doesn't seem to be going well--we'll wrap up early and allow you to re-tool!<br />";
			break;
		}
	}
	fclose($fh);
	echo "<br />CSV Query Complete. " . JSLink('dismiss', "window.top.completeCSVQuery();", 'button');
}

function DeleteCSVFile() { if($uploadFileName = GetUploadedCSVFileName()) unlink($uploadFileName); }
function GetUploadedCSVFileName() { return GetUploadedFileName("/\\.trueDATCSV_.{10}_uploadFile/"); }
/*
//	End SECTION::CSV Queries
****************************************************************************/



/****************************************************************************
//	SECTION::Value Finder
*/
function DrawValueFinderInterface() {
?>
<div style="width: 540px; margin: 0 auto;">
 <b>Find A Value</b>
 <form method="post" action="<?=thisPage?>" target="valueFinderIFrame"
	  onsubmit="JavaScript: return beginValueFinder(this);">
  <input type="hidden" name="a" value="findValue">
  <input type="hidden" name="which" />
  <table width="540" align="center" class="top">
    <tr>
      <td width="90">Find a string:</td>
      <td>
      	<input type="text" name="string" style="width: 278px;" />
      	&nbsp;
      	<input type="checkbox" name="like" id="valueLikeCheckbox" checked />
      	<label for="valueLikeCheckbox">LIKE '%__%'</label>
      </td>
      <td width="42"><input type="submit" value="Go" onclick="JavaScript: this.form.which.value='string';" /></td>
    </tr>
    <tr>
      <td>Find a number:</td>
      <td><input type="text" name="number" style="width: 380px;" /></td>
      <td><input type="submit" value="Go" onclick="JavaScript: this.form.which.value='number';" /></td>
    </tr>
    <tr>
      <td>Find a date:</td>
      <td><input type="text" name="date" style="width: 380px;" /></td>
      <td><input type="submit" value="Go" onclick="JavaScript: this.form.which.value='date';" /></td>
    </tr>
  </table>
 </form>
 <iframe name="valueFinderIFrame" id="valueFinderIFrame" class="toolIFrame" style="display: none;"></iframe>
</div>
<?
}

function PerformValueFind() {
	
	$which = Request('which');
	$like = RequestCheckbox('like');
	switch($which) {
		case 'string':	$value = $like ? "'%" . SQLSafe(Request('string')) . "%'" : SQLValue(Request('string')); break;
		case 'number':	$value = RequestNumber('number');										break;
		case 'date':	$value = RequestDate('date') ? SQLDate((RequestDate('date'))) : false;	break;
	}
	if(!$value) return;
	
	set_time_limit(999);
	BeginIFrame();
?>
  <h4>Value Finder Results</h4>
<?
	
	$whichTypeClauseSet = array(
		'string' => "Type LIKE '%char%' OR Type LIKE '%text%'",
		'number' => "Type LIKE 'int%' OR Type LIKE 'float%' OR Type LIKE 'decimal%'",
		'date'	 => "Type IN ('date', 'datetime', 'timestamp')",
	);
	
	// Iterate through every table, query appropriate columns for each based on $which type of search:
	$hasResults = false;
	$tRS = ExecuteSQLTD("SHOW TABLES");
	while($tR = rs_fetch_array($tRS)) {
		$tableName = $tR[0];
		
		// Iterate through columns to find relevant ones:
		$valueClauseSet = array();
		$cRS = ExecuteSQLTD("SHOW COLUMNS FROM `$tableName` WHERE {$whichTypeClauseSet[$which]}");
		while($cR = mysqli_fetch_assoc($cRS)) {
			$valueClauseSet[] = "`{$cR['Field']}`" . (($which == 'string' &&  $like) ? " LIKE " : " = ") . $value;
		}
		if(count($valueClauseSet) > 0) { // this table is worth searching in!
			$valueClause = implode(" OR ", $valueClauseSet);
			$resultCount = GetSQLValueTD("SELECT COUNT(*) FROM $tableName WHERE $valueClause");
			if($resultCount > 0) {
				if(!$hasResults) {
					echo "<table class=\"data\"><tr><th>Table</th><th>Records</th><th>Actions</th></tr>";
					$hasResults = true;
				}
				// Now sort out which column(s) led to us getting results in this table:
				$relevantClauseSet = array();
				$relevantFieldSet = array();
				foreach($valueClauseSet as $fieldClause) {
					if(GetSQLValueTD("SELECT COUNT(*) FROM $tableName WHERE $fieldClause") > 0) {
						$relevantClauseSet[] = $fieldClause;
						preg_match("/^`(.+)`/", $fieldClause, $matchSet); // what was that field name again?
						$relevantFieldSet[] = $matchSet[1];
					}
				}
				$relevantClause = implode("\n    OR ", $relevantClauseSet);
				$theSQL = JSValue("SELECT * FROM $tableName\n WHERE $relevantClause");
?>
  <tr>
    <td><?=$tableName?></td>
    <td style="text-align: right;"><?=$resultCount?></td>
    <td class="verticalMiddle">
      <?=DrawButton('SELECT', "window.top.selectValueFinderResults($theSQL);")?> &nbsp; &nbsp;
      <?=DrawButton('Add SELECT', "window.top.selectValueFinderResults($theSQL, true);")?> &nbsp; &nbsp;
      <?=DrawButton('Replace with...', "$(this).setStyle('display', 'none'); $(this).getNext().setStyle('display', 'block');")?>
      <div class="NextElement" style="display: none;">
        Replace with <input type="text" name="replace" style="width: 180px;" />
         <div class="button" onclick="JavaScript: window.top.replaceValueFinderResults(<?=JSValue($tableName)?>,
         	'<?=implode("', '", $relevantFieldSet)?>',
         	<?=JSValue($relevantClause)?>,
         	<?=JSValue(SQLSafe(Request('string')))?>,
         	this.getPrevious().value);">Go</div>
      </div>
    </td>
  </tr>
<?
			}
		}
	}
	
	if($hasResults)
		echo "</table>";
	else
		echo "The $which $value could not be found.<br />";
?>
  <br />Value Find complete. <?=JSLink('dismiss', "window.top.completeValueFinder();", 'button')?>
<?
}
/*
//	End SECTION::Value Finder
****************************************************************************/


function GetUploadedFileName($fileNamePattern) {
	$result = false;
	$theFolder = dir(dirname(__FILE__));
	while(($entry = $theFolder->read()) !== false) {
		if(preg_match($fileNamePattern, $entry)) { // found it!
			$result = $entry;
			break;
		}
	}
    $theFolder->close();
    return $result;
}

function BeginIFrame() { 
	global $trueDATBaseURL;
?>
<html>
<head>
  <link href="<?=$trueDATBaseURL?>css/trueDAT4.css" media="screen" rel="Stylesheet" type="text/css" />
  <script type="text/javascript" src="<?=$trueDATBaseURL?>scripts/mootools-core-1.4.1.js"></script>
</head>
<body class="iframe">
<?
}

function GetTablePrimaryKey($tableName) {
	switch(GetCurrentDBType()) {
		case 'MySQL':
			$cR = mysqli_fetch_assoc(ExecuteSQLTD("SHOW KEYS FROM `$tableName` WHERE Key_name = 'PRIMARY'"));
			return $cR['Column_name'];
			break;
		case 'MSSQL':
			return GetSQLValueTD(
				"SELECT name FROM syscolumns
				  WHERE id=(SELECT ID FROM sysobjects WHERE name='$tableName')
				    AND colid=(SELECT SIK.colid FROM sysindexkeys SIK INNER JOIN sysobjects SO ON SIK.id=SO.id WHERE SIK.indid=1 AND SO.name='$tableName')");
			break;
	}
	
}
function GetShowTablesSQL() {
	switch(GetCurrentDBType()) {
		case 'MySQL':	return "SHOW TABLES";														break;
		case 'MSSQL':	return "SELECT name FROM sysobjects WHERE xtype='U' ORDER BY name";			break;
	}
}
function GetShowSPsSQL() {
	switch(GetCurrentDBType()) {
		case 'MySQL':	return "SHOW PROCEDURE STATUS WHERE Db=" . SQLValue(GetCurrentDBName());	break;
		case 'MSSQL':	return "SELECT 1 AS X, name FROM sysobjects WHERE xtype='P' ORDER BY name";	break;
	}
}

function LoadDBStructure() {
	$tableSet  = array();
	$tableLabelSet  = array();
	$primaryKeySet = array();
	$tRS = ExecuteSQLTD(GetShowTablesSQL());
	while($tR = rs_fetch_array($tRS)) {
		$tableName = $tR[0];
		$tableSet[] = $tableName;
		$tableLabelSet[] = "$tableName (" . GetSQLValueTD("SELECT COUNT(*) FROM {$tR[0]}") . ")";
		$primaryKeySet[$tableName] = GetTablePrimaryKey($tableName);
	}
	
	$SPSet = array();
	$spRS = ExecuteSQLTD(GetShowSPsSQL());
	while($spR = rs_fetch_array($spRS)) {
		$SPSet[] = $spR[1];
	}
	
	$foreignKeySet = array();
	global $TDConfig;
	if($TDConfig['options']['enableForeignKeySurfing']) { // this approach takes so long, and still fails to find foreign keys in many databases... ugh!
		set_time_limit(199);
		$fkRS = ExecuteSQLTD(
			"SELECT CONCAT(TABLE_NAME, '.', COLUMN_NAME) AS FKColumn,
					REFERENCED_TABLE_NAME  AS parentTable, 
					REFERENCED_COLUMN_NAME AS parentRow
			   FROM information_schema.KEY_COLUMN_USAGE
			  WHERE REFERENCED_TABLE_SCHEMA = " . SQLValue(GetCurrentDBName()));
		while($fkR = rs_fetch_array($fkRS)) {
			$foreignKeySet[$fkR['FKColumn']] = array($fkR['parentTable'], $fkR['parentRow']);
		}
	}
	
	// Now gather all of the items to suggest basd on the option:
	$suggestionSet = array();
	$suggestItemSet = explode('&', $TDConfig['options']['suggestItems']);
	if(in_array('tables', $suggestItemSet))		// include tables
		$suggestionSet = $tableSet;
	if(in_array('columns', $suggestItemSet)) {	// include columns
		foreach($tableSet as $tableName) {
			$cRS = GetTableColumnRS($tableName);
			while($cR = rs_fetch_array($cRS)) {
				$suggestionSet[] = $cR[0];
			}
		}
	}
	if(in_array('SPs', $suggestItemSet)) {		// include stored procedures
		foreach($SPSet as $SPName) {
			$suggestionSet[] = $SPName;
		}
	}
	$suggestionSet = array_values(array_unique($suggestionSet)); // remove duplicates and keep it a straight numeric indexed array
		
	$DBData = array(
		'databaseType' => GetCurrentDBType(),
		'statementDelimiter' => $TDConfig['options']['statementDelimiter'],
		'tableSet' => $tableSet,
		'tableLabelSet' => $tableLabelSet,
		'tablePrimaryKeySet' => $primaryKeySet,
		'suggestionSet' => $suggestionSet,
		'foreignKeySet' => $foreignKeySet,
		'SPSet' => $SPSet);
	echo json_encode($DBData);
}


function GetStoredProcedureDefinition() {
	$SPName = StraightRequestText('SPName');
	global $TDConfig;
	$statementDelimiter = $TDConfig['options']['statementDelimiter'];
	switch(GetCurrentDBType()) {
		case 'MySQL':
			$xR = mysqli_fetch_array(ExecuteSQLTD("SHOW CREATE PROCEDURE $SPName"));
			$spText = "DROP PROCEDURE IF EXISTS $statementDelimiter$delim{$xR[2]}";
			break;
		case 'MSSQL':
		
			break;
	}
	echo $spText;
}



function MySQLDateToString($dateValue) {
	$dateParts = preg_split('/[: -]/', $dateValue . " 00");
	if(sizeof($dateParts) >= 6) {
		list($year, $month, $day, $hour, $min, $sec) = $dateParts;
		return "$month/$day/$year" . ($hour + $min + $sec > 0 ? " $hour:$min" : '') . ($sec == "00" ? "" : ":$sec");
	}
	return $dateValue; // doesn't parse as a date, so...
}


function JSLink($label, $onClick, $class = '') {
	return "<a href=\"JavaScript:void(0);\" onclick=\"JavaScript: $onClick\" class=\"$class\">$label</a>";
}





/*============================================================================
SECTION :: Utilities                                                        */


/*============================================================================
SECTION :: General                                                          */
function currentPageURL() {
	$URLPartSet = explode('/', $_SERVER['SERVER_NAME'] . $_SERVER['PHP_SELF']);
	return join('/', array_splice($URLPartSet, 0, -1)) . '/';
}

function RequestInt($RVN, $badReturnValue = 0) {
	return ProperInt(Request($RVN), $badReturnValue);
}
function RequestNumber($RVN, $badReturnValue = 0) {
	return ProperNumber(Request($RVN), $badReturnValue);
}
function ProperInt($theValue, $badReturnValue = 0) {
	if(is_numeric($theValue))
		return intval($theValue);
	elseif(is_string($theValue) && is_numeric(str_replace(',', '', $theValue)))
		return intval(str_replace(',', '', $theValue));
	else
		return $badReturnValue;
}
function ProperNumber($theValue, $badReturnValue = 0) {
	if(is_numeric($theValue))
		return (float)$theValue;
	elseif(is_string($theValue) && is_numeric(str_replace(',', '', $theValue)))
		return (float)(str_replace(',', '', $theValue));
	else
		return $badReturnValue;
}
function ProperBoolean($value, $badReturnValue=false) {
	if($value === true  ||  (is_string($value)  &&  strtolower($value) == 'true'))
		return true;
	elseif($value === false  ||  (is_string($value)  &&  strtolower($value) == 'false'))
		return false;
	else return $badReturnValue;
}
function StringProperBoolean($value, $badReturnValue=false) {
	if(ProperInt($value, "nan") != "nan") {
		return ProperInt($value, "nan") != 0;
	}
	if(strtolower($value) == 'true')
		return true;
	elseif(strtolower($value) == 'false')
		return false;
	else return $badReturnValue;
}
function Request($RVN, $emptyValue = '') {
	if(isset($_GET[$RVN]))
		return $_GET[$RVN];
	elseif(isset($_POST[$RVN]))
		return $_POST[$RVN];
	else
		return $emptyValue;
}

function StraightRequestText($RVN, $maxLength = 0) {
	$value = Request($RVN);
	if(is_array($value))
		$value = join(", ", $value);
	return str_replace("\'", "'", StraightText($value, $maxLength));
}

function RequestCheckbox($RVN) {
	if(!isset($_REQUEST[$RVN]))
		return false;
	else
		return (strlen($_REQUEST[$RVN]) > 0);
}
function RequestDate($RVN, $badReturnValue=false) {
	return ProperDate(Request($RVN), $badReturnValue);
}
function HTMLWhiteSpace($theString) {
	return str_replace(array("\r\n", "\n", "\n", '  ', "\t"),
		array('<br />', '<br />', '<br />', '&nbsp;&nbsp;', '&nbsp;&nbsp;&nbsp;&nbsp;'),
		$theString);
}

function TagHTMLEncode($value) {
	return str_replace("&amp;", "&", htmlspecialchars($value));
}

function StraightText($value, $maxLength = 0) {
	$result = str_replace(array(chr(145), chr(146), chr(147), chr(148)),
		array('\'', '\'', '\"', '\"'), $value); // do away with all smart quotes
	$result = trim(strip_tags($result));
	if(ProperInt($maxLength, 0) > 0) // user has indicated a limit on length, so...
		$result = substr($result, 0, $maxLength);

	return $result;
}
function Session($SVN, $badReturnValue = '') {
	if(!isset($_SESSION[$SVN]))
		return $badReturnValue;
	else
		return $_SESSION[$SVN];
}

function GetFileText($fileSpec, $badReturnValue = '') {
	if(!file_exists($fileSpec)) return $badReturnValue;
	
	$fh = fopen($fileSpec, 'r');
	flock($fh, LOCK_SH);
	$result = fread($fh, filesize($fileSpec));
	fclose($fh);
	return $result;
}
/*==== End SECTION :: General ==============================================*/


/*============================================================================
SECTION :: JavaScript Output                                                */
function JSSafe($theString) {
	$result = $theString;
	$result = str_replace("\r\n", "\r", $result);
	$result = str_replace("\n", "\r", $result);
	$result = str_replace("\\", "\\\\", $result);
	$result = str_replace("'", "\\'", $result);
	$result = str_replace("\"", "\\\"", $result);
	$result = str_replace("\r", "\\n", $result);
	return $result;
}
function JSValue($theString) {
	return "'" . JSSafe($theString) . "'";
}
function JSBoolean($value) {
	return ($value ? 'true' : 'false');
}
/*==== End SECTION :: JavaScript Output ====================================*/



/*============================================================================
SECTION :: QueryString Manipulation                                         */

function QueryStringEncode($s) {
	return str_replace(array("%", "&", "?", "="), array("%25", "%26", "%3F", "%3D"), $s);
}
function QueryStringUnencode($s) {
	return str_replace(array("%25", "%26", "%3F", "%3D"), array("%", "&", "?", "="), $s);
}



function ArrayToQueryString($theArray, $ignoreEmpties = false) {
	$result = "";
	foreach($theArray as $key => $value) {
		if(strlen($value) > 0  ||  !$ignoreEmpties)
			$result .= "&$key=" . QueryStringEncode($value);
	}
	return ltrim($result, "&");
}

function QueryStringToArray($queryString) {
	if(strpos($queryString, '=') === false) return array(); // special case for empty
	
	$fieldSet = explode("&", $queryString);
	$result = array();
	for($fLoop = 0; $fLoop < sizeof($fieldSet); $fLoop++) {
		$thisNVP = explode("=", $fieldSet[$fLoop]); // name/value pair
		$result[$thisNVP[0]] = QueryStringUnencode($thisNVP[1]);
	}
	return $result;
}
/*==== End SECTION :: QueryString Manipulation =============================*/



/*============================================================================
SECTION :: Performance Timing/Tuning                                        */
$ticTimers = array();
function tic($key = "**DEFAULT**") {
	global $ticTimers;
	$ticTimers[$key] = time()+microtime();
}
function toc($key = "**DEFAULT**") {
	global $ticTimers;
	if($ticTimers[$key])
		return number_format((time()+microtime()) - $ticTimers[$key], 7);
	else
		return -1;
}

/*==== End SECTION :: Performance Timing/Tuning ============================*/



/*============================================================================
SECTION :: String Helpers                                                   */
function ConditionalMark($theBoolean, $theMark) { return $theBoolean ? $theMark : ''; }
function CheckedMark($isChecked)   { return ($isChecked  ||  ord($isChecked) == 1) ? " checked"  : ''; }
function SelectedMark($isSelected) { return $isSelected ? " selected" : ''; }
function DisabledMark($isDisabled) { return $isDisabled ? " disabled" : ''; }
function PluralS($theNumber) { return ($theNumber==1 ? '' : 's'); }

function TruncatedString($theString, $charLimit) {
	if($charLimit <= 0  ||  strlen($theString . "") <= $charLimit)
		return $theString;
	else
		return substr($theString, 0, $charLimit) . "...";
}
function BeginsWith($theString, $targetPrefix) {
	if(is_array($targetPrefix)) {
		foreach($targetPrefix as $prefix) {
			if(BeginsWith($theString, $prefix))
				return true;
		}
		return false;
	}
	return (strncmp($theString, $targetPrefix, strlen($targetPrefix)) == 0);
}
function EnsureBeginsWith($theString, $prefix) {
	$result = $theString;
	if(!BeginsWith($result, $prefix))
		$result = $prefix . $result;
	return $result;
}

function EndsWith($theString, $targetSuffix) {
	return (substr($theString, strlen($theString) - strlen($targetSuffix) ) == $targetSuffix);
}
function EnsureEndsWith($theString, $prefix) {
	$result = $theString;
	if(!EndsWith($result, $prefix))
		$result = $result .  $prefix;
	return $result;
}

function TrimLeading($theString, $leadingString) {
	if(BeginsWith($theString, $leadingString))
		return substr($theString, strlen($leadingString));
	else
		return $theString;
}
function TrimTrailing($theString, $trailingString) {
	if(EndsWith($theString, $trailingString))
		return substr($theString, 0, strlen($theString) - strlen($trailingString));
	else
		return $theString;
}

function HTTPifyURL($URL) {
	$URL = TrimLeading($URL, '//');
	if(!BeginsWith($URL, 'https://'))
		$URL = EnsureBeginsWith($URL, 'http://');
	return $URL;
}

function makeRandomHash($lenth = 5, $charRange = false) {
	// makes a random alpha numeric string of a given length
	if(!$charRange)
		$charRange = array_merge(range('A', 'Z'), range('a', 'z'), range(0, 9));
	$result = '';
	for($c=0;$c < $lenth; $c++) {
		$result .= $charRange[mt_rand(0, count($charRange)-1)];
	}
	return $result;
}
/*==== End SECTION :: String Helpers =======================================*/



/*============================================================================
SECTION :: SQL Helpers                                                      */
function SQLValue($s) { return "'" . str_replace("'", "''", $s) . "'"; }
function SQLSafe($s)  { return       str_replace("'", "''", $s);       }
function SQLBit($boolValue) { return ($boolValue ? 1 : 0); }
function SQLDate($dateValue) {
	$timeStamp = ProperDate($dateValue, 0);
	return SQLValue(date('Y-m-d H:i:s', $timeStamp));  // YYYY-MM-DD HH:MM:SS
}
function ProperDate($dateValue, $badReturnValue = false, $formatString = false) {
	if(is_integer($dateValue)) // already a date, in PHP world
		$result = $dateValue;
	elseif(ProperInt($dateValue) > 0) {
		$result = ProperInt($dateValue);
	} else {
		$result = strtotime($dateValue);
		if($result === false)
			$result = $badReturnValue;
	}
	if($formatString  &&  $result != $badReturnValue)
		$result = date($formatString, $result);
	
	return $result;
}


function XXFormatRSDate($formatString, $DBDate, $badReturnValue = '') {
	if(!strtotime($DBDate)) return $badReturnValue;
	return date($formatString, strtotime($DBDate));
}
function RSBool($RSBitField) { return ord($RSBitField) == 1  ||  $RSBitField == '1'; }
/*==== End SECTION :: SQL Helpers ===========================================*/



/*============================================================================
SECTION :: CSV Helpers                                                      */
function CSVValue($value) {
	if(is_null($value)) return '';
	if(strpos($value, "\"") !== false  ||  strpos($value, ",")  !== false  ||
	   strpos($value, "\n") !== false  ||  strpos($value, "\r") !== false)
		return "\"" . str_replace("\"", "\"\"", $value) . "\"";
	else
		return $value;
}

function WriteRecordSetAsCSV($xRS, $fileName, $includeHeader = true, $fieldOutputDescriptor = null, $delim = " ") {
	
	$fh = fopen($fileName, 'w') or die("can't open $fileName for WriteRecordSetAsCSV");
	if($includeHeader) { // includeHeader can either be true (meaning generate it for me) or a custom string
		fwrite($fh, (is_string($includeHeader) ? $includeHeader : HeaderCSVLineOfRecordSet($xRS)) . "\r\n");
	}
	
	if(!$fieldOutputDescriptor) {
		$fieldSet = rs_get_field_names($xRS);
		$fieldOutputDescriptor = implode($delim, $fieldSet);
	}
	$fieldOutputSet = explode($delim, $fieldOutputDescriptor);
	
	while($xR = rs_fetch_array($xRS)) {
		$dataSet = array();
		for($fLoop = 0; $fLoop < count($fieldOutputSet); $fLoop++) {
			$dataSet[] = CSVValue(ExtractFormattedValueFromRS($fieldOutputSet[$fLoop], $xR));
		}
		
		fwrite($fh, implode(',', $dataSet) . "\r\n");
	}
	fclose($fh);
}

function WriteAndDeliverRSAsCSV(&$xRS, $fileName, $iH = true, $fOD = null, $d = ' ') {
	WriteRecordSetAsCSV($xRS, $fileName, $iH, $fOD, $d);
	DeliverFileAsInlineDownload($fileName);
	unlink($fileName);
}

function ExtractFormattedValueFromRS($fieldDescriptor, $xR) {
	
	$fieldDescriptorSet = explode(':', $fieldDescriptor, 2);
	$fieldName = $fieldDescriptorSet[0];

	$result = $xR[$fieldName]; // and possibly to be formatted!
	if(count($fieldDescriptorSet) > 1) { // 
		/// "name:200 URL:500 privacyURL:500 tagLine:500 bid:n priority:i isActive:b");
		$dataType = Left($fieldDescriptorSet[1], 1);
		$specifier= rtrim(ltrim(substr($fieldDescriptorSet[1], 1), "("), ")");  // whatever follows first char, ()'s optional!
		switch($dataType) { // our formatting instructions
			case '$': // money
				$result = "$" . number_format($result, 2); break;
			case 'd': // date
				$result = date(($specifier ? $specifier : "m/d/y"), $result); break;
			case 'b': // boolean
				$YNSet = explode('/', $specifier . '/');
				$result = RSBool($result) ? $YNSet[0]  ||  "True" :  $YNSet[1] ||  "False"; break;
		}
	}
	if($result == chr(0x01)  ||  $result == chr(0x00)) // handle bit fields to make more friendly
		$result = ($result == chr(0x01) ? "True" : "False");
	
	return $result;
}


function HeaderCSVLineOfRecordSet($xRS) {
	$fieldSet = rs_get_field_names($xRS);
//	echo implode(',', $fieldSet); exit();
	return implode(',', $fieldSet);
	for($fLoop = 0; $fLoop < $fieldCount; $fLoop++) {
		$theField = rs_get_field_names($xRS, $fLoop);
		$fieldSet[] = $theField->name;
	}
	return implode(',', $fieldSet);
}

function DeliverFileAsInlineDownload($file, $displayFileName = false) {
	if(!$displayFileName)
		$displayFileName = basename($file);
	if(file_exists($file)) {
		header('Content-Description: File Transfer');
		header('Content-Type: application/octet-stream');
		header('Content-Disposition: attachment; filename=' . $displayFileName);
		header('Content-Transfer-Encoding: binary');
		header('Expires: 0');
		header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
		header('Pragma: public');
		header('Content-Length: ' . filesize($file));
		flush();
		readfile($file);
	}
}
/*==== End SECTION :: CSV Helpers ===========================================*/