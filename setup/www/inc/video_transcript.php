<?php

$template_video_transcript = array(
	# welcome note
	'screen_1' => array(
		"duration" => 8,
		"transcript" => 'Hello #NAME#. Welcome to your Video Bill',
		"transcript_video" => 'Hello #NAME#'
	),
	
	# pending payment detail
	'screen_2' => array(
		"no_pending_payment" => array(
			"duration" => 12,		
			"transcript" => "Thanks for paying bill amounts on time and there are no pending dues to be paid."
		),
		"pending_payment_head" => array(
			"duration" => 4,		
			"transcript" => "There are some old bills not paid yet.  These are... "
		),
		"pending_payment_tail" => array(
			"duration" => 10,		
			"transcript" => "Please pay these pending bill amounts as soon as possible for uninteruppted services."
		),
		"PENDING_BILL_DETAIL" => array(
			"duration" => 7,
			"video_title" => array('Bill No.', 'Bill date', 'Due date', 'Amount'),
			"video_records" => array('#BILL_NO#', '#BILL_DATE#', '#DUE_DATE#', '#BILL_AMOUNT# €'),
			"transcript" => "bill #BILL_NO# has total amount #BILL_AMOUNT#€, "
		)
	),
	
	'screen_3' => array(
		"summary" => array(
			"duration" => 12,
			"transcript" => "The total bill amount for the current month including tax is #BILL_AMOUNT#€.  The summary of the total amount at service levels are... #SERVICE_SUMMARY#."
		),
		'SERVICE_SUMMARY' => array(
			"duration" => 8,
			"video_title" => array('Service', 'Amount'),
			// "video_records" => array('#SERVICE_ID#', '#SERVICE_TOTAL#€'),
			"transcript" => 'for #SERVICE_TYPE# service #SERVICE_ID# is #SERVICE_TOTAL# €, '
		)
	),
	
	'screen_4' => array(
		"duration" => 10,
		"transcript" => "Let's now detail the services"	
	),
	
	"service_detail" => array(
		"duration" => array(5, 5, 5, 5, 5),
		"transcript" => array(
			'The #SERVICE_TYPE# service #SERVICE_ID# has the charges are ... ',
			'monthly plan #PLAN_NAME#,  #PLAN_CHARGE#€. ',
			array(
				'non-recurring charge #TOTAL_NRC#€ for #NRC_DETAIL#. ',     	 // if only one NRC
				'total non-recurring charge #TOTAL_NRC#€ which includes ',   // if there is multiple NRC
				'#NRC_DETAIL# charge #NRC_DETAIL_CHARGE#€, '					 // if there is multiple NRC
				)
			,
			'total usages #TOTAL_USAGE#€. ',
			'discount #DISCOUNT_DETAIL# applicable individually on each different types of usage. ')
	),
	
	"bill_plan_recommendation" => array(
		"duration" => array(15, 15),
		"transcript" => array(
			"Based on the average usage of the service in the past six months and analysing with the available bill plans in our system, we have a bill plan recommendation that can be most appropriate and cost effective. ",
			"For #SERVICE_TYPE# service #SERVICE_ID#, we recommend plan #NEW_PLAN_NAME# and that can save monthly around #SAVING_AMOUNG#€. "
		)
	),
	
	'screen_6' => array(
		"duration" => 5,
		"transcript" => "Thank You.",
		"transcript_video" => "THANK YOU!!"
	),
);

