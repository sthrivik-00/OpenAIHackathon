<?php

set_time_limit (60*10);

# include subscripts
require_once('inc/init.php');
require_once('inc/video_transcript.php');

# application constant definition
define('USAGE_LOCAL', 400);
define('USAGE_NATIONAL', 401);
define('USAGE_INTERNATIONAL', 402);
define('USAGE_ROA_NATIONAL', 403);
define('USAGE_ROA_INTERNATIONAL', 404);

define('SERVICE_TYPE_MSISDN', 1);
define('SERVICE_TYPE_TV', 2);

$desc_service = array(
	1 => 'GSM',
	2 => 'Television',
	3 => 'Landline',
	4 => 'Water',
	4 => 'Electricity'
);

$lang = (isset($_REQUEST['lang']) && preg_match('/^(en|fr|de|es|ja|it|pt|ar)$/', $_REQUEST['lang'])) ? $_REQUEST['lang'] : die('Error: Invalid language');
$watson_voice = $watson_translator_voice[$lang];

if (!LOCAL) 
// if (1 == 0)   // to skip
{
# global variables
$rec_bill_plans = array();
$rec_discounts = array();
$rec_charges = array();
$rec_usages = array();
$rec_plan_discounts = array();
$analyze_subscription_bill_plan = array();
$audio_transcript = array();
$rec_pending_bill_payments = array();
$rec_invoices = array();

# system will obtain only the latest bill of a customer
# discounts are applicable only for the usages (ignore account and service level in the database)
# discounts will be applied individually for each type of usages (such as local, national, international)
# discounts not applicable for roaming usages
# discounts should be applied only through the bill plans
# there should be only one recurring charge
# there can be maximum 3 nrc billed in an invoice for a customer
# there should be no account level charges or discounts


# check whether there is customer account number passed as an argument or not in URL
if (!isset($_REQUEST['ext_id']) || intval($_REQUEST['ext_id']) <= 0 ) {
	echo 'Error: Missing customer account in the URL';
	exit;
}

$external_id = $_REQUEST['ext_id'];

#============== CLEAN TEMPORARY AND OLD FILES
shell_exec("rm -f /var/www/audios/*.transcript.wav watson/* 2>/dev/null");
shell_exec('date >> export/access.log');


# MODEL
#============== FETCH DATA FROM DATABASE
# bill_plans
$result_bill_plans = pg_query($db_conn, 'SELECT * FROM bill_plans') or die('Error: Query failed - '. pg_last_error());

if (pg_num_rows($result_bill_plans) <= 0) {
	echo 'Error:  No bill plans found in the database.';
	exit;
} else {
	while ($rec_table_datas = pg_fetch_row($result_bill_plans)) {
		// array_push($rec_bill_plans, $rec_table_datas[0], $rec_table_datas);
		$rec_bill_plans[$rec_table_datas[0]] = $rec_table_datas;
	}
}

# discounts
$result_discounts = pg_query($db_conn, 'SELECT * FROM discounts') or die('Error: Query failed - '. pg_last_error());

if (pg_num_rows($result_discounts) <= 0) {
	echo 'Error:  No discounts found in the database.';
	exit;
}else {
	while ($rec_table_datas = pg_fetch_row($result_discounts)) {
		// array_push($rec_discounts, $rec_table_datas[0], $rec_table_datas);
		$rec_discounts[$rec_table_datas[0]] = $rec_table_datas;
	}
}

# one time charges
$result_charges = pg_query($db_conn, 'SELECT * FROM charges');

if (pg_num_rows($result_charges) <= 0) {
	echo 'Error:  No charges found in the database.';
	exit;
} else {
	while ($rec_table_datas = pg_fetch_row($result_charges)) {
		// array_push($rec_charges, $rec_table_datas[0], $rec_table_datas);
		$rec_charges[$rec_table_datas[0]] = $rec_table_datas;
	}
}

# usage definitions
$result_usages_def = pg_query($db_conn, 'SELECT * FROM usages');

if (pg_num_rows($result_usages_def) <= 0) {
	echo 'Error:  No usages definition found in the database.';
	exit;
} else {
	while ($rec_table_datas = pg_fetch_row($result_usages_def)) {
		// array_push($rec_usages, $rec_table_datas[0], $rec_table_datas);
		$rec_usages[$rec_table_datas[0]] = $rec_table_datas;
	}
}

# account details
$result_account = pg_query($db_conn, "SELECT * FROM accounts WHERE external_id = '".$_REQUEST['ext_id']."'");

if (pg_num_rows($result_account) <= 0) {
	echo 'Error:  No account detail found in the database.';
	exit;
} else {
	$rec_account = pg_fetch_row($result_account);
}


# services
$result_services = pg_query($db_conn, 'SELECT * FROM services WHERE account_no = '.$rec_account[0]);

if (pg_num_rows($result_services) <= 0) {
	echo 'Error:  No services found for the account in the database.'.PHP_EOL;
	exit;
} else {
	while ($rec_table_datas = pg_fetch_row($result_services)) {
		// array_push($rec_usages, $rec_table_datas[0], $rec_table_datas);
		$rec_services[$rec_table_datas[0]] = $rec_table_datas;
	}
}

# bill planx <=> discounts
$result_bill_plans_discounts = pg_query($db_conn, <<<EOD
select bp.*, flat_rate, percentage from bill_plans bp, discounts d where bp.discount_id=d.discount_id
union
select bp.*, NULL, NULL from bill_plans bp where discount_id is null
EOD
);

if (pg_num_rows($result_bill_plans_discounts) <= 0) {
	echo 'Error:  No bill plan X discount combinations found in the database.';
	exit;
} else {
	while ($rec_table_datas = pg_fetch_row($result_bill_plans_discounts)) {
		$rec_plan_discounts[$rec_table_datas[0]] = $rec_table_datas;
	}
}

# calculate discount benefit of flat discounts
foreach ($rec_plan_discounts AS $plan_id => $plan_detail) {
	if ($plan_detail[7] != '' && $plan_detail[8] != '') {
		$rec_plan_discounts[$plan_id][4] = $rec_plan_discounts[$plan_id][8]/$rec_usages[USAGE_LOCAL][2];
		$rec_plan_discounts[$plan_id][5] = $rec_plan_discounts[$plan_id][8]/$rec_usages[USAGE_NATIONAL][2];
		$rec_plan_discounts[$plan_id][6] = $rec_plan_discounts[$plan_id][8]/$rec_usages[USAGE_INTERNATIONAL][2];
	}
}

# obtain customer's average local, national, international usages of last six month for each subscription
$subscr_avg_usages = array();
$result_cust_avg_usage = pg_query($db_conn, <<<EOD
-- SELECT subscr_no, subtype_code, avg(usage_duration)
SELECT subscr_no, subtype_code, avg(usage_duration)+(MAX(usage_duration) -avg(usage_duration))/2
FROM accounts a, invoice b, invoice_details c
WHERE a.account_no = b.account_no and b.bill_no = c.bill_no 
	-- and statement_date >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH) 
	and statement_date >= (CURRENT_DATE - INTERVAL '12 MONTH')
	and type_code = 7
	and external_id = '$external_id'
GROUP BY subscr_no, subtype_code 
ORDER BY subscr_no, subtype_code
EOD
);

if (pg_num_rows($result_cust_avg_usage) <= 0) {
	echo 'Error: Unable to find montly average usage of the customer.';
	exit;
} else {
	while ($rec_table_datas = pg_fetch_row($result_cust_avg_usage)) {
		if (!isset($subscr_avg_usages[$rec_table_datas[0]])) {
			$subscr_avg_usages[$rec_table_datas[0]][400] = 0;
			$subscr_avg_usages[$rec_table_datas[0]][401] = 0;
			$subscr_avg_usages[$rec_table_datas[0]][402] = 0;
		}
		$subscr_avg_usages[$rec_table_datas[0]][$rec_table_datas[1]]=$rec_table_datas[2];
		
		if (!isset($analyze_subscription_bill_plan[$rec_table_datas[0]])) {
			$analyze_subscription_bill_plan[$rec_table_datas[0]] = $rec_plan_discounts;
		}
	}
}



