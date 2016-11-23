/* ===== Global Variables ===== */

var map;
var geoXml; // notneeded
var zoomlistener;

/* ========= Main Functions =============== */

var map;

function load() {

    // alert('starting');

    var australia = new google.maps.LatLng(pY, pX);
    map = new google.maps.Map(document.getElementById('map'), {
	center : australia,
	zoom : pZ,
	mapTypeId : google.maps.MapTypeId.ROADMAP
    });

    readdata();// new function

    /*
     * V2: map = new GMap(document.getElementById("map")); map.setCenter(new
     * GLatLng(pY,pX), pZ); map.enableScrollWheelZoom(); //map.addControl(new
     * GLargeMapControl()); map.addControl(new GLargeMapControl3D());
     * map.addControl(new GMapTypeControl());//add thorugh function below
     * map.addControl(new GScaleControl(),new
     * GControlPosition(G_ANCHOR_BOTTOM_LEFT, new GSize(2, 40)));
     *
     *
     * readdata();//new function
     *
     *
     * //function to add scroll down notice //scrlfn();
     */

}

/* =========== function to get and parse other postcodes ============ */

var on = []; // State |Ced |Client
var tw = [];// value |State |CED
var th = [];// number |Value |State
var fo = [];// avg |NUmber |Value
var fi = [];// year |Avg |Street
var si = [];// icon |Year |Adr2
var se = [];// lat |icon |Yr
var ei = [];// lng |sq km |lat
var ni = [];// not used |lat |lng
var te = [];// not used |lng |industry

// selection of feeds
var sel = [];// what is selected

var feedsel = '';

/* new */

var mappt = [];// array to store coords for table clicks

function readdata() {

    mappt = [];

    // remove markers
    deleteMarkers();

    // clear markers
    /*
     * V2: if(gmarkers){ var hlngm=gmarkers.length;
     *
     * for (j=0;j<hlngm;j++) {
     *
     * //remove only markers from map, map.removeOverlay(gmarkers[j]);
     *
     * if(j==hlngm-1){gmarkers=[];} }
     *  }
     *
     */

    // Deletes all markers in the array by removing references to them.
    // function deleteMarkers() {
    // clearMarkers();
    // markers = [];
    // }

    initMap();

    initTable();

}

// Sets the map on all markers in the array.
function setAllMap(map) {
    for ( var i = 0; i < gmarkers.length; i++) {
	gmarkers[i].setMap(map);
    }
}

// Removes the markers from the map, but keeps them in the array.
function clearMarkers() {
    setAllMap(null);
}

// Shows any markers currently in the array.
function showMarkers() {
    setAllMap(map);
}

// Deletes all markers in the array by removing references to them.
function deleteMarkers() {
    clearMarkers();
    gmarkers = [];
}

/* new */

var gmarkers = [];

function initMap() {

    var mlng = data.length;

    var marker;

    var infowindow = new google.maps.InfoWindow({
	maxWidth : 400
    });

    for ( var j = 0; j < mlng; j++) {

	var coord = data[j]['point_str'];
	var cd = coord.split(" ");

	var icn;
	// var whicn=data[j]['category'];

	var whicn1 = data[j]['category'];

	var whicn2 = whicn1.split("/");

	var whicn = whicn2[0];

	switch (whicn) {
	case "emergency warning":
	    icn = 1;
	    break;
	case "watch and act":
	    icn = 2;
	    break;
	case "advice":
	    icn = 3;
	    break;
	case "no alert level":
	    icn = 4;
	    break;
	case "advice/incidents/open":
	    icn = 3;
	    break;
	case "advice/incidents/closed":
	    icn = 3;
	    break;
	case "watch and act/incidents/open":
	    icn = 2;
	    break;
	case "watch and act/incidents/closed":
	    icn = 2;
	    break;
	case "emergency warning/incidents/open":
	    icn = 1;
	    break;
	case "emergency warning/incidents/closed":
	    icn = 1;
	    break;
	default:
	    icn = 4;
	}

	var topicon;
	var imp;

	switch (icn) {
	case 1:
	    topicon = 'images/Emergency.gif';
	    imp = 4;
	    break;
	case 2:
	    topicon = 'images/WatchandAct.gif';
	    imp = 3;
	    break;
	case 3:
	    topicon = 'images/Advice.gif';
	    imp = 2;
	    break;
	case 4:
	    topicon = 'images/Incident.gif';
	    imp = 1;
	    break;
	}

	var image = {
	    url : topicon,
	    // This marker is 20 pixels wide by 32 pixels tall.
	    size : new google.maps.Size(25, 25),
	    // The origin for this image is 0,0.
	    origin : new google.maps.Point(0, 0),
	    // The anchor for this image is the base of the flagpole at 0,32.
	    anchor : new google.maps.Point(12, 25)
	};

	// Shapes define the clickable region of the icon.
	// The type defines an HTML &lt;area&gt; element 'poly' which
	// traces out a polygon as a series of X,Y points. The final
	// coordinate closes the poly by connecting to the first
	// coordinate.
	var shape = {
	    coord : [ 1, 1, 1, 25, 25, 25, 25, 1 ],
	    type : 'poly'
	};

	// convert timestamp to user location time:

	var d0 = new Date(data[j]["unixtimestamp"] * 1000); // epoch to date

	var d = d0.toLocaleString(); // user local time

	var myLatLng = new google.maps.LatLng(cd[1], cd[0]);

	marker = new google.maps.Marker({
	    position : myLatLng,
	    map : map,
	    icon : image,
	    shape : shape,
	    title : data[j]["category"],
	    zIndex : imp
	});

	google.maps.event.addListener(marker, 'click', (function(marker, j) {
	    return function() {

		var html = '<b>Alert Level:</b> ' + data[j]["category"]
			+ '<br><b>Location:</b> ' + data[j]["title"]
			+ '<br><b>Time:</b> ' + d + '<br> '
			+ data[j]["description"] + '<br><br> ';
		infowindow.setContent(html);
		infowindow.open(map, marker);
	    }
	})(marker, j));

	// save the info we need to use later for the side_bar
	gmarkers.push(marker);

	// V2: var point = new GLatLng(cd[1],cd[0]);

    }
}

