<?php
session_start();

// destroy session
session_unset();
session_destroy();

// redirect to single home page
header("Location: index.php");
exit();
?>