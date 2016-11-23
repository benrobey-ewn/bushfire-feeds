<?php

/*
$t=$_REQUEST['t']; 
$s=$_REQUEST['s']; 

if($t!=''){$tf="t=".$t;}
if($s!=''){$sf="s=".$s;}
*/

$url="http://data.ewn.com.au/bushfires/dumpng.php?i=-24&timestamp=".time();



$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $url);
curl_setopt($ch, CURLOPT_HEADER, 0);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);

$data = curl_exec($ch);
echo $data;

curl_close($ch);
?> 