/* --- temporary skip #1 - start
# calculate discount benefit of percentage discounts based on the average usage per month in the last six month period

$template_tradeoff_input = array(
	"subject" => "bill-plans",
	"columns" => array(
		array (
		  "key" => "price",
		  "type" => "numeric",
		  "goal" => "min",
		  "is_objective" => true,
		  "full_name" => "Price",
		  "format" => "number:2"
		)
	),
	"options" => array()
);

# compose input data of complete bill plans for tradeoff analysis in json format
# for each subscription, do the analysis with all the available bill plans and find the recommended plans based on the usages
foreach ($analyze_subscription_bill_plan AS $subscr_no => $bill_plans) {
	// $index_key = 1;
	$tradeoff_input = $template_tradeoff_input;
	$tradeoff_input_file = "watson/$subscr_no.tradeoff_input.json";
	$tradeoff_output_file = "watson/$subscr_no.tradeoff_output.json";
	
	$analysis_result = array();
	$avg_usages = array();
	foreach ($subscr_avg_usages[$subscr_no] AS $usage_type => $avg_usage) {
		array_push($avg_usages, $avg_usage);
	}
	bill_plan_analyse($rec_bill_plans, $rec_discounts, $rec_usages, $avg_usages, $analysis_result  );

	// foreach ($bill_plans AS $plan_id => $plan_detail) {
		// array_push($tradeoff_input["options"], array( 
			// "key" => "$plan_id",
			// "name" => $plan_detail[3],
			// "values" => array( "price" => $plan_detail[2], "local" => $plan_detail[4], "national" => $plan_detail[5], "international" => $plan_detail[6] )
		// ));
	// }
	foreach ($analysis_result AS $plan_id => $plan_detail) {
		if (is_array($plan_detail)) {
			array_push($tradeoff_input["options"], array( 
				"key" => "$plan_id",
				"name" => $plan_detail[3],
				"values" => array( "price" => $plan_detail[19])
			));
		}
	}
	
	// if (!LOCAL) 
	{
		// file_put_contents($tradeoff_input_file, json_encode($tradeoff_input, JSON_NUMERIC_CHECK | JSON_PRETTY_PRINT));
		file_put_contents($tradeoff_input_file, json_encode($tradeoff_input, JSON_NUMERIC_CHECK));

		# watson call
		// $result = shell_exec("/usr/bin/curl -X POST --user \"4d5b882a-d85a-405a-8dfc-e7867bd36aee\":\"mCoL86wHmWV1\" --header \"Content-Type: application/json\" --data @$tradeoff_input_file \"https://gateway.watsonplatform.net/tradeoff-analytics/api/v1/dilemmas?generate_visualization=false\" | python -m json.tool > $tradeoff_output_file");
		#$result = shell_exec("curl -X POST -u \"4d5b882a-d85a-405a-8dfc-e7867bd36aee\":\"mCoL86wHmWV1\" --header \"Content-Type: application/json\" --data @$tradeoff_input_file \"https://gateway.watsonplatform.net/tradeoff-analytics/api/v1/dilemmas?generate_visualization=false\" > $tradeoff_output_file");
	}
	
	# capture watson result
	$watson_output = file_get_contents("watson/$subscr_no.tradeoff_output.json");
	$watson_result = json_decode($watson_output);
	foreach ($bill_plans AS $plan_id => $plan_detail) {
		foreach ( $watson_result->resolution->solutions AS $watson_solution) {
			if ($watson_solution->solution_ref == $plan_id) {
				array_push($analyze_subscription_bill_plan[$subscr_no][$plan_id], $watson_solution->status);
			}
		}
	}
	
	$selected_plan_id = -1;
	$selected_plan_rate = -1;
	foreach ($analyze_subscription_bill_plan[$subscr_no] AS $plan_id => $plan_detail) {
		if ($plan_detail[10] == 'FRONT') {
			if ($selected_plan_id == -1) {
				$selected_plan_id = $plan_id;
				$selected_plan_rate = $plan_detail[2];
			} else {
				if ($selected_plan_id != $plan_id && $selected_plan_rate > $plan_detail[2]) {
					$selected_plan_id = $plan_id;
					$selected_plan_rate = $plan_detail[2];				
				}			
			}
		}
	}
	
	if ($selected_plan_id != -1 && $selected_plan_id == $analysis_result['recommendation']) {
		$analyze_subscription_bill_plan[$subscr_no]['recommendation'] = $analysis_result['recommendation'];
		$analyze_subscription_bill_plan[$subscr_no]['savings'] = $analysis_result[$rec_services[$subscr_no][3]][19] - $analysis_result[$analysis_result['recommendation']][19];
	} else {
		$analyze_subscription_bill_plan[$subscr_no]['recommendation'] = -1;
		$analyze_subscription_bill_plan[$subscr_no]['savings'] = 0.0;
	}
}

# show the bill plan analysis report
if (isset($_REQUEST['stage']) && $_REQUEST['stage'] == 1) {
	echo <<<EOD
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link rel="stylesheet" href="css/bootstrap.min.css">
  <script src="js/jquery.min.js"></script>
  <script src="js/bootstrap.min.js"></script>
</head><body>
<div class="container" style="padding-left:0;margin-left:25px">
<p style="font-size:18pt;text-align:center;font-weight:bold">${DBDATABASE}</p>
<table>
EOD;

	foreach ($analyze_subscription_bill_plan AS $subscr_no => $bill_plans) {
		echo '<tr><td>Service:  <b>'.$rec_services[$subscr_no][1].'</b></td></tr>';
		echo '<tr><td><table border=1 cellpadding=1 cellspacing=0 class="table table-striped">';
		// echo '<tr><th>Plan ID</th><th>Code</th><th>Description</th><th>Rate</th><th>Local</th><th>National</th><th>International</th><th>Discount ID</th><th>Discount</th><th>Watson</th><th>Recommendation</th></tr>';
		echo '<tr><th>Plan ID</th><th>Code</th><th>Description</th><th>Rate</th><th>Local</th><th>National</th><th>International</th><th>Discount ID</th><th>Discount</th><th>Recommendation</th></tr>';
		foreach ($bill_plans AS $plan_id => $plan_detail) {
			if ($analyze_subscription_bill_plan[$subscr_no]['recommendation'] == $plan_id) {
				$status_selection = 'Recommended';
			} else {
				$status_selection = '';
			}
			
			if ($rec_services[$subscr_no][3] == $plan_id) {
				echo "<tr style=\"font-weight:bold\"><td>".$plan_id.'</td><td>'.$plan_detail[1].'</td><td>'.$plan_detail[3].'</td><td>'.$plan_detail[2].'</td><td>'.$plan_detail[4].'</td><td>'.$plan_detail[5].'</td><td>'.$plan_detail[6].'</td><td>'.$plan_detail[7].'</td><td>'.(($plan_detail[8]!=''||$plan_detail[9]!='') ? (($plan_detail[8]!='') ? $plan_detail[8].'€' : $plan_detail[9].'%') : '').'</td><td>'.$plan_detail[10].'</td><td>'.$status_selection.'</td></tr>';
				// echo "<tr style=\"font-weight:bold\"><td>".$plan_id.'</td><td>'.$plan_detail[1].'</td><td>'.$plan_detail[3].'</td><td>'.$plan_detail[2].'</td><td>'.$plan_detail[4].'</td><td>'.$plan_detail[5].'</td><td>'.$plan_detail[6].'</td><td>'.$plan_detail[7].'</td><td>'.(($plan_detail[8]!=''||$plan_detail[9]!='') ? (($plan_detail[8]!='') ? $plan_detail[8].'€' : $plan_detail[9].'%') : '').'</td><td>'.$status_selection.'</td></tr>';
			} else {
				echo "<tr><td>".$plan_id.'</td><td>'.$plan_detail[1].'</td><td>'.$plan_detail[3].'</td><td>'.$plan_detail[2].'</td><td>'.$plan_detail[4].'</td><td>'.$plan_detail[5].'</td><td>'.$plan_detail[6].'</td><td>'.$plan_detail[7].'</td><td>'.(($plan_detail[8]!=''||$plan_detail[9]!='') ? (($plan_detail[8]!='') ? $plan_detail[8].'€' : $plan_detail[9].'%') : '').'</td><td>'.$plan_detail[10].'</td><td>'.$status_selection.'</td></tr>';
				// echo "<tr><td>".$plan_id.'</td><td>'.$plan_detail[1].'</td><td>'.$plan_detail[3].'</td><td>'.$plan_detail[2].'</td><td>'.$plan_detail[4].'</td><td>'.$plan_detail[5].'</td><td>'.$plan_detail[6].'</td><td>'.$plan_detail[7].'</td><td>'.(($plan_detail[8]!=''||$plan_detail[9]!='') ? (($plan_detail[8]!='') ? $plan_detail[8].'€' : $plan_detail[9].'%') : '').'</td><td>'.$status_selection.'</td></tr>';
			}
		}
		echo '</table></td></tr>';
		echo '<tr><td><p>&nbsp;</td></tr>';
	}
	echo '</table></div></body></html>';
	exit;
}

export_data('analyze_subscription_bill_plan', $analyze_subscription_bill_plan);
*/  //--- temporary skip #1 - end

#========================================================================================================================================================

# fetch invoice summary
$num_invoices = 0;
$num_invoice_pending_payments = 0;
$result_invoices = pg_query($db_conn, 'select * from invoice where account_no = '.$rec_account[0].' order by statement_date');
$num_invoices = pg_num_rows($result_invoices);
if ($num_invoices > 0) {
	while ($rec_table_datas = pg_fetch_row($result_invoices)) {
		array_push($rec_invoices, $rec_table_datas);
		
		if (strlen($rec_table_datas[8]) != 10) {
			$num_invoice_pending_payments++;
		}
	}
} else {
	echo 'Error: No invoice found for this customer';
}

# fetch invoice details of the latest invoice
$invoice_details = array();
// $invoice_details['num_services']=0;
$tmp_subscr_no = 0;
$result_invoice_details = pg_query($db_conn, <<<EOD
select (select external_id from services where subscr_no = b.subscr_no) service, subscr_no, type_code, subtype_code, amount, usage_duration, a.bill_no
from invoice a, invoice_details b 
where a.bill_no = b.bill_no 
	and a.bill_no = (select max(bill_no) from invoice where account_no = $rec_account[0])
order by subscr_no, type_code, subtype_code
EOD
);

