<!DOCTYPE html>
<?php
ini_set('max_execution_time', 3600);
$xml=simplexml_load_file("Provresultat.xml");
//include('httpful.phar');
$array=$xml->R;
$oldPlace="?";
$i=1;
include('httpful.phar');

$iForPoints=0;
foreach($array as $a){
	
	$place=$a->C7;
	$place = parse_url($place,PHP_URL_FRAGMENT);
	$place=substr($place,46);
	$place=substr($place,0,-4);
	if(preg_match('/"/', $place)>0){
		$place="Okänt namn";
	}
	if($iForPoints==100){
		$iForPoints=0;
	}
	if(($iForPoints==0&&$a->C10!=""&&$a->C9!=""&&strpos($place,$oldPlace)===false)||$i==1){
		$lng=$a->C10;
		$lat=$a->C9;		
		while(strlen($lng)>9){
			$lng=substr($lng,0,-1);
		}
		while(strlen($lat)>9){
			$lat=substr($lat,0,-1);
		}

		$oldPlace=$place;

		$alg=$a->C15;	
		$arrayAlg[]=$alg;
		$temp=$a->C0;
		//echo $place."<br><br>";
		$url = "http://opendata-download-metfcst.smhi.se/api/category/pmp2g/version/2/geotype/point/lon/$lng/lat/$lat/data.json";
		$response = \Httpful\Request::get($url)
		->send();
		$rows = json_decode($response, true);
		$timeNow=date("Y-m-d h:a",time());
		$rowsUsed= $rows['timeSeries'];
		if(count($rowsUsed)!=0){		
			foreach($rowsUsed as $time){				
				$bT=strtotime($time['validTime']);
				$t=date("Y-m-d h:a",strtotime("-2 hours",$bT));
				if($t==$timeNow){
					$rowsUsed=$time['parameters'];
					foreach($rowsUsed as $weather){
						if($weather['name']=='t'){
							$weatherTemp=$weather['values'][0];
						}
					}
				}
			}
		}		
		
		//<b>$place</b><br>
		$object[]=array("lat"=>(float)$lat,"lng"=>(float)$lng,"info"=>"<b>$place</b><br>Det finns: $alg i det har vattendraget<br>Vattentemperaturen ar vid senaste matningen: $temp grader celsius<br>Temperaturen på platsen är: $weatherTemp grader celsius");	
		$i++;
		
	}
	//echo $iForPoints;
	$iForPoints++;
}
if(isset($object)){
	$array_json = json_encode($object);
	$array_json_alg=json_encode($arrayAlg);
	//var_dump($arrayAlg);
	
}
?>
<html>
  <head>
    <meta name="viewport" content="initial-scale=1.0, user-scalable=no">
    <meta charset="utf-8">
    <title>Marker Clustering</title>
    <style>
      /* Always set the map height explicitly to define the size of the div
       * element that contains the map. */
      #map {
        height: 100%;
      }
      /* Optional: Makes the sample page fill the window. */
      html, body {
        height: 100%;
        margin: 0;
        padding: 0;
      }
    </style>
  </head>
  <body>
    <div id="map"></div>
    <script>

function initMap() {
	var alg = JSON.parse('<?= $array_json_alg; ?>');	
  var map = new google.maps.Map(document.getElementById('map'), {
    zoom: 3,
    center: {
      lat: 62.381435,
      lng: 17.383067
    }
  });
  var infoWin = new google.maps.InfoWindow();
  // Add some markers to the map.
  // Note: The code uses the JavaScript Array.prototype.map() method to
  // create an array of markers based on a given "locations" array.
  // The map() method here has nothing to do with the Google Maps API.
	var green='http://maps.google.com/mapfiles/ms/icons/green-dot.png';
	var red='http://maps.google.com/mapfiles/ms/icons/red-dot.png';
	var yellow='http://maps.google.com/mapfiles/ms/icons/yellow-dot.png';
  
	    var marker, i;
	//alert(alg[0]);
	

var mcOptions = {styles: [{
//grönt hjärta	
height: 26,
url: "https://raw.githubusercontent.com/plank/MarkerClusterer/mod/images/heart30.png",
width: 30
},
{ 
//blå	
height: 53,
url: "https://raw.githubusercontent.com/googlemaps/v3-utility-library/master/markerclusterer/images/m1.png",
width: 53
},
{
//gul	
height: 56,
url: "https://raw.githubusercontent.com/googlemaps/v3-utility-library/master/markerclusterer/images/m2.png",
width: 56
},
{
//röd	
height: 66,
url: "https://raw.githubusercontent.com/googlemaps/v3-utility-library/master/markerclusterer/images/m3.png",
width: 66
},
{
//ljuslila		
height: 78,
url: "https://raw.githubusercontent.com/googlemaps/v3-utility-library/master/markerclusterer/images/m4.png",
width: 78
},
{
//mörklila
height: 90,
url: "https://raw.githubusercontent.com/googlemaps/v3-utility-library/master/markerclusterer/images/m5.png",
width: 90
}]}

  var markers = locations.map(function(location, i) {
		console.log(alg[i][0]);
	 	if(alg[i][0]=="Ingen blomning"){	
			var color=green;
		}
		if(alg[i][0]=="Blomning"){
			var color=red;
		}
		if(alg[i][0]=="Ingen uppgift"){
			var color=yellow;
		}	 
    var marker = new google.maps.Marker({
		
      position: location,
	  icon: color
	  
    });

	
    google.maps.event.addListener(marker, 'click', function(evt) {
      infoWin.setContent(location.info);
      infoWin.open(map, marker);
    })
    return marker;
  });

  // Add a marker clusterer to manage the markers.
  var markerCluster = new MarkerClusterer(map, markers,mcOptions);
  
	  markerCluster.setCalculator(function(markers, numStyles){
	  var index = 0;
	  var i2=1;
	  var lengthOfCluster = markers.length;
	 /* if(i2==1){
		  i2++;
		 // alert(i2);
		//  alert("hej1");
		  console.log(markers[0]['icon']+"<br>");
		  console.log(markers[1]['icon']);
		console.log(markers[2]['icon']);
	  	
	  }*/
	 //	 console.log(lengthOfCluster);
	 // console.log(markers[2]/*['icon']*/);
	 // alert(lengthOfCluster);
	// var i2=0;
	  for(var i=0;i<lengthOfCluster;i++){	
	 	if(markers[i]['icon'].includes("red")){
			var index=4;
			break;
		}
		if(markers[i]['icon'].includes("green")){	
			index=1;
		}	
	  }
	///	alert("hej");
	 /* var count = markers.length;
	  var total = count;
	  while (total !== 0) {
		total = parseInt(total / 11);
		index++;
	  }
	  index = Math.min(index, numStyles);
	  console.log(index);
	  */
	  //console.log(index);
	  return {
		text: lengthOfCluster,
		index: index
	  };
	});  
}
var obj = JSON.parse('<?= $array_json; ?>');
var locations = obj;
//console.log(locations);
google.maps.event.addDomListener(window, "load", initMap);
    </script>
    <script src="https://developers.google.com/maps/documentation/javascript/examples/markerclusterer/markerclusterer.js">
    </script>
    <script async defer
    src="https://maps.googleapis.com/maps/api/js?key=AIzaSyBTmzSil2kh4Qii5NGbMuS6UWUoXSzzExk&callback=initMap">
    </script>
  </body>
</html>