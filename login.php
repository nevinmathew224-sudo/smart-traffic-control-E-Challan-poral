<?php
$vno = !empty($_GET['vno']) ? '?vno=' . urlencode($_GET['vno']) : '';
header("Location: user_login.php" . $vno);
exit();
