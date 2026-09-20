<?php
session_start();
require dirname(__DIR__, 3) . '/core/Auth.php';
require dirname(__DIR__, 3) . '/core/Json.php';

Auth::logout();

Json::ok();