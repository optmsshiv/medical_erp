<?php
session_start();
require dirname(__DIR__, 2) . '/middleware/tenant.php';
require dirname(__DIR__, 2) . '/core/Auth.php';
require dirname(__DIR__, 2) . '/core/Json.php';
require dirname(__DIR__, 2) . '/models/Customer.php';
require dirname(__DIR__, 2) . '/models/Medicine.php';
require dirname(__DIR__, 2) . '/models/Sale.php';
require dirname(__DIR__, 2) . '/models/SaleItem.php';

if (!Auth::check()) {
    Json::error('Not authenticated.', 401);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    Json::error('Method not allowed.', 405);
}

/**
 * Wholesale invoice — POST /api/v1/wholesale.php
 *
 * Hardening over the first draft:
 *  · GST-clean WS/YYYY-YY/#### series from a locked counter (no gaps from retail sales)
 *  · idempotent posting — a client UUID can never create a second invoice
 *  · expired batches hard-blocked; one line can consume MULTIPLE batches (FEFO split)
 *  · billed rate may never exceed the batch's own MRP
 *  · credit bills honour customers.credit_limit — over-cap needs client acknowledgement
 */

$input = json_decode(file_get_contents('php://input'), true) ?? [];

$customerId     = (int) ($input['customerId'] ?? 0);
$interstate     = !empty($input['interstate']);
$schemeDiscPct  = min(100, max(0, (float) ($input['schemeDiscPct'] ?? 0)));
$overallDiscPct = min(100, max(0, (float) ($input['overallDiscPct'] ?? 0)));
$paymentMode    = $input['paymentMode'] ?? 'cash';
$items          = $input['items'] ?? [];
$idempotencyKey = substr(trim((string) ($input['idempotencyKey'] ?? '')), 0, 64);
$creditAck      = !empty($input['creditAcknowledged']);

if (!$customerId) {
    Json::error('Select a valid customer.', 422);
}
if (!in_array($paymentMode, ['cash', 'upi', 'bank', 'credit'], true)) {
    $paymentMode = 'cash';
}
if (!is_array($items) || count($items) === 0) {
    Json::error('Add at least one item line.', 422);
}

$pdo = Tenant::db();

