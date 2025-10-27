<?php
session_start();
session_destroy();
header('Location: index.php?message=' . urlencode('You have been logged out successfully') . '&type=success');
exit;
?>
