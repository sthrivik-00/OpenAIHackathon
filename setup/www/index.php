<?php

require_once('inc/init.php');

# account details
$szCuentas = '';
$result_account = pg_query($db_conn, "SELECT external_id FROM accounts");
if (pg_num_rows($result_account) <= 0) {
	echo 'Error:  No account detail found in the database.';
	exit;
} else {
	while ($rec = pg_fetch_row($result_account)) {
		$szCuentas .= "<option value=\"". $rec[0] ."\">". $rec[0] ."</option>";
	}
}

?>
<html>
<head>
<link rel="icon" href="favicon.ico" type="image/x-icon" /><link rel="shortcut icon" href="favicon.ico" type="image/x-icon" />
</head>
<body>
<p>&nbsp;</p>
<p>&nbsp;</p>
<form method=post action=videobill.php><table width=100% border=0><tr><td align=center><img src=img/vodafone.png><br><br></td></tr><tr><td align=center><p style="padding-top:5px;color:red;font-weight:bold;font-size:19pt;font-family:Arial">Welcome to Video Bill</style></td></tr>
<tr><td><hr size=1 color=orange width=800></td></tr>
<tr align=center><td><br>Customer ID : <select name=customer_id><?php echo $szCuentas; ?></select></td></tr>
<tr align=center><td><br>Language : <select name=lang>
<option value="en">English</option>
<option value="es">Spanish</option>
<option value="ar">Arabic</option>
<option value="fr">French</option>
<option value="de">German</option>
<option value="it">Italian</option>
<option value="ja">Japanese</option>
<option value="pt">Portuguese</option>
</select></td></tr>
<tr align=center><td><br><input type=submit value="Login"></td></tr>
</table></form>
</body>
</html>


