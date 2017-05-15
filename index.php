
<!-- Adam Lundström, Lina Harge & Sofie Karlsson DT159G, Starstida innehållande Google Maps karta samt hämtar data från SMHI och bad- och vattenmyndigheten -->
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
$oldWind="";
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
			//SMHIs api anropas med kordinaterna från xml-filen.
			$url = "http://opendata-download-metfcst.smhi.se/api/category/pmp2g/version/2/geotype/point/lon/$lng/lat/$lat/data.json";
			$response = \Httpful\Request::get($url)
			->send();
			//Datan från SMHIs api hämtas och avkoder JSONformatet.
			$rows = json_decode($response, true);
			//Den aktuella tiden (nutid) hämtas.
			$timeNow=date("Y-m-d h:a",time());
			//Tiden från apin anges.
			$rowsUsed= $rows['timeSeries'];
			//Kontrolerar att det finns några rader.
			if(count($rowsUsed)!=0){		
				//Går igenom alla arrays med objekt.
				foreach($rowsUsed as $time){	
					//Tiden för mätningen av temperaturen hämtas.
					$bT=strtotime($time['validTime']);
					//Tiden görs om till jämförbart format och det dras av två timmar.
					//Annars korrelerar inte SMHIs tid med den aktuella tiden.
					$t=date("Y-m-d h:a",strtotime("-2 hours",$bT));
					//Går vidare om tiden från SMHIs mätning matchar den nuvarande tiden (finns flera
					//tidpunkter med temperaturangivelse).
					if($t==$timeNow){
						//En array med objekt som innehåller inforamtion så som väder anges.
						$rowsUsed=$time['parameters'];
						//Går igenom varje objekt som innehåller information om väder. 
						foreach($rowsUsed as $weather){
							//Om objektet beskriver temperaturen "t" hämtas värdet. 
							if($weather['name']=='t'){
								$weatherTemp=$weather['values'][0];
							}
							//Om objektet beskriver vindhastigheten "ws" hämtas värdet. 
							if($weather['name']=='ws'){
								$wind=$weather['values'][0];
							}							
						}
					}
				}
			}
		}
		//Om ingen ny data hämtas anges de gammla föregående värdena.	
		else{
			$weatherTemp=$oldWeatherTemp;
			$wind=$oldWind;
		}		
		//En array med all information som kommer användas av Google Maps api i javascriptet.
		$object[]=array("lat"=>$lat,"lng"=>$lng,"info"=>"<b>$place</b><br>Senaste mätningen av algblomningen och vattentemperatur genomfördes $measuredAt.<br>$algInfo<br>Vattentemperaturen var $temp grader celsius.<br>Temperaturen på platsen är $weatherTemp grader celsius och vindhastigheten är $wind m/s.");	
		$oldLng=$lng;
		$oldLat=$lat;
		$oldWeatherTemp=$weatherTemp;
		$oldWind=$wind;
		$i++;
	}
	
	//$iForPoints++;
}
//Går vidare om det finns ett objekt.
if(isset($object)){
	//Arrayerna kodas om till JSON för att kunna användas av javascriptet.
	$array_json = json_encode($object);
	$array_json_alg=json_encode($arrayAlg);	
}
?>
<html lang="sv">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
	
    <title>Bad&amp;vader</title>
	<!--Länkar till css -->
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
					<!--Meny på framsida och länkar till undersidor -->
                        <li class="dropdown"><a class="dropdown-toggle" data-toggle="dropdown" aria-expanded="false" href="#">Information om algblomning</a>
                            <ul class="dropdown-menu" role="menu">
                                <li role="presentation"><a href="vadaralgblomning.html">Vad är algblomning?</a></li>
                                <li role="presentation"><a href="gardetattbada.html">Går det att bada?</a></li>
                                <li role="presentation"><a href="symptomer.html">Symptomer &amp; råd</a></li>
                                <li role="presentation"><a href="bralankar.html">Bra länkar</a></li>
                            </ul>
                        </li>
						<!--Om oss i menyrad -->
                    </ul><a class="btn btn-default navbar-btn action-button" role="button" href="omoss.html">Om oss</a>
                    <p class="navbar-text navbar-right actions"> </p>
                </div>
            </div>
        </nav>
    </div>
	<div class="container">
            <div class="row">
               <div id="map" class="col-md-6" style="left:15px;margin-right:30px;">
				</div>
   <script>
