<?php

    require_once(dirname(__FILE__).'/config/startup.php');
    require_once(dirname(__FILE__).'/lib/App.php');
    require_once(dirname(__FILE__).'/lib/KLogger.php');
    require_once(dirname(__FILE__).'/lib/Db.php');
    require_once(dirname(__FILE__).'/models/Incident.php');

    $configPath = realpath(dirname(__FILE__) . '/config/config.php');

    function getBaseFolder(){
        if(isset($_SERVER["SCRIPT_URI"])){
            $url = $_SERVER["SCRIPT_URI"];
        }else{
            $url = "http://" . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'];
        }
        return implode("/", (explode('/', $url, -1))).'/';
    }

    try{
        App::createApp($configPath);

        $incident = new Incident();
        $groups = $incident->getGroups($_GET);
        $data = $incident->getData($_GET);
    }catch (Exception $e){
        App::$logger->LogError($e->getMessage());
        exit();
    }
?>
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Strict//EN"
    "http://www.w3.org/TR/xhtml1/DTD/xhtml1-strict.dtd">
<html xmlns="http://www.w3.org/1999/xhtml">

<head>
<title>Bush Fire Warnings</title>
<meta http-equiv="content-type" content="text/html; charset=ISO-8859-1">
<meta http-equiv="imagetoolbar" content="no">
<meta name="keywords" content="bush fire warnings, severe weather warnings , watch and act, emergency alerts" />
<meta name="description" content="Bush fire warnings and incidents map, watch and act and emergency alerts. Also available on your phone" />

<script src="http://maps.google.com/maps?file=api&v=2&key=ABQIAAAAMly_1laEsjOiKeAQ32ignBTcqwqxnSe0YdS224QkurfjcQkUEhQzN_AZc-gkngYOKZh3YoHlELqKsw&sensor=false&indexing=false" type="text/javascript"></script>
<script type="text/javascript" src="js/script_mod.js"></script>
<script type="text/javascript" src="js/main20120901.js"></script>


<link rel="stylesheet" href="css/main20120715.css" />

<style type="text/css">
#menu_matrix {top:3px; left:5px;width:700px; }
#menu_matrix a {text-decoration:none;}
#menu_matrix a:link {color:#336699;}
#menu_matrix a:visited {color:#336699;}
#menu_matrix a:hover {color:red; text-decoration:underline;}
#menu_matrix a:active {color:#336699;}

</style>


