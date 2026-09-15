<?php
session_start();
require __DIR__ . '/core/Auth.php';

Auth::logout();

header('Location: /login.php');
exit;