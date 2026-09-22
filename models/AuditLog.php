<?php
require_once __DIR__ . '/../core/Model.php';

class AuditLog extends Model
{
    protected static string $table = 'audit_logs';
}
