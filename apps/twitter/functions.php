<?php
session_start();
include 'views/connect-db.php';

if (isset($_GET['function']) && $_GET['function'] == "logout") {
	session_unset();
}

function time_since($since) {
	$chunks = array(
		array(60 * 60 * 24 * 365 , 'year'),
		array(60 * 60 * 24 * 30 , 'month'),
		array(60 * 60 * 24 * 7, 'week'),
		array(60 * 60 * 24 , 'day'),
		array(60 * 60 , 'hour'),
		array(60 , 'min'),
		array(1 , 'sec')
	);

	for ($i = 0, $j = count($chunks); $i < $j; $i++) {
		$seconds = $chunks[$i][0];
		$name = $chunks[$i][1];
		if (($count = floor($since / $seconds)) != 0) {
			break;
		}
	}

	$print = ($count == 1) ? '1 '.$name : "$count {$name}s";
	return $print;
}

function displayTweets($type) {
	global $link;
	if ($type == 'public') {
		$whereClause = '';
	} else if ($type == 'isFollowing') {
		$query= "SELECT * FROM `twitter_isFollowing` WHERE follower = '".project_value($link, $_SESSION['id'])."'";
		$result = project_query($link, $query);
		$whereClause = '';
		while ($row= project_fetch_assoc($result)) {
			if ($whereClause == '') {
				$whereClause = " WHERE";
			} else {
				$whereClause.= " OR";
			}
			$whereClause.= " userid =".$row['isFollowing'];
		}
	} else if ($type == 'yourtweets') {
		$whereClause = " WHERE userid =".project_value($link, $_SESSION['id']);
	} else if ($type == 'search') {
		echo '<p>Showing results for: '.project_value($link, $_GET['q']).'</p>';
		$whereClause = " WHERE tweet LIKE '%".project_value($link, $_GET['q'])."%'";
	} else if (is_numeric($type)) {
		$userQuery = "SELECT * FROM `twitter_users` WHERE id=".project_value($link, $type)." LIMIT 1";
		$userQueryResult = project_query($link, $userQuery);
		$user = project_fetch_assoc($userQueryResult);

		echo '<h2>'.$user['email'].'\'s Tweets</h2>';

		$whereClause = " WHERE userid =".project_value($link, $type);
	}
	$query = "SELECT * FROM `twitter_tweets`".$whereClause." ORDER BY `datetime` DESC LIMIT 100";
	$result = project_query($link, $query);
	if (project_num_rows($result) == 0) { 
		echo "There are no tweets to display";
	} else {
		while ($row = project_fetch_assoc($result)) {
			$userQuery = "SELECT * FROM `twitter_users` WHERE id=".project_value($link, $row['userid'])." LIMIT 1";
			$userQueryResult = project_query($link, $userQuery);
			$user = project_fetch_assoc($userQueryResult);
			echo '<div class="tweet"><p><a href="?page=publicprofiles&userid='.$user['id'].'">'.$user['email'].'</a> <span class="time">'.time_since(time()-strtotime($row['datetime'])).' ago</span>:</p>';
			echo '<p>'.$row['tweet'].'</p>';
			echo '<p><a class="toggleFollow" data-userId='.$row['userid'].'>';
			$isFollowingQuery= "SELECT * FROM `twitter_isFollowing` WHERE follower = '".project_value($link, $_SESSION['id'] ?? 0)."' AND isFollowing = '".project_value($link, $row['userid'])."' LIMIT 1";
			$isFollowingResult = project_query($link, $isFollowingQuery);
			if (project_num_rows($isFollowingResult) > 0) {
				echo "UnFollow";
			}
			else {
				echo "Follow";
			}
			echo '</a></p></div>';
		}
	}
}

function displaySearch() {
	echo '<form class="form-inline" autocomplete="off">
	<div class="form-group">
	<input type="hidden" name="page" value="search">
	<label for="search" class="sr-only">Search</label>
	<input type="text" name="q" class="form-control" id="search" placeholder="Search"';
	if (isset($_GET['q']) && $_GET['q']!='') {
		echo 'value="'.$_GET['q'].'"';
	}
	echo '">
	</div>
	<button type="submit" class="btn btn-primary">Search Tweets</button>
	</form>';
}

function displayTweetBox() {
	if (isset($_SESSION['id']) && $_SESSION['id'] > 0) {
		echo '<div id="tweetSuccess" class="alert alert-success">Your tweet was successfully posted. <a href="">Reload</a>.</div>
		<div id="tweetFail" class="alert alert-danger"></div>
		<div>
		<div class="form-group">
		<label for="tweetcontent">Post New Tweet</label>
		<textarea class="form-control" id="tweetContent" rows="3" placeholder="Enter your tweet"></textarea>
		</div>
		<button id="postTweetButton" class="btn btn-primary">Post Tweet</button>
		</div>';
	}
}
function displayUsers() {
	global $link;
	$query = "SELECT * FROM `twitter_users` LIMIT 10";
	$result = project_query($link, $query);
	if (project_num_rows($result) == 0) { 
		echo "There are no users to display";
	} else {
		while ($row = project_fetch_assoc($result)) {
			echo '<p><a href="?page=publicprofiles&userid='.$row['id'].'">'.$row['email'].'</a></p>';
		}
	}
}
?>
