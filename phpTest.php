<?php
$urlToCurl = "https://maps.cfs.sa.gov.au/kmls/incidents";
$newFile = dirname('_FILE_') . '/KMZtempSA.kmz';
$newUnzippedFileDir = dirname('_FILE_') . '/';

getFileFromURl($urlToCurl, $newFile);
//Can be called form within extract data from kml but want to make easier to read.
$zipFileName = unzipFile($newFile, $newUnzippedFileDir);
extractDataFromKml($zipFileName);

function getFileFromURL($urlToCurl, $newFile) {
	$newFile = fopen ($newFile, 'w+');
	//Create resource/ set url.
	$curlInit = curl_init($urlToCurl);
	//Set timeout.
	curl_setopt($curlInit, CURLOPT_TIMEOUT, 50);
	//Return transfer as string.
	curl_setopt($curlInit, CURLOPT_FILE, $newFile);
	//Specify header return.
	curl_setopt($curlInit, CURLOPT_FOLLOWLOCATION, true);
	//$curlResult contains the output string.
	$curlResult = curl_exec($curlInit);
	//Close curl.
	curl_close($curlInit);
	fclose($newFile);
}

function unzipFile($fileToUnzip, $directory) {
	$zipFileName = "";
	$zip = new ZipArchive;
	$result = $zip->open($fileToUnzip);
	if ($result == true) {
		$zip->extractTo($directory);
		$zipFileName = $zip->getNameIndex(0);
		$zip->close();
		echo "Unzip successful.\n";
	}
	else {
		echo "Unzip failed.\n";
	}
	return $zipFileName;
}

//Get timestamp and coords
function extractDataFromKml($kmlFile) {
	$contents = file_get_contents($kmlFile);
	$xml = new SimpleXMLElement($contents);
	$childrenXml = $xml->Document->Placemark;

	foreach($childrenXml as $child) {
		//Child object == placemark.
		echo "Timestamp: " . $child->TimeStamp->when . "\n";
		echo "Style: " . $child->styleUrl . "\n";
		echo "Co-ords: " . $child->Point->coordinates . "\n\n";
	}
}

?>