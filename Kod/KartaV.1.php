<!DOCTYPE html>
<?php
ini_set('max_execution_time', 3600);
$xml=simplexml_load_file("Provresultat.xml");
//include('httpful.phar');
$array=$xml->R;
$oldPlace="?";
$i=1;
include('httpful.phar');
//echo count($array)."<br>";
$iForPoints=0;
foreach($array as $a){
	
	$place=$a->C7;
	//$place=trim(urlencode($a->C7),"%");	
	//var_dump($place);
	$place = parse_url($place,PHP_URL_FRAGMENT);
	$place=substr($place,46);
	$place=substr($place,0,-4);
	if(preg_match('/"/', $place)>0){
		$place="Okänt namn";
	}
	/*while(strpos($place,"<")!==false){
		$place=substr($place,1);
	}*/
	/*if($iForPoints==90){
		$iForPoints=0;
	}*/
	if(($iForPoints==0&&$a->C10!=""&&$a->C9!=""&&strpos($place,$oldPlace)===false)||$i==1){
		
	//if(($a->C10!=""&&$a->C9!=""&&strpos($place,$oldPlace)===false)||$i==1){
		$lng=$a->C10;
		$lat=$a->C9;		
		while(strlen($lng)>9){
			$lng=substr($lng,0,-1);
		}
		while(strlen($lat)>9){
			$lat=substr($lat,0,-1);
		}
		/*if($i==447){
			echo $i."lng".$lng."<br>lat".$lat."<br><br>";	
		}			
		if($i==448){
			echo $i."lng".$lng."<br>lat".$lat."<br><br>";	
		}	
		if($i==449){
			echo $place."<br>";
			echo $i."lng".$lng."<br>lat".$lat."<br><br>";
			//var_dump($i."lng".$lng."<br>lat".$lat."<br><br>");
		}	
		if($i==450){
			echo $i."lng".$lng."<br>lat".$lat."<br><br>";
		}
		if($i==1000){
			//echo $i."lng".$lng."<br>lat".$lat."<br><br>";
			break;				
		}*/		
		$oldPlace=$place;
		//echo $oldPlace."2<br><br>"; 
		$alg=$a->C15;
		$temp=$a->C0;
		$arrayAlg[]=$alg;
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
		$object[]=array("<b>$place</b><br>Det finns: $alg i det har vattendraget<br>Vattentemperaturen ar vid senaste matningen: $temp grader celsius<br>Temperaturen på platsen är: $weatherTemp grader celsius" , $lat , $lng ,$i);	
		$i++;
		
	}
	//echo $iForPoints;
	//$iForPoints++;
}
if(isset($object)){
	$array_json = json_encode($object);
	$array_json_alg=json_encode($arrayAlg);
	
}
//Potentiella fel:
//""Kaffeberget"""
// ["<b>Laholmsbukten, Birger Pers v\u00e4g<\/b><br>Det finns: Ingen blomning i det har vattendraget<br>Vattentemperaturen ar vid senaste matningen: 18 grader celsius<br>Temperaturen p\u00e5 platsen \u00e4r: 10.8 grader celsius","","",449]
//["<b>Laholmsbukten, Koloniv\u00e4gen<\/b><br>Det finns: Ingen blomning i det har vattendraget<br>Vattentemperaturen ar vid senaste matningen: 18 grader celsius<br>Temperaturen p\u00e5 platsen \u00e4r: 10.8 grader celsius","5","1",448]
//["<b>Laholmsbukten, Fiskaregatan<\/b><br>Det finns: Ingen blomning i det har vattendraget<br>Vattentemperaturen ar vid senaste matningen: 18 grader celsius<br>Temperaturen p\u00e5 platsen \u00e4r: 10.8 grader celsius",{"0":"56.4566"},{"0":"12.908"},447]
?>	
<html>

<body> 
<div id="map" style="width:500px;height:500px"></div>
<script>


function myMap(){
	var obj = JSON.parse('<?= $array_json; ?>');
	var alg = JSON.parse('<?= $array_json_alg; ?>');

    var locations = obj;
	//console.log(obj);
    var map = new google.maps.Map(document.getElementById('map'), {
      zoom: 4,
      center: new google.maps.LatLng(62.381435, 17.383067),
      mapTypeId: google.maps.MapTypeId.ROADMAP
    });

    var infowindow = new google.maps.InfoWindow();
	var green='http://maps.google.com/mapfiles/ms/icons/green-dot.png';
	var red='http://maps.google.com/mapfiles/ms/icons/red-dot.png';
	var yellow='http://maps.google.com/mapfiles/ms/icons/yellow-dot.png';

    var marker, i;

    for (i = 0; i < locations.length; i++) {  
	    	if(alg[i][0]=="Ingen blomning"){
			var color=green;
		}
		if(alg[i][0]=="Blomning"){
			var color=red;
		}
		if(alg[i][0]=="Ingen uppgift"){
			var color=yellow;
		}	
      marker = new google.maps.Marker({
        position: new google.maps.LatLng(locations[i][1], locations[i][2]),
        map: map
      });

      google.maps.event.addListener(marker, 'click', (function(marker, i) {
        return function() {
          infowindow.setContent(locations[i][0]);
          infowindow.open(map, marker);
        }
      })(marker, i));
    }
}	
</script>	
	
<script src="https://maps.googleapis.com/maps/api/js?key=AIzaSyBTmzSil2kh4Qii5NGbMuS6UWUoXSzzExk&callback=myMap"></script>
</body>
</html> 
