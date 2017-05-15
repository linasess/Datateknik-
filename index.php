<!DOCTYPE html>

<?php
//Förlänger den maximala processtiden för sidan.
ini_set('max_execution_time', 3600);
//Laddar xml-filen från 
$xml=simplexml_load_file("Provresultat.xml");
//Skapar en array med alla objekt av provresultat (tex vattentemperatur info om algblomning).
$array=$xml->R;
$oldPlace="?";
$oldLng=0;
$oldLat=0;
$algInfo="";
$i=1;
$oldWeatherTemp=0;
//Inkluderar biblioteket httpful.
include('httpful.phar');

$iForPoints=0;
//En array som går igenom alla objekt med provresultat.
foreach($array as $a){
	
	//Platsen plockas fram från det aktuella objektet.
	$place=$a->C7;
	//Platsen är skriven som en länk i xml-filen så i följande array omvandlas den till en sträng.
	$place = parse_url($place,PHP_URL_FRAGMENT);
	$place=substr($place,46);
	$place=substr($place,0,-4);
	//Om platsen är skriven med cituationstecken i xml-filen så skrivs "okänt namn" ut.
	if(preg_match('/"/', $place)>0){
		$place="Okänt namn";
	}
	//Hoppar över punkter. 
	/*if($iForPoints==500){
		$iForPoints=0;
	}*/
	//Om "hoppa över räknaren" är 0, kordinaterna inte är tomma och den nya punkten inte har värden från samma vattendraget
	//som den förra eller huvudräknaren är 1 väljs nya värden ut.
	if(($iForPoints==0&&$a->C10!=""&&$a->C9!=""&&strpos($place,$oldPlace)===false)||$i==1){
		//Kordinaterna anges.
		$lng=$a->C10;
		$lat=$a->C9;		
		//Om kordinaterna består av mer en nio tecken tas tecken bort för att kordinaterna ska kunna användas
		//i anropet till SMHIs api nedan.
		while(strlen($lng)>9){
			$lng=substr($lng,0,-1);
		}
		while(strlen($lat)>9){
			$lat=substr($lat,0,-1);
		}
		//Den nuvarande platsen sätts som den förra för att jämföras med nästa objekt.
		$oldPlace=$place;
		//Statusen för algbildning anges.
		$alg=$a->C15;	
		//Statusen för algbildning sätts in i en array som senare kommer användas i javascriptet
		//för att välja vilken färg varje punkt ska ha på Google Maps karta.
		$arrayAlg[]=$alg;
		//Om statusen för algblomning är "ingen uppgit" skrivs informationen om
		//för att bli tydligare för användaren.
		$alg=strtolower($alg);
		if($alg=="ingen uppgift"){
			$algInfo="Det finns ingen uppgift om algblomningen på platsen.";
		}
		else{
			$algInfo="Då fanns $alg i vattendraget.";
		}
		//Temperaturen anges.
		$temp=$a->C0;
		//Datum för senaste mätningen av algblomning och vattentemperatur anges.
		$measuredAt=strtotime($a->C12);
		$measuredAt=date("Y-m-d",$measuredAt);
		//Kordinaterna görs om från sträng till float.
		$lng=(float)$lng;
		$lat=(float)$lat;
		//Följande funktion tittar om de nya kordinaterna är ett visst avstånd från de gammla.
		//Om de nya kordinaterna är utanför spannet hämtas aktuell temperatur.
		$diff=0.5;
		if(($oldLng-$diff)>$lng||($oldLng+$diff)<$lng||$oldLat-$diff>$lat||($oldLat+$diff)<$lat||$oldWeatherTemp==0){
			//SMHIs api anropas med kordinaterna från xml-filen
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
		}		
		else{
			$weatherTemp=$oldWeatherTemp;
		}		
		
		$object[]=array("lat"=>$lat,"lng"=>$lng,"info"=>"<b>$place</b><br>Senaste mätningen av algblomningen och vattentemperatur genomfördes $measuredAt.<br>$algInfo<br>Vattentemperaturen var $temp grader celsius.<br>Temperaturen på platsen är $weatherTemp grader celsius.");	
		$oldLng=$lng;
		$oldLat=$lat;
		$oldWeatherTemp=$weatherTemp;
		$i++;
	}
	
	//$iForPoints++;
}
if(isset($object)){
	$array_json = json_encode($object);
	$array_json_alg=json_encode($arrayAlg);	
}
?>

