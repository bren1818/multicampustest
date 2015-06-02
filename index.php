<?php
	include "IP_RANGES.php";
	//$DEFAULT_CAMPUS = "waterloo"; -- from request?
	$CURRENT_CAMPUS = "waterloo"; //from Shibboleth or session?
	define('OUTPUT_LOG',  1);
	define('CLEAR_BUFFER', 0);
	
	$ip = $_SERVER['REMOTE_ADDR'];
	
	
	function startsWith($haystack, $needle) {
		// search backwards starting from haystack length characters from the end
		return $needle === "" || strrpos($haystack, $needle, -strlen($haystack)) !== FALSE;
	}
	
	function endsWith($haystack, $needle) {
		// search forward starting from end minus needle length characters
		return $needle === "" || (($temp = strlen($haystack) - strlen($needle)) >= 0 && strpos($haystack, $needle, $temp) !== FALSE);
	}
	
	if( isset($_REQUEST) && isset($_REQUEST['setCampus']) && $_REQUEST['setCampus'] != "" ){
		$newCampus = strtolower($_REQUEST['setCampus']); //check it is a valid campus
		if( in_array($newCampus,array("milton","waterloo","brantford")) ){
			setcookie("campus", $newCampus, time()+60*60*24*30, '/' );
			setcookie("campus_vlan", "unknown_external", time()+60*60*24*30, '/' );
			if(OUTPUT_LOG){ 
				echo "Setting Campus to: ".$newCampus.'<br />';
			}
			//make smart to look at current folder...
			if(CLEAR_BUFFER){ob_clean(); }
			if( $_SERVER['REDIRECT_URL'] != ""){
				header("Location: ".$_SERVER['REDIRECT_URL']);
			}else{
				header("Location: /");
			}
			
		}	
	}
	
	if( isset( $_COOKIE['campus']) ){
		if(OUTPUT_LOG){ 
			echo 'campus cookie set and has value of: '.$_COOKIE['campus'].'<br />';
		}
		$CURRENT_CAMPUS = $_COOKIE['campus'];
	}else{
		$CURRENT_CAMPUS = determineCurrentCampus($ip);
	}
	

	function determineCampus($options, $preferred){
		
		echo $preferred;
		echo $options;
		
		if( in_array($preferred,$options)){
			return $preferred;
		}else{
			//preferred option doesn't exists
			if($preferred == "waterloo" && in_array("brantford",$options) ){
				return "brantford";
			}else if($preferred == "waterloo" && in_array("milton",$options)){
				return "milton";
			}else if($preferred == "brantford" && in_array("waterloo",$options)){
				return "waterloo";
			}else if($preferred == "brantford" && in_array("milton",$options)){
				return "milton";
			}else if($preferred == "milton" && in_array("waterloo",$options)){
				return "waterloo";
			}else if($preferred == "milton" && in_array("brantford",$options)){
				return "branford";
			}else{
				return $options[0];
			}
		} 
	}
	
	
	function ip_in_range( $ip, $range ) {
		if ( strpos( $range, '/' ) == false ) {
			$range .= '/32';
		}
		// $range is in IP/CIDR format eg 127.0.0.1/24
		list( $range, $netmask ) = explode( '/', $range, 2 );
		$range_decimal = ip2long( $range );
		$ip_decimal = ip2long( $ip );
		$wildcard_decimal = pow( 2, ( 32 - $netmask ) ) - 1;
		$netmask_decimal = ~ $wildcard_decimal;
		return ( ( $ip_decimal & $netmask_decimal ) == ( $range_decimal & $netmask_decimal ) );
	}
	
	function determineCurrentCampus($ip){
		$cookie = "waterloo";
		/*if(OUTPUT_LOG){ 
			echo 'Campus cookie not set!<br />';
			if( isset( $_COOKIE['campus']) ){
				echo "&emsp;".";) Actually it is set: and is: ".$_COOKIE['campus'].' <br />';
			}
			echo "Your IP: ".$ip.'<br />';
		}*/
		global $IP_RANGE;
		
		$found = 0;
		foreach( $IP_RANGE as $range){
			//echo "checking: ".$range[0]." - ".$range[1].'<br />';
			if( ip_in_range($ip, $range[0].$range[1])){
				if(OUTPUT_LOG){ 
					echo "IP Found in Range!";
					pa( $range );
				}
				$found = 1;
				//set the cookie!
				$cookie = $range[2];
				setcookie("campus", $range[2], time()+60*60*24*30, '/' );
				setcookie("campus_vlan", $range[3], time()+60*60*24*30, '/' );
				break;
			}
		}
		
		if( $found != 1){
			if(OUTPUT_LOG){ 
				echo "IP Not found. Defaulting to waterloo<br />";
			}
			$cookie = "waterloo";
			setcookie("campus", "waterloo", time()+60*60*24*30, '/' );
			setcookie("campus_vlan", "unknown_external", time()+60*60*24*30, '/' );
		}
		
		return $cookie;
	}
	
	function errorHandle($file){
		echo "Got Requested URI (error): ".$file;
	}
	
	function openPage($path){
		if(CLEAR_BUFFER){ob_clean(); }
		//check if path or url
		if(strrpos($path, getcwd()) === false ){
			//URL
			if($_SERVER['REQUEST_URI'] !=  $path) {
				header("Location: ".$path);
				//echo file_get_contents($path);
			}else{
				//uri is the path
				//header('Content-Type: text/html; charset=utf-8');
				//echo file_get_contents($path);
				include $path;
			}
		}else{
			$webPath = substr($path,strlen($_SERVER['DOCUMENT_ROOT']));
			$webPath = str_replace('\\', '/', $webPath);
			
			if($_SERVER['REQUEST_URI'] != $webPath ) {
				header("Location: ".$webPath);
				//echo file_get_contents($path);
			}else{
				if(CLEAR_BUFFER){ob_clean(); }
				//header('Content-Type: text/html; charset=utf-8');
				//echo file_get_contents($path);
				include $path;
			}
		}
	}
	
	function pa($array){
		echo '<pre>'.print_r($array,true).'</pre>';
	}
	
	//check if above is a folder or a page and if exists
	$path = $_SERVER['REQUEST_URI'];
	//$maybeFile = end(explode("/",$_SERVER['REDIRECT_URL']));
	
	$cwd = getcwd();
	$fullPath = realpath($cwd.$path);
	$chooseCampus = 0;
	
	if($path == "/" || endsWith($path, "/")){
		if( is_dir($fullPath) ){
			//directory
			if(OUTPUT_LOG){
				echo "Directory Found <br />";
			}
			$files = scandir($fullPath);

			$options = array();
			foreach($files as $file){
				if( $file !="." && $file != ".."){
					if( $file == "index-brantford.html" || $file == "index-milton.html" || $file == "index.html" ){
						if($file == "index-brantford.html"){
							$options[] = "brantford";
						}
						if($file == "index-milton.html"){
							$options[] = "milton";
						}
						if($file == "index.html"){
							$options[] = "waterloo";
						}
					}
				}
			}
			
			if(OUTPUT_LOG){
				echo "Number of options: ".sizeof($options).'<br />';
			}
			
			if( sizeof($options) == 1){
				//load the file
				
				$file = $options[0];
				
				if( $file == "waterloo"){
					$file =  $fullPath."\index.html";
				}else if( $file == "brantford"){
					$file =  $fullPath."\index-brantford.html";
				}else if( $file == "milton"){
					$file =  $fullPath."\index-milton.html";
				}
				
				if (file_exists($file)) {
					openPage($file);
				}else{
					errorHandle( $fullPath.DIRECTORY_SEPARATOR.$file );
				}
				
			}else{
		
				global $CURRENT_CAMPUS;	
	
				//need to get which file to load...
				$file = determineCampus($options, $CURRENT_CAMPUS);
				if(OUTPUT_LOG){
					echo "Serve Up: ".$file.'<br />';
				}
				
				
				
				if( $file == "waterloo"){
					$file =  $fullPath."\index.html";
				}else if( $file == "brantford"){
					$file =  $fullPath."\index-brantford.html";
				}else if( $file == "milton"){
					$file =  $fullPath."\index-milton.html";
				}else{
					//errorHandle( $fullPath.DIRECTORY_SEPARATOR.$file );
					$file =  $fullPath;
				}
				
				if (file_exists($file)) {
					openPage($file);
				}else{
					errorHandle( $fullPath.DIRECTORY_SEPARATOR.$file );
				}
			}
		}
	}

	if( !is_file($fullPath) ){
		if(OUTPUT_LOG){ 
			echo "No Exist! - 404"; 
			echo "No Exist! - 404"; 
			echo '<pre>'.print_r($_SERVER,true).'</pre>';
		}
	}else{
		//if(OUTPUT_LOG){ echo "File Found - serve it?<br />"; }
		openPage($fullPath);
	}
?>