<script type="text/javascript" src="<?php print getBaseFolder(); ?>js/jquery.js"></script>
<script type="text/javascript">


   var menuMatrixParams = {};

    <?php
        $menuMatrixParams = array();
        if(isset($_GET['state'])){
            $menuMatrixParams['state'] = $_GET['state'];
        }
        if(isset($_GET['event'])){
            $menuMatrixParams['event'] = $_GET['event'];
        }
        if(isset($_GET['time'])){
            $menuMatrixParams['time'] = $_GET['time'];
        }
        if($menuMatrixParams){
            echo "menuMatrixParams = eval(" . json_encode($menuMatrixParams) .");\n";
        }
    ?>

    // ajax
    function getMenuItems(){
        var data = menuMatrixParams;
        //data['timestamp'] = new Date().getTime();

        $.ajax({
            type: "GET",
            async: false,
            url: "./ajaxgetmenu.php",
            dataType: "json",
            data: data,
            success: updateMenuMap
        });
    }

    function updateMenuMap(ajaxData){
        groups = ajaxData['groups'];
        data = ajaxData['data'];
        showMenuMatrix(groups);


 
//map refresh

readdata();


 }

    function resetMenuMatrix(param){
        if(typeof param == 'undefined'){
            menuMatrixParams = {};
        }else{
            delete menuMatrixParams[param];
        }
        getMenuItems();
    }

    function refineMenuMatrix(name, value){
        menuMatrixParams[name] = value;
        getMenuItems();
    }

    function sizeOfJSON(jsonObj){
        count = 0;
        for (var key in jsonObj){
            if (jsonObj.hasOwnProperty(key)){
                count++;
            }
        }
        return count;
    }

    function showMenuMatrix(menuJSON){
        var stateNumbers = new Array();

        if(sizeOfJSON(menuJSON['states']) == 1){
            for (var i in menuJSON['states']){
                stateNumbers.push(i + ' (' + menuJSON['states'][i] + ')');
            }
        }else{
            for (var i in menuJSON['states']){
                stateNumbers.push('<a href="javascript: refineMenuMatrix(\'state\', \'' + i + '\');">' + i + ' (' + menuJSON['states'][i] + ')</a>');
            }
        }


        var eventNumbers = new Array();
        if(sizeOfJSON(menuJSON['events']) == 1){
            for (var i in menuJSON['events']){
                eventNumbers.push(i + ' (' + menuJSON['events'][i] + ')');
            }
        }else{
            for (var i in menuJSON['events']){
                eventNumbers.push('<a href="javascript: refineMenuMatrix(\'event\', \'' + i + '\');">' + i + ' (' + menuJSON['events'][i] + ')</a>');
            }
        }

        var timeNumbers = new Array();
        if(sizeOfJSON(menuJSON['times']) == 1){
            for (var i in menuJSON['times']){
                if (menuJSON['times'].hasOwnProperty(i)){
		if(i == 0){
                    timeNumbers.push('upcoming (' + menuJSON['times'][i] + ')');
                }else if(i == 1){
                    timeNumbers.push('last hour (' + menuJSON['times'][i] + ')');
                }else{
                    timeNumbers.push('last ' + i +' hrs (' + menuJSON['times'][i] + ')');
                }
		}
            }
        }else{

            for (var i in menuJSON['times']){
		if(i == 0){
                    timeNumbers.push('<a href="javascript: refineMenuMatrix(\'time\', \'' + i + '\');">upcoming (' + menuJSON['times'][i] + ')</a>');
                }else if(i == 1){
                    timeNumbers.push('<a href="javascript: refineMenuMatrix(\'time\', \'' + i + '\');">last hour (' + menuJSON['times'][i] + ')</a>');
                }else{
                    timeNumbers.push('<a href="javascript: refineMenuMatrix(\'time\', \'' + i + '\');">last ' + i +' hrs (' + menuJSON['times'][i] + ')</a>');
                }
            }
        }


        var menuHtml = 'States: ' + stateNumbers.join(', ');
        if(typeof(menuMatrixParams['state']) !== 'undefined'){
            menuHtml += '&nbsp;&nbsp;<a href="javascript: resetMenuMatrix(\'state\');"><img src="images/refresh.gif" border="0" title="reset" /></a>';
        }
        menuHtml +=  '<br />';
        menuHtml += 'Category: ' + eventNumbers.join(', ');
        if(typeof(menuMatrixParams['event']) !== 'undefined'){
            menuHtml += '&nbsp;&nbsp;<a href="javascript: resetMenuMatrix(\'event\');"><img src="images/refresh.gif" border="0" title="reset" /></a>';
        }
        menuHtml +=  '<br />';
        menuHtml += 'Time: ' + timeNumbers.join(', ');
        if(typeof(menuMatrixParams['time']) !== 'undefined'){
            menuHtml += '&nbsp;&nbsp;<a href="javascript: resetMenuMatrix(\'time\');"><img src="images/refresh.gif" border="0" title="reset" /></a>';
        }
        menuHtml +=  '<br />'
        menuHtml += '<a href="javascript: resetMenuMatrix();"><b>Reset All</b> <img src="images/refresh.gif" border="0" title="reset" /></a>';

        $("#menu_matrix").html(menuHtml);

        /**
         *  TODO: work with data.
         **/
    }
    <?php
        echo "var groups = eval(" . json_encode($groups) .");\n";
        echo "var data = eval(" . json_encode($data) .");\n";
    ?>
    $(document).ready(function () {
        showMenuMatrix(groups);
        load();

    /**
        *  TODO: work with data.
        **/
    });
    //alert(topGroups['states']['NSW']);

/* ======= Map script =========== */



var pX=  134.6923828125 , pY= -28.30438068296277,  pZ=4, mt=0;



</script>
</head>


<!-- <body onload="startload()" onunload="GUnload()"> -->
<body onunload="GUnload()">


<div id="veil2"></div>
<div id="box">
<div id="desc"></div>
<div id="close"><a href="http://ewn.com.au" onClick="change2();return false" title="Close Window"><img src="images/close.png" border="0" alt="close window"/></a></div>
</div>




<div id="global">

<div id="top"></div>
<div id="left"></div>
<div id="logo"><div id="title"><h1>EMERGENCY ALERTS AND CURRENT INCIDENTS</h1></div><div id="logoimg"></div></div>
<div id="spacer"></div>

<div id="tab"><div id="tabcnt">
<div id="menu_matrix"></div>

</div>

<div id="tabcnt1"><a href="http://ewn.com.au" onclick="change(2);return false">| Show ungeocoded locations |</a></div>


<div id="tabcnt2"><a href="http://ewn.com.au" onClick="change(1);return false" title="About this application"><img id="info" src="images/info.png" border="0" alt="About this application" /></a></div>
</div>




<div id="map" ><br><br><br>&nbsp;&nbsp;&nbsp; loading map ... please wait</div>
<div id='warning'><span class="red2">Click on table row to show individual recipients</span></div>
<div id="tabls">

<?php

require("table.inc");

?>




<br><br>



</div>
<div id="spacer2"></div>
<div id="footer"><div id="footertxt"> <a href="http://www.aus-emaps.com/">created by: aus-emaps.com</a></div></div>
<div id="spacer3"></div>
</div>
<div id="bottom"><div id="cls2"><a href="http://ewn.com.au" onClick="cls();return false" title="Close"><img src="images/close.png" border="0" ></a></div><div id="cls3"><p>Scroll down to table</p></div><div id="cls4"><img src="images/scrl.png" border="0" ></div></div>

</body>
</html>