/*
 * DELETE
 *
 * function add_marker(point, note) { var marker = new google.maps.Marker({map:
 * map, position: point, clickable: true});
 *
 * var info = new google.maps.InfoWindow({ // content: html, // maxWidth: 400
 *
 * });
 *
 * marker.note = note; google.maps.event.addListener(marker, 'click', function() {
 * info.content = this.note; info.open(this.getMap(), this); }); return marker; }
 *
 */

/* marker function */
/* === DELETE from HERE === */

/*
 * var iconRed = new GIcon(); iconRed.image = 'images/red.png'; //
 * iconGreen.shadow = ''; iconRed.iconSize = new GSize(12, 20);
 *
 * iconRed.iconAnchor = new GPoint(6, 20); iconRed.infoWindowAnchor = new
 * GPoint(5, 1);
 *
 *
 * var iconEm1 = new GIcon(); // iconEm1.image = 'images/Emergency_Warning.png';
 * iconEm1.image = 'images/Emergency.gif'; iconEm1.iconSize = new GSize(25, 25);
 * iconEm1.iconAnchor = new GPoint(12, 25); iconEm1.infoWindowAnchor = new
 * GPoint(12, 1);
 *
 *
 * var iconEm2 = new GIcon(); // iconEm2.image = 'images/WatchandAct.png';
 * iconEm2.image = 'images/WatchandAct.gif'; iconEm2.iconSize = new GSize(25,
 * 25); iconEm2.iconAnchor = new GPoint(12, 25); iconEm2.infoWindowAnchor = new
 * GPoint(12, 1);
 *
 *
 * var iconEm3 = new GIcon(); // iconEm3.image = 'images/Advice.png';
 * iconEm3.image = 'images/Advice.gif'; iconEm3.iconSize = new GSize(25, 25);
 * iconEm3.iconAnchor = new GPoint(12, 25); iconEm3.infoWindowAnchor = new
 * GPoint(12, 1);
 *
 * var iconEm4 = new GIcon(); // iconEm4.image = 'images/NotApplicable.png';
 * iconEm4.image = 'images/Incident.gif'; iconEm4.iconSize = new GSize(25, 25);
 * iconEm4.iconAnchor = new GPoint(12, 25); iconEm4.infoWindowAnchor = new
 * GPoint(12, 1);
 *
 *  // for clicks on table rows //var gmarkers = [];
 */
/*
 * // for marker z-index function importanceOrder (marker,b) { return
 * GOverlay.getZIndex(marker.getPoint().lat()) + marker.importance*1000000; }
 */

