    <footer class="footer">
    	<div class="container">
    		<!--container causes the text to shift a little right-->
    		<span class="text-muted">Twitter Clone, &copy;2018</span>
    	</div>
    </footer>
    <!-- Optional JavaScript -->
    <!-- jQuery first, then Popper.js, then Bootstrap JS -->
    <script src="https://code.jquery.com/jquery-3.3.1.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/popper.js/1.14.3/umd/popper.min.js" integrity="sha384-ZMP7rVo3mIykV+2+9J3UJ46jBk0WLaUAdn689aCwoqbBJiSnjAK/l8WvCWPIPm49" crossorigin="anonymous"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.1.3/js/bootstrap.min.js" integrity="sha384-ChfqqxuZUCnJSK3+MXmPNIyE6ZbWh2IMqE241rYiqJxyMiZ6OW/JmZQ5stwEULTy" crossorigin="anonymous"></script>

    <!-- Modal -->
    <div class="modal fade" id="signup-in" tabindex="-1" role="dialog" aria-labelledby="signup-in" aria-hidden="true">
    	<div class="modal-dialog" role="document">
    		<div class="modal-content">
    			<div class="modal-header">
    				<h5 class="modal-title" id="loginModalTitle">Login</h5>
    				<button type="button" class="close" data-dismiss="modal" aria-label="Close">
    					<span aria-hidden="true">&times;</span>
    				</button>
    			</div>
    			<div class="modal-body">
    				<div class="alert alert-danger" id="loginAlert"></div>
    				<form>
    					<div class="form-group">
    						<input type="hidden" id="loginActive" name="loginactive" value="1">
    						<label for="email">Email address</label>
    						<input type="email" class="form-control" id="email" aria-describedby="emailHelp" placeholder="Enter email">
    						<small id="emailHelp" class="form-text text-muted">We'll never share your email with anyone else.</small>
    					</div>
    					<div class="form-group">
    						<label for="password">Password</label>
    						<input type="password" class="form-control" id="password" placeholder="Password">
    					</div>
    				</form>
    			</div>
    			<div class="modal-footer">
    				<a id="toggleLogin">Signup</a>
    				<button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
    				<button type="button" class="btn btn-primary" id="loginSignupButton">Login</button>
    			</div>
    		</div>
    	</div>
    </div>
    <script type="text/javascript">
    	$("#toggleLogin").click(function(){
    		if ($("#loginActive").val() == "1") {

    			$("#loginActive").val("0");
    			$("#loginModalTitle").html("Sign Up");
    			$("#toggleLogin").html("Log In");
    			$("#loginSignupButton").html("Sign Up");

    		} else {

    			$("#loginActive").val("1");
    			$("#loginModalTitle").html("Log In");
    			$("#toggleLogin").html("Sign Up");
    			$("#loginSignupButton").html("Log In");

    		}
    	})

    	$("#loginSignupButton").click(function(){

    		$.ajax({
    			type: "POST",
    			url: "actions.php?action=loginSignup",
    			data: "email="+ $("#email").val() + "&password="+$("#password").val() + "&loginActive="+$("#loginActive").val(),
    			success: function(result) {
    				if (result== '1') {
    					window.location.assign("?success=1");
    				} else {
    					$("#loginAlert").html(result).show();
    				}
    			}
    		})
    	})

    	$(".toggleFollow").click(function(){
    		//alert($(this).attr("data-userId"));
    		//data- attributes should not contain uppercase letters but chrome handles it well by autoconverting to lowercase
    		var id= $(this).attr("data-userId");
    		$.ajax({
    			type: "POST",
    			url: "actions.php?action=toggleFollow",
    			data: "userId="+ id,
    			success: function(result) {
    				if (result == "1") {
    					$("a[data-userId='"+id+"']").html("Follow");
    				} else if (result == "2"){
    					$("a[data-userId='"+id+"']").html("UnFollow");
    				}
    			}
    		})
    	})

    	$("#postTweetButton").click(function() {
    		var tweetContent = $("#tweetContent").val();
    		$.ajax({
    			type: "POST",
    			url: "actions.php?action=postTweet",
    			data: "tweetContent="+ tweetContent,
    			success: function(result) {
    				if (result == '1') {
    					$("#tweetSuccess").show();
    					$("#tweetFail").hide();
    				} else if (result!='') {
    					$("#tweetFail").html(result).show();
    					$("#tweetSuccess").hide();
    				}
    			}
    		})
    	})
    </script>
</body>
</html>