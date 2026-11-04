<?php
session_start();
session_destroy();

// Redirect to the public index page (go up one directory level)
header("Location: ../index.php");
exit();
?>