<?php
/**
 * middleware/tenant.php
 *
 * Include this at the very top of EVERY client-facing page/endpoint,
 * before anything else runs. It figures out which client this subdomain
 * belongs to and blocks the request cleanly if it can't.
 */
require_once dirname(__DIR__) . '/core/Database.php';
require_once dirname(__DIR__) . '/core/Tenant.php';

Tenant::resolve();