if (pg_num_rows($result_invoice_details) > 0) {
	while ($rec_table_datas = pg_fetch_row($result_invoice_details)) {
		if ($tmp_subscr_no != $rec_table_datas[1]) {
			$invoice_details[$rec_table_datas[1]]['bill_num']=0;
			$invoice_details[$rec_table_datas[1]]['total']=0.0;
			$invoice_details[$rec_table_datas[1]]['plan_id']=0;
			$invoice_details[$rec_table_datas[1]]['plan_charge']=0.0;
			$invoice_details[$rec_table_datas[1]]['service']=$rec_table_datas[0];
			$invoice_details[$rec_table_datas[1]]['num_charges']=0;
			$invoice_details[$rec_table_datas[1]]['total_nrc_charge']=0.0;
			$invoice_details[$rec_table_datas[1]]['charges']=array();
			// $invoice_details[$rec_table_datas[1]]['details']=array();
			$invoice_details[$rec_table_datas[1]]['local_usage'] = 0;
			$invoice_details[$rec_table_datas[1]]['local_charge'] = 0;
			$invoice_details[$rec_table_datas[1]]['national_usage'] = 0;
			$invoice_details[$rec_table_datas[1]]['national_charge'] = 0;
			$invoice_details[$rec_table_datas[1]]['international_usage'] = 0;
			$invoice_details[$rec_table_datas[1]]['international_charge'] = 0;
			$invoice_details[$rec_table_datas[1]]['discount_id'] = 0;
			$invoice_details[$rec_table_datas[1]]['discount_flat'] = 0;
			$invoice_details[$rec_table_datas[1]]['discount_per'] = 0;
			$invoice_details[$rec_table_datas[1]]['discount_amount'] = 0.0;
			$tmp_subscr_no = $rec_table_datas[1];
			// $invoice_details['num_services']++;
		}
		
		switch($rec_table_datas[2]) {
			case 2:  #
				$invoice_details[$rec_table_datas[1]]['plan_id'] = $rec_table_datas[3];
				$invoice_details[$rec_table_datas[1]]['plan_charge'] = $rec_table_datas[4];
				break;
				
			case 3:
				$invoice_details[$rec_table_datas[1]]['num_charges']++;
				$invoice_details[$rec_table_datas[1]]['total_nrc_charge']+=$rec_table_datas[4];
				array_push($invoice_details[$rec_table_datas[1]]['charges'], array($rec_table_datas[3], $rec_table_datas[4]));
				break;
				
			case 5:
				$invoice_details[$rec_table_datas[1]]['discount_id'] = $rec_table_datas[3];
				$invoice_details[$rec_table_datas[1]]['discount_flat'] = $rec_discounts[$rec_table_datas[3]][2];
				$invoice_details[$rec_table_datas[1]]['discount_per'] = $rec_discounts[$rec_table_datas[3]][3];
				$invoice_details[$rec_table_datas[1]]['discount_amount'] = $rec_table_datas[4];	
				break;
				
			case 7:
				if ($rec_table_datas[3] == 400) {
					$invoice_details[$rec_table_datas[1]]['local_usage'] =  $rec_table_datas[5];
					$invoice_details[$rec_table_datas[1]]['local_charge'] =  $rec_table_datas[4];
				} elseif ($rec_table_datas[3] == 401) {
					$invoice_details[$rec_table_datas[1]]['national_usage'] =  $rec_table_datas[5];
					$invoice_details[$rec_table_datas[1]]['national_charge'] =  $rec_table_datas[4];
				} elseif ($rec_table_datas[3] == 402) {
					$invoice_details[$rec_table_datas[1]]['international_usage'] =  $rec_table_datas[5];
					$invoice_details[$rec_table_datas[1]]['international_charge'] =  $rec_table_datas[4];
				}
				break;
		}
		
		if ($rec_table_datas[2] != 5) {
			$invoice_details[$rec_table_datas[1]]['total'] += $rec_table_datas[4];	
		} else {
			$invoice_details[$rec_table_datas[1]]['total'] -= $rec_table_datas[4];	
		}
		$invoice_details[$rec_table_datas[1]]['bill_num'] = $rec_table_datas[6];	
		// array_push($invoice_details[$rec_table_datas[1]]['details'], $rec_table_datas);
	}
}

export_data('invoice_details', $invoice_details);

# generate transcript
$text_transcript = $template_video_transcript;

# transcript: welcome
array_push($audio_transcript, array(
	$text_transcript['screen_1']['duration'],
	str_replace('#NAME#', $rec_account[2].' '.$rec_account[3], $text_transcript['screen_1']['transcript']),
	str_replace('#NAME#', $rec_account[2].' '.$rec_account[3], $text_transcript['screen_1']['transcript_video'])
	));
		
# transcript: pending bill payment
if ($num_invoice_pending_payments <= 0) {
	array_push($audio_transcript, array(
		$text_transcript['screen_2']['no_pending_payment']['duration'],
		$text_transcript['screen_2']['no_pending_payment']['transcript'],
		(isset($text_transcript['screen_2']['no_pending_payment']['transcript_video']))?$text_transcript['screen_2']['no_pending_payment']['transcript_video']:$text_transcript['screen_2']['no_pending_payment']['transcript'],
		0
		));
} else {
	$aux_duration = 0;
	$txt_transcript = '';
	$transcript_duration = array($text_transcript['screen_2']['pending_payment_head']['duration']);
	$transcript_audio = array($text_transcript['screen_2']['pending_payment_head']['transcript']);
	$transcript_video = array(array($text_transcript['screen_2']['PENDING_BILL_DETAIL']['video_title']));
	for ($i=0; $i < $num_invoice_pending_payments-1; $i++) {
		// if ( strlen($rec_invoices[$i][8]) != 10 ) {
			$search_find = array('#BILL_NO#', '#BILL_AMOUNT#' , '#BILL_DATE#', '#DUE_DATE#');
			$search_replace = array($rec_invoices[$i][1], floatval($rec_invoices[$i][7]), $rec_invoices[$i][2], $rec_invoices[$i][2]);
			$txt_transcript = str_replace($search_find, $search_replace, $text_transcript['screen_2']['PENDING_BILL_DETAIL']['transcript']);
			
			#records to be interpreted
			$video_rec = $text_transcript['screen_2']['PENDING_BILL_DETAIL']['video_records'];
			for ($j=0; $j<count($video_rec); $j++) {
				$search_find = array('#BILL_NO#', '#BILL_AMOUNT#' , '#BILL_DATE#', '#DUE_DATE#');
				$search_replace = array($rec_invoices[$i][1], sprintf('%.2f',$rec_invoices[$i][7]), $rec_invoices[$i][2], $rec_invoices[$i][2]);
				$video_rec[$j] = str_replace($search_find, $search_replace, $video_rec[$j]);
			}
			array_push($transcript_video[0], $video_rec);
			array_push($transcript_video, $i);
			
			$aux_duration += $text_transcript['screen_2']['PENDING_BILL_DETAIL']['duration'];
			
			if ($i == $num_invoice_pending_payments - 3 && $num_invoice_pending_payments >= 3) $txt_transcript .= ' and ';
			array_push($transcript_audio, $txt_transcript);
			array_push($transcript_duration, $text_transcript['screen_2']['PENDING_BILL_DETAIL']['duration']);
		// }
	}
	array_push($transcript_audio, $text_transcript['screen_2']['pending_payment_tail']['transcript']);
	array_push($transcript_duration, $text_transcript['screen_2']['pending_payment_tail']['duration']);
	array_push($transcript_video, NULL);

	// $txt_transcript = preg_replace('/, $/', '', $txt_transcript);
	// $final_transcript = str_replace('#PENDING_BILL_DETAIL#', $txt_transcript, $text_transcript['screen_2']['pending_payment']['transcript']);
	array_push($audio_transcript, array(
		// $aux_duration + $text_transcript['screen_2']['pending_payment_head']['duration'] + $text_transcript['screen_2']['pending_payment_tail']['duration'],
		$transcript_duration,
		$transcript_audio,
		$transcript_video,
		$num_invoice_pending_payments-1
		));
}


# transcript: current bill summary
$i = 0;
$bill_number = 0;
$aux_duration = 0;
$subscr_detail_transcript = '';
// $subscr_detail_transcript_video = '';
$transcript_video = array($text_transcript['screen_3']['SERVICE_SUMMARY']['video_title']);
$search_find = array('#SERVICE_TYPE#', '#SERVICE_ID#', '#SERVICE_TOTAL#');
foreach ($invoice_details AS $subscr_no => $subscr_detail) {
	$service_type = $rec_services[$subscr_no][2];
	$search_replace = array($desc_service[$service_type], implode(' ', str_split($subscr_detail['service'])), floatval($subscr_detail['total']));
	$search_replace_video = array($desc_service[$service_type], $subscr_detail['service'], floatval($subscr_detail['total']));
	$subscr_detail_transcript .= str_replace($search_find, $search_replace, $text_transcript['screen_3']['SERVICE_SUMMARY']['transcript']);
	// $subscr_detail_transcript_video .= str_replace($search_find, $search_replace_video, $text_transcript['screen_3']['SERVICE_SUMMARY']['transcript']);
	$aux_duration += $text_transcript['screen_3']['SERVICE_SUMMARY']['duration'];
	
	if ($i == (count($invoice_details) - 2) && count($invoice_details) >= 2) {
		$subscr_detail_transcript .= ' and ';
		// $subscr_detail_transcript_video .= ' and ';
	}
	
	array_push($transcript_video, array($subscr_detail['service'], $subscr_detail['total']));
	$bill_number = $subscr_detail['bill_num'];
	$i++;
}

