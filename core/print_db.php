<?php
/**
 * core/print_db.php
 * Database schema initialization, migrations, and shared utilities
 * for Graphicafix Multi-Vendor Print Procurement Engine.
 */

require_once __DIR__ . '/config.php';

function init_print_database(mysqli $conn) {
    // 1. print_vendors table
    $conn->query("CREATE TABLE IF NOT EXISTS `print_vendors` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `company_name` VARCHAR(150) NOT NULL,
        `contact_person` VARCHAR(100) NOT NULL,
        `phone` VARCHAR(30) NOT NULL,
        `whatsapp` VARCHAR(30) NOT NULL,
        `email` VARCHAR(100) DEFAULT NULL,
        `city` VARCHAR(80) NOT NULL DEFAULT 'Karachi',
        `address` TEXT DEFAULT NULL,
        `specialties` TEXT DEFAULT NULL COMMENT 'Comma-separated tags: offset, digital, packaging, large_format',
        `price_refresh_frequency_days` INT NOT NULL DEFAULT 1 COMMENT '1 = Daily, 3 = 3 Days, 7 = Weekly, etc.',
        `last_price_refresh_at` DATETIME DEFAULT NULL,
        `rating` DECIMAL(3, 2) NOT NULL DEFAULT 5.00,
        `completed_orders_count` INT NOT NULL DEFAULT 0,
        `status` ENUM('active', 'inactive', 'suspended') NOT NULL DEFAULT 'active',
        `access_token` VARCHAR(64) DEFAULT NULL,
        `password_hash` VARCHAR(255) DEFAULT NULL,
        `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
        `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        INDEX (`status`),
        INDEX (`whatsapp`),
        INDEX (`access_token`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    // Auto-patch password_hash column if missing
    $v_cols = @array_column(
        @$conn->query("SHOW COLUMNS FROM print_vendors")->fetch_all(MYSQLI_ASSOC),
        'Field'
    ) ?: [];
    if ($v_cols && !in_array('password_hash', $v_cols)) {
        @$conn->query("ALTER TABLE print_vendors ADD COLUMN `password_hash` VARCHAR(255) DEFAULT NULL AFTER `access_token`");
    }

    // 2. print_products table
    $conn->query("CREATE TABLE IF NOT EXISTS `print_products` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `name` VARCHAR(150) NOT NULL,
        `category` VARCHAR(100) NOT NULL,
        `allowed_stocks` TEXT DEFAULT NULL COMMENT 'JSON array of paper stock choices',
        `allowed_finishes` TEXT DEFAULT NULL COMMENT 'JSON array of finish choices',
        `standard_sizes` TEXT DEFAULT NULL COMMENT 'JSON array of standard sizes',
        `unit_label` VARCHAR(30) NOT NULL DEFAULT 'pieces',
        `is_active` TINYINT(1) NOT NULL DEFAULT 1,
        `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    // 3. print_vendor_rates table
    $conn->query("CREATE TABLE IF NOT EXISTS `print_vendor_rates` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `vendor_id` INT NOT NULL,
        `product_id` INT NOT NULL,
        `paper_stock` VARCHAR(100) NOT NULL,
        `finish_type` VARCHAR(100) NOT NULL DEFAULT 'None',
        `size` VARCHAR(50) NOT NULL DEFAULT 'Standard',
        `min_quantity` INT NOT NULL DEFAULT 1000,
        `unit_price` DECIMAL(10, 4) NOT NULL,
        `batch_price` DECIMAL(12, 2) NOT NULL,
        `additional_per_thousand` DECIMAL(12, 2) DEFAULT NULL,
        `turnaround_days` INT NOT NULL DEFAULT 2,
        `notes` VARCHAR(255) DEFAULT NULL,
        `last_confirmed_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
        `expires_at` DATETIME NOT NULL,
        `status` ENUM('active', 'expired', 'archived') NOT NULL DEFAULT 'active',
        `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
        `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (`vendor_id`) REFERENCES `print_vendors`(`id`) ON DELETE CASCADE,
        FOREIGN KEY (`product_id`) REFERENCES `print_products`(`id`) ON DELETE CASCADE,
        INDEX (`expires_at`),
        INDEX (`status`),
        INDEX (`product_id`, `paper_stock`, `finish_type`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    // 4. print_vendor_orders table
    $conn->query("CREATE TABLE IF NOT EXISTS `print_vendor_orders` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `po_number` VARCHAR(50) NOT NULL UNIQUE,
        `quotation_id` INT DEFAULT NULL,
        `client_id` INT DEFAULT NULL,
        `client_name` VARCHAR(150) NOT NULL,
        `vendor_id` INT NOT NULL,
        `rate_id` INT DEFAULT NULL,
        `assigned_by_user_id` INT NOT NULL,
        `product_name` VARCHAR(150) NOT NULL,
        `specifications_json` JSON NOT NULL,
        `quantity` INT NOT NULL,
        `vendor_unit_cost` DECIMAL(10, 4) NOT NULL,
        `vendor_total_cost` DECIMAL(12, 2) NOT NULL,
        `client_selling_price` DECIMAL(12, 2) NOT NULL,
        `gross_margin_amount` DECIMAL(12, 2) NOT NULL,
        `gross_margin_percentage` DECIMAL(5, 2) NOT NULL,
        `artwork_file_url` TEXT DEFAULT NULL,
        `delivery_deadline` DATE NOT NULL,
        `delivery_address` TEXT DEFAULT NULL,
        `special_instructions` TEXT DEFAULT NULL,
        `order_status` ENUM('assigned', 'acknowledged', 'in_production', 'qc_ready', 'dispatched', 'delivered', 'cancelled') NOT NULL DEFAULT 'assigned',
        `payment_status` ENUM('unpaid', 'advance_paid', 'fully_paid') NOT NULL DEFAULT 'unpaid',
        `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
        `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (`vendor_id`) REFERENCES `print_vendors`(`id`),
        INDEX (`order_status`),
        INDEX (`po_number`),
        INDEX (`quotation_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    // 5. print_rate_refresh_logs table
    $conn->query("CREATE TABLE IF NOT EXISTS `print_rate_refresh_logs` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `vendor_id` INT NOT NULL,
        `event_type` ENUM('refreshed', 'bulk_updated', 'auto_expired', 'manual_override') NOT NULL,
        `rates_affected_count` INT NOT NULL DEFAULT 0,
        `previous_expires_at` DATETIME DEFAULT NULL,
        `new_expires_at` DATETIME DEFAULT NULL,
        `triggered_by` VARCHAR(100) DEFAULT 'system',
        `ip_address` VARCHAR(45) DEFAULT NULL,
        `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (`vendor_id`) REFERENCES `print_vendors`(`id`) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    // 6. Seed initial standard products if empty
    $prod_check = $conn->query("SELECT COUNT(*) AS total FROM print_products")->fetch_assoc();
    if (intval($prod_check['total'] ?? 0) === 0) {
        $initial_products = [
            [
                'name' => 'Business Cards (Visiting Cards)',
                'category' => 'Stationery',
                'allowed_stocks' => json_encode(['350gsm Matt Art Card', '350gsm Gloss Art Card', '300gsm Textured Linen Card', '400gsm Heavy Bleach Board']),
                'allowed_finishes' => json_encode(['No Lamination', 'Matt Lamination Both Sides', 'Gloss Lamination Both Sides', 'Matt + Spot UV One Side', 'Matt + Spot UV Both Sides', 'Gold Foil Stamping']),
                'standard_sizes' => json_encode(['Standard (3.5 x 2.0 in)', 'Square (2.5 x 2.5 in)', 'Slim (3.5 x 1.75 in)']),
                'unit_label' => 'cards'
            ],
            [
                'name' => 'Flyers / Handbills',
                'category' => 'Marketing',
                'allowed_stocks' => json_encode(['128gsm Gloss Art Paper', '150gsm Gloss Art Paper', '170gsm Matt Art Paper', '100gsm Offset Bond']),
                'allowed_finishes' => json_encode(['None', 'Gloss Varnish', 'Matt Lamination Both Sides']),
                'standard_sizes' => json_encode(['A4 (8.27 x 11.69 in)', 'A5 (5.83 x 8.27 in)', 'DL (3.9 x 8.27 in)']),
                'unit_label' => 'flyers'
            ],
            [
                'name' => 'Brochures / Catalogues',
                'category' => 'Marketing',
                'allowed_stocks' => json_encode(['150gsm Gloss Inside + 300gsm Cover', '170gsm Matt Art Paper (All)', '128gsm Gloss (All)']),
                'allowed_finishes' => json_encode(['Center Pin Staple', 'Perfect Binding', 'Matt Lamination Cover + Spot UV']),
                'standard_sizes' => json_encode(['A4 (4 Pages Bi-Fold)', 'A4 (6 Pages Tri-Fold)', 'A5 Multi-Page Booklet']),
                'unit_label' => 'brochures'
            ],
            [
                'name' => 'Official Letterheads',
                'category' => 'Stationery',
                'allowed_stocks' => json_encode(['100gsm Premium Bond Paper', '120gsm Conqueror Textured Bond', '80gsm Standard Bond']),
                'allowed_finishes' => json_encode(['Standard CMYK Single Side', 'CMYK Double Sided', 'Gold Emboss Crest']),
                'standard_sizes' => json_encode(['A4 (8.27 x 11.69 in)', 'Letter (8.5 x 11.0 in)']),
                'unit_label' => 'sheets'
            ],
            [
                'name' => 'Flex Banners & Roll-up Standees',
                'category' => 'Outdoor & Signage',
                'allowed_stocks' => json_encode(['Star Frontlit Flex 280gsm', 'Star Backlit Flex 320gsm', 'Matte Non-Tearable Vinyl']),
                'allowed_finishes' => json_encode(['Eyelets / Grommets All Corners', 'Pocket Seaming Top/Bottom', 'Mounted on Roll-Up Standee']),
                'standard_sizes' => json_encode(['Roll-up Standee (2.5 x 6.0 ft)', 'Roll-up Standee (3.0 x 6.5 ft)', 'Banner (6 x 3 ft)', 'Banner (10 x 4 ft)']),
                'unit_label' => 'units'
            ],
            [
                'name' => 'Custom Packaging Boxes',
                'category' => 'Packaging',
                'allowed_stocks' => json_encode(['350gsm Bleach Board', '400gsm Kraft Paper Cardboard', 'Duplex Board 350gsm Grey Back']),
                'allowed_finishes' => json_encode(['Matt Lamination + Die-Cut Creasing', 'Gloss Lamination + Window Patching', 'Foil Stamping + Spot UV']),
                'standard_sizes' => json_encode(['Custom Box (Small 4x4x2 in)', 'Custom Box (Medium 8x6x3 in)', 'Product Mailer Box (10x8x4 in)']),
                'unit_label' => 'boxes'
            ]
        ];

        $ins_p = $conn->prepare("INSERT INTO print_products (name, category, allowed_stocks, allowed_finishes, standard_sizes, unit_label) VALUES (?, ?, ?, ?, ?, ?)");
        foreach ($initial_products as $prod) {
            $ins_p->bind_param("ssssss", $prod['name'], $prod['category'], $prod['allowed_stocks'], $prod['allowed_finishes'], $prod['standard_sizes'], $prod['unit_label']);
            $ins_p->execute();
        }
    }
}

/**
 * Seed sample rate cards for demo vendors
 */
function seed_vendor_sample_rates(mysqli $conn, int $vendor_id, int $frequency_days, int $hours_offset) {
    // Get product IDs
    $p_res = $conn->query("SELECT id, name FROM print_products");
    $prods = [];
    while ($r = $p_res->fetch_assoc()) {
        $prods[$r['name']] = $r['id'];
    }

    $now = time();
    if ($hours_offset < 0) {
        // Expired rate: confirmed 3 days ago with 1 day frequency -> expired 2 days ago
        $confirmed_at = date('Y-m-d H:i:s', $now - (86400 * 3));
        $expires_at   = date('Y-m-d H:i:s', $now - (86400 * 2));
        $rate_status  = 'expired';
    } else {
        $confirmed_at = date('Y-m-d H:i:s', $now - ($hours_offset * 3600));
        $expires_at   = date('Y-m-d H:i:s', strtotime($confirmed_at) + ($frequency_days * 86400));
        $rate_status  = (strtotime($expires_at) < $now) ? 'expired' : 'active';
    }

    $ins_rate = $conn->prepare("INSERT INTO print_vendor_rates 
        (vendor_id, product_id, paper_stock, finish_type, size, min_quantity, unit_price, batch_price, additional_per_thousand, turnaround_days, notes, last_confirmed_at, expires_at, status) 
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");

    // Sample product 1: Business Cards
    if (isset($prods['Business Cards (Visiting Cards)'])) {
        $p_id = $prods['Business Cards (Visiting Cards)'];
        // Variation 1: 350gsm Matt Art Card + Matt Lamination Both Sides
        // Slight vendor pricing variances
        $var = ($vendor_id % 3) * 150;
        $batch = 2400.00 + $var;
        $unit  = $batch / 1000;
        $turnaround = 2;
        $note = "Pack of 1,000 in rigid transparent card containers.";
        $stock = '350gsm Matt Art Card';
        $finish = 'Matt Lamination Both Sides';
        $size = 'Standard (3.5 x 2.0 in)';
        $min_q = 1000;
        $add_k = $batch * 0.85;

        $ins_rate->bind_param("iisssiddsdssss", $vendor_id, $p_id, $stock, $finish, $size, $min_q, $unit, $batch, $add_k, $turnaround, $note, $confirmed_at, $expires_at, $rate_status);
        $ins_rate->execute();

        // Variation 2: 350gsm Matt Art Card + Matt + Spot UV Both Sides
        $batch_spot = 3600.00 + $var;
        $unit_spot  = $batch_spot / 1000;
        $finish_spot = 'Matt + Spot UV Both Sides';
        $note_spot = "High precision spot gloss film with sharp registration.";
        $ins_rate->bind_param("iisssiddsdssss", $vendor_id, $p_id, $stock, $finish_spot, $size, $min_q, $unit_spot, $batch_spot, $add_k, $turnaround, $note_spot, $confirmed_at, $expires_at, $rate_status);
        $ins_rate->execute();
    }

    // Sample product 2: Flyers
    if (isset($prods['Flyers / Handbills'])) {
        $p_id = $prods['Flyers / Handbills'];
        $var = ($vendor_id % 2) * 200;
        $batch = 4800.00 + $var;
        $unit = $batch / 1000;
        $turnaround = 2;
        $note = "4-Color Heidelberg offset printing, trimmed to precision.";
        $stock = '128gsm Gloss Art Paper';
        $finish = 'None';
        $size = 'A5 (5.83 x 8.27 in)';
        $min_q = 1000;
        $add_k = 4200.00;

        $ins_rate->bind_param("iisssiddsdssss", $vendor_id, $p_id, $stock, $finish, $size, $min_q, $unit, $batch, $add_k, $turnaround, $note, $confirmed_at, $expires_at, $rate_status);
        $ins_rate->execute();
    }
}

/**
 * Re-evaluate rates status and mark expired
 */
function run_rates_expiration_check(mysqli $conn): int {
    $now = date('Y-m-d H:i:s');
    $sql = "UPDATE print_vendor_rates 
            SET status = 'expired' 
            WHERE status = 'active' AND expires_at < '{$now}'";
    $conn->query($sql);
    return $conn->affected_rows;
}
