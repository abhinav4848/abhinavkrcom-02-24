<?php
    session_start();
    if (array_key_exists("content", $_POST)) {
        include("connection.php");
        $query = "UPDATE `secdiary_users` SET `diary` = '".project_value($link, $_POST['content'])."' WHERE id = ".project_value($link, $_SESSION['id'])." LIMIT 1";  
        /*if(project_query($link, $query)) {
        	echo "success";
        } else {
        	echo "fail";
        }*/
        project_query($link, $query);
    }
?>