$subscr_detail_transcript = preg_replace('/, $/', '', $subscr_detail_transcript);
// $subscr_detail_transcript_video = preg_replace('/, $/', '', $subscr_detail_transcript_video);
$search_find = array('#BILL_AMOUNT#', '#SERVICE_SUMMARY#');
$search_replace = array(floatval($rec_invoices[$num_invoice_pending_payments-1][7]), $subscr_detail_transcript);
// $search_replace_video = array(floatval($rec_invoices[$num_invoice_pending_payments-1][7]), $subscr_detail_transcript_video);
array_push($audio_transcript, array(
		$aux_duration + $text_transcript['screen_3']['summary']['duration'],
		str_replace($search_find, $search_replace, $text_transcript['screen_3']['summary']['transcript']),
		// str_replace($search_find, $search_replace_video, $text_transcript['screen_3']['summary']['transcript'])
		$transcript_video,
		array($bill_number, floatval($rec_invoices[$num_invoice_pending_payments-1][7]))  // bill amount of current bill
		));

# transcript: service details
$ar_service_details = array();
foreach ($invoice_details AS $subscr_no => $subscr_detail) {
	$duration = 0;
	$nrc_total = 0.0;
	$discount_txt = '';
	if ($subscr_detail['discount_id'] > 0) {
		$discount_txt = ($rec_discounts[$subscr_detail['discount_id']][2]!='')?floatval($rec_discounts[$subscr_detail['discount_id']][2]).' €':$rec_discounts[$subscr_detail['discount_id']][3].'%';
	}
	if ($subscr_detail['num_charges']>0) {
		if ($subscr_detail['num_charges'] == 1) {
			$search_find = array('#SERVICE_TYPE#','#SERVICE_ID#','#PLAN_NAME#','#PLAN_CHARGE#','#TOTAL_NRC#','#NRC_DETAIL#','#NRC_DETAIL_CHARGE#','#TOTAL_USAGE#','#DISCOUNT_DETAIL#');
			$search_replace = array( $desc_service[$rec_services[$subscr_no][2]], 
									implode(' ', str_split($subscr_detail['service'])),
									$rec_bill_plans[$subscr_detail['plan_id']][3],
									floatval($subscr_detail['plan_charge']),
									floatval($subscr_detail['total_nrc_charge']),
									$rec_charges[$subscr_detail['charges'][0][0]][2],
									floatval($subscr_detail['charges'][0][1]),
									floatval($subscr_detail['local_charge']+$subscr_detail['national_charge']+$subscr_detail['international_charge']),
									$discount_txt);
									
			$nrc_total += $subscr_detail['charges'][0][1];
		} else {
			$nrc_txt = '';
			$nrc_find = array('#NRC_DETAIL#','#NRC_DETAIL_CHARGE#');
			for ($i=0;$i<$subscr_detail['num_charges'];$i++) {
				$nrc_replace = array($rec_charges[$subscr_detail['charges'][$i][0]][2],floatval($subscr_detail['num_charges'][$i][1]));
				$nrc_txt .= str_replace($nrc_find, $nrc_replace, $text_transcript['service_detail']['transcript'][2][2]).', ';
				
				if ($i == $subscr_detail['num_charges']-2) {
					$nrc_txt .= ' and ';
				}
				
				$duration += $text_transcript['service_detail']['duration'][2];
				$nrc_total += $subscr_detail['charges'][$i][1];
			}
			
			$search_find = array('#SERVICE_TYPE#','#SERVICE_ID#','#PLAN_NAME#','#PLAN_CHARGE#','#TOTAL_NRC#','#TOTAL_USAGE#','#DISCOUNT_DETAIL#');
			$search_replace = array( $desc_service[$rec_services[$subscr_no][2]], 
									implode(' ', str_split($subscr_detail['service'])),
									$rec_bill_plans[$subscr_detail['plan_id']][3],
									floatval($subscr_detail['plan_charge']),
									floatval($subscr_detail['total_nrc_charge']),
									floatval($subscr_detail['local_charge']+$subscr_detail['national_charge']+$subscr_detail['international_charge']),
									$discount_txt);
		}
	} else {
		$search_find = array('#SERVICE_TYPE#','#SERVICE_ID#','#PLAN_NAME#','#PLAN_CHARGE#','#TOTAL_NRC#','#NRC_DETAIL#','#NRC_DETAIL_CHARGE#','#TOTAL_USAGE#','#DISCOUNT_DETAIL#');
		$search_replace = array( $desc_service[$rec_services[$subscr_no][2]], 
								implode(' ', str_split($subscr_detail['service'])),
								$rec_bill_plans[$subscr_detail['plan_id']][3],
								floatval($subscr_detail['plan_charge']),
								floatval($subscr_detail['total_nrc_charge']),
								$rec_charges[$subscr_detail['charges'][0][0]][2],
								floatval($subscr_detail['charges'][0][1]),
								floatval($subscr_detail['local_charge']+$subscr_detail['national_charge']+$subscr_detail['international_charge']),
								$discount_txt);
	}
		
	
	$transcript_audio = $text_transcript['service_detail']['transcript'][0];		// service
	$duration += $text_transcript['service_detail']['duration'][0];
	$transcript_audio .= $text_transcript['service_detail']['transcript'][1];		// plan
	$duration += $text_transcript['service_detail']['duration'][1];

	if ($subscr_detail['num_charges']>0) {
		if ($subscr_detail['num_charges']==1) {
			$transcript_audio .= $text_transcript['service_detail']['transcript'][2][0];	// nrc
			$duration += $text_transcript['service_detail']['duration'][2];
		} else {
			$transcript_audio .= $nrc_txt.'.';											// nrc
		}
	}
	
	$usage_total = $subscr_detail['local_charge']+$subscr_detail['national_charge']+$subscr_detail['international_charge'];
	if (($usage_total)>0) {
		$transcript_audio .= $text_transcript['service_detail']['transcript'][3];		// usage
		$duration += $text_transcript['service_detail']['duration'][3];
	}
	
	if ($subscr_detail['discount_id'] != 0) {
		$transcript_audio .= $text_transcript['service_detail']['transcript'][4];		// discount
		$duration += $text_transcript['service_detail']['duration'][4];
	}
	
	$transcript_video = array(
		$desc_service[$rec_services[$subscr_no][2]],
		$subscr_detail['service'],
		
		$rec_bill_plans[$subscr_detail['plan_id']][3],
		sprintf('%.2f', $subscr_detail['plan_charge']).' €',
		
		sprintf('%.2f', $nrc_total).' €',
		sprintf('%.2f', $usage_total).' €',
		$discount_txt
	);
		
	$transcript_audio = preg_replace('/, $/', '.', $transcript_audio);
	array_push($ar_service_details, array(
			$duration,
			str_replace($search_find, $search_replace, $transcript_audio),
			$transcript_video,
			));
}
array_push($audio_transcript, $ar_service_details);


# bill plan recommendation
$audio_txt = '';
$duration = 0;
$transcript_video = array( array('Service', 'Recommended plan', 'Saving') );
foreach ($analyze_subscription_bill_plan AS $subscr_no => $bill_plans) {
	if ($analyze_subscription_bill_plan[$subscr_no]['recommendation'] != -1 && $analyze_subscription_bill_plan[$subscr_no]['savings'] > 0.0) {
		$search_find = array('#SERVICE_TYPE#','#SERVICE_ID#','#NEW_PLAN_NAME#','#SAVING_AMOUNG#');
		$search_replace = array($desc_service[$rec_services[$subscr_no][2]],
								implode(' ', str_split($rec_services[$subscr_no][1])), 
								$rec_bill_plans[$analyze_subscription_bill_plan[$subscr_no]['recommendation']][3], 
								$analyze_subscription_bill_plan[$subscr_no]['savings']);
		$audio_txt .= str_replace($search_find, $search_replace, $text_transcript['bill_plan_recommendation']['transcript'][1]);
		$duration += $text_transcript['bill_plan_recommendation']['duration'][1];
		
		array_push($transcript_video, array($rec_services[$subscr_no][1], 
										$rec_bill_plans[$analyze_subscription_bill_plan[$subscr_no]['recommendation']][3],
										$analyze_subscription_bill_plan[$subscr_no]['savings'].' €'
										));
	}
}

if ($duration > 0) {
	array_push($audio_transcript, array(
			$text_transcript['bill_plan_recommendation']['duration'][0] + $duration,
			$text_transcript['bill_plan_recommendation']['transcript'][0] . $audio_txt,
			$transcript_video
			));
} else {
	array_push($audio_transcript, array(0, '', ''));
}
		
		
#thank you
array_push($audio_transcript, array($text_transcript['screen_6']['duration'], $text_transcript['screen_6']['transcript'], $text_transcript['screen_6']['transcript_video']));
		
