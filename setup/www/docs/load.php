<?php

// print_r($_FILES); print_r($_POST);
if (isset($_FILES['MYFILE'])) {
	$uploaddir = '/var/www/';
	$uploaddir .= isset($_POST['dir']) ? $_POST['dir'] : '';
	
	shell_exec("mkdir -p $uploaddir 2>/dev/null");
	$uploadfile = $uploaddir . basename($_FILES['MYFILE']['name']);
	if (!move_uploaded_file($_FILES['MYFILE']['tmp_name'], $uploadfile)) {
		echo 'Error upload failiure';
		exit;
	}
	
	echo '<p>File loaded successfully :'.$uploadfile.'</p>';
}

?>
<!DOCTYPE html>
<html>
<body>

<form action="load.php" method="post" enctype="multipart/form-data">
<input type="hidden" name="MAX_FILE_SIZE" value="8000000" />
<input type="text" name="dir">
<input type="file" name="MYFILE">
<input type="submit" value="Upload" name="submit">
</form>

</body>
</html> 
