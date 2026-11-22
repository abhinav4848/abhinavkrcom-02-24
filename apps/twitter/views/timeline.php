<div class="container mainContainer">

	<div class="row">
		<div class="col-8">
			<h2>Tweets for You</h2>
			<?php displayTweets('isFollowing'); ?>
		</div>
		<div class="col-4">
			<h2>Options</h2>
			<?php displaySearch(); 
			echo '<hr />';
			displayTweetBox();?>
		</div>
	</div>

</div>