#=====================================================================================================================================================
# translate if the selected language is different from English
if ($lang != 'en') {
	foreach ($audio_transcript AS $trans_id => $transcript) {
		if ($trans_id==1) {
			for ($i=0; $i<count($transcript[1]); $i++) {
				// $audio_transcript[$trans_id][1][$i] = watson_translate_data('en', $lang, $transcript[1][$i]);
				$json_data = json_decode(watson_translate_data('en', $lang, $transcript[1][$i]));
				$json_translated_data = $json_data->{'translations'};
				$audio_transcript[$trans_id][1][$i] = $json_translated_data[0]->{'translation'};
				
				$audio_transcript[$trans_id][0][$i] = intval(($json_data->{'word_count'}/3)*2);
				// $audio_transcript[$trans_id][0][$i] = intval($json_data->{'word_count'});
			}
		} elseif ($trans_id==3) {
			for ($i=0; $i<count($transcript); $i++) {
				// $audio_transcript[$trans_id][$i][1] = watson_translate_data('en', $lang, $transcript[$i][1]);
				
				$json_data = json_decode(watson_translate_data('en', $lang, $transcript[$i][1]));
				$json_translated_data = $json_data->{'translations'};
				$audio_transcript[$trans_id][$i][1] = $json_translated_data[0]->{'translation'};
				
				$audio_transcript[$trans_id][$i][0] = intval(($json_data->{'word_count'}/3)*2);
				// $audio_transcript[$trans_id][$i][0] = intval($json_data->{'word_count'});
			}
		} else {
			// $audio_transcript[$trans_id][1] = watson_translate_data('en', $lang, $transcript[1]);
			
			$json_data = json_decode(watson_translate_data('en', $lang, $transcript[1]));
			$json_translated_data = $json_data->{'translations'};
			
			$audio_transcript[$trans_id][1] = $json_translated_data[0]->{'translation'};
			
			$audio_transcript[$trans_id][0] = intval(($json_data->{'word_count'}/3)*2);
			// $audio_transcript[$trans_id][0] = intval($json_data->{'word_count'});
		}
	}
}

export_data('audio_transcript', $audio_transcript);

#=====================================================================================================================================================
# generation of audio using Watson Text-to-Speech technology
# generates audio files in wav format in the subdirectory audios
if (isset($_REQUEST['stage']) == 2) echo '<table><tr><td>Transcript</td><td>Audio</td></tr>';
foreach ($audio_transcript AS $trans_id => $transcript) {
	// if (!LOCAL) 
	{
		if ($trans_id==1) {
			for ($i=0; $i<count($transcript[1]); $i++) {
				$message = $transcript[1][$i];
				#$result = shell_exec("curl -k -u \"2d4cacf9-1954-4fad-8890-878f2152829f\":\"2QqD4AQ3zpiJ\" -X POST --header \"Content-Type: application/json\" --header \"Accept: audio/wav\" --max-time 90000 --output \"audios/$trans_id.$i.transcript.wav\"  --data \"{\\\"text\\\":\\\"$message\\\"}\" https://stream.watsonplatform.net/text-to-speech/api/v1/synthesize?voice=$watson_voice");
				$result = shell_exec("curl -u \"apikey:". SVC_TEXT2SPEECH_APIKEY ."\" -X POST --header \"Content-Type: application/json\" --data \"{\\\"text\\\":\\\"$message\\\"}\" --header \"Accept: audio/wav;rate=22050\" --output \"audios/$trans_id.$i.transcript.wav\" " . SVC_TEXT2SPEECH_URL . "?voice=$watson_voice");
				// echo "curl -u \"apikey:". SVC_TEXT2SPEECH_APIKEY ."\" -X POST --header \"Content-Type: application/json\" --data \"{\\\"text\\\":\\\"$message\\\"}\" --header \"Accept: audio/wav;rate=22050\" --output \"audios/$trans_id.$i.transcript.wav\" https://api.us-south.text-to-speech.watson.cloud.ibm.com/instances/3d5d0271-78e4-41dc-9b31-76d3ce4142d0/v1/synthesize?voice=$watson_voice\n\n";
			}
		} elseif ($trans_id==3) {
			for ($i=0; $i<count($transcript); $i++) {
				$message = $transcript[$i][1];
				#$result = shell_exec("curl -k -u \"2d4cacf9-1954-4fad-8890-878f2152829f\":\"2QqD4AQ3zpiJ\" -X POST --header \"Content-Type: application/json\" --header \"Accept: audio/wav\" --max-time 90000 --output \"audios/$trans_id.$i.transcript.wav\"  --data \"{\\\"text\\\":\\\"$message\\\"}\" https://stream.watsonplatform.net/text-to-speech/api/v1/synthesize?voice=$watson_voice");
				$result = shell_exec("curl -u \"apikey:". SVC_TEXT2SPEECH_APIKEY ."\" -X POST --header \"Content-Type: application/json\" --data \"{\\\"text\\\":\\\"$message\\\"}\" --header \"Accept: audio/wav;rate=22050\" --output \"audios/$trans_id.$i.transcript.wav\" " . SVC_TEXT2SPEECH_URL . "?voice=$watson_voice");
				// echo "curl -u \"apikey:". SVC_TEXT2SPEECH_APIKEY ."\" -X POST --header \"Content-Type: application/json\" --data \"{\\\"text\\\":\\\"$message\\\"}\" --header \"Accept: audio/wav;rate=22050\" --output \"audios/$trans_id.$i.transcript.wav\" https://api.us-south.text-to-speech.watson.cloud.ibm.com/instances/3d5d0271-78e4-41dc-9b31-76d3ce4142d0/v1/synthesize?voice=$watson_voice\n\n";
			}			
		} else {
			#$result = shell_exec("curl -k -u \"2d4cacf9-1954-4fad-8890-878f2152829f\":\"2QqD4AQ3zpiJ\" -X POST --header \"Content-Type: application/json\" --header \"Accept: audio/wav\" --max-time 90000 --output \"audios/$trans_id.transcript.wav\"  --data \"{\\\"text\\\":\\\"$transcript[1]\\\"}\" https://stream.watsonplatform.net/text-to-speech/api/v1/synthesize?voice=$watson_voice");
			$result = shell_exec("curl -u \"apikey:". SVC_TEXT2SPEECH_APIKEY ."\" -X POST --header \"Content-Type: application/json\" --data \"{\\\"text\\\":\\\"$transcript[1]\\\"}\" --header \"Accept: audio/wav;rate=22050\" --output \"audios/$trans_id.transcript.wav\" " . SVC_TEXT2SPEECH_URL . "?voice=$watson_voice");
			// echo "curl -u \"apikey:". SVC_TEXT2SPEECH_APIKEY ."\" -X POST --header \"Content-Type: application/json\" --data \"{\\\"text\\\":\\\"$transcript[1]\\\"}\" --header \"Accept: audio/wav;rate=22050\" --output \"audios/$trans_id.transcript.wav\" https://api.us-south.text-to-speech.watson.cloud.ibm.com/instances/3d5d0271-78e4-41dc-9b31-76d3ce4142d0/v1/synthesize?voice=$watson_voice\n\n";
		}
	}
	
	if (isset($_REQUEST['stage']) == 2) {
		echo "<tr><td>$transcript[1]</td><td><a href=audio/$trans_id.transcript.wav>$trans_id.transcript.wav</a></td></tr>";
	}
}
if (isset($_REQUEST['stage']) == 2) echo '</table>';
if (isset($_REQUEST['stage']) == 2) print_r($audio_transcript);

}

$audio_transcript = import_data('audio_transcript');



#=====================================================================================================================================================
# VIEW: <PRESENTATION LAYER>
# Flash video generation

$video = new SWFMovie();
$video->setRate(MOVIE_FPS);
$video->setDimension(MOVIE_WIDTH, MOVIE_HEIGHT);
$video->streamMp3(fopen(FILE_AUDIO,"rb"), 0);
$video->setBackground(255, 255, 255);
$video->add(new SWFAction("Stage.showMenu = false;"));

$img_svc_mobile = new SWFBitmap(fopen("img/mobile1.png","rb"));
$img_svc_tv = new SWFBitmap(fopen("img/tv2.png","rb"));
$img_svc_landline = new SWFBitmap(fopen("img/landline1.png","rb"));

$font = new SWFBrowserFont("DejaVu Serif");
$font = new SWFBrowserFont("Verdana");
$font_test = new SWFBrowserFont("Garamond");
$font_greet = new SWFBrowserFont("Century Schoolbook L");
$font_test = new SWFBrowserFont("Georgia");
$font_head = new SWFBrowserFont("Franklin Gothic Medium");
// $font_head = new SWFBrowserFont("Garamond");

function draw_square_image($size, $fill) {
	$sh = new SWFShape();
	$f = $sh->addFill($fill, SWFFILL_CLIPPED_BITMAP);
	$sh->setRightFill($f);
	// $sh->movePenTo(-$fill->getWidth()/2, -$fill->getHeight()/2);
	$sh->drawLine($fill->getWidth(), 0);
	$sh->drawLine(0, $fill->getHeight());
	$sh->drawLine(-$fill->getWidth(), 0);
	$sh->drawLine(0, -$fill->getHeight());
	return $sh;
}

function draw_shape_rgba($width, $height, $r, $g, $b, $a) {
	$sh = new SWFShape();
	$f = $sh->addFill($r,$g,$b,$a);
	$sh->setRightFill($f);
	$sh->drawLine($width, 0);
	$sh->drawLine(0, $height);
	$sh->drawLine(-$width, 0);
	$sh->drawLine(0, -$height);
	return $sh;
}

function generate_button($width, $height, $img, $action) {
	$but = new SWFButton( );
	$sh = new SWFShape( );
	$f = $sh->addFill(new SWFBitmap(fopen($img,'rb')));
	$sh->setRightFill($f);
	$sh->movePenTo(0,0);
	$sh->drawLineTo(0, $height);
	$sh->drawLineTo($width, $height);
	$sh->drawLineTo($width, 0);
	$sh->drawLineTo(0, 0);
	$but->addShape($sh, SWFBUTTON_HIT | SWFBUTTON_UP | SWFBUTTON_DOWN | SWFBUTTON_OVER);
	if ($action != "") $but->addAction(new SWFAction($action), SWFBUTTON_MOUSEDOWN);
	return $but;
}

