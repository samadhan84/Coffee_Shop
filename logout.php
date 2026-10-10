<?php
require_once __DIR__ . '/includes/bootstrap.php';

$_SESSION = [];
session_regenerate_id(true);
redirect('login.php');