/*
 * // marker for events function createMarker(point, html, icn) {
 *
 *
 *
 * var marker;
 *
 *
 * var topicon;
 *
 * switch (icn) { case 1: topicon=iconEm1; break; case 2: topicon=iconEm2;
 * break; case 3: topicon=iconEm3; break; case 4: topicon=iconEm4; break; }
 *
 *  // var marker = new
 * GMarker(point,{icon:icon,zIndexProcess:importanceOrder,title:cat});
 * //marker.importance = imp;
 *
 *
 *
 *
 * markerOptions = { icon:topicon,zIndexProcess:importanceOrder }; marker = new
 * GMarker(point, markerOptions);
 *
 * var imp=5-icn; marker.importance = imp;
 *
 *
 * //var marker = new LabeledMarker(point, {icon: geticn, labelText: no,
 * labelOffset: ofst});
 *
 * //var marker = new LabeledMarker(point, {icon: geticn, labelText: no,
 * labelOffset: ofst});
 *  // var txt = "<b>" + no + ":</b> html <br/>" ;
 *
 *
 * GEvent.addListener(marker, 'click', function() {
 * marker.openInfoWindowHtml(html); });
 *
 *  // save the info we need to use later for the side_bar
 * gmarkers.push(marker);
 *
 *
 * return marker; }
 *
 *
 */

/* for clicks on table row */

function getpt(no) {

    // window.alert(on[no]);

    // var coordpt=arrData[no][5];
    var coordpt = mappt[no];

    var cdpt = coordpt.split(" ");

    // var point = new GLatLng(cd[1],cd[0]);

    var zmto = map.getZoom();
    // if(zmto<15){map.setCenter(new GLatLng(cdpt[1],cdpt[0]),15);

    if (zmto < 15) {
	map.setCenter(new google.maps.LatLng(cdpt[1], cdpt[0]));
	map.setZoom(15);

	// open baloon
	// GEvent.trigger(gmarkers[no], "click");
	google.maps.event.trigger(gmarkers[no], 'click');
    } else {

	map.setCenter(new google.maps.LatLng(cdpt[1], cdpt[0]));
	map.setZoom(zmto);
	// open baloon
	// GEvent.trigger(gmarkers[no], "click");
	google.maps.event.trigger(gmarkers[no], 'click');
    }

    // go to map window
    window.location.hash = "top";

}

/* === New table === */

function initTable() {

    // mappt=[];//clear array?

    var top = '<table cellpadding="0" cellspacing="0" border="0" id="table" class="tinytable"><thead><tr><th><h3>Alert Level</h3></th><th><h3>State</h3></th><th><h3>Location</h3></th><th><h3>Time</h3></th><th><h3>Description</h3></th></tr></thead><tbody>';

    var bot = '</tbody></table>';

    var mid = '';

    var hl = data.length;

    // window.alert(hl);

    // if(hl>2){

    if (hl > 0) {

	// for (var k = 0; k < hl-1; k++) {
	for ( var k = 0; k < hl; k++) {

	    // add points to a separate array
	    mappt.push(data[k]['point_str']);

	    // convert timestamp to user location time:
	    // var d = new Date(0); // The 0 there is the key, which sets the
	    // date to the epoch - but need to shift it to AEST!

	    // d.setUTCSeconds(data[k]["unixtimestamp"]); // but this shows utc
	    // time as local time !? how to convert to +10 then back to date? no
	    // need!!!
	    var d0 = new Date(data[k]["unixtimestamp"] * 1000);

	    var d = d0.toLocaleString();

	    mid += '<tr onclick="getpt(' + k + ')"><td>' + data[k]["category"]
		    + '</td><td>' + data[k]["state"] + '</td><td>'
		    + data[k]["title"] + '</td><td>' + d + '</td><td>'
		    + data[k]["description"] + '</td></tr>';

	    // mid+='<tr
	    // onclick="getpt('+k+')"><td>'+arrData[k][3]+'</td><td>'+arrData[k][1]+'</td><td>'+arrData[k][2]+'</td><td>'+arrData[k][0]+'</td><td>'+arrData[k][4]+'</td></tr>';

	    if (k == hl - 1) {
		// if (k==hl-1){
		document.getElementById("mtb").innerHTML = top + mid + bot;
		sorter.init();// initiate table

	    }

	}
    } else {

	mid += '<tr><td>No current reports</td><td></td><td></td><td></td><td></td></tr>';
	document.getElementById("mtb").innerHTML = top + mid + bot;
	sorter.init();// initiate table

    }

}

