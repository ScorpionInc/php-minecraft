<!DOCTYPE html>
<head>
<title>Minecraft Modded Server API by ScorpionInc</title>
<script type="module" src="https://ajax.googleapis.com/ajax/libs/model-viewer/3.3.0/model-viewer.min.js"></script>
<style>
.closeButton{
	border-radius:1px;
	background-color:darkred;
	color:red;
	display:inline;
}
.whitelistTable{
	background-color:#000000;
	border-radius:2px;
	font-family:monospace,monospace;
}
.whitelistHeader{
	background-color:#111111;
	color:#DDDDDD;
	text-align:center;
}
.whitelistLightRow{
	background-color:#AAAAAA;
}
.whitelistDarkRow{
	background-color:#333333;
}
form{
	display:inline;
}
.whitelistTable > tbody > tr > td, .whitelistTable > tr > td{
	padding:3px;
}
</style>
</head>
<body>
<center><h1>Minecraft Modded Server API</h1><br/><small>by ScorpionInc</small></center>
<?php
//Restart Apache2: /etc/init.d/apache2 restart
function verifyCommand($command) :bool {
  $windows = strpos(PHP_OS, 'WIN') === 0;
  $test = $windows ? 'where' : 'command -v';
  return is_executable(trim(shell_exec("$test $command")));
}
function getPID_Linux(string $pname) :int {
	// Returns PID of process $pname in a linux environment.
	// Returns -1 on failure due to process not running.
	// Returns -2 on failure due to pgrep not available.
	if(!verifyCommand("pgrep")){
		return(-2);
	}
	exec("pgrep " . $pname, $output, $return);
	if ($return == 0) {
		//echo "Ok, process is running\n";//!Debugging
	} else {
		return(-1);
	}
	return($output[0]);
}
function isProcessRunning_Linux(string $pname) :bool {
	//Helper Function
	return(getPID_Linux($pname) >= 0);
}
function stopProcess_Linux(string $pname){
	//return posix_kill($pname, SIGABRT);
	/*
	if(!verifyCommand("kill")){
		echo "killProcess_Linux() failed to find executable 'kill'.<br/>\n"; //!Debugging
	}//*/
	$tpid = getPID_Linux($pname);
	if($tpid < 0){
		echo "killProcess_Linux() failed to find target process '" . $pname . "'.<br/>\n"; //Debugging
		return;
	}
	$cmdstr = ("kill " . strval($tpid));
	exec($cmdstr, $output, $return);
	//$output = shell_exec($cmdstr);
	echo "Kill attempt '" . $cmdstr . "' output: '" . strval($output[0]) . "' returning: '" . strval($return) . "'.<br/>\n";
	return($return);
}
function killProcess_Linux(string $pname){
        //return posix_kill($pname, SIGTERM);
        /*
        if(!verifyCommand("kill")){
                echo "killProcess_Linux() failed to find executable 'kill'.<br/>\n"; //!Debugging
        }//*/
        $tpid = getPID_Linux($pname);
        if($tpid < 0){
                echo "killProcess_Linux() failed to find target process '" . $pname . "'.<br/>\n"; //Debugging
                return;
        }
        $cmdstr = ("kill -9 " . strval($tpid)); //-9
        exec($cmdstr, $output, $return);
        //$output = shell_exec($cmdstr);
        echo "Kill attempt '" . $cmdstr . "' output: '" . strval($output[0]) . "' returning: '" . strval($return) . "'.<br/>\n";
        return($return);
}
function startServerScript() {
	if(isProcessRunning_Linux("java")){
		echo "startServerScript() returning due to server already being running.<br/>\n";
		return;
	}
	$targetPath = "/home/scorpioninc/DawnCraft/1.31_f/";
	$targetFile = "/bin/bash ./run.sh 2>&1";
	$cwd = getcwd();
	$success = chdir($targetPath);
	if(!$success){
		echo "startServerScript() Failed to change to directory of start script...<br/>\n"; //!Debugging
		return;
	}
	exec($targetFile, $output, $retCode);
	foreach($output as $key => $value){
		echo "Output Line[" . strval($key) . "]: " . strval($value) . "<br/\n>";
	}
	if($success){
		//Return to the working directory we were in.
		chdir($cwd);
	}
}
function parseJSONFile(string $fpath) {
	$file_data = file_get_contents("" . strval($fpath));
	$json_data = json_decode($file_data, true); //true-array, false-objects
	return($json_data);
}
function getWhitelistValue(){
	//TODO
	return(parseJSONFile("/home/scorpioninc/DawnCraft/1.31_f/whitelist.json"));
}
function findWhitelistIndexByUUID(array $data, string $uuid) : int{
	//Returns index of matching UUID on success.
	//Returns -1 of error
	$formateduuid = formatMinecraftUUID($uuid);
	foreach($data as $key => $value) {
		foreach($value as $subkey => $subvalue){
			if($subkey != "uuid")
				continue;
			if($subkey == $formateduuid)
				return($key);
		}
	}
	return(-1);
}
function doesWhitelistContainUUID(array $data, string $uuid) : bool{
	//Returns true when uuid exists within data
	//Returns false otherwise.
	return(findWhitelistIndexByUUID($data, $uuid) >= 0);
}
function get_content(string $URL) {
	$ch = curl_init();
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
	curl_setopt($ch, CURLOPT_URL, $URL);
	$data = curl_exec($ch);
	curl_close($ch);
	return $data;
}
function getMinecraftUUIDFromUsername(string $username) : string{
	//Returns stripped UUID of Minecraft user by their username.
	//https://api.mojang.com/users/profiles/minecraft/ScorpionInc
	if(!$username){
		echo "getMinecraftUUIDFromUsername() Failed due to empty username value.<br/>\n";
		return("");
	}
	$remote_data = get_content("https://api.mojang.com/users/profiles/minecraft/" . $username);
	$json_data = json_decode($remote_data, true); //true-array, false-objects
	//echo "getMinecraftUUIDFromUsername(\"" . $username . "\") => '"; //!Debugging
	//print_r($json_data); //Debugging
	//echo "'.<br/>\n"; //!Debugging
	return($json_data["id"]);
}
function stripMinecraftUUID(string $uuid) : string{
	//Returns uuid value without any padding or extra symbols.
	return(preg_replace('/[^a-z0-9]/', '', strtolower($uuid)));
}
function formatMinecraftUUID(string $uuid) : string{
	//Returns formatted version of provided uuid sring on success.
	//Returns empty string on failure.
	if(!$uuid){
		echo "formatMinecraftUUID() Failed due to undefined parameter value.<br/>\n"; //Debugging
		return("");
	}
	$minecraft_uuid_length = 32;
	$stripped_uuid = stripMinecraftUUID(strval($uuid));
	$formatted_uuid = "";
	$stripped_length = strlen($stripped_uuid);
	if($stripped_length != $minecraft_uuid_length){
		echo "formatMinecraftUUID() Failed due to input size mismatch. Expected: " . strval($minecraft_uuid_length) . " Got: " . strval($stripped_length) . "<br/>\n"; //Debugging
		return("");
	}
	//We have the correct input length.
	//Do Formatting 8-4-4-4-12.
	$delimiter = "-";
	$formatted_uuid = $formatted_uuid . substr($stripped_uuid, 0, 8) . $delimiter;
	$stripped_uuid = substr($stripped_uuid, 8);
	for($i = 0; $i < 3; $i++){
		$formatted_uuid = $formatted_uuid . substr($stripped_uuid, 0, 4) . $delimiter;
		$stripped_uuid = substr($stripped_uuid, 4);
	}
	$formatted_uuid = $formatted_uuid . $stripped_uuid;
	//Finished
	return($formatted_uuid);
}
function getMinecraftProfileDataFromUUID(string $uuid) : array{
	//Returns encoded profile data in Base64 containing URL of player skin/cape and actions.
	//https://sessionserver.mojang.com/session/minecraft/profile/<UUID>
	if(!$uuid){
		echo "getMinecraftProfileDataFromUUID() Failed due to empty uuid parameter.<br/>\n";
		return(array());
	}
	$remote_data = get_content("https://sessionserver.mojang.com/session/minecraft/profile/" . strval($uuid));
	$json_data = json_decode($remote_data, true); //true-array, false-objects
	//print_r($json_data);
	return($json_data);
}
function getMinecraftProfileDataFromUsername(string $username) : array{
	//Returns encoded profile data with Base64 containing URL of player skin, cape, and actions.
	//Helper Function
	return(getMinecraftProfileDataFromUUID(getMinecraftUUIDFromUsername($username)));
}
function getEncodedMinecraftTextureDataFromProfileData(array $profileData) : string{
	//Returns encoded JSON of texture URLs on success.
	//Returns empty string on failure.
	if(!$profileData){
		echo "getEncodedMinecraftTextureDataFromProfileData() Failed due to empty parameter value.<br/>\n"; //Debugging
		return("");
	}
	foreach($profileData["properties"] as $key => $value){
		$nextEntryName = "";
		foreach($value as $subkey => $subvalue){
			if($subkey != "name")
				continue;
			$nextEntryName = $subvalue;
			break;
		}
		if($nextEntryName == ""){
			//Entry has no name...
			continue;
		}
		if($nextEntryName != "textures"){
			//Entry is not for texture data...
			continue;
		}
		//This is the textures entry! Extract it's value.
		foreach($value as $subkey => $subvalue){
			if($subkey != "value")
				continue;
			return($subvalue);
		}
		//Textures entry found but entry has no value...
		break;
	}
	echo "getEncodedMinecraftTextureDataFromProfileData() Failed due to missing texture(s).<br/>\n"; //!Debugging
	return("");
}
function getEncodedMinecraftTextureDataFromUsername(string $username) : string{
	//Helper Function
	return(getEncodedMinecraftTextureDataFromProfileData(getMinecraftProfileDataFromUsername($username)));
}
function getDecodedMinecraftTextureData(string $textureData) : array{
	//Returns decoded texture JSON array from encoded texture data Base64 String on success.
	//Returns empty string on failure.
	if(!$textureData){
		echo "getSkinURLFromEncodedMinecraftTextureData() Failed due to undefined parameter value.<br/>\n"; //!Debugging
		return("");
	}
	$decoded_data = base64_decode(strval($textureData), false);
	if(!$decoded_data){
		echo "getSkinURLFromEncodedMinecraftTextureData() Failed due Base64 Parsing Error.<br/>\n"; //!Debugging
		return("");
	}
	$json_data = json_decode($decoded_data, true); //true-array, false-objects
	return($json_data["textures"]);
}
function getDecodedMinecraftTextureDataFromUsername(string $username) : array{
	//Helper Function
	return(getDecodedMinecraftTextureData(getEncodedMinecraftTextureDataFromUsername($username)));
}
function getSkinURLFromDecodedMinecraftTextureData(array $textureData) : string{
	//Returns URL of the Skin Texture from a decoded texture array if successful.
	//Returns empty string on failure.
	if(!$textureData){
		echo "getSkinURLFromDecodedMinecraftTextureData() Failed due to undefined parameter value.<br/>\n"; //!Debugging
		return("");
	}
	foreach($textureData as $key => $value){
		if($key != "SKIN")
			continue;
		foreach($value as $subkey => $subvalue){
			if($subkey != "url")
				continue;
			return($subvalue);
		}
		//SKIN has no url...
		echo "getSkinURLFromDecodedMinecraftTextureData() Failed due to SKIN having no url.<br/>\n"; //!Debugging
	}
	//No entry named "SKIN"
	//echo "getSkinURLFromDecodedMinecraftTextureData() Failed due SKIN being undefined.<br/>\n"; //!Debugging
	return("");
}
function getCapeURLFromDecodedMinecraftTextureData(array $textureData) : string{
	//Returns URL of the Cape Texture from a decoded texture array if successful.
        //Returns empty string on failure.
	if(!$textureData){
                echo "getCapeURLFromDecodedMinecraftTextureData() Failed due to undefined parameter value.<br/>\n"; //!Debugging
                return("");
        }
        foreach($textureData as $key => $value){
                if($key != "CAPE")
                        continue;
                foreach($value as $subkey => $subvalue){
                        if($subkey != "url")
                                continue;
                        return($subvalue);
                }
                //CAPE has no url...
                echo "getCapeURLFromDecodedMinecraftTextureData() Failed due to CAPE having no url.<br/>\n"; //!Debugging
        }
        //No entry named "CAPE"
        //echo "getCapeURLFromDecodedMinecraftTextureData() Failed due CAPE being undefined.<br/>\n"; //!Debugging
        return("");
}
?>
<div>
<?php
if($_GET["action"]){
	echo "<h3>Action Output:\n";
	echo "<form method=\"GET\" action=\"#\"><input class=\"closeButton\" value=\"X\" type=\"submit\" /></form>\n"; //Close Button
	echo "</h3><div>\n";
	echo "<span>Action is being processed...</span><br/>\n";
	if($_GET["action"] == "Stop Server"){
		echo "<span>Attempting to stop the server...</span><br/>\n";
		stopProcess_Linux("java"); //Kills any one java instance.
		//Wait for process to exit before updating the rest of the page.
		sleep(10);
	} elseif($_GET["action"] == "Kill Server") {
		echo "<span>Attempting to stop the server...</span><br/>\n";
                killProcess_Linux("java"); //Kills any one java instance.
                //Wait for process to exit before updating the rest of the page.
                sleep(3); //Should be fast as its not a graceful exit.
	} elseif($_GET["action"] == "Start Server") {
		echo "<span>Attempting to start the server...</span><br/>\n";
		startServerScript();
	} elseif($_GET["action"] == "Allow Username") {
		echo "<span>Adding username of: '" . $_GET["NewUsername"] . "' to whitelist...</span><br/>\n";
		$username = $_GET["NewUsername"];
		if($username == ""){
			echo "action[\"Allow Username\"]: ERROR: No username supplied!<br/>\n"; //Debugging
			abort();
		}
		$newUUID = getMinecraftUUIDFromUsername($username);
		if($newUUID == ""){
			echo "action[\"Allow Username\"]: ERROR user doesn't have remote UUID.<br/>\n"; //!Debugging
			abort();
		}
		$wlv = getWhitelistValue();
		if(doesWhitelistContainUUID($wlv, $newUUID)){
			echo "action[\"Allow Username\"]: WARNING User's UUID of '" . formatMinecraftUUID($newUUID) . "' is already whitelisted."; //Debugging
			abort();
		}
		echo "action[\"Allow Username\"]: Validated new UUID of User: '" . $username . "' of: '" . $newUUID . "'.<br/>\n"; //Debugging
	} elseif($_GET["action"] == "Remove User") {
		echo "<span>Attempting to remove user at: " . strval($_GET["jsonIndex"]) . " from whitelist.</span><br/>\n"; //!Debugging
	} elseif($_GET["action"] == "Reload Textures") {
		$jid = $_GET["jsonIndex"];
		echo "<span>Attempting to reload textures for user at index: " . strval($jid) . "</span><br/>\n"; //!Debugging
		$wlv = getWhitelistValue();
		$username = $wlv[$jid]["name"];
		$uu = getMinecraftUUIDFromUsername($username);
		if($wlv[$jid]["uuid"] != formatMinecraftUUID($uu)){
			echo "action[\"Reload Textures\"]: Warning: Remote UUID Doesn't match known UUID for username: '" . $username . "'.<br/>\n"; //!Debugging
			echo "JSON: '" . $wlv[$jid]["uuid"] . "' != Remote: '" . formatMinecraftUUID($uu) . "'<br/>\n"; //!Debugging
		} else {
			echo "action[\"Reload Textures\"]: Validated UUID value for user: '" . $username . "'.<br/>\n";
		}
		$pd = getMinecraftProfileDataFromUUID($uu);
		$etd = getEncodedMinecraftTextureDataFromProfileData($pd);
		$dtd = getDecodedMinecraftTextureData($etd);
		$skn = getSkinURLFromDecodedMinecraftTextureData($dtd);
		$cpe = getCapeURLFromDecodedMinecraftTextureData($dtd);
		//Validate Paths
		if (!file_exists('./skins'))
			mkdir('./skins', 0777, true);
		if (!file_exists('./capes'))
                        mkdir('./capes', 0777, true);
		//Save Textures
		if($skn != "")
			file_put_contents("./skins/" . strval($wlv[$jid]["uuid"]) . "_skin.png", file_get_contents(strval($skn)));
		if($cpe != "")
			file_put_contents("./capes/" . strval($wlv[$jid]["uuid"]) . "_cape.png", file_get_contents(strval($cpe)));
	} else {
		echo "<span>Unknown action specified: '" . $_GET["action"] . "'.</span><br/>\n";
	}
	echo "<span>Action has been completed.</span><br/>\n";
	echo "</div>\n";
}
?>
</div>
<div>
<h3>Server Status:</h3>
<?php
$spid = getPID_Linux("java");
$startBtnValue = "⛏️Start Server💎"; //!TODO
$stopBtnValue = "✋Stop Server🛑";
$killBtnValue = "🗡️Kill Server☠️";
if($spid >= 0){
	echo "<span style=\"color:MediumSeaGreen;\">Server is Running with PID of: " . $spid . ".</span>";
	echo "<br/><input style=\"color:DarkSlateGrey;background-color:DimGrey;\" name=\"placebo\" value=\"⛏️Start Server💎\" type=\"button\" disabled />\n";
	echo "<form method=\"GET\" action=\"#\">\n";
	echo "\t<input name=\"action\" value=\"Stop Server\" type=\"hidden\" />";
	echo "\t<input style=\"color:Tomato;\" name=\"doit\" value=\"✋Stop Server🛑\" type=\"submit\" />";
	echo "<form method=\"GET\" action=\"#\">\n";
	echo "\t<input name=\"action\" value=\"Kill Server\" type=\"hidden\" />";
	echo "\t<input style=\"color:Maroon;\" name=\"doit\" value=\"🗡️Kill Server☠️\" type=\"submit\" />";
	echo "</form>\n";
} else {
	echo "<span style=\"color:Tomato;\">Server is not Running. :`(</span>\n";
	echo "<form method=\"GET\" action=\"#\">\n";
	echo "\t<input name=\"action\" value=\"Start Server\" type=\"hidden\" />\n";
	echo "\t<input style=\"color:MediumSeaGreen;\" name=\"doit\" value=\"⛏️Start Server💎\" type=\"submit\"/>";
	echo "</form>\n";
	echo "<input style=\"color:DarkTomato;background-color:DimGrey;\" name=\"placebo01\" value=\"✋Stop Server🛑\" type=\"button\" disabled />\n";
	echo "<input style=\"color:DarkMaroon;background-color:DimGrey;\" name=\"placebo02\" value=\"🗡️Kill Server☠️\" type=\"button\" disabled />\n";
}
?>
</div>
<div>
<h3>Server Whitelist Access:</h3>
<table class="whitelistTable">
<tr class="whitelistHeader">
	<td><h4>Index:</h4></td>
	<td><h4>Username:</h4></td>
	<td><h4>UUID:</h4></td>
	<td><h4>Skin:</h4></td>
	<td><h4>Cape:</h4></td>
	<td><h4>3D Model:</h4></td>
	<td><h4>Actions:</h4></td>