function initMap() {
	//Hämtar objektet med information om algblomning från php.
	var alg = JSON.parse('<?= $array_json_alg; ?>');	
	//En ny karta från Google Maps anges med initiell zoom och utgångspunkt (Sundsvall).
	var map = new google.maps.Map(document.getElementById('map'), {
		zoom: 4,
		center: {
			lat: 62.381435,
			lng: 17.383067
		}
	});
	//En ny inforuta anges.
	var infoWin = new google.maps.InfoWindow();
	//Färger till punkterna på kartan hämtas.
	var green='http://maps.google.com/mapfiles/ms/icons/green-dot.png';
	var red='http://maps.google.com/mapfiles/ms/icons/red-dot.png';
	var yellow='http://maps.google.com/mapfiles/ms/icons/yellow-dot.png';
	//Inställningar för klustret anges.
	var mcOptions = {styles: [{
	//grön	
	height: 66,
	url: "https://raw.githubusercontent.com/linasess/Datateknik-/master/green2.png",
	width: 66
	},
	{	
	//röd	
	height: 66,
	url: "https://raw.githubusercontent.com/googlemaps/v3-utility-library/master/markerclusterer/images/m3.png",
	width: 66
	}]}
	//Lägger till alla punkter från arrayen "locations" nedan.
	var markers = locations.map(function(location, i) {
		//Om algstatusen för platsen är "Ingen blomning" anges färgen grön till punkten.
	 	if(alg[i][0]=="Ingen blomning"){	
			var color=green;
		}
		//Om algstatusen för platsen är "Blomning" anges färgen röd till punkten.
		if(alg[i][0]=="Blomning"){
			var color=red;
		}
		//Om algstatusen för platsen är "Ingen uppgifte" anges färgen gul till punkten.
		if(alg[i][0]=="Ingen uppgift"){
			var color=yellow;
		}	 
		//Varje punkt anges med postition och färg.
		var marker = new google.maps.Marker({
		
			position: location,
			icon: color
	  
		});
		//En inforutan anges till varje punkt där informationen också kommer från arrayen "locations" nedan.
		google.maps.event.addListener(marker, 'click', function(evt) {
			infoWin.setContent(location.info);
			infoWin.open(map, marker);
		})
		//Punkten retuneras slutligen från funktionen.
		return marker;
	});

	//Lägger til ett kluster av punkterna.
	var markerCluster = new MarkerClusterer(map, markers,mcOptions);
	//En funktion som bestämmer inställningarna för klustret.
	markerCluster.setCalculator(function(markers, numStyles){
		var index = 0;
		//Tar fram längden av varje kluster.
		var lengthOfCluster = markers.length;
		//En loop som går igenom alla punkter i klustret.
		for(var i=0;i<lengthOfCluster;i++){	
			//Om ikonen i klustret är röd anges index=2 som representeras av röd (se ovan)
			//och sedan bryts loopen. Färgen på ikonen för klustret blir alltså röd om
			//någon punkt i klustret är röd, dvs har status algblmoning.
			if(markers[i]['icon'].includes("red")){
				var index=2	;
				break;
			}
			//Om ikonen är grön dvs inte har algblomning anges index=1 som representeras av färgen grön.
			if(markers[i]['icon'].includes("green")){	
				index=1;
			}	
		}
		//Funtionen returnerar, för varje kluster, längden på klustret samt inställnigar (i detta fall
		//färg och storlek på ikon).
		return {
		text: lengthOfCluster,
		index: index
		};
	}); 
		
}
//Hämtar objektet med information från php.
var obj = JSON.parse('<?= $array_json; ?>');
//Alla punkter med medföljande information läggs till.
var locations = obj;
//Laddar kartan.
google.maps.event.addDomListener(window, "load", initMap);
    </script>
	<!--Klustret hämtas från Google Maps-->
    <script src="https://developers.google.com/maps/documentation/javascript/examples/markerclusterer/markerclusterer.js">
    </script>
	<!--Kartan hämtas från Google Maps som anropar javascriptfunktionen 'initMap'-->
    <script async defer
    src="https://maps.googleapis.com/maps/api/js?key=AIzaSyBTmzSil2kh4Qii5NGbMuS6UWUoXSzzExk&callback=initMap">
    </script>
	
 <!-- Informationstext -->
<div class="col-md-6" style="width:300px;">
				
				
                    <div><h1>Bad & Väder </h1>
					<h2> Här kan du se väder, temperatur och status för algblomning vid just din badplats. </h2>

					<h2>Genom att klicka på det området som du befinner dig i så får du upp status för algbloming vid den senaste mätningen. </h2>
					<ul>
					<li>Grön färg- Ingen algblomning</li>
					<li>Gul färg- Ingen mätdata</li>
					<li>Röd färg- Algblomning </li>
					</ul>
					<h2>Bada lungt!</h2>
					</div>
                </div>
				</div>
				</div>
<div class="footer-basic">
<!--Länkar till socialamedier -->
        <footer>
            <div class="social"><a href="https://www.instagram.com/"><i class="icon ion-social-instagram"></i></a><a href="https://twitter.com/"><i class="icon ion-social-twitter"></i></a><a href="https://www.facebook.com/"><i class="icon ion-social-facebook"></i></a></div>
            <ul
            class="list-inline">
                <li><a href="index.php">Hem</a></li>
                <li></li>
                <li><a href="omoss.html">Om oss</a></li>
                </ul>
                <p class="copyright">Bad &amp; Väder © 2017</p>
				<!-- Kontaktinfromation -->
		<p class="copyright">Kontakta oss: 073-9596915 badovader@gmail.com Holmgatan 10, 851 70 Sundsvall </p> 
        </footer>
    </div>
    <script src="assets/js/jquery.min.js"></script>
    <script src="assets/bootstrap/js/bootstrap.min.js"></script>
</body>
</html> 
