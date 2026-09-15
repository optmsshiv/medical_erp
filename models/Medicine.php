<?php
require_once __DIR__ . '/../core/Model.php';

class Medicine extends Model
{
    protected static string $table = 'medicines';
}

/*
 * That's it — every method below now works against the CURRENT
 * client's database automatically, resolved once per request by
 * Tenant::resolve() in middleware/tenant.php:
 *
 *   Medicine::all();
 *   Medicine::find(5);
 *   Medicine::where('name', 'LIKE', '%para%');
 *   Medicine::create(['name' => 'Paracetamol 500mg', 'unit' => 'strip']);
 *   Medicine::update(5, ['sale_rate' => 12.50]);
 *   Medicine::delete(5);
 *
 * Copy this pattern for Batch, Sale, Purchase, Customer, Supplier, etc. —
 * each is a 3-line file naming its table.
 */