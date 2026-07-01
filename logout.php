<?php
session_start();
session_destroy();
header('Location: index.php?bye=1');
exit;
