<?php 
	session_start();
	session_destroy();
	unset($_SESSION["email"]);

 ?>
 <script type="text/javascript">
 	window.location="../index.php";
 </script>