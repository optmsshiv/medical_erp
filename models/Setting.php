<?php
require_once __DIR__ . '/../core/Model.php';

class Setting extends Model
{
    protected static string $table = 'settings';
    protected static string $primaryKey = 'setting_key';
}
