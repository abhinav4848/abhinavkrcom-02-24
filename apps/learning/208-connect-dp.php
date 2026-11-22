<?php
require_once __DIR__ . '/../_shared/database.php';
$link = project_database('learning');
// Check connection



//$query = "SELECT * FROM users";
//$query = "SELECT * FROM users WHERE id = 1";
//% symbol means "anything" and then what you put after means anything that ends with gmail.com
//$query = "SELECT * FROM users WHERE email LIKE '%gmail.com'";
$name = "Abhinav o' grady";
$query = "SELECT `email` FROM users WHERE name = '".project_value($link, $name)."'";

if ($result = project_query($link, $query)) {
	/**
	//each time you project_fetch_array($result), you fetch a new row. Hence all 3 below are diff. So might as well make a while loop.
	$row = project_fetch_array($result);
	print_r($row);
	$row = project_fetch_array($result);
	print_r($row);
	$row = project_fetch_array($result);
	print_r($row);*/
	while ($row = project_fetch_array($result)) {
		print_r($row);
		echo "<br>";
	}
}
?>