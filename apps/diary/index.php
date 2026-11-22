<?php
session_start();

$error = "";
if (array_key_exists("logout", $_GET)) {
	unset($_SESSION);
	setcookie("id", "", time()-60*60);
	$_COOKIE["id"] = "";
} elseif ((array_key_exists("id", $_SESSION) AND $_SESSION['id']) OR (array_key_exists("id", $_COOKIE) AND $_COOKIE['id'])) {
	//if there is a session id or cookie id indicating person is already logged in, take them to loginpage.
	header("Location: loggedinpage.php");
}

if (array_key_exists("submit", $_POST)) {
include 'connection.php';

	//gathering errors if any
	if (!$_POST['email']) {
		$error .= "An email address is needed. <br />";
	}
	if (!$_POST['password']) {
		$error .= "A password is needed. <br />";
	}
	if ($error !="") {
		$error = "<p><strong>There were errors in you form:</strong></p>".$error;
	} else {
		//print_r($_POST);
		//if there is no error, check if sign up or login.
		if ($_POST['signUp']=='1') {
			$query = "SELECT id FROM `secdiary_users` WHERE email = '".project_value($link, $_POST['email'])."' LIMIT 1";
			$result = project_query($link, $query);
			//check if the email address is taken, else sign them up.
			if (project_num_rows($result) > 0) {
				$error= "<p><strong>There were errors in you form:</strong></p>The email address is taken.";
			} else {
				$hash = password_hash($_POST['password'], PASSWORD_DEFAULT);
				$query = "INSERT INTO `secdiary_users` (`email`, `password`, `diary`) VALUES ('".project_value($link, $_POST['email'])."','".project_value($link, $hash)."','')";
				if (!project_query($link, $query)) {
					$error = "<p>Could not sign you up, try later</p>";
				} else {
					$_SESSION['id'] = project_insert_id($link);
				//store logged in cookie if checkbox was checked.
					if ($_POST['stayLoggedIn'] == '1') {
						setcookie("id", project_insert_id($link), time()+60*60*24*365);
					}
				//now that sign up is done, take them to logged in page
					header("Location: loggedinpage.php");
				}
			}
		} else {
			$query = "SELECT * FROM `secdiary_users` WHERE email = '".project_value($link, $_POST['email'])."'";
			$result = project_query($link, $query);
			$row = project_fetch_array($result);
			if (isset($row)) {
				$hashedPassword = md5(md5($row['id']).$_POST['password']);
				if (password_verify($_POST['password'], $row['password']) || hash_equals($row['password'], $hashedPassword)) {
					$_SESSION['id'] = $row['id'];
					if ($_POST['stayLoggedIn'] == '1') {
						setcookie("id", $row['id'], time()+60*60*24*365);
					}
					header("Location: loggedinpage.php");
				} else {
					$error= "That password is incorrect.";
				}
			} else {
				$error= "That email doesn't exist.";
			}
			if ($error !="") {
				$error = "<p><strong>There were errors in you form:</strong></p>".$error;
			}
		}
	}
}
?>


<?php include 'header.php'; ?>
<div class="container" id="homePageContainer">
	<h1>Secret Diary</h1>
	<div id="error"><?php if($error!="") { echo '<div class="alert alert-danger" role="alert">'.$error.'</div>';} ?></div>
	<form method="post" id="signUpForm">
		<div class="form-group">
			<input type="email" class="form-control" name="email" placeholder="Your Email">
		</div>
		<div class="form-group">
			<input type="password" class="form-control" name="password" placeholder="Password">
		</div>
		<div class="form-check">
			<input type="checkbox" class="form-check-input" name="stayLoggedIn" id="stayLoggedIn1" value="1">
			<label class="form-check-label" for="stayLoggedIn1">Stay Logged In</label>
		</div>
		<input type="hidden" name="signUp" value="1">
		<button type="submit" name="submit" class="btn btn-primary">Sign Up</button>
		<p><a class="toggleForms">Log in</a></p>
	</form>
	<form method="post" id="logInForm">
		<div class="form-group">
			<input type="email" class="form-control" name="email" placeholder="Your Email">
		</div>
		<div class="form-group">
			<input type="password" class="form-control" name="password" placeholder="Password">
		</div>
		<div class="form-check">
			<input type="checkbox" class="form-check-input" name="stayLoggedIn" value="1" id="stayLoggedIn2">
			<label class="form-check-label" for="stayLoggedIn2">Stay Logged In</label>
		</div>
		<input type="hidden" name="signUp" value="0">
		<button type="submit" name="submit" class="btn btn-primary">Log In</button>
		<p><a class="toggleForms">Sign Up</a></p>
	</form>
</div>
<?php include 'footer.php'; ?>