# on-screen controls (such as Play, Pause, Stop)
// $but_play = generate_button(20, 20, 'img/play.png', 'play();');
// $h_play = $video->add($but_play);
// $h_play->moveTo(MOVIE_WIDTH-75, 1);

// $but_pause = generate_button(20, 20, 'img/pause.png', 'stop();stopAllSound();');
// $h_pause = $video->add($but_pause);
// $h_pause->moveTo(MOVIE_WIDTH-50, 1);

// $but_stop = generate_button(20, 20, 'img/stop.png', 'stop();stopAllSound();gotoAndStop(3);');
// $h_stop = $video->add($but_stop);
// $h_stop->moveTo(MOVIE_WIDTH-25, 1);

$init_logo_fill = new SWFBitmap(fopen("img/init_vodafone.png","rb"));
$logo = draw_square_image(LOGO_SIZE, new SWFBitmap(fopen("img/vodafone.png","rb")));
$logo2 = draw_square_image(LOGO_SIZE, new SWFBitmap(fopen("img/vodafone-logo.png","rb")));

#graphics
$shape1 = new SWFShape();
$shape1->setLine(1,255,0,200);
$shape1->drawCircle(75);
$spr1 = new SWFSprite(); $spr2 = new SWFSprite();
for ($i=1; $i<=50; $i++) {
	$f1 = $spr1->add($shape1);
	$f1->multColor(1,1,1,$i/100);
	$f1->scale($i*rand(1,8),$i*rand(1,5));
	$f1->rotate(-.2 * rand(1,5));
}
$spr1->nextFrame();

// $a = $spr2->add($spr1); $a->moveTo(rand(1,MOVIE_WIDTH), rand(1,MOVIE_HEIGHT));
// $b = $spr2->add($spr1); $b->moveTo(rand(1,MOVIE_WIDTH), rand(1,MOVIE_HEIGHT));
// for ($i=1; $i<=250; $i++) { $a->rotate(-.2 * rand(1,2)); $b->rotate(.2 * rand(1,2)); $spr2->nextFrame(); }

$num_bars = 2;
$graph_bars = array();
for ($i=1; $i<=$num_bars; $i++)
{
	$graph_bars[$i] = $spr2->add($spr1); 
	$graph_bars[$i]->moveTo(rand(1,MOVIE_WIDTH), rand(1,MOVIE_HEIGHT));
}

for ($i=1; $i<=250; $i++) { 
	for ($j=1; $j<=$num_bars; $j++) {
		if ($j%2 == 0)
			$graph_bars[$j]->rotate(.2 * rand(1,2)); 
		else
			$graph_bars[$j]->rotate(-.2 * rand(1,2)); 
	}	
	$spr2->nextFrame(); 
}

$h_graphics = $video->add($spr2);
// $h_graphics->setName("bgspr");
// $video->add(new SWFAction("_root.bgspr.cacheAsBitmap=True;_root.bgspr.opaqueBackground=0xFF0000;"));
$video->nextFrame();


#loading
$init_logo_cont= new SWFSprite();

