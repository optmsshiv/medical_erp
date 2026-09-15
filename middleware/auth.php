<?php
/**
 * Stopgap protection for the super-admin portal.
 * Once middleware/auth.php + a real roles system exists, replace this
 * with a proper "is logged in AND role = super_admin" check.
 */
session_start();

if (empty($_SESSION['super_admin'])) {
    header('Location: /super-admin/login.php');
    exit;
}