/** Small infra tables — counters never share sequences across tiers. */
try {
    $pdo->exec('CREATE TABLE IF NOT EXISTS invoice_series (
        fy VARCHAR(9) NOT NULL,
        prefix VARCHAR(12) NOT NULL,
        last_no INT UNSIGNED NOT NULL DEFAULT 0,
        PRIMARY KEY (fy, prefix)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');
} catch (Throwable $e) { /* usually exists */ }
try {
    $pdo->exec('CREATE TABLE IF NOT EXISTS api_idempotency (
        k VARCHAR(64) NOT NULL PRIMARY KEY,
        invoice_no VARCHAR(60) NOT NULL,
        created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');
} catch (Throwable $e) { /* usually exists */ }

function fyLabel(): string
{
    // Indian FY, April → March (e.g. 2026-27).
    $start = (int) date('n') >= 4 ? (int) date('Y') : (int) date('Y') - 1;
    return $start . '-' . substr((string) ($start + 1), -2);
}

$inr = fn (float $n) => number_format($n, 2, '.', '');

try {
    $pdo->beginTransaction();

    // ---------- Idempotency: refresh/retry must replay, never duplicate. ----------
    if ($idempotencyKey !== '') {
        $st = $pdo->prepare('SELECT invoice_no FROM api_idempotency WHERE k = :k FOR UPDATE');
        $st->execute(['k' => $idempotencyKey]);
        $prev = $st->fetch();
        if ($prev) {
            $pdo->commit();
            Json::ok([
                'id' => 0,
                'invoiceNo' => $prev['invoice_no'],
                'grandTotal' => 0,
                'balanceDue' => 0,
                'duplicate' => true,
            ]);
        }
    }

    // ---------- Party snapshot straight from the database, not client text. ----------
    $st = $pdo->prepare('SELECT id, name, business_name, type, gstin, dl_no, address, credit_limit, credit_days FROM customers WHERE id = :id LIMIT 1');
    $st->execute(['id' => $customerId]);
    $customer = $st->fetch();
    if (!$customer) {
        throw new RuntimeException('Select a valid customer.');
    }

    // ---------- Resolve lines → batch allocations (FEFO, expiry-safe, multi-batch). ----------
    $lines = [];
    $subtotal = 0.0;
    $lineDiscTotal = 0.0;
    $taxAtLineRate = 0.0;

    foreach ($items as $item) {
        $medId = (int) ($item['medId'] ?? 0);
        $qty   = (int) ($item['qty'] ?? 0);
        if (!$medId || $qty <= 0) {
            continue;
        }
        $medicine = Medicine::find($medId);
        if (!$medicine) {
            continue;
        }

        $freeQty  = max(0, (int) ($item['freeQty'] ?? 0));
        $rate     = max(0, (float) ($item['rate'] ?? $medicine['wholesale_rate']));
        $discPct  = min(100, max(0, (float) ($item['discPct'] ?? 0)));
        $gstPct   = (float) $medicine['gst_rate'];
        $batchNo  = strtoupper(trim((string) ($item['batch'] ?? '')));
        $need     = $qty + $freeQty;

        // Candidates: a typed batch always goes first (validated: exists + unexpired),
        // then remaining stock flows across other batches FEFO.
        $candidates = [];
        if ($batchNo !== '' && $batchNo !== 'AUTO') {
            $b1 = $pdo->prepare('SELECT * FROM batches WHERE medicine_id = :m AND batch_no = :b LIMIT 1 FOR UPDATE');
            $b1->execute(['m' => $medId, 'b' => $batchNo]);
            $found = $b1->fetch();
            if (!$found) {
                throw new RuntimeException("Batch {$batchNo} of {$medicine['name']} is not in stock — clear the batch column and FEFO picks it for you.");
            }
            if ($found['expiry_date'] < date('Y-m-d')) {
                throw new RuntimeException("Batch {$batchNo} of {$medicine['name']} expired on {$found['expiry_date']} — expired stock cannot be billed.");
            }
            $candidates[] = $found;
        }
        $st = $pdo->prepare(
            'SELECT * FROM batches WHERE medicine_id = :m AND (quantity - COALESCE(reserved, 0)) > 0 AND expiry_date >= CURDATE() ORDER BY expiry_date ASC FOR UPDATE'
        );
        $st->execute(['m' => $medId]);
        foreach ($st->fetchAll() as $b) {
            $used = false;
            foreach ($candidates as $c) {
                if ((int) $c['id'] === (int) $b['id']) { $used = true; break; }
            }
            if (!$used) $candidates[] = $b;
        }

        // Allocate greedily across the candidates.
        $left = $need;
        $takes = [];
        foreach ($candidates as $b) {
            if ($left <= 0) break;
            $avail = (int) $b['quantity'] - (int) $b['reserved'];
            if ($avail <= 0) continue;
            // Legal ceiling: the billed rate may not cross the batch's own MRP.
            $bm = (float) $b['mrp'];
            if ($bm > 0 && $rate > $bm + 0.001) {
                throw new RuntimeException("Rate ₹{$inr($rate)} crosses the ₹{$inr($bm)} printed MRP on batch {$b['batch_no']} ({$medicine['name']}). Lower the rate — MRP is a ceiling.");
            }
            $take = min($left, $avail);
            $takes[] = ['batch' => $b, 'take' => $take];
            $left -= $take;
        }
        if ($left > 0) {
            $inStock = $need - $left;
            throw new RuntimeException("{$medicine['name']}: only {$inStock} unit(s) of saleable (unexpired) stock across batches — you asked for {$need}.");
        }

        $lineGross  = $qty * $rate;
        $thisDisc   = $lineGross * ($discPct / 100);
        $lineAmount = $lineGross - $thisDisc;

        $subtotal      += $lineGross;
        $lineDiscTotal += $thisDisc;
        $taxAtLineRate += $lineAmount * ($gstPct / 100);

        $lines[] = [
            'medId' => $medId, 'name' => $medicine['name'], 'hsn' => (string) ($medicine['hsn'] ?? ''),
            'qty' => $qty, 'freeQty' => $freeQty, 'rate' => $rate, 'discPct' => $discPct,
            'gstPct' => $gstPct, 'amount' => $lineAmount, 'totalQty' => $qty + $freeQty, 'takes' => $takes,
        ];
    }

    if (count($lines) === 0) {
        throw new RuntimeException('Add at least one valid item line.');
    }

    // Two-stage bill-level discount, same formula as the UI: scheme first, then overall.
    $afterLine  = $subtotal - $lineDiscTotal;
    $schemeAmt  = $afterLine * ($schemeDiscPct / 100);
    $overallAmt = ($afterLine - $schemeAmt) * ($overallDiscPct / 100);
    $discount   = $lineDiscTotal + $schemeAmt + $overallAmt;
    $taxable    = $afterLine - $schemeAmt - $overallAmt;

    // Spread the bill-level discount proportionally across the tax too.
    $taxAfterDisc = $afterLine > 0 ? $taxAtLineRate * ($taxable / $afterLine) : 0;

    $rawGrand = $taxable + $taxAfterDisc;
    $grand    = round($rawGrand);
    $roundOff = $grand - $rawGrand;

    $cgst = $interstate ? 0.0 : $taxAfterDisc / 2;
    $sgst = $interstate ? 0.0 : $taxAfterDisc / 2;
    $igst = $interstate ? $taxAfterDisc : 0.0;

    $amountPaid = $paymentMode === 'credit' ? 0.0 : $grand;
    $balanceDue = max(0, $grand - $amountPaid);

    // ---------- Credit policy: a wholesale credit bill honours the account cap. ----------
    $dueBy = null;
    if ($paymentMode === 'credit') {
        $limit = (float) ($customer['credit_limit'] ?? 0);
        if ($limit > 0) {
            // Outstanding identical to the dues-ledger formula: bill balances, minus
            // only the part of customer payments above bill-level collections.
            $s1 = $pdo->prepare('SELECT COALESCE(SUM(balance_due),0) AS bal, COALESCE(SUM(amount_paid),0) AS paid FROM sales WHERE customer_id = :id');
            $s1->execute(['id' => $customerId]);
            $b = $s1->fetch() ?: ['bal' => 0, 'paid' => 0];
            $s2 = $pdo->prepare("SELECT COALESCE(SUM(amount),0) AS pays FROM payments WHERE party_type = 'customer' AND party_id = :id");
            $s2->execute(['id' => $customerId]);
            $pay = (float) ($s2->fetch()['pays'] ?? 0);
            $outstanding = max(0.0, (float) $b['bal'] - max(0.0, $pay - (float) $b['paid']));
            $projected   = $outstanding + $grand;
            if ($projected > $limit + 0.004 && !$creditAck) {
                throw new RuntimeException(sprintf(
                    'CREDIT_CAP|%s crosses its ₹%s cap: dues ₹%s + this bill ₹%s → ₹%s (%s over). Acknowledge to proceed knowingly.',
                    $customer['name'], $inr($limit), $inr($outstanding), $inr($grand), $inr($projected), $inr($projected - $limit)
                ));
            }
            if ($projected > $limit + 0.004) {
                // Acknowledged breach — still on record.
                try {
                    $pdo->exec('CREATE TABLE IF NOT EXISTS sale_audit (
                        id INT UNSIGNED NOT NULL AUTO_INCREMENT,
                        invoice_no VARCHAR(60) NOT NULL,
                        event VARCHAR(40) NOT NULL,
                        detail TEXT NULL,
                        created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                        PRIMARY KEY (id), KEY ix_event (event)
                    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');
                    $stmtA = $pdo->prepare('INSERT INTO sale_audit (invoice_no, event, detail) VALUES (:inv, :ev, :d)');
                    $stmtA->execute([
                        'inv' => 'PENDING',
                        'ev'  => 'WHOLESALE_OVER_LIMIT',
                        'd'   => sprintf('%s billed ₹%s on credit past its ₹%s cap; projected dues ₹%s.', $customer['name'], $inr($grand), $inr($limit), $inr($projected)),
                    ]);
                } catch (Throwable $e) { /* audit is best-effort */ }
            }
        }
        $days = (int) ($customer['credit_days'] ?? 0);
        if ($days > 0) {
            $dueBy = date('Y-m-d', strtotime("+{$days} days"));
        }
    }

    // ---------- Invoice number from the WS counter — contiguous FY series. ----------
    $fy = fyLabel();
    $stmtS = $pdo->prepare(
        'INSERT INTO invoice_series (fy, prefix, last_no) VALUES (:fy, "WS", LAST_INSERT_ID(1))
         ON DUPLICATE KEY UPDATE last_no = LAST_INSERT_ID(last_no + 1)'
    );
    $stmtS->execute(['fy' => $fy]);
    $seqNo = (int) $pdo->lastInsertId();
    $invoiceNo = 'WS/' . $fy . '/' . str_pad((string) $seqNo, 4, '0', STR_PAD_LEFT);

    $saleId = Sale::create([
        'customer_id'  => $customerId,
        'channel'      => 'wholesale',
        'invoice_no'   => $invoiceNo,
        'sale_date'    => date('Y-m-d'),
        'gstin'        => $customer['gstin'],
        'dl_no'        => $customer['dl_no'],
        'subtotal'     => $subtotal,
        'discount'     => $discount,
        'gst_amount'   => $taxAfterDisc,
        'cgst'         => $cgst,
        'sgst'         => $sgst,
        'igst'         => $igst,
        'round_off'    => $roundOff,
        'grand_total'  => $grand,
        'payment_mode' => $paymentMode,
        'amount_paid'  => $amountPaid,
        'balance_due'  => $balanceDue,
    ]);

    // ---------- Items (one row per allocation) + atomic stock decrement. ----------
    // Money follows PAID units; physical stock-out consumes paid + free together.
    $printLines = [];
    foreach ($lines as $line) {
        $amountLeft = $line['amount'];
        $paidLeft   = $line['qty'];
        $takeCount  = count($line['takes']);
        $i = 0;
        foreach ($line['takes'] as $t) {
            $b         = $t['batch'];
            $takeUnits = $t['take'];
            $paidPart  = min($paidLeft, $takeUnits);
            $freePart  = $takeUnits - $paidPart;
            $paidLeft -= $paidPart;

            $amt = ($i === $takeCount - 1)
                ? $amountLeft                                                        // rounding remainder lands on the final split
                : ($line['qty'] > 0 ? round($line['amount'] * ($paidPart / $line['qty']), 2) : 0.0);
            $amountLeft -= $amt;

            SaleItem::create([
                'sale_id'     => $saleId,
                'medicine_id' => $line['medId'],
                'batch_id'    => $b['id'],
                'qty'         => $paidPart,
                'free_qty'    => $freePart,
                'rate'        => $line['rate'],
                'disc_pct'    => $line['discPct'],
                'gst_pct'     => $line['gstPct'],
                'amount'      => $amt,
            ]);

            $stmt = $pdo->prepare(
                'UPDATE batches SET quantity = quantity - :qty WHERE id = :id AND (quantity - COALESCE(reserved, 0)) >= :qty2'
            );
            $stmt->execute(['qty' => $takeUnits, 'id' => $b['id'], 'qty2' => $takeUnits]);
            if ($stmt->rowCount() === 0) {
                throw new RuntimeException("Stock moved for batch {$b['batch_no']} while billing — please retry once.");
            }

            $printLines[] = [
                'medId'   => $line['medId'],
                'name'    => $line['name'],
                'hsn'     => $line['hsn'],
                'batchNo' => $b['batch_no'],
                'expiry'  => (string) $b['expiry_date'],
                'qty'     => $paidPart,
                'freeQty' => $freePart,
                'rate'    => $line['rate'],
                'discPct' => $line['discPct'],
                'gstPct'  => $line['gstPct'],
                'amount'  => $amt,
            ];
            $i++;
        }
    }

    if ($idempotencyKey !== '') {
        $stmtI = $pdo->prepare('INSERT INTO api_idempotency (k, invoice_no) VALUES (:k, :inv)');
        $stmtI->execute(['k' => $idempotencyKey, 'inv' => $invoiceNo]);
    }

    $pdo->commit();
} catch (Throwable $e) {
    $pdo->rollBack();
    Json::error($e->getMessage() ?: 'Could not save the invoice.', 422);
}

require dirname(__DIR__, 2) . '/core/Audit.php';
Audit::log('WHOLESALE_SALE', "{$invoiceNo} · ₹{$grand}");

Json::ok([
    'id'         => $saleId,
    'invoiceNo'  => $invoiceNo,
    'grandTotal' => $grand,
    'balanceDue' => $balanceDue,
    'dueBy'      => $dueBy,
    'lines'      => $printLines,
]);
