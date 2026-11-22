<?php
session_start();

//doing this so purposeful malicious edit to the id parameter being passed through a form doesn't change data for the new id value that may have been inserted.
if ($_SESSION['permission'] == 'admin' or ($_SESSION['permission'] == 'supervisor' and $_SESSION['id'] == $_GET['id'])) {
    if (array_key_exists("id", $_GET) or array_key_exists("username", $_GET)) {
        include('connect-db.php');
        if (array_key_exists("id", $_GET)) {
            $query = "SELECT * FROM `cutabove_council` WHERE council_id = '".project_value($link, $_GET['id'])."' LIMIT 1";
        } elseif (array_key_exists("username", $_GET)) {
            $query = "SELECT * FROM `cutabove_council` WHERE username = '".project_value($link, $_GET['username'])."' LIMIT 1";
        }
        $result = project_query($link, $query);
        if (project_num_rows($result)!=0) {
            $row = project_fetch_array($result);
        } else {
            echo 'The council member doesn\'t exist. Go to <a href="all-council.php">Admin mode</a> to click on a user for details.';
            die();
        }
    } else {
        echo 'You didn\'t specify which id to fetch. You are admin. Use the <a href="all-council.php">Admin mode</a> to click on a user for details.';
        die();
    }
} else {
    header("Location: index.php?redirect=".$_SERVER['REQUEST_URI']);
}
if (array_key_exists("submit", $_POST)) {
    //echo '<div id="tablediv">';
    //print_r($_POST);
    //echo '</div>';
    $successfullyCompleted = '';
    if ($_SESSION['id'] == $row['council_id']) {
        if ($_POST['password']!= $row['password']) {
            $query = "UPDATE `cutabove_council` 
			SET `password` = '".project_value($link, $_POST['password'])."'
			WHERE `cutabove_council`.`council_id` = '".$row['council_id']."' LIMIT 1";

            if (project_query($link, $query)) {
                $successfullyCompleted = 1;
            }
        }
        if ($_POST['name']!= $row['name']) {
            $query = "UPDATE `cutabove_council` 
			SET `name` = '".project_value($link, $_POST['name'])."'
			WHERE `cutabove_council`.`council_id` = '".$row['council_id']."' LIMIT 1";

            if (project_query($link, $query)) {
                $successfullyCompleted = 1;
            }
        }
        $_SESSION['theme'] = project_value($link, $_POST['theme']);
    }
    if ($_SESSION['permission']== 'admin') {
        $query = "UPDATE `cutabove_council` 
		SET `permission` = '".project_value($link, $_POST['permission'])."'
		WHERE `cutabove_council`.`council_id` = '".$row['council_id']."' LIMIT 1";

        if (project_query($link, $query)) {
            $successfullyCompleted = 1;
        }
    }
    $query = "UPDATE `cutabove_council` 
	SET `theme` = '".project_value($link, $_POST['theme'])."', 
	`phone` = '".project_value($link, $_POST['phone'])."', 
	`phone_whatsapp` = '".project_value($link, $_POST['phone_whatsapp'])."', 
	`email` = '".project_value($link, $_POST['email'])."', 
	`comments` = '".project_value($link, $row['comments'])." <br /><small>[". date('d-m-Y h:i:sa') ."] ".project_value($link, $_SESSION['name'])." (ID# ".project_value($link, $_SESSION['id'])."):</small> ".project_value($link, $_POST['comments'])."',  
	`edited_by` = '".$_SESSION['name']."', 
	`last_edited_at` = '". date('d-m-Y h:i:sa') ."'
	WHERE `cutabove_council`.`council_id` = '".$row['council_id']."' LIMIT 1";

    if (project_query($link, $query)) {
        $successfullyCompleted = 1;
    }

    if ($successfullyCompleted == 1) {
        //success
        header("Location: edit-council.php?id=".$row['council_id']."&successEdit=1");
    } else {
        echo '<div id="tablediv">';
        echo "failed to edit.";
        echo '</div>';
    }
}
?>

<!doctype html>
<html lang="en">

<head>
    <!-- Required meta tags -->
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">

    <?php include 'header.php'; //css-theme detector?>

    <title>Edit Council Member</title>
</head>

