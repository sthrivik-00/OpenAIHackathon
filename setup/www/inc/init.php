<?php

define('FILE_AUDIO', 'audios/Inspire-The-People.new.mp3');
define('MOVIE_FPS', 25);
define('MOVIE_WIDTH', 715);
define('MOVIE_HEIGHT', 400);
define('LOGO_SIZE', 100);
define('SWF_TEXT_FIELD_OPTION',  SWFTEXTFIELD_NOEDIT | SWFTEXTFIELD_NOSELECT | SWFTEXTFIELD_WORDWRAP | SWFTEXTFIELD_MULTILINE);

#watson voice
$watson_translator_voice = array(
	'en' => 'en-US_AllisonVoice',
	'es' => 'es-ES_LauraVoice',
	'de' => 'de-DE_BirgitVoice',
	'fr' => 'fr-FR_ReneeVoice',
	'ja' => 'ja-JP_EmiVoice',
	'it' => 'it-IT_FrancescaVoice',
	'pt' => 'pt-BR_IsabelaVoice'
);

// ming_setScale(20.00000000);
ming_setScale(1);
ming_useswfversion(6);

if ($_SERVER['HTTP_HOST'] === 'localhost:8080') {
	define('LOCAL', true);
    
    define('DBSERVER', '172.17.0.2');
    define('DBUSER', 'videobill');
    define('DBPASS', 'vspexplorers');
    define('DBDATABASE', 'videobill');

	define('SVC_TRANSLATOR_APIKEY', 'Sl7QDoQDH3sAT5Fp0PGDhAsL2jZrROF0XCU8llJX0stu');
	define('SVC_TRANSLATOR_URL', 'https://api.us-south.language-translator.watson.cloud.ibm.com/instances/802da60f-a628-48df-9d76-d1c44e587909/v3/translate?version=2018-05-01');
	define('SVC_TEXT2SPEECH_APIKEY', 'n6xPr6FV6dDoJhcf8RaPQga0Toy4_mD1XDn6VRahgISE');
	define('SVC_TEXT2SPEECH_URL', 'https://api.us-south.text-to-speech.watson.cloud.ibm.com/instances/9795079f-5421-429f-98ea-87fb790d6230/v1/synthesize');

} else {
	define('LOCAL', false);
    
	if (isset($_ENV['videobill_db_ip']) && isset($_ENV['videobill_db_user']) 
			&& isset($_ENV['videobill_db_password']) && isset($_ENV['videobill_db_name'])
			&& isset($_ENV['videobill_watson_trans_key']) && isset($_ENV['videobill_watson_trans_url'])
			&& isset($_ENV['videobill_watson_tts_key']) && isset($_ENV['videobill_watson_tts_url']))
	{
		define('DBSERVER', $_ENV['videobill_db_ip']);
		define('DBUSER', $_ENV['videobill_db_user']);
		define('DBPASS', $_ENV['videobill_db_password']);
		define('DBDATABASE', $_ENV['videobill_db_name']);
		
		define('SVC_TRANSLATOR_APIKEY', $_ENV['videobill_watson_trans_key']);
		define('SVC_TRANSLATOR_URL', $_ENV['videobill_watson_trans_url']);
		define('SVC_TEXT2SPEECH_APIKEY', $_ENV['videobill_watson_tts_key']);
		define('SVC_TEXT2SPEECH_URL', $_ENV['videobill_watson_tts_url']);
	}
	else
	{
		echo "ERROR: Environment variable not set";
		exit;
	}
	
}

# connect to the MySQL database server
$db_conn = pg_connect("host=" . DBSERVER . " dbname=" . DBDATABASE . " user=" . DBUSER . " password=". DBPASS)
                or die('Error: Unable to connect to database - ' .  pg_last_error());
                
// Set the client encoding to UNICODE.  Data will be automatically
// converted from the backend encoding to the frontend.
pg_set_client_encoding($db_conn, "UNICODE");

function export_data($file, $data) {
	file_put_contents("export/$file.dat", serialize($data));	
	file_put_contents("export/$file.txt", print_r($data, true));	
}

