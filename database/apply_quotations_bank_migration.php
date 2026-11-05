<?php
/**
 * database/apply_quotations_bank_migration.php
 * Adds Remittance Bank Details columns to the quotations table.
 */
require_once __DIR__ . '/../core/config.php';

if (php_sapi_name() !== 'cli') {
    header('Content-Type: text/plain; charset=utf-8');
}

echo "========================================================\n";
echo " Graphicafix: Quotations Remittance Bank Details Migration\n";
echo "========================================================\n\n";

if (!isset($conn) || !($conn instanceof mysqli)) {
    echo "[ERROR] Database connection failed.\n";
    exit(1);
}

$existing_cols = array_column($conn->query("SHOW COLUMNS FROM `quotations`")->fetch_all(MYSQLI_ASSOC), 'Field');

$columns_to_add = [
    'bank_name'       => "VARCHAR(150) NULL DEFAULT 'Meezan Bank Ltd'",
    'bank_branch'     => "VARCHAR(150) NULL DEFAULT 'Sector F-11 Branch, Islamabad'",
    'account_name'    => "VARCHAR(150) NULL DEFAULT 'Graphica Fix'",
    'account_number'  => "VARCHAR(100) NULL DEFAULT 'PK09MEZN000810011299029'",
    'swift_code'      => "VARCHAR(50) NULL DEFAULT 'MEZNPKKA'",
    'payment_methods' => "VARCHAR(255) NULL DEFAULT 'Bank Transfer, JazzCash, Safepay'"
];

foreach ($columns_to_add as $col => $definition) {
    if (!in_array($col, $existing_cols)) {
        $sql = "ALTER TABLE `quotations` ADD COLUMN `{$col}` {$definition}";
        if ($conn->query($sql)) {
            echo "   [ADDED] quotations.{$col}\n";
        } else {
            echo "   [ERROR] Failed to add quotations.{$col}: " . $conn->error . "\n";
        }
    } else {
        echo "   [EXISTS] quotations.{$col}\n";
    }
}

echo "\nMigration finished successfully!\n";
