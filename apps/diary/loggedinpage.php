<?php
session_start();
$diaryContent = "";
if (array_key_exists("id", $_COOKIE) && $_COOKIE['id']) {
	$_SESSION['id'] = $_COOKIE['id'];
}
if (array_key_exists("id", $_SESSION)) {
	//echo "<p>Logged in! <a href='index.php?logout=1'>Log Out</a></p>";
	include 'connection.php';
	$query= "SELECT diary FROM `secdiary_users` WHERE id = ".project_value($link, $_SESSION['id'])." LIMIT 1"; 
	$row = project_fetch_array(project_query($link, $query));
	$diaryContent = $row['diary'];
} else {
	header("Location: index.php");
}

include 'header.php';
?>

<nav class="navbar navbar-expand-lg navbar-light bg-light">
	<a class="navbar-brand" href="#">Secret Diary</a>
	<button class="navbar-toggler" type="button" data-toggle="collapse" data-target="#navbarSupportedContent">
		<span class="navbar-toggler-icon"></span>
	</button>

	<div class="collapse navbar-collapse" id="navbarSupportedContent">
		<div class="form-inline my-2 my-lg-0">
			<a href="index.php?logout=1"><button class="btn btn-outline-success my-2 my-sm-0" type="submit">Logout</button></a>
		</div>
	</div>
</nav>

<div class="container-fluid" id="containerLoggedInPage">
	<textarea class="form-control" id="diary"><?php echo $diaryContent; ?></textarea>
</div>

<?php include 'footer.php'; ?>

