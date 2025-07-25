<?php
session_start();
session_destroy();
header("Location: pharmacy_login.php");
exit();
?> 