function import_data($file) {
	$tmp_data = file_get_contents("export/$file.dat");
	$data = unserialize($tmp_data);
	return $data;
}

function bill_plan_analyse($base_plans, $base_discounts, $base_usage, $avg_usage, &$result=NULL) {
	$result = $base_plans;
	
	# plan analysis
	foreach ($base_plans as $plan_id => $plan) {
		$rate_local = 0.0;
		$rate_national = 0.0;
		$rate_international = 0.0;
		$mins_local = 0;
		$mins_national = 0;
		$mins_international = 0;
		$mins_local_wo_discount = 0;
		$mins_national_wo_discount = 0;
		$mins_international_wo_discount = 0;
		
		$discount = '-';
		if ($plan[7]!='') {  // discount
			if ($base_discounts[$plan[7]][2] != 0) {  									// flat: flat discount amount/usage rate = number of mins free
				$discount = $base_discounts[$plan[7]][2];
				
				// $mins_local_free = $base_discounts[$plan[7]][2]/$base_usage[400][2];
				// $mins_national_free = $base_discounts[$plan[7]][2]/$base_usage[401][2];
				// $mins_international_free = $base_discounts[$plan[7]][2]/$base_usage[402][2];
				
				// $mins_local = ($avg_usage[0] > $mins_local_free) ? $mins_local_free : $avg_usage[0];
				// $mins_national = ($avg_usage[1] > $mins_national_free) ? $mins_national_free : $avg_usage[1];
				// $mins_international = ($avg_usage[2] > $mins_international_free) ? $mins_international_free : $avg_usage[2];
				
				// $mins_local_wo_discount = $avg_usage[0] - $mins_local;
				// $mins_national_wo_discount =  $avg_usage[1] - $mins_national;
				// $mins_international_wo_discount =  $avg_usage[2] - $mins_international;
				
				if ($avg_usage[0] > 0) {
					$mins_local_free = $base_discounts[$plan[7]][2]/$base_usage[400][2];
					$mins_local = ($avg_usage[0] > $mins_local_free) ? $mins_local_free : $avg_usage[0];
					$mins_local_wo_discount = $avg_usage[0] - $mins_local;
				}
				
				if ($avg_usage[1] > 0) {
					$mins_national_free = $base_discounts[$plan[7]][2]/$base_usage[401][2];
					$mins_national = ($avg_usage[1] > $mins_national_free) ? $mins_national_free : $avg_usage[1];
					$mins_national_wo_discount =  $avg_usage[1] - $mins_national;
				}
				
				if ($avg_usage[2] > 0) {
					$mins_international_free = $base_discounts[$plan[7]][2]/$base_usage[402][2];
					$mins_international = ($avg_usage[2] > $mins_international_free) ? $mins_international_free : $avg_usage[2];
					$mins_international_wo_discount =  $avg_usage[2] - $mins_international;
				}
				
			} else {  																	// percentage: ?% * avg. usages/100 =  number of mins free
				$discount = $base_discounts[$plan[7]][3].'%';
				
				// $mins_local = $base_discounts[$plan[7]][3]*$avg_usage[0]/100;
				// $mins_national = $base_discounts[$plan[7]][3]*$avg_usage[1]/100;
				// $mins_international = $base_discounts[$plan[7]][3]*$avg_usage[2]/100;
				
				// $mins_local_wo_discount = $avg_usage[0] - $mins_local;
				// $mins_national_wo_discount =  $avg_usage[1] - $mins_national;
				// $mins_international_wo_discount =  $avg_usage[2] - $mins_international;
				
				if ($avg_usage[0] > 0) {
					$mins_local = $base_discounts[$plan[7]][3]*$avg_usage[0]/100;
					$mins_local_wo_discount = $avg_usage[0] - $mins_local;
				}
				
				if ($avg_usage[1] > 0) {
					$mins_national = $base_discounts[$plan[7]][3]*$avg_usage[1]/100;
					$mins_national_wo_discount =  $avg_usage[1] - $mins_national;
				}
				
				if ($avg_usage[2] > 0) {
					$mins_international = $base_discounts[$plan[7]][3]*$avg_usage[2]/100;
					$mins_international_wo_discount =  $avg_usage[2] - $mins_international;
				}
				
			}
		} else {																		// mins free usage or avg free usage depending upon which is max.
			$mins_local = ($avg_usage[0] > $plan[4]) ? $plan[4] : $avg_usage[0];
			$mins_national = ($avg_usage[1] > $plan[5]) ? $plan[5] : $avg_usage[1];
			$mins_international = ($avg_usage[2] > $plan[6]) ? $plan[6] : $avg_usage[2];
			
			$mins_local_wo_discount = ($avg_usage[0] > $plan[4]) ? ($avg_usage[0]-$plan[4]) : 0;
			$mins_national_wo_discount = ($avg_usage[1] > $plan[5]) ? ($avg_usage[1]-$plan[5]) : 0;
			$mins_international_wo_discount = ($avg_usage[2] > $plan[6]) ? ($avg_usage[2]-$plan[6]) : 0;
		}
		
		$rate_local = $mins_local_wo_discount * $base_usage[400][2];
		$rate_national = $mins_national_wo_discount * $base_usage[401][2];
		$rate_international = $mins_international_wo_discount * $base_usage[402][2];
			
		array_push($result[$plan_id],
			$discount,
			$mins_local,
			$mins_national,
			$mins_international,
			$mins_local_wo_discount,
			$mins_national_wo_discount,
			$mins_international_wo_discount,
			$rate_local,
			$rate_national,
			$rate_international,
			($rate_local+$rate_national+$rate_international),
			$plan[2]+($rate_local+$rate_national+$rate_international)
			);
	}
	
	# plan recommendation
	$rec_plan_id = -1;
	foreach ($result AS $plan_id => $plan) {
		if ($rec_plan_id == -1) {
			$rec_plan_id = $plan_id;
		}
		
		if ($rec_plan_id != $plan_id) {
			if ($plan[19] < $result[$rec_plan_id][19]) {
				$rec_plan_id = $plan_id;
			}
		}
	}
	
	$result['recommendation'] = $rec_plan_id;
}