</tr>
<?php
	$placeholder_img = "https://placehold.co/60";
	$wlv = getWhitelistValue();
	//echo "wlv: "; print_r($wlv); echo "<br/>\n"; //Debugging
	foreach($wlv as $key => $value){
		echo "<tr class=\"whitelist";
		if(($key % 2) == 0){
			echo "Light";
		}else{
			echo "Dark";
		}
		echo "Row\">";
		echo "<td>" . $key . "</td>"; //Index
		echo "<td>" . $value["name"] . "</td>";
		echo "<td>" . $value["uuid"] . "</td>";
		echo "<td><center><img src=\"";
		echo "./skins/" . strval($value["uuid"]) . "_skin.png";
		echo "\" onerror=\"this.src='" . $placeholder_img . "';\" /></center></td>"; //Skin
		echo "<td><center><img src=\"";
		echo "./capes/" . strval($value["uuid"]) . "_cape.png";
		echo "\" onerror=\"this.src='" . $placeholder_img . "';\" /></center></td>"; //Cape
		echo "<td><model-viewer poster=\"" . strval($placeholder_img) . "\"></model-viewer></td>"; //3D Model
		echo "<td>";
		echo "<form method=\"GET\" action=\"#\"><input name=\"jsonIndex\" value=\"" . strval($key) . "\" type=\"hidden\" /><input name=\"action\" value=\"Reload Textures\" type=\"submit\" /></form><br/>\n"; //Reload Textures
		echo "<form method=\"GET\" action=\"#\"><input name=\"jsonIndex\" value=\"" . strval($key) . "\" type=\"hidden\" /><input name=\"action\" value=\"Remove User\" type=\"submit\" /></form>"; //Remove from Whitelist.
		echo "</td>";
		echo "</tr>\n";
	}
?>
</table>
<form method="GET" action="#">
Add new username:<input style="" name="NewUsername" value="" type="text" /><input style="" name="action" value="Allow Username" type="submit" />
</form>
</div>
</body>
</html>