$init_logo_spr= new SWFSprite();
$init_logo_box=new SWFShape(); 
$f = $init_logo_box->addFill($init_logo_fill, SWFFILL_CLIPPED_BITMAP);
$f->moveTo(-50,-50); $f->scaleTo(1,1);
$init_logo_box->setRightFill($f);
$init_logo_box->movePenTo(-50,-50); 
$init_logo_box->drawLine(100,0);  
$init_logo_box->drawLine(0,100); 
$init_logo_box->drawLine(-100,0); 
$init_logo_box->drawLine(0,-100); 
$init_logo_spr->add(new SWFAction("onEnterFrame=function(){
	this.logo_shape.onEnterFrame = function(){
		this._rotation+=8;
	};
};"));
$f1 = $init_logo_spr->add($init_logo_box);
$f1->setName('logo_shape');
$init_logo_spr->nextFrame();
$init_logo_cont->add($init_logo_spr);

$strAction=<<<EOD
createTextField("feedback",22,0,0,100,100);
myformat = new TextFormat();
myformat.autoSize = "left";
myformat.color = 0xaa8855;
myformat.size =1;
myformat.font = "Tahoma";
feedback.text="Loading: 0%";
feedback.autoSize = "left";
feedback.html = 0;
feedback.setNewTextFormat(myformat);
feedback.setTextFormat(myformat);
feedback._x=-5;
feedback._y=1.5;

onEnterFrame=function(){
	loading = _parent.getBytesLoaded();
	total = _parent.getBytesTotal();
	percent -= (percent-((loading/total)*100))*0.25;
	per = int(percent);
	feedback.text =  "Loading: "+per+"%";
	if (percent>99) {
		feedback.text =  "Loading: "+100+"%";
		delete onEnterFrame;
		_root.gotoAndPlay(3);
	}   
};
EOD;
$init_logo_cont->add(new SWFAction(str_replace("\r","", $strAction)));
$init_logo_cont->nextFrame();
$init_cont=$video->add($init_logo_cont);
$init_cont->moveTo(MOVIE_WIDTH/2, MOVIE_HEIGHT/2);
$video->add(new SWFAction("stop();"));
$video->nextFrame();

$video->remove($init_cont);
$video->nextFrame();

$h_logo = $video->add($logo);
$h_logo->moveTo(MOVIE_WIDTH/2-50, MOVIE_HEIGHT/2-50);
$h_logo->multColor(1,1,1,0);
$h_logo2 = $video->add($logo2);
$h_logo2->moveTo(15, 25);
$h_logo2->multColor(1,1,1,0);
$video->nextFrame();

#release: eliminate one of the following lines
for ($i=1; $i<=50; $i++) { $h_logo->multColor(1,1,1,($i*2)/100); $video->nextFrame(); }
for ($i=1; $i<=35; $i++) { $h_logo->move(0,-2); $video->nextFrame(); }
// $h_logo->multColor(1,1,1,1);$h_logo->move(0, -70);

foreach ($audio_transcript AS $trans_id => $transcript) {
	switch ($trans_id) {
		case 0: // welcome note
			$spr = new SWFSprite();
			#release: remove comment
			$audio_track = new SWFSound(fopen("audios/$trans_id.transcript.wav", 'rb'), SWF_SOUND_22KHZ|SWF_SOUND_16BITS|SWF_SOUND_MONO);
			$txt = new SWFTextField(SWF_TEXT_FIELD_OPTION);
			$txt->setBounds(MOVIE_WIDTH,100);
			$txt->align(SWFTEXTFIELD_ALIGN_CENTER);
			$txt->setFont($font_greet);
			$txt->setColor(200, 0, 0);
			$txt->setHeight(40);
			$txt->addString($transcript[2]);
			$spr_txt = $spr->add($txt);
			#release: remove comment
			$snd_ref = $spr->startSound($audio_track);
			$snd_ref->loopcount(1);
			$snd_ref->loopinpoint(1*1000);
			$spr->nextFrame();
			
			#release: remove comments
			$transcript_msg = $video->add($spr);
			$transcript_msg->moveTo($transcript_msg->getXScale(), MOVIE_HEIGHT/2);
			for ($i=1; $i<=($transcript[0]*MOVIE_FPS); $i++) { $video->nextFrame(); }
			$video->remove($transcript_msg); $video->remove($h_logo); $video->nextFrame();
			for ($i=1; $i<=25; $i++) { $h_logo2->multColor(1,1,1,($i*4)/100); $video->nextFrame(); }
			break;
		
		case 1: // pending payments
			$spr = new SWFSprite();
			$pos_x = 0;
			$pos_y = 0;
			$fld_rec = array();
			$h_fld_rec = array();
			
			$txt = new SWFTextField(SWF_TEXT_FIELD_OPTION);
			$txt->setBounds(MOVIE_WIDTH-120,100);
			$txt->align(SWFTEXTFIELD_ALIGN_CENTER);
			$txt->setFont($font_head);
			$txt->setHeight(25);
			$txt->setColor(255, 57, 57);
			$txt->addString('Pending Bills');
			$sh_txt = $spr->add($txt);
			$sh_txt->moveTo($pos_x, $pos_y);
			$pos_y+=50;
			
			$sh = new SWFShape();
			$sh->setLine(1, 0x7f, 0, 0);
			$sh->setRightFill($sh->addFill(0xff,0,0));
			$sh->movePenTo(0,25+25+25);
			$sh->drawLineTo(MOVIE_WIDTH-120,25+25+25);
			$spr->add($sh);
			for ($i=0; $i<=$transcript[3]; $i++) {
				for ($j=0; $j<count($transcript[2][0][0]); $j++) {
					$fld_rec[$i][$j] = new SWFTextField(SWF_TEXT_FIELD_OPTION);
					$fld_rec[$i][$j]->setBounds(150,100);
					if ($j==3 && $i!=0) {
						$fld_rec[$i][$j]->align(SWFTEXTFIELD_ALIGN_RIGHT);
					} else {
						$fld_rec[$i][$j]->align(SWFTEXTFIELD_ALIGN_LEFT);
					}
					$fld_rec[$i][$j]->setFont($font);
					$fld_rec[$i][$j]->setHeight(20);
					$fld_rec[$i][$j]->setColor(171, 0, 52);
					
					if (($j==1 || $j==2)&& $i!=0) {
						$fld_rec[$i][$j]->addString( date('d/m/Y', strtotime($transcript[2][0][$i][$j])) );
					} else {
						$fld_rec[$i][$j]->addString($transcript[2][0][$i][$j]);
					}
					$h_fld_rec[$i][$j] = $spr->add($fld_rec[$i][$j]);
					$h_fld_rec[$i][$j]->moveTo($pos_x + ($j * 150), $pos_y + ($i * 30));
				}
			}
			$spr->nextFrame();
			#release: remove complete comments
			$transcript_msg = $video->add($spr); $transcript_msg->moveTo(50,100); $video->nextFrame();
			for ($i=0; $i<count($transcript[1]); $i++) {
				$audio_track = new SWFSound(fopen("audios/$trans_id.$i.transcript.wav", 'rb'), SWF_SOUND_22KHZ|SWF_SOUND_16BITS|SWF_SOUND_MONO);
				$snd_ref = $video->startSound($audio_track);
				$snd_ref->loopcount(1);
				$snd_ref->loopinpoint(1*1000);
				
				$spr->nextFrame();
				for ($j=1; $j<=($transcript[0][$i]*MOVIE_FPS); $j++) { $video->nextFrame(); }
				$video->nextFrame();
			}
			$video->remove($transcript_msg); 
			break;
		
		case 2: // bill summary
			$spr = new SWFSprite();
			$pos_x = 0;
			$pos_y = 0;
			$fld_rec = array();
			$h_fld_rec = array();
			
			$txt = new SWFTextField(SWF_TEXT_FIELD_OPTION);
			$txt->setBounds(MOVIE_WIDTH,100);
			$txt->align(SWFTEXTFIELD_ALIGN_CENTER);
			$txt->setFont($font_head);
			$txt->setHeight(25);
			$txt->setColor(255, 57, 57);
			$txt->addString('Current Bill - Summary');
			$sh_txt = $spr->add($txt);
			$sh_txt->moveTo($pos_x, $pos_y);
			$pos_y+=50;
			
			$txt = new SWFTextField(SWF_TEXT_FIELD_OPTION);
			$txt->setBounds(MOVIE_WIDTH,100);
			$txt->align(SWFTEXTFIELD_ALIGN_LEFT);
			$txt->setFont($font);
			$txt->setHeight(20);
			$txt->setColor(140, 0, 70);
			$txt->addString('Bill No.: '.$transcript[3][0]);
			$sh_txt = $spr->add($txt);
			$sh_txt->moveTo($pos_x+100, $pos_y);
			$pos_y+=30;
			
			$txt = new SWFTextField(SWF_TEXT_FIELD_OPTION);
			$txt->setBounds(MOVIE_WIDTH,100);
			$txt->align(SWFTEXTFIELD_ALIGN_LEFT);
			$txt->setFont($font);
			$txt->setHeight(20);
			$txt->setColor(140, 0, 70);
			$txt->addString(sprintf('Total amount: %.2f €', $transcript[3][1]));
			$sh_txt = $spr->add($txt);
			$sh_txt->moveTo($pos_x+100, $pos_y);
			$pos_x+=200;
			$pos_y+=25;
			
			$audio_track = new SWFSound(fopen("audios/$trans_id.transcript.wav", 'rb'), SWF_SOUND_22KHZ|SWF_SOUND_16BITS|SWF_SOUND_MONO);			
			$sh = new SWFShape();
			$sh->setLine(1, 0x7f, 0, 0);
			$sh->setRightFill($sh->addFill(0xff,0,0));
			$sh->movePenTo($pos_x,$pos_y+52);
			$sh->drawLineTo($pos_x+count($transcript[2][0])*150,$pos_y+52);
			$spr->add($sh);
			$sh = new SWFShape();
			$sh->setLine(1, 0x7f, 0, 0);
			$sh->setRightFill($sh->addFill(0xff,0,0));
			$sh->movePenTo($pos_x,$pos_y+52+(count($transcript[2])*20));
			$sh->drawLineTo($pos_x+count($transcript[2][0])*150,$pos_y+52+(count($transcript[2])*20));
			$spr->add($sh);
			$pos_y+=25;
			
			$total = 0.0;
			for ($i=0; $i<count($transcript[2]); $i++) {
				$total += $transcript[2][$i][1];
			}
			
			array_push($transcript[2], array('Total', $total));
			
			for ($i=0; $i<count($transcript[2]); $i++) {
				for ($j=0; $j<count($transcript[2][0]); $j++) {
					$fld_rec[$i][$j] = new SWFTextField(SWF_TEXT_FIELD_OPTION);
					$fld_rec[$i][$j]->setBounds(150,100);
					if ($j==1 && $i!=0) {
						$fld_rec[$i][$j]->align(SWFTEXTFIELD_ALIGN_RIGHT);
					} else {
						if ($i==0) {
							$fld_rec[$i][$j]->align(SWFTEXTFIELD_ALIGN_CENTER);
						} else {
							$fld_rec[$i][$j]->align(SWFTEXTFIELD_ALIGN_LEFT);
						}
					}
					$fld_rec[$i][$j]->setFont($font);
					$fld_rec[$i][$j]->setHeight(20);
					$fld_rec[$i][$j]->setColor(155, 0, 88);
					
					if ($i!=0 && $j==1) {
						$fld_rec[$i][$j]->addString( sprintf('%.2f €', $transcript[2][$i][$j]) );
					} else {
						$fld_rec[$i][$j]->addString($transcript[2][$i][$j]);
					}
					$h_fld_rec[$i][$j] = $spr->add($fld_rec[$i][$j]);
					$h_fld_rec[$i][$j]->moveTo($pos_x + ($j * 150), $pos_y);
				}
				$pos_y+=30;
			}
			$snd_ref = $spr->startSound($audio_track);
			$snd_ref->loopcount(1);
			$snd_ref->loopinpoint(1*1000);
			$spr->nextFrame();
			#release: remove complete comments
			$transcript_msg = $video->add($spr);
			$transcript_msg->moveTo(0,100);
			$video->nextFrame();
			for ($i=1; $i<=($transcript[0]*MOVIE_FPS); $i++) { $video->nextFrame(); }
			$video->remove($transcript_msg); 
			break;		
		
		case 3: // bill - service details
			for ($i=0; $i<count($transcript); $i++) {
				$pos_x = 0;
				$pos_y = 0;
				$fld_rec = array();
				$h_fld_rec = array();
				
				$spr = new SWFSprite();
				
				#service image
				$spr_svc_logo = new SWFSprite();
				$sh_svc=new SWFShape(); 
				if ($transcript[$i][2][0] == 'GSM') {
					$img_data = $img_svc_mobile;
				} elseif ($transcript[$i][2][0] == 'Landline') {
					$img_data = $img_svc_landline;
				} elseif ($transcript[$i][2][0] == 'Television') {
					$img_data = $img_svc_tv;
				}
				$f = $sh_svc->addFill($img_data, SWFFILL_CLIPPED_BITMAP);
				$f->moveTo(-$img_data->getWidth()/2,-$img_data->getHeight()/2); $f->scaleTo(1,1);
				$sh_svc->setRightFill($f);
				$sh_svc->movePenTo(-$img_data->getWidth()/2,-$img_data->getHeight()/2); 
				$sh_svc->drawLine($img_data->getWidth(),0);  
				$sh_svc->drawLine(0,$img_data->getHeight()); 
				$sh_svc->drawLine(-$img_data->getWidth(),0); 
				$sh_svc->drawLine(0,-$img_data->getHeight());
				$spr_sh_svc = $spr_svc_logo->add($sh_svc);
				for($k=1;$k<=20;$k++) {
					$spr_sh_svc->moveTo($pos_x+150, MOVIE_HEIGHT - 100 - ($k*7.5));
					$spr_sh_svc->multColor(1,1,1,($k*5)/100);
					$spr_svc_logo->nextFrame();
				}
				$spr_svc_logo->add(new SWFAction("this.stop();"));
				$spr_svc_logo->nextFrame();
				$spr->add($spr_svc_logo);
				
				$audio_track = new SWFSound(fopen("audios/$trans_id.$i.transcript.wav", 'rb'), SWF_SOUND_22KHZ|SWF_SOUND_16BITS|SWF_SOUND_MONO);
				
				$txt = new SWFTextField(SWF_TEXT_FIELD_OPTION);
				$txt->setBounds(MOVIE_WIDTH,100);
				$txt->align(SWFTEXTFIELD_ALIGN_CENTER);
				$txt->setFont($font_head);
				$txt->setHeight(25);
				$txt->setColor(255, 57, 57);
				$txt->addString('Current Bill - Service detail');
				$sh_txt = $spr->add($txt);
				$sh_txt->moveTo($pos_x, $pos_y);
				$pos_x+=200;
				$pos_y+=50;
			
				# service id
				$txt = new SWFTextField(SWF_TEXT_FIELD_OPTION);
				$txt->setBounds(MOVIE_WIDTH,100);
				$txt->align(SWFTEXTFIELD_ALIGN_LEFT);
				$txt->setFont($font);
				$txt->setHeight(20);
				$txt->setColor(140, 0, 70);
				$txt->addString($transcript[$i][2][0].' '.$transcript[$i][2][1]);
				$sh_txt = $spr->add($txt);
				$sh_txt->moveTo($pos_x+100, $pos_y);
				$pos_y+=30;
				
				# bill plan
				$txt = new SWFTextField(SWF_TEXT_FIELD_OPTION);
				$txt->setBounds(MOVIE_WIDTH,100);
				$txt->align(SWFTEXTFIELD_ALIGN_LEFT);
				$txt->setFont($font);
				$txt->setHeight(20);
				$txt->setColor(140, 0, 70);
				$txt->addString('Plan: '. $transcript[$i][2][2]);
				$sh_txt = $spr->add($txt);
				$sh_txt->moveTo($pos_x+100, $pos_y);
				$pos_x+=200;
				$pos_y+=40;
				
				# monthly charge
				$txt = new SWFTextField(SWF_TEXT_FIELD_OPTION);
				$txt->setBounds(MOVIE_WIDTH,100);
				$txt->align(SWFTEXTFIELD_ALIGN_LEFT);
				$txt->setFont($font);
				$txt->setHeight(20);
				$txt->setColor(140, 0, 70);
				$txt->addString('Monthly: '. $transcript[$i][2][3]);
				$sh_txt = $spr->add($txt);
				$sh_txt->moveTo($pos_x, $pos_y);
				$pos_y+=30;
				
				#nrc
				if ($transcript[$i][2][4] != '0€') {
					$txt = new SWFTextField(SWF_TEXT_FIELD_OPTION);
					$txt->setBounds(MOVIE_WIDTH,100);
					$txt->align(SWFTEXTFIELD_ALIGN_LEFT);
					$txt->setFont($font);
					$txt->setHeight(20);
					$txt->setColor(140, 0, 70);
					$txt->addString('One time charge: '. $transcript[$i][2][4]);
					$sh_txt = $spr->add($txt);
					$sh_txt->moveTo($pos_x, $pos_y);
					$pos_y+=30;
				}
				
				#usage
				if ($transcript[$i][2][5] != '0€') {
					$txt = new SWFTextField(SWF_TEXT_FIELD_OPTION);
					$txt->setBounds(MOVIE_WIDTH,100);
					$txt->align(SWFTEXTFIELD_ALIGN_LEFT);
					$txt->setFont($font);
					$txt->setHeight(20);
					$txt->setColor(140, 0, 70);
					$txt->addString('Usage charge: '. $transcript[$i][2][5]);
					$sh_txt = $spr->add($txt);
					$sh_txt->moveTo($pos_x, $pos_y);
					$pos_y+=30;
				}
				
				#discount
				if ($transcript[$i][2][6] > 0) {
					$txt = new SWFTextField(SWF_TEXT_FIELD_OPTION);
					$txt->setBounds(MOVIE_WIDTH,100);
					$txt->align(SWFTEXTFIELD_ALIGN_LEFT);
					$txt->setFont($font);
					$txt->setHeight(20);
					$txt->setColor(140, 0, 70);
					$txt->addString('Discount: '. $transcript[$i][2][6]);
					$sh_txt = $spr->add($txt);
					$sh_txt->moveTo($pos_x, $pos_y);
					$pos_y+=30;
				}
				
				$snd_ref = $spr->startSound($audio_track);
				$snd_ref->loopcount(1);
				$snd_ref->loopinpoint(1*1000);
				$spr->nextFrame();
				#release: remove complete comments
				$transcript_msg = $video->add($spr);
				$transcript_msg->moveTo(0,100);
				for ($j=1; $j<=($transcript[$i][0]*MOVIE_FPS); $j++) { $video->nextFrame(); }
				$video->remove($transcript_msg); 
			}
			break;		
		
		case 4: // bill plan recommendations
			if ($transcript[0] > 0) {
				$spr = new SWFSprite();
				$pos_x = 0;
				$pos_y = 0;
				$fld_rec = array();
				$h_fld_rec = array();
				
				$txt = new SWFTextField(SWF_TEXT_FIELD_OPTION);
				$txt->setBounds(MOVIE_WIDTH-120,100);
				$txt->align(SWFTEXTFIELD_ALIGN_CENTER);
				$txt->setFont($font_head);
				$txt->setHeight(25);
				$txt->setColor(255, 57, 57);
				$txt->addString('Bill Plan Recommendation');
				$sh_txt = $spr->add($txt);
				$sh_txt->moveTo($pos_x, $pos_y);
				$pos_y+=50;
				
				$field_size = array(130,350,110);
				$sh = new SWFShape();
				$sh->setLine(1, 0x7f, 0, 0);
				$sh->setRightFill($sh->addFill(0xff,0,0));
				$sh->movePenTo(0,25+25+25);
				$sh->drawLineTo(MOVIE_WIDTH-120,25+25+25);
				$spr->add($sh);
				
				#service records
				for ($i=0; $i<count($transcript[2]); $i++) {
					$pos_x = 0;
					for ($j=0; $j<count($transcript[2][0]); $j++) {
						$fld_rec[$i][$j] = new SWFTextField(SWF_TEXT_FIELD_OPTION);
						$fld_rec[$i][$j]->setBounds($field_size[$j],100);
						if ($j==2 && $i!=0) {
							$fld_rec[$i][$j]->align(SWFTEXTFIELD_ALIGN_RIGHT);
						} else {
							$fld_rec[$i][$j]->align(SWFTEXTFIELD_ALIGN_LEFT);
						}
						$fld_rec[$i][$j]->setFont($font);
						$fld_rec[$i][$j]->setHeight(20);
						$fld_rec[$i][$j]->setColor(171, 0, 52);
						$fld_rec[$i][$j]->addString($transcript[2][$i][$j]);
						$h_fld_rec[$i][$j] = $spr->add($fld_rec[$i][$j]);
						$h_fld_rec[$i][$j]->moveTo($pos_x, $pos_y + ($i * 30));
						$pos_x += $field_size[$j];
					}
				}
				$spr->nextFrame();
				#release: remove complete comments
				$transcript_msg = $video->add($spr); $transcript_msg->moveTo(50,100); $video->nextFrame();
				$audio_track = new SWFSound(fopen("audios/$trans_id.transcript.wav", 'rb'), SWF_SOUND_22KHZ|SWF_SOUND_16BITS|SWF_SOUND_MONO);
				$snd_ref = $video->startSound($audio_track);
				$snd_ref->loopcount(1);
				$snd_ref->loopinpoint(1*1000);
				for ($j=1; $j<=($transcript[0]*MOVIE_FPS); $j++) { $video->nextFrame(); }
				$video->nextFrame();
				$video->remove($transcript_msg); 
			}
			break;	
	
		case 5: // thank you
			$audio_track = new SWFSound(fopen("audios/$trans_id.transcript.wav", 'rb'), SWF_SOUND_22KHZ|SWF_SOUND_16BITS|SWF_SOUND_MONO);
			$spr = new SWFSprite();
			$pos_x = 0;
			$pos_y = 0;
			$fld_rec = array();
			$h_fld_rec = array();
			
			$sh = new SWFShape();
			$line_size=150;
			$sh->setLine(2, 200, 100, 0);
			$sh->setRightFill($sh->addFill(0xff,0,0));
			$sh->movePenTo(-$line_size,0);
			$sh->drawLineTo($line_size,0);
			$line1 = $spr->add($sh);
			$line1->moveTo(-$line_size,MOVIE_HEIGHT/2-35);
			$line2 = $spr->add($sh);
			$line1->multColor(1,1,1,0);
			$line2->moveTo(MOVIE_WIDTH+$line_size,MOVIE_HEIGHT/2+29);
			$line2->multColor(1,1,1,0);
		
			$txt = new SWFTextField(SWF_TEXT_FIELD_OPTION);
			$txt->setBounds(MOVIE_WIDTH,100);
			$txt->align(SWFTEXTFIELD_ALIGN_CENTER);
			$txt->setFont($font_greet);
			$txt->setHeight(40);
			$txt->setColor(200, 0, 0);
			$txt->addString('THANK YOU');
			$sh_txt = $spr->add($txt);
			$sh_txt->moveTo(0,MOVIE_HEIGHT/2-25);
			$sh_txt->multColor(1,1,1,0);
			$pos_y+=50;	

			for ($i=1; $i<=50; $i++) {
				$line1->multColor(1,1,1,$i/50);
				$line2->multColor(1,1,1,$i/50);
				$sh_txt->multColor(1,1,1,$i/50);
				
				$line1->moveTo(((MOVIE_WIDTH/2)*$i/50), MOVIE_HEIGHT/2-35);
				$line2->moveTo(MOVIE_WIDTH - ((MOVIE_WIDTH/2)*$i/50),MOVIE_HEIGHT/2+29);
				
				$spr->nextFrame();
			}
			
			$snd_ref = $spr->startSound($audio_track);
			$snd_ref->loopcount(1);
			$snd_ref->loopinpoint(1*1000);
			$spr->nextFrame();
			
			$spr->add(new SWFAction("this.stop();"));
			$spr->nextFrame();
			
			#release: remove complete comments
			$transcript_msg = $video->add($spr);
			$transcript_msg->moveTo(0,0);
			for ($j=1; $j<=($transcript[0]*MOVIE_FPS); $j++) { $video->nextFrame(); }
			// $video->remove($transcript_msg);
			
			$video->add(new SWFAction("stop();"));
			$video->nextFrame();
			break;
	}

}

header('Content-type: application/x-shockwave-flash');
#release: remove one
$video->output();
// $video->save('test_flash2.swf');


