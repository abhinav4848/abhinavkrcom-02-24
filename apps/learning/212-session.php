<?php
	session_start();
	if (!empty($_SESSION['email'])) {
		echo "You're logged in, " . htmlspecialchars($_SESSION['email'], ENT_QUOTES, 'UTF-8');
	} else {
		//generates an error in the error log that email is undefined. duh!
		header("Location: 210-mini-signup.php");
	}
?>
