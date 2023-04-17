<?php

require_once('test/fpdf.php');
require_once('inc/init.php');

$literals = array(
	array(
		"en" => 'No. of invoice:',
		"es" => 'Nº de factura:',
		"ar" => 'No. of invoice:',
		"fr" => 'N° de facture:',
		"de" => 'Nr. Der Rechnung:',
		"it" => 'Numero di fattura:',
		"ja" => '????:',
		"pt" => 'Nº da fatura:'
	),
	
	array(
		"en" => 'Vodamobile account number:',
		"es" => 'Nº de cuenta Vodamobile:',
		"ar" => 'Vodamobile account number:',
		"fr" => 'Numéro de compte Vodamobile:',
		"de" => 'Vodamobile-Kontonummer:',
		"it" => 'Numero di conto Vodamobile:',
		"ja" => '??????????:',
		"pt" => 'Número da conta Vodamobile:'
	),
	
	array(
		"en" => 'Statement date:',
		"es" => 'Fecha de emisión:',
		"ar" => 'Statement date:',
		"fr" => 'Date du relevé:',
		"de" => 'Abrechnungsdatum:',
		"it" => "Data dell\'estratto conto:",
		"ja" => '???:',
		"pt" => 'Data de declaração:'
	),
		array(
		"en" => 'Place of issue:',
		"es" => 'Lugar de emisión:',
		"ar" => 'No. of invoice:',
		"fr" => 'Lieu de délivrance:',
		"de" => 'Ausstellungsort:',
		"it" => 'Luogo di rilascio:',
		"ja" => '?????:',
		"pt" => 'Local de emissão:'
	),
		array(
		"en" => 'Payment method:',
		"es" => 'Forma de pago:',
		"ar" => 'Payment method:',
		"fr" => 'Mode de paiement:',
		"de" => 'Bezahlverfahren:',
		"it" => 'Metodo di pagamento:',
		"ja" => '????:',
		"pt" => 'Forma de pagamento:'
	),
		array(
		"en" => 'Bank name:',
		"es" => 'Entidad bancaria:',
		"ar" => 'Bank name:',
		"fr" => 'Nom de banque:',
		"de" => 'Bank Name:',
		"it" => 'nome della banca:',
		"ja" => '???:',
		"pt" => 'nome do banco:'
	),
		array(
		"en" => 'Account number:',
		"es" => 'Nº de cuenta:',
		"ar" => 'Account number:',
		"fr" => 'Numéro de compte:',
		"de" => 'Kontonummer:',
		"it" => 'Numero di conto:',
		"ja" => '????:',
		"pt" => 'Número da conta:'
	),
		array(
		"en" => 'Due date:',
		"es" => 'Fecha de vencimiento:',
		"ar" => 'Due date:',
		"fr" => "Date d\'échéance:",
		"de" => 'Geburtstermin:',
		"it" => 'Scadenza:',
		"ja" => '??:',
		"pt" => 'Data de Vencimento:'
	),
		array(
		"en" => 'Billing period:',
		"es" => 'Periodo de facturación:',
		"ar" => 'Billing period:',
		"fr" => 'Période de facturation:',
		"de" => 'Abrechnungszeitraum:',
		"it" => 'Periodo di fatturazione:',
		"ja" => '??????:',
		"pt" => 'Período de pagamento:'
	),
		array(
		"en" => 'Base taxable:',
		"es" => 'Base imponible',
		"ar" => 'Base taxable:',
		"fr" => 'Assiette imposable:',
		"de" => 'Steuerpflichtige Basis:',
		"it" => 'Base imponibile:',
		"ja" => '?????:',
		"pt" => 'Base tributável:'
	),
		array(
		"en" => 'Tax:',
		"es" => 'Impuesto:',
		"ar" => 'Tax:',
		"fr" => 'Impôt:',
		"de" => 'MwSt:',
		"it" => 'Imposta:',
		"ja" => '??:',
		"pt" => 'Imposto:'
	),
		array(
		"en" => 'Invoice no.',
		"es" => 'Total Factura',
		"ar" => 'Invoice no. ',
		"fr" => 'Facture no.',
		"de" => 'Rechnung Nr.',
		"it" => 'Fattura n.',
		"ja" => '??????',
		"pt" => 'Fatura no.'
	),
		array(
		"en" => 'Total to pay',
		"es" => 'Total a Pagar',
		"ar" => 'Total to pay',
		"fr" => 'Total à payer',
		"de" => 'Insgesamt zu bezahlen',
		"it" => 'Totale da pagare',
		"ja" => '??????',
		"pt" => 'Total a pagar'
	),
		array(
		"en" => 'Summary',
		"es" => 'Resumen',
		"ar" => 'Summary',
		"fr" => 'Résumé',
		"de" => 'Zusammenfassung',
		"it" => 'Sommario',
		"ja" => '??',
		"pt" => 'Resumo'
	),
);

