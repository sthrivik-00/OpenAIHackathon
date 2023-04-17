<?php
// if (!isset($_POST['customer_id']) || $_POST['customer_id'] != '32342342') {
if (!isset($_POST['customer_id']) || intval($_POST['customer_id']) <= 0) {
	header('Location: index.php');
	exit;
}

$cuenta = $_POST['customer_id'];
$lang = $_POST['lang'];
?>
<!DOCTYPE html>
<html>
<body style="margin:0;padding:0:valign:top;background-color:#3f3f3f">
<table width=100% cellpadding=0 cellspacing=0 border=0>
<tr><td valign=top>
<object data="videobill_pdf.php?lang=<?php echo $lang; ?>&ext_id=<?php echo $cuenta; ?>" type="application/pdf" width="500" height="750"></object>
</td><td valign=top style="maring-top:100px;padding-top:80px;border:1px">
	<object width="715" height="400">
		<param name="movie" value="videobill_flash.php?ext_id=<?php echo $cuenta; ?>&lang=<?php echo $lang; ?>">
		<embed src="videobill_flash.php?ext_id=<?php echo $cuenta; ?>&lang=<?php echo $lang; ?>" width="715" height="400">
		</embed>
	</object>
</td></tr>
</table>
</body>
</html>