var sorter = new TINY.table.sorter('sorter', 'table', {
    headclass : 'head',
    ascclass : 'asc',
    descclass : 'desc',
    evenclass : 'evenrow',
    oddclass : 'oddrow',
    evenselclass : 'evenselected',
    oddselclass : 'oddselected',
    paginate : true,
    size : 1000,
    colddid : 'columns',
    currentid : 'currentpage',
    totalid : 'totalpages',
    startingrecid : 'startrecord',
    endingrecid : 'endrecord',
    totalrecid : 'totalrecords',
    hoverid : 'selectedrow',
    pageddid : 'pagedropdown',
    navid : 'tablenav',
    sortcolumn : 3,
    sortdir : 1,
// sum:[2],
// avg:[6,7,8],
// columns:[{index:2, format:'$', decimals:0},{index:3, format:'$', decimals:0}]
// init:true
});

/* DELETE in next version - still used for parsing non- geocoded results */
/* parsing csv */
/* http://www.bennadel.com/blog/1504-Ask-Ben-Parsing-CSV-Strings-With-Javascript-Exec-Regular-Expression-Command.htm */

var arrData = [ [] ];

/* ========== Parsing ungeocode ============= */

/* Change this function! in next edition */

function parseNG(event) {

    function showUngeocoded(records){

	arrData = records;

	var text2 = '<h2>Ungeocoded locations</h2><p>Alerts and current incidents, as reported by State emergency authorities (WA, SA, Vic, Tas and NSW), with unresolved geographic locations. Listing sorted from the most recent to the oldest.</p><p></p>';

	var listng = '';
	var hla = arrData.length;
	// window.alert(arrData[0]);

	// window.alert(hla);
	// window.alert(arrData[0]+'and '+arrData[1])
	// arrData[k][3]+'</td><td>'+arrData[k][1]

	if (hla < 3) {
	    listng = '<hr><p><span class="red2">No ungeocoded locations.</span></p>'
	}

	else {

	    for ( var k = 0; k < hla - 1; k++) {
		listng += '<hr><p><span class="red2">State: '
			+ arrData[k][1] + ' Alert level: '
			+ arrData[k][3] + '</span></p><p>'
			+ arrData[k][2] + '</p><p>' + arrData[k][4]
			+ '</p>';
	    }
	}

	document.getElementById("desc").innerHTML = text2 + listng;
    }


    $.ajax({
        type: "GET",
        async: true,
        url: "./dumpngjson.php?e=" + event,
        dataType: "json",
        success: showUngeocoded
    });
}

// +++++++++++ function to determine if to show scroll down box +++++++++++

function scrlfn() {
    var clientheight = document.body.clientHeight;

    if (clientheight < 680) {
	var sd = document.getElementById("bottom").style;
	sd.display = "block";
    }

}

// function to close scroll window

function cls() {
    var sd = document.getElementById("bottom").style;
    sd.display = "none";
}

/* ============ script for gray overlay ========== */
function change2() {
    e = document.getElementById("veil2");
    e.style.visibility = "hidden";

    d = document.getElementById("box");
    d.style.visibility = "hidden";

    //map.closeInfoWindow();
}

/* script to open gray overlay */

function change(wtxt, event) {
    e = document.getElementById("veil2");
    e.style.visibility = "visible";

    d = document.getElementById("box");
    d.style.visibility = "visible";

    // fix for veil height - only FF and IE
    var hheight = document.body.parentNode.scrollHeight;
    // var hheight=document.body.parentNode.offsetHeight;
    e.style.height = hheight + 'px';// has to have px for FF

    if (wtxt == 1) {

	var text1 = '<h2>About This Application</h2><p><span class="bd">Alerts and current incidents</span> as reported by four State emergency authorities (SA, Vic, Tas, NSW).</p><p></p><table><tr class="yellow"><td><span class="bd">LEGEND</span></td><td><span class="bd"></span></td></tr><tr><td><img id="info2" src="images/Emergency.gif" border="0" alt="legend" /></td><td><p><b>Emergency Warning</b> - You may be in danger and need to take action immediately. Any delay now puts your life at risk.</p> </td></tr><tr><td><img id="info2" src="images/WatchandAct.gif" border="0" alt="legend" /></td><td><p><b>Watch and Act</b> - A heightened level of threat. Conditions are changing; you need to start taking action now to protect you and your family.</p></td></tr><tr><td><img id="info2" src="images/Advice.gif" border="0" alt="legend" /></td><td><p><b>Advice</b> - A fire has started - there is no immediate danger.</p></td></tr><tr><td><img id="info2" src="images/Incident.gif" border="0" alt="legend" /></td><td><p>Not applicable</p></td></tr></table><p></p><p> </p>';

	document.getElementById("desc").innerHTML = text1;

    }

    if (wtxt == 2) {

	//get ungeocoded
	parseNG(event);

    }

}
