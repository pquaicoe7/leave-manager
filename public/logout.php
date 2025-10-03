<?php
require __DIR__ . '/../config.php';
session_destroy();
header('Location: /eban-leave/public/index.php');