function watson_translate_data($source, $target, $sentence) {
	$file_trans_input = 'watson_translator_input.data';
	$file_trans_output = 'watson_translator_output.txt';
	
	// $json_trans_param = array('source' => $source, 'target' => $target, 'text' => array($sentence));
	$json_trans_param = array('text' => array($sentence), 'model_id' => "$source-$target");
	
	if (!LOCAL) {
		$r = file_put_contents($file_trans_input, json_encode($json_trans_param, JSON_NUMERIC_CHECK | JSON_PRETTY_PRINT));
	} else {
		$r = file_put_contents($file_trans_input, json_encode($json_trans_param, JSON_NUMERIC_CHECK));
	}

	if ($r == FALSE || $r <= 0)
	{
		echo "Error writing to flat file $file_trans_input";
		exit;
	}
	
	// $result = shell_exec("curl -X POST -u \"".SVC_TRANSLATOR_USER."\":\"".SVC_TRANSLATOR_PASS."\" --header \"Content-Type: application/json\" --data @$file_trans_input  \"".SVC_TRANSLATOR_URL."\"  > $file_trans_output");

	// curl -X POST -u "apikey:Sl7QDoQDH3sAT5Fp0PGDhAsL2jZrROF0XCU8llJX0stu" --header "Content-Type: application/json" --data "{\"text\": [\"Hello, world! \", \"How are you?\"], \"model_id\":\"en-es\"}" "https://api.us-south.language-translator.watson.cloud.ibm.com/instances/802da60f-a628-48df-9d76-d1c44e587909/v3/translate?version=2018-05-01"
	$result = shell_exec("curl -X POST -u \"apikey:". SVC_TRANSLATOR_APIKEY ."\" --header \"Content-Type: application/json\" --data @$file_trans_input \"". SVC_TRANSLATOR_URL . "\" > $file_trans_output");

	$trans_output = file_get_contents($file_trans_output);
	
	#shell_exec("rm -f $file_trans_input $file_trans_output");

	return  $trans_output;
}