<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Bad&amp;vader</title>
    <link rel="stylesheet" href="assets/bootstrap/css/bootstrap.min.css">
    <link rel="stylesheet" href="assets/fonts/ionicons.min.css">
    <link rel="stylesheet" href="assets/css/Footer-Basic.css">
    <link rel="stylesheet" href="assets/css/Map-Clean.css">
    <link rel="stylesheet" href="assets/css/Navigation-Clean1.css">
    <link rel="stylesheet" href="assets/css/Navigation-with-Button1.css">
    <link rel="stylesheet" href="assets/css/styles.css">
	<link rel="stylesheet" href="fontCSS.css">
	
	
</head>
<body> 
 <div></div>
    <div>
        <nav class="navbar navbar-default navigation-clean-button">
            <div class="container">
                <div class="navbar-header"><a class="navbar-brand navbar-link" href="index.php" >Bad &amp; Väder</a>
                    <button class="navbar-toggle collapsed" data-toggle="collapse" data-target="#navcol-1"><span class="sr-only">Toggle navigation</span><span class="icon-bar"></span><span class="icon-bar"></span><span class="icon-bar"></span></button>
                </div>
                <div class="collapse navbar-collapse" id="navcol-1">
                    <ul class="nav navbar-nav">
                        <li class="dropdown"><a class="dropdown-toggle" data-toggle="dropdown" aria-expanded="false" href="#">Information om algblomning</a>
                            <ul class="dropdown-menu" role="menu">
                                <li role="presentation"><a href="vadaralgblomning.html">Vad är algblomning?</a></li>
                                <li role="presentation"><a href="gardetattbada.html">Går det att bada?</a></li>
                                <li role="presentation"><a href="symptomer.html">Symptomer &amp; råd</a></li>
                                <li role="presentation"><a href="bralankar.html">Bra länkar</a></li>
                            </ul>
                        </li>
                    </ul><a class="btn btn-default navbar-btn action-button" role="button" href="omoss.html">Om oss</a>
                    <p class="navbar-text navbar-right actions"> </p>
                </div>
            </div>
        </nav>
    </div>
    <div>
	<div class="container">
            <div class="row">
               <div id="map" class="col-md-6" style="left:15px;margin-right:30px;">
				</div>
   <script>
function initMap() {
	var alg = JSON.parse('<?= $array_json_alg; ?>');	
  var map = new google.maps.Map(document.getElementById('map'), {
    zoom: 4,
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
//grön	
height: 66,
url: "https://raw.githubusercontent.com/linasess/Datateknik-/master/green2.png",
width: 66
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

<div class="col-md-6" style="width:300px;">
				
				
                    <div><p><h1>Bad & Väder </h1>
					<h2> Här kan du se väder, temperatur och status för algblomning vid just din badplats. </h2>

					<h2>Genom att klicka på det området som du befinner dig i så kan du enkelt hitta information om din badplats. </h2>
					<ul>
					<li>Grön - Ingen algblomning</li>
					<li>Gul - Ingen mätdata</li>
					<li>Röd - Algblomning </li>
					</ul></p></div>
                </div>
				</div>
				</div>
<div class="footer-basic">
        <footer>
            <div class="social"><a href="https://www.instagram.com/"><i class="icon ion-social-instagram"></i></a><a href="https://twitter.com/"><i class="icon ion-social-twitter"></i></a><a href="https://www.facebook.com/"><i class="icon ion-social-facebook"></i></a></div>
            <ul
            class="list-inline">
                <li><a href="index.php">Hem</a></li>
                <li></li>
                <li><a href="omoss.html">Om oss</a></li>
                </ul>
                <p class="copyright">Bad &amp; Väder © 2017</p>
        </footer>
    </div>
    <script src="assets/js/jquery.min.js"></script>
    <script src="assets/bootstrap/js/bootstrap.min.js"></script>
</body>
</html> 