<body>
    <?php include 'navbar.php'; ?>

    <div id="tablediv" class="container-fluid">
        <form method="post">
            <?php
              //display general data
            echo '<table class="table table-responsive-sm">';
            echo '<thead class="thead-dark"><tr> <th>Property</th> <th>Value</th> </tr></thead>';
            echo '<tbody>';
            echo '<tr><th scope="row">ID</th><td>'. $row['council_id'] .'</td></tr>';
            echo '<tr><th scope="row">Username</th><td>' . $row['username'] . '</td></tr>';
            if ($_SESSION['id'] == $row['council_id']) {
                echo '<tr><th scope="row">Password</th><td><input type="text" class="form-control" name="password" value="' . $row['password'] . '"></td></tr>';
                echo '<tr><th scope="row">Name</th><td><input type="text" class="form-control" name="name" value="' . $row['name'] . '"></td></tr>';
            } else {
                echo '<tr><th scope="row">password</th><td>You don\'t have access to the password.</td></tr>';
                echo '<tr><th scope="row">Name</th><td>' . $row['name'] . '</td></tr>';
            }
            
            if ($_SESSION['permission'] == 'admin') {
                echo '<tr><th scope="row">Permission</th><td><select class="form-control" name="permission" id="permission"><option ';
                if ($row['permission'] == 'admin') {
                    echo 'selected';
                }
                echo' value="admin">Admin</option><option ';
                if ($row['permission'] == 'supervisor') {
                    echo 'selected';
                }
                echo' value="supervisor">Supervisor</option><option ';
                if ($row['permission'] == 'retired') {
                    echo 'selected';
                }
                echo' value="retired">Retired</option></select></td></tr>';
            } else {
                echo '<tr><th scope="row">Permission</th><td>Supervisor</td></tr>';
            }

            //theme select
            echo '<tr><th scope="row">Theme</th><td><select class="form-control" name="theme">
            <option '; if ($row['theme'] == 'cyborg') {
                echo 'selected';
            } echo' value="cyborg">Cyborg</option>
            <option '; if ($row['theme'] == 'darkly') {
                echo 'selected';
            } echo' value="darkly">Darkly</option>
            <option '; if ($row['theme'] == 'litera') {
                echo 'selected';
            } echo' value="litera">Litera</option>
            <option '; if ($row['theme'] == 'normal') {
                echo 'selected';
            } echo' value="normal">Normal</option>
            <option '; if ($row['theme'] == 'no-one') {
                echo 'selected';
            } echo' value="no-one">No-one</option>
            </select></td></tr>';

            echo '<tr><th scope="row">Phone (Call)</th><td><input type="number" class="form-control" name="phone" value="' . $row['phone'] . '"></td></tr>';
            echo '<tr><th scope="row">Phone (Whatsapp)</th><td><input type="number" class="form-control" name="phone_whatsapp" value="' . $row['phone_whatsapp'] . '"></td></tr>';
            echo '<tr><th scope="row">E-mail</th><td><input type="email" class="form-control" name="email" value="' . $row['email'] . '"></td></tr>';
            if ($_SESSION['permission'] == 'admin') {
                echo '<tr><th scope="row">Comments</th><td><button type="button" id="show-hide" class="btn btn-outline-'.$_SESSION['colour'].'">Toggle Comments</button><div id="comments">' . $row['comments'] . '</div></td></tr>';
                echo '<tr><th scope="row">Comments (Only admins can view)</th><td><textarea class="form-control" name="comments" placeholder="The new text you add will be appended to the previous pieces of text" onKeyDown="limitText(this.form.comments,this.form.countdown,280);" 
				onKeyUp="limitText(this.form.comments,this.form.countdown,280);"></textarea>';
                echo '<font size="1">You have <input readonly type="text" name="countdown" size="3" value="280"> characters left. Same as Twitter</font></td></tr>';
            }
            echo '</tbody></table>';
            ?>
            <button type="submit" name="submit" class="btn btn-<?php echo $_SESSION['colour'];?>">Submit Page</button>
        </form>
    </div>
    <br>

    <!-- Optional JavaScript -->
    <!-- jQuery first, then Popper.js, then Bootstrap JS -->
    <!--using non slim version of jquery-->
    <script src="https://code.jquery.com/jquery-3.3.1.min.js"
        integrity="sha256-FgpCb/KJQlLNfOu91ta32o/NMZxltwRo8QtmkMRdAu8=" crossorigin="anonymous"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/popper.js/1.12.9/umd/popper.min.js"
        integrity="sha384-ApNbgh9B+Y1QKtv3Rn7W3mgPxhU9K/ScQsAP7hUibX39j7fakFPskvXusvfa0b4Q" crossorigin="anonymous">
    </script>
    <script src="https://maxcdn.bootstrapcdn.com/bootstrap/4.0.0/js/bootstrap.min.js"
        integrity="sha384-JZR6Spejh4U02d8jOt6vLEHfe/JQGiRRSQQxSfFWpi1MquVdAyjUar5+76PVCmYl" crossorigin="anonymous">
    </script>

    <script type="text/javascript">
    function limitText(limitField, limitCount, limitNum) {
        if (limitField.value.length > limitNum) {
            limitField.value = limitField.value.substring(0, limitNum);
        } else {
            limitCount.value = limitNum - limitField.value.length;
        }
    }

    $("#show-hide").click(function() {
        $("#comments").toggle();
        $("#show-hide").button('toggle');
    });
    </script>
</body>

</html>