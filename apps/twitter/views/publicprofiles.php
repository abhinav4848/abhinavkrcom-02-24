<div class="container mainContainer">

  <div class="row">
    <div class="col-8">
      <?php if ($_GET['userid']) { 
        displayTweets($_GET['userid']);
      } else { 
        echo '<h2>Active Users</h2>';
        displayUsers();
      } ?>
    </div>
    <div class="col-4">
      <h2>Options</h2>
      <?php displaySearch(); 
      echo '<hr />';
      displayTweetBox();?>
    </div>
  </div>

</div>

