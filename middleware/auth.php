<?php
/**
 * middleware/auth.php
 *
 * Include this on any page that requires a logged-in user, AFTER
 * middleware/tenant.php (auth checks happen within the current client's
 * own database, so tenant must be resolved first).
 */
require_once dirname(__DIR__) . '/core/Auth.php';

if (!Auth::check()) {
    header('Location: /login.php');
    exit;
}