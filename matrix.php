<?php

require_once(dirname(__FILE__) . '/config/startup.php');
require_once(dirname(__FILE__) . '/lib/App.php');
require_once(dirname(__FILE__) . '/lib/KLogger.php');
require_once(dirname(__FILE__) . '/lib/Db.php');
require_once(dirname(__FILE__) . '/models/Incident.php');

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
<html>
<head>

<script type="text/javascript"
	src="<?php print getBaseFolder(); ?>js/jquery.js"></script>
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
        /**
         *  TODO: work with data.
         **/
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
        for (var i in jsonObj){
            count++;
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
                if(i == 0){
                    timeNumbers.push('upcoming (' + menuJSON['times'][i] + ')');
                }else if(i == 1){
                    timeNumbers.push('last hour (' + menuJSON['times'][i] + ')');
                }else{
                    timeNumbers.push('last ' + i +' hrs (' + menuJSON['times'][i] + ')');
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
            menuHtml += '&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;<a href="javascript: resetMenuMatrix(\'state\');">Reset</a>';
        }
        menuHtml +=  '<br />';
        menuHtml += 'Category: ' + eventNumbers.join(', ');
        if(typeof(menuMatrixParams['event']) !== 'undefined'){
            menuHtml += '&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;<a href="javascript: resetMenuMatrix(\'event\');">Reset</a>';
        }
        menuHtml +=  '<br />';
        menuHtml += 'Time: ' + timeNumbers.join(', ');
        if(typeof(menuMatrixParams['time']) !== 'undefined'){
            menuHtml += '&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;<a href="javascript: resetMenuMatrix(\'time\');">Reset</a>';
        }
        menuHtml +=  '<br />'
        menuHtml += '<a href="javascript: resetMenuMatrix();">Reset All</a>';

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
        /**
        *  TODO: work with data.
        **/
    });
    //alert(topGroups['states']['NSW']);
</script>
</head>
<body>
<div id="menu_matrix"></div>
</body>
</html>
