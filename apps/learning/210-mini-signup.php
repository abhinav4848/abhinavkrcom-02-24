<?php
session_start();
require_once __DIR__ . '/../_shared/database.php';
$link = project_database('learning');
//check to see if you have any post variables
if (array_key_exists('email', $_POST) OR array_key_exists('password', $_POST)) {
	//check if the user actually typed something in.
	if ($_POST['email'] == '') {
		echo "<p>Email is required.</p>";
	} elseif ($_POST['password'] =='') {
		echo "<p>Password is required</p>";
	} else {
		//check if the typed email already exists.
		$query = "SELECT `id` FROM `users` WHERE email = '".project_value($link, $_POST['email'])."'";
		$result = project_query($link, $query);
		if (project_num_rows($result)>0) {
			echo "<p>That email address <strong>".$_POST['email']."</strong> has already been taken.</p>";
		} else {
			//else sign up the user with that email
			$hash = password_hash($_POST['password'], PASSWORD_DEFAULT);
			$query = "INSERT INTO `users` (`email`,`password`,`name`) VALUES ('".project_value($link, $_POST['email'])."','".project_value($link, $hash)."','')";
			if (project_query ($link, $query)) {
				//echo "<p>You were signed up with <strong>".$_POST['email']."</strong></p>";
				$_SESSION['email'] = $_POST['email'];
				header("Location: 212-session.php");
			} else {
				echo "<p>Sign up failed. Please try later.</p>";
			}
		}
	}
}
// Check connection

?>
<form method="post">
	<input type="email" name="email" placeholder="Email Address" value = "<?php if (array_key_exists('email', $_POST)) {echo $_POST['email'];} ?>">
	<input type="password" name="password" placeholder="Password">
	<input type="submit" value="Sign Up">
</form>
