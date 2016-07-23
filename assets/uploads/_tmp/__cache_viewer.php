<?php

//Cache dir
$dir = getcwd();

//Delete cache files?
$clearType = $_GET["forceCacheClear"];
$deletedItems = $_GET["deletedItems"];

//Delete cache files
if ($clearType != ""){	
	$delCount = 0;
	//Cycle through all files and delete appropriate type
	foreach (scandir($dir) as $file) {
		if (strpos($file,".php") === false && substr($file,0,1) != "."){
			//Delete images	
			if ($clearType == "all" || $clearType == "images"){
				//Is this file a cached image?
				if (strpos($file,".jpg") > -1 || strpos($file,".jpeg") > -1 || strpos($file,".gif") > -1 || strpos($file,".png") > -1) {
					//Get file age
					$fileAgeMinutes = round(abs(time() - filemtime($file)) / 60,2);
					
					//Don't clear images that have been created in last 10 minutes in case they're in process of being used for image manipulation (very unlikely)
					if ($fileAgeMinutes > 10){
						$delCount++;
						unlink($file);
					}
				}
			}
			//Delete pages
			if ($clearType == "all" || $clearType == "pages"){		
				//If it's not an image it's assumed to be a page
				if (strpos($file,".jpg") > -1 || strpos($file,".jpeg") > -1 || strpos($file,".gif") > -1 || strpos($file,".png") > -1) {
				}else{
					$fileAgeSeconds = time() - filemtime($file);
					//Give cache 5 seconds before deleting just in case
					if ($fileAgeSeconds > 5){
						$delCount++;
						unlink($file);						
					}
				}
			}
		}		
	}
	header('Location: __cache_viewer.php?deletedItems=' . $delCount);
}
?>
<html>
<head>
<style>
	body{font-size:10px;font-family:Arial,Tahoma;}
	td,th {font-size:10px;}
	th {background-color:black;color:white;}
	h1 {margin:0;padding:0;text-transform:uppercase;font-family:Tahoma;}
	.msg {padding:10px;background-color:#dddddd;margin-top:5px;margin-bottom:5px;}
</style>
<script type='text/javascript'>
function deleteCache(cacheType){
	if (cacheType == "images"){
		confirmMsg = "Are you sure you want to clear the image cache? Please note that only images older than 10 minutes will be deleted.";
	}else{
		confirmMsg = "Are you sure you want to clear the page cache?";
	}
	
	if (confirm(confirmMsg)){
		window.location.href="__cache_viewer.php?forceCacheClear=" + cacheType;
	}
}
</script>
</head>
<body>
<?php

echo "<h1>File Cache Viewer</h1>";

if ($deletedItems != ""){
	echo "<div class='msg'>". $deletedItems . " items deleted from cache.</div>";
}


//Write out all files in the cache
$fileCount = 0;
foreach (scandir($dir) as $file) {
	//Don't show garbage tmp files or php files
	if (strpos($file,".php") === false && substr($file,0,1) != "."){	
		$fileCount++;
		
		if ($fileCount == 1){
			echo "<table border='1' cellspacing='0' cellpadding='4'>";
			echo "<tr>";
			echo "<th>File</th>";
			echo "<th>Size</th>";
			echo "<th>Age</th>";
			echo "</tr>";		
		}
	
		echo "<tr>";
		echo "<td><a href='".$file."' target='_blank'>" . $file . "</a></td>";
		echo "<td>". displayFileSize(filesize($file))  . "</td>";
		echo "<td>". displayTimeDifference(filemtime($file)). "</td>";
		echo "</tr>";
	}	
}
if ($fileCount > 0){
	echo "<th colspan='3'>";
	echo "<input type='button' name='deleteImageCache' value='DELETE IMAGE CACHE' onclick=\"deleteCache('images');\">";
	echo "<input type='button' name='deletePageCache' value='DELETE PAGE CACHE' onclick=\"deleteCache('pages');\">";
	echo "</th>";
	echo "</table>";
}else{
	echo "<div class='msg'>There are no pages or images in the file cache.</div>";
}

echo "</body>";

function displayFileSize($bytes){
	$kb = round($bytes/1024,2);
	
	if ($kb > 1024){
		$sizeDisplay = round($kb/1024,2) . " MB";
	}else{
		$sizeDisplay = $kb . " KB";
	}
	return $sizeDisplay;
}


function displayTimeDifference($timeIn){
	$minutes = round(abs(time() - $timeIn) / 60,2);
	
	if ($minutes > 1440){
		$days = round($minutes/1440,0);
		if ($days == 1){ 
			$timeDisplay = $days . " day";
		}else{
			$timeDisplay = $days . " days";
		}		
	}else if ($minutes > 60){
		$hours = round($minutes/60,0);
		if ($hours == 1){ 
			$timeDisplay = $hours . " hour";
		}else{
			$timeDisplay = $hours . " hours";
		}
	}else{
		$timeDisplay = $minutes . " minutes";
	}

	return $timeDisplay;
}
