<?php
include 'functions.php';

if ($_GET['action'] == "loginSignup") {
	$error = '';
	if (!$_POST['email']) {
		$error.= 'Enter an email';
	} else if (!$_POST['password']) {
		$error.= 'Enter a password';
	} else if (!filter_var($_POST['email'], FILTER_VALIDATE_EMAIL)) {
		$error.= 'Enter a valid email';
	} else if ($_POST['loginActive'] == 0) {
		//checking if the e-mail id being used to sign up is already taken
		$query= "SELECT * FROM `twitter_users` WHERE email = '".project_value($link, $_POST['email'])."' LIMIT 1";
		$result = project_query($link, $query);
		if (project_num_rows($result)>0) {
			$error.= "That email address is already taken";
		} else {
			$hash = password_hash($_POST['password'], PASSWORD_DEFAULT);
			$query = "INSERT INTO `twitter_users` (`email`, `password`) 
			VALUES (
			'".project_value($link, $_POST['email'])."', 
			'".project_value($link, $hash)."');";

			if(project_query($link, $query)) {
				$_SESSION['id'] = project_insert_id($link);
				echo 1;
			} else {
				$error.= 'Couldn\'t sign you up.';
			}
		}
	} else {
		//attept logging in
		$query= "SELECT * FROM `twitter_users` WHERE email = '".project_value($link, $_POST['email'])."' LIMIT 1";
		$result = project_query($link, $query);
		$row= project_fetch_assoc($result);
		if ($row && (password_verify($_POST['password'], $row['password'])
				|| hash_equals($row['password'], md5(md5($row['id']).$_POST['password'])))) {
			//Logged in
			echo 1;
			$_SESSION['id'] = $row['id'];
		} else {
			$error.= "Could not find the id pass combo";
		}
	}

	if ($error !='') {
		echo $error;
		exit();
	}
}

if (isset($_GET['action']) && $_GET['action'] == 'toggleFollow') {
	$query= "SELECT * FROM `twitter_isFollowing` WHERE follower = '".project_value($link, $_SESSION['id'])."' AND isFollowing = '".project_value($link, $_POST['userId'])."' LIMIT 1";
	$result = project_query($link, $query);
	if (project_num_rows($result)>0) {
		$row= project_fetch_assoc($result);
		project_query($link, "DELETE FROM `twitter_isFollowing` WHERE id=".project_value($link, $row['id'])." LIMIT 1");
		echo 1;
	} else {
		project_query($link, "INSERT INTO `twitter_isFollowing` (`follower`, `isFollowing`) VALUES(
			'".project_value($link, $_SESSION['id'])."',
			'".project_value($link, $_POST['userId'])."')");
		echo 2;
	}
}

if (isset($_GET['action']) && $_GET['action'] == 'postTweet') {
	if (!$_POST['tweetContent']) {
		echo "Your tweet is empty";
	} else if (strlen($_POST['tweetContent']) > 140) {
		echo 'Your tweet is '.(strlen($_POST['tweetContent'])-140).' characters too long!';
	} else {
		project_query($link, "INSERT INTO `twitter_tweets` (`tweet`, `userid`, `datetime`) VALUES(
			'".project_value($link, $_POST['tweetContent'])."',
			'".project_value($link, $_SESSION['id'])."',
			NOW())");
		echo 1;
	}
}
?>