$lang = (isset($_REQUEST['lang']) && preg_match('/^(en|fr|de|es|ja|it|pt|ar)$/', $_REQUEST['lang'])) ? $_REQUEST['lang'] : die('Error: Invalid language');
define('LANG', $lang);

# check whether there is customer account number passed as an argument or not in URL
if (!isset($_REQUEST['ext_id']) || intval($_REQUEST['ext_id']) <= 0 ) {
	echo 'Error: Missing customer account in the URL';
	exit;
}
define('EXTERNAL_ID', $_REQUEST['ext_id']);



//================================== Data Extraction =================================================================================================================================
# account details
$result_account = pg_query($db_conn, "SELECT * FROM accounts WHERE external_id = '".$_REQUEST['ext_id']."'");

if (pg_num_rows($result_account) <= 0) {
	echo 'Error:  No account detail found in the database.';
	exit;
} else {
	$rec_account = pg_fetch_row($result_account);
}

$result_services = pg_query($db_conn, "SELECT * FROM services WHERE account_no=".$rec_account[0]);
if (pg_num_rows($result_services) <= 0) {
	echo 'Error: Service data retrieval failed';
	exit;
} else {
	$rec_services = pg_fetch_row($result_services);
}

$result_invoice_data =  pg_query($db_conn, "
SELECT a.bill_no, statement_date, from_date, to_date, total, tax, total_invoice, payment_date,
	b.subscr_no, type_code, subtype_code, amount, usage_duration,
	external_id, service_type, 
	(select plan_name from bill_plans where bill_plan_id = c.bill_plan_id) plan_name,
	(select rate from bill_plans where bill_plan_id = c.bill_plan_id) rate,
	(select description from bill_plans where bill_plan_id = c.bill_plan_id) description
FROM invoice a, invoice_details b
LEFT JOIN services c ON b.subscr_no=c.subscr_no
WHERE a.bill_no=b.bill_no and a.bill_no=(select max(bill_no) from invoice where account_no=$rec_account[0])
ORDER BY b.subscr_no, type_code, subtype_code");
if (pg_num_rows($result_invoice_data) <= 0) {
	echo 'Error:  Invoice data retrieval failed';
	exit;
} else {
	$rec_invoice_data = array();
	while ($rec = pg_fetch_row($result_invoice_data)) {
		array_push($rec_invoice_data, $rec);
	}
}

//================================== PDF Generation ==================================================================================================================================
$pdf = new FPDF('P','mm','A4');
$pdf->AddPage();

#$pdf->Image('img/invoice_header.png',10,6,42.84,4.08);
$pdf->Image('img/invoice_header.png',15,15,171.36,16.32);

$pdf->SetFont('Arial','',9);
// $w = $pdf->GetStringWidth($literals[0][LANG])+6;
// $pdf->SetX( (210-$w)/2 );
// $pdf->Cell(0,4, $literals[0][LANG]);

$field_left_width=40;

# Left side - bill detail
$pdf->SetY(40);
$pdf->SetX(15); $pdf->Cell($field_left_width,4, $literals[0][LANG]); $pdf->Cell($field_left_width,4, $literals[1][LANG]); $pdf->Ln(4);
$pdf->SetX(15); $pdf->Cell($field_left_width,4, $literals[1][LANG]); $pdf->Cell($field_left_width,4, $literals[3][LANG]); $pdf->Ln(4);
$pdf->SetX(15); $pdf->Cell($field_left_width,4, $literals[2][LANG]); $pdf->Cell($field_left_width,4, "Madrid"); $pdf->Ln(4);
$pdf->Ln(4);
$pdf->SetX(15); $pdf->Cell($field_left_width,4, $literals[3][LANG]); $pdf->Cell($field_left_width,4, "Domiciliación bancaria"); $pdf->Ln(4);
$pdf->SetX(15); $pdf->Cell($field_left_width,4, $literals[4][LANG]); $pdf->Cell($field_left_width,4, "BANKIA, S.A."); $pdf->Ln(4);
$pdf->SetX(15); $pdf->Cell($field_left_width,4, $literals[5][LANG]); $pdf->Cell($field_left_width,4, "******9766"); $pdf->Ln(4);
$pdf->SetX(15); $pdf->Cell($field_left_width,4, $literals[6][LANG]); $pdf->Cell($field_left_width,4, "******9766"); $pdf->Ln(4);
$pdf->Ln(4);

$pdf->SetFont('Arial','B',9);
$pdf->SetX(15); $pdf->Cell($field_left_width,4, $literals[7][LANG]); $pdf->Cell($field_left_width,4, $rec_invoice_data[0][2].' - '.$rec_invoice_data[0][3]); $pdf->Ln(4);

# Right side - Address 
$pdf->SetFont('Arial','',9);
$pdf->SetY(45);
$pdf->SetX(130); $pdf->Cell($field_left_width,5, iconv("UTF-8", "Windows-1252", $rec_account[2].' '.$rec_account[3])); $pdf->Ln(5);
$pdf->SetX(130); $pdf->Cell($field_left_width,5, iconv("UTF-8", "Windows-1252", $rec_account[4])); $pdf->Ln(5);
$pdf->SetX(130); $pdf->Cell($field_left_width,5, iconv("UTF-8", "Windows-1252", $rec_account[7].' '.$rec_account[5])); $pdf->Ln(5);
$pdf->SetX(130); $pdf->Cell($field_left_width,5, iconv("UTF-8", "Windows-1252", $rec_account[6])); $pdf->Ln(5);
$pdf->SetX(130); $pdf->Cell($field_left_width,5, iconv("UTF-8", "Windows-1252", $rec_account[8])); $pdf->Ln(5);

# Bill total summary
$pdf->SetY(90); 
// $pdf->SetDrawColor(0,80,180);
$pdf->SetFillColor(175,175,175);
$pdf->SetTextColor(0,0,1);
$pdf->SetFont('Arial','',10);
// $pdf->SetLineWidth(1);
$pdf->Rect(15, 85, 100, 35, 'F');
$pdf->SetX(18); $pdf->Cell(60,6,$literals[9][LANG]); $pdf->Cell(40,6,sprintf('%.2f €',$rec_invoice_data[0][4])); $pdf->Ln(6);
$pdf->SetX(18); $pdf->Cell(60,6,$literals[10][LANG].' (15%)'); $pdf->Cell(40,6,sprintf('%.2f €',$rec_invoice_data[0][5])); $pdf->Ln(6);
$pdf->SetFont('Arial','B',10);
$pdf->SetX(18); $pdf->Cell(60,6,$literals[11][LANG]); $pdf->Cell(40,6,sprintf('%.2f €',$rec_invoice_data[0][6])); $pdf->Ln(6);
$pdf->SetX(18); $pdf->Cell(60,6,$literals[12][LANG]); $pdf->Cell(40,6,sprintf('%.2f €',$rec_invoice_data[0][4]+$rec_invoice_data[0][5])); $pdf->Ln(6);


# Resumen
$service = '';
$posY = 120;
for ($i=0; $i< count($rec_invoice_data); $i++)
{
	$posY += 5; $pdf->SetY($posY); 
	if ($service != $rec_invoice_data[$i][13])
	{
		# account details
		$bill_no = $rec_invoice_data[$i][0];
		$subscr_no = $rec_invoice_data[$i][8];
		
		$sql = "SELECT 
			(SELECT coalesce(SUM(amount),0) FROM invoice_details WHERE bill_no = $bill_no and subscr_no = $subscr_no and type_code = 2)
			+ (SELECT coalesce(SUM(amount),0) FROM invoice_details WHERE bill_no = $bill_no and subscr_no = $subscr_no and type_code = 7)
			- (SELECT coalesce(SUM(amount),0) FROM invoice_details WHERE bill_no = $bill_no and subscr_no = $subscr_no and type_code = 5)";
			
		$result_serv_total = pg_query($db_conn, $sql);

		if (pg_num_rows($result_serv_total) <= 0) {
			echo "Error:  Service total\n";
			echo $sql;
			exit;
		} else {
			$rec_serv_summary = pg_fetch_row($result_serv_total);
		}

		$posY += 5; $pdf->SetY($posY);
		$service = $rec_invoice_data[$i][13];
		$pdf->SetFillColor(175,175,175);
		$pdf->SetTextColor(0,0,1);
		$pdf->SetFont('Arial','',10);
		$pdf->Rect(15, $posY, 180, 6, 'F');

		$pdf->SetX(18); $pdf->Cell(30,6,$literals[13][LANG]); 	// resumen
		$pdf->Cell(60,6,$rec_invoice_data[$i][13]); 			// service number
		$pdf->Cell(45,6,$rec_invoice_data[$i][17]); 			// plan name
		$pdf->Cell(40,6,$rec_serv_summary[0].' €'); 			// service total
		
		$posY += 7; $pdf->SetY($posY);
		$val = sprintf('%0.4f €', $rec_invoice_data[$i][11]);
		$pdf->SetX(25);
		$pdf->Cell(60,6,'Plan:');
		$pdf->Cell(30,6,$val);
	}

	if ($rec_invoice_data[$i][9] == 5)											// Discount
	{
		$val = sprintf('-%0.4f €', $rec_invoice_data[$i][11]);
		$pdf->SetX(25);
		$pdf->Cell(60,6,'Usage Discount:');
		$pdf->Cell(30,6,$val);
	}
	elseif ($rec_invoice_data[$i][9] == 7)										// Usage
	{
		if ($rec_invoice_data[$i][10] == 400) // local usag
			$label = "Usage local:";
		elseif ($rec_invoice_data[$i][10] == 401) // national usage
			$label = "Usage national:";
		elseif ($rec_invoice_data[$i][10] == 402) // international usage
			$label = "Usage international:";
			
		$val = sprintf('%0.4f €', $rec_invoice_data[$i][11]);
		$pdf->SetX(25);
		$pdf->Cell(60,6,$label);
		$pdf->Cell(30,6,$val);
	}
}


$pdf->Output();





