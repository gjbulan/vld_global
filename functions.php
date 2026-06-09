<?php
include_once __DIR__ . '/config.php';

function getVldPackageIds() {
    return [
        'vision' => 1,
        'legacy' => 2,
        'dominance' => 3
    ];
}

function generateMemberCode($conn) {
    $result = $conn->query("SELECT id FROM members ORDER BY id DESC LIMIT 1");
    $row = $result->fetch_assoc();
    $next = ($row ? $row['id'] + 1 : 1);
    return "STG" . str_pad($next, 6, "0", STR_PAD_LEFT);
}

function getMemberByUsername($conn, $username) {
    $stmt = $conn->prepare("SELECT * FROM members WHERE username=?");
    $stmt->bind_param("s", $username);
    $stmt->execute();
    return $stmt->get_result()->fetch_assoc();
}

function getMemberPackage($conn, $member_id) {
    $stmt = $conn->prepare("
        SELECT m.id, m.username, m.full_name, m.package_id, m.status, p.name AS package_name, p.price
        FROM members m
        LEFT JOIN packages p ON m.package_id = p.id
        WHERE m.id=?
        LIMIT 1
    ");
    $stmt->bind_param("i", $member_id);
    $stmt->execute();
    return $stmt->get_result()->fetch_assoc();
}

function getPackagePrice($conn, $package_id) {
    $stmt = $conn->prepare("SELECT price FROM packages WHERE id=? LIMIT 1");
    $stmt->bind_param("i", $package_id);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    return $row ? (float)$row['price'] : 0;
}

function getTravelRankLevels() {
    return [
        1 => 500000.00,
        2 => 3000000.00,
        3 => 15000000.00,
        4 => 60000000.00,
        5 => 180000000.00,
        6 => 540000000.00
    ];
}

function formatTravelRankName($rank_level) {
    $rank_level = (int)$rank_level;
    return $rank_level > 0 ? "Level " . $rank_level : "No Rank";
}

function ensureMemberRankHistoryTable($conn) {
    static $ensured = false;

    if ($ensured) {
        return;
    }

    $sql = "
        CREATE TABLE IF NOT EXISTS member_rank_history (
            id int(11) NOT NULL AUTO_INCREMENT,
            member_id int(11) NOT NULL,
            rank_level int(11) NOT NULL,
            required_volume decimal(15,2) NOT NULL DEFAULT 0.00,
            achieved_volume decimal(15,2) NOT NULL DEFAULT 0.00,
            qualified_at datetime NOT NULL,
            created_at datetime DEFAULT current_timestamp(),
            PRIMARY KEY (id),
            UNIQUE KEY uniq_member_rank_level (member_id, rank_level),
            KEY idx_member_rank_member_id (member_id),
            KEY idx_member_rank_rank_level (rank_level),
            KEY idx_member_rank_qualified_at (qualified_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
    ";

    if (!$conn->query($sql)) {
        throw new Exception("Unable to create member rank history table.");
    }

    $ensured = true;
}

function getDirectSalesVolumeFromCurrentPackages($conn, $member_id) {
    $stmt = $conn->prepare("
        SELECT COALESCE(SUM(p.price), 0) AS total
        FROM members d
        JOIN packages p ON d.package_id = p.id
        WHERE d.sponsor_id=?
    ");
    $stmt->bind_param("i", $member_id);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    return $row ? (float)$row['total'] : 0.00;
}

function getDirectSalesVolume($conn, $member_id) {
    return getDirectSalesVolumeFromCurrentPackages($conn, $member_id);
}

function getCurrentTravelRank($conn, $member_id) {
    ensureMemberRankHistoryTable($conn);

    $stmt = $conn->prepare("
        SELECT rank_level, required_volume, achieved_volume, qualified_at
        FROM member_rank_history
        WHERE member_id=?
        ORDER BY rank_level DESC
        LIMIT 1
    ");
    $stmt->bind_param("i", $member_id);
    $stmt->execute();
    $rank = $stmt->get_result()->fetch_assoc();
    $direct_sales_volume = getDirectSalesVolume($conn, $member_id);

    if (!$rank) {
        return [
            'rank_level' => 0,
            'rank_name' => 'No Rank',
            'required_volume' => 0.00,
            'achieved_volume' => 0.00,
            'direct_sales_volume' => $direct_sales_volume,
            'qualified_at' => null
        ];
    }

    $rank_level = (int)$rank['rank_level'];

    return [
        'rank_level' => $rank_level,
        'rank_name' => formatTravelRankName($rank_level),
        'required_volume' => (float)$rank['required_volume'],
        'achieved_volume' => (float)$rank['achieved_volume'],
        'direct_sales_volume' => $direct_sales_volume,
        'qualified_at' => $rank['qualified_at']
    ];
}

function getNextTravelRank($conn, $member_id) {
    $current_rank = getCurrentTravelRank($conn, $member_id);
    $current_level = (int)$current_rank['rank_level'];
    $direct_sales_volume = (float)$current_rank['direct_sales_volume'];
    $rank_levels = getTravelRankLevels();

    foreach ($rank_levels as $rank_level => $required_volume) {
        if ($rank_level > $current_level) {
            return [
                'rank_level' => $rank_level,
                'rank_name' => formatTravelRankName($rank_level),
                'required_volume' => (float)$required_volume,
                'remaining_volume' => max(0, (float)$required_volume - $direct_sales_volume),
                'progress_percent' => $required_volume > 0 ? min(100, ($direct_sales_volume / (float)$required_volume) * 100) : 100
            ];
        }
    }

    return [
        'rank_level' => 0,
        'rank_name' => 'Max Rank',
        'required_volume' => 0.00,
        'remaining_volume' => 0.00,
        'progress_percent' => 100
    ];
}

function processTravelRankQualification($conn, $member_id) {
    ensureMemberRankHistoryTable($conn);

    $member_id = (int)$member_id;

    if ($member_id <= 0) {
        return [];
    }

    $direct_sales_volume = getDirectSalesVolume($conn, $member_id);
    $rank_levels = getTravelRankLevels();
    $inserted = [];

    foreach ($rank_levels as $rank_level => $required_volume) {
        if ($direct_sales_volume < $required_volume) {
            continue;
        }

        $stmt = $conn->prepare("
            INSERT IGNORE INTO member_rank_history
            (member_id, rank_level, required_volume, achieved_volume, qualified_at)
            VALUES (?, ?, ?, ?, NOW())
        ");
        $stmt->bind_param("iidd", $member_id, $rank_level, $required_volume, $direct_sales_volume);

        if (!$stmt->execute()) {
            throw new Exception("Unable to insert travel rank achievement.");
        }

        if ($stmt->affected_rows === 1) {
            $inserted[] = [
                'member_id' => $member_id,
                'rank_level' => $rank_level,
                'required_volume' => (float)$required_volume,
                'achieved_volume' => $direct_sales_volume
            ];
        }
    }

    return $inserted;
}

function processAllTravelRankQualifications($conn) {
    ensureMemberRankHistoryTable($conn);

    $result = $conn->query("SELECT id FROM members ORDER BY id ASC");

    if (!$result) {
        throw new Exception("Unable to load members for travel rank qualification.");
    }

    $processed = [];

    while ($member = $result->fetch_assoc()) {
        $new_ranks = processTravelRankQualification($conn, (int)$member['id']);

        if ($new_ranks) {
            $processed[(int)$member['id']] = $new_ranks;
        }
    }

    return $processed;
}

function getPackageGenerationValue($package_id) {
    $package_ids = getVldPackageIds();
    $package_id = (int)$package_id;

    if ($package_id === $package_ids['vision']) {
        return 100.00;
    }

    if ($package_id === $package_ids['legacy']) {
        return 150.00;
    }

    if ($package_id === $package_ids['dominance']) {
        return 1000.00;
    }

    return 0.00;
}

function calculateGenerationBonusAmount($upline_package_id, $downline_package_id) {
    $upline_value = getPackageGenerationValue($upline_package_id);
    $downline_value = getPackageGenerationValue($downline_package_id);

    if ($upline_value <= 0 || $downline_value <= 0) {
        return 0.00;
    }

    return min($upline_value, $downline_value);
}

function getUpline($conn, $member_id, $levels = 8) {
    $uplines = [];
    $current = $member_id;

    for ($i = 1; $i <= $levels; $i++) {
        $stmt = $conn->prepare("SELECT sponsor_id FROM members WHERE id=?");
        $stmt->bind_param("i", $current);
        $stmt->execute();
        $res = $stmt->get_result()->fetch_assoc();

        if (!$res || !$res['sponsor_id']) {
            break;
        }

        $uplines[$i] = $res['sponsor_id'];
        $current = $res['sponsor_id'];
    }

    return $uplines;
}

function addBonus($conn, $member_id, $amount, $type, $desc) {
    $stmt = $conn->prepare("INSERT INTO bonus_ledger (member_id, amount, type, description) VALUES (?, ?, ?, ?)");
    $stmt->bind_param("idss", $member_id, $amount, $type, $desc);

    if (!$stmt->execute()) {
        throw new Exception("Unable to insert bonus ledger entry.");
    }

    return $conn->insert_id;
}

function tableColumnExists($conn, $table_name, $column_name) {
    $stmt = $conn->prepare("
        SELECT COUNT(*) AS total
        FROM INFORMATION_SCHEMA.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = ?
          AND COLUMN_NAME = ?
    ");
    $stmt->bind_param("ss", $table_name, $column_name);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();

    return $row && (int)$row['total'] > 0;
}

function tableIndexExists($conn, $table_name, $index_name) {
    $stmt = $conn->prepare("
        SELECT COUNT(*) AS total
        FROM INFORMATION_SCHEMA.STATISTICS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = ?
          AND INDEX_NAME = ?
    ");
    $stmt->bind_param("ss", $table_name, $index_name);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();

    return $row && (int)$row['total'] > 0;
}

function addColumnIfMissing($conn, $table_name, $column_name, $definition) {
    if (!tableColumnExists($conn, $table_name, $column_name)) {
        if (!$conn->query("ALTER TABLE `$table_name` ADD COLUMN `$column_name` $definition")) {
            throw new Exception("Unable to add column $table_name.$column_name.");
        }
    }
}

function addIndexIfMissing($conn, $table_name, $index_name, $definition) {
    if (!tableIndexExists($conn, $table_name, $index_name)) {
        if (!$conn->query("ALTER TABLE `$table_name` ADD $definition")) {
            throw new Exception("Unable to add index $index_name on $table_name.");
        }
    }
}

function ensureProductBonusSchema($conn) {
    static $ensured = false;

    if ($ensured) {
        return;
    }

    addColumnIfMissing($conn, "products", "personal_bonus", "decimal(10,2) NOT NULL DEFAULT 0.00");
    addColumnIfMissing($conn, "products", "community_bonus", "decimal(10,2) NOT NULL DEFAULT 0.00");
    addColumnIfMissing($conn, "products", "status", "varchar(20) NOT NULL DEFAULT 'active'");
    addColumnIfMissing($conn, "products", "created_at", "datetime DEFAULT current_timestamp()");
    addIndexIfMissing($conn, "products", "idx_products_status", "KEY `idx_products_status` (`status`)");

    addColumnIfMissing($conn, "product_purchases", "product_code_id", "int(11) DEFAULT NULL");
    addIndexIfMissing($conn, "product_purchases", "uniq_product_purchases_product_code", "UNIQUE KEY `uniq_product_purchases_product_code` (`product_code_id`)");
    addIndexIfMissing($conn, "product_purchases", "idx_product_purchases_member_created", "KEY `idx_product_purchases_member_created` (`member_id`,`created_at`)");
    addIndexIfMissing($conn, "product_purchases", "idx_product_purchases_product_id", "KEY `idx_product_purchases_product_id` (`product_id`)");

    addColumnIfMissing($conn, "community_bonus_ledger", "product_id", "int(11) DEFAULT NULL");
    addColumnIfMissing($conn, "community_bonus_ledger", "product_purchase_id", "int(11) DEFAULT NULL");
    addColumnIfMissing($conn, "community_bonus_ledger", "source_product_code_id", "int(11) DEFAULT NULL");
    addColumnIfMissing($conn, "community_bonus_ledger", "quantity", "int(11) NOT NULL DEFAULT 0");
    addColumnIfMissing($conn, "community_bonus_ledger", "bonus_ledger_id", "int(11) DEFAULT NULL");
    addIndexIfMissing($conn, "community_bonus_ledger", "uniq_community_purchase_bonus_source", "UNIQUE KEY `uniq_community_purchase_bonus_source` (`source_product_code_id`,`member_id`,`level`)");
    addIndexIfMissing($conn, "community_bonus_ledger", "idx_community_product_purchase_id", "KEY `idx_community_product_purchase_id` (`product_purchase_id`)");
    addIndexIfMissing($conn, "community_bonus_ledger", "idx_community_product_id", "KEY `idx_community_product_id` (`product_id`)");
    addIndexIfMissing($conn, "community_bonus_ledger", "idx_community_bonus_ledger_id", "KEY `idx_community_bonus_ledger_id` (`bonus_ledger_id`)");

    addIndexIfMissing($conn, "product_codes", "idx_product_codes_product_status", "KEY `idx_product_codes_product_status` (`product_id`,`status`)");

    $ensured = true;
}

function seedDefaultProducts($conn) {
    ensureProductBonusSchema($conn);

    $defaults = [
        [
            'id' => 1,
            'name' => 'Nutramin',
            'personal_bonus' => 15.00,
            'community_bonus' => 5.00,
            'status' => 'active'
        ],
        [
            'id' => 2,
            'name' => 'Healthy Coffee',
            'personal_bonus' => 10.00,
            'community_bonus' => 5.00,
            'status' => 'active'
        ]
    ];

    $stmt = $conn->prepare("
        INSERT INTO products (id, name, personal_bonus, community_bonus, status)
        VALUES (?, ?, ?, ?, ?)
        ON DUPLICATE KEY UPDATE
            name=VALUES(name),
            personal_bonus=VALUES(personal_bonus),
            community_bonus=VALUES(community_bonus),
            status=VALUES(status)
    ");

    foreach ($defaults as $product) {
        $id = (int)$product['id'];
        $name = $product['name'];
        $personal_bonus = (float)$product['personal_bonus'];
        $community_bonus = (float)$product['community_bonus'];
        $status = $product['status'];

        $stmt->bind_param(
            "isdds",
            $id,
            $name,
            $personal_bonus,
            $community_bonus,
            $status
        );

        if (!$stmt->execute()) {
            throw new Exception("Unable to seed default products.");
        }
    }

    $stmt = $conn->prepare("UPDATE products SET status='inactive' WHERE id NOT IN (1, 2)");

    if (!$stmt->execute()) {
        throw new Exception("Unable to deactivate non-default products.");
    }
}

function ensureChairmanBonusLedgerTable($conn) {
    $sql = "
        CREATE TABLE IF NOT EXISTS chairman_bonus_ledger (
            id int(11) NOT NULL AUTO_INCREMENT,
            member_id int(11) NOT NULL,
            from_member_id int(11) NOT NULL,
            source_bonus_ledger_id int(11) NOT NULL,
            source_generation_bonus_amount decimal(10,2) NOT NULL DEFAULT 0.00,
            percentage decimal(5,2) NOT NULL DEFAULT 0.02,
            amount decimal(10,2) NOT NULL DEFAULT 0.00,
            created_at datetime DEFAULT current_timestamp(),
            PRIMARY KEY (id),
            UNIQUE KEY uniq_chairman_source_bonus (source_bonus_ledger_id),
            KEY idx_chairman_member_id (member_id),
            KEY idx_chairman_from_member_id (from_member_id),
            KEY idx_chairman_created_at (created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
    ";

    if (!$conn->query($sql)) {
        throw new Exception("Unable to create Chairman Bonus ledger table.");
    }
}

function isChairmanQualified($conn, $member_id) {
    $package_ids = getVldPackageIds();

    $stmt = $conn->prepare("
        SELECT package_id, status
        FROM members
        WHERE id=?
        LIMIT 1
    ");
    $stmt->bind_param("i", $member_id);
    $stmt->execute();
    $member = $stmt->get_result()->fetch_assoc();

    if (!$member || $member['status'] !== 'active' || (int)$member['package_id'] !== $package_ids['dominance']) {
        return false;
    }

    $stmt = $conn->prepare("
        SELECT COUNT(*) AS total
        FROM members
        WHERE sponsor_id=? AND status='active' AND package_id=?
    ");
    $stmt->bind_param("ii", $member_id, $package_ids['dominance']);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();

    return $row && (int)$row['total'] >= 5;
}

function processChairmanBonus($conn, $generation_receiver_id, $source_bonus_ledger_id, $generation_bonus_amount) {
    $generation_bonus_amount = (float)$generation_bonus_amount;

    if ($generation_bonus_amount <= 0) {
        return [
            'awarded' => false,
            'message' => 'Generation bonus amount is zero.'
        ];
    }

    $stmt = $conn->prepare("
        SELECT id, username, sponsor_id, status
        FROM members
        WHERE id=?
        LIMIT 1
    ");
    $stmt->bind_param("i", $generation_receiver_id);
    $stmt->execute();
    $receiver = $stmt->get_result()->fetch_assoc();

    if (!$receiver || !$receiver['sponsor_id'] || $receiver['status'] !== 'active') {
        return [
            'awarded' => false,
            'message' => 'Generation receiver does not have an active sponsor.'
        ];
    }

    $chairman_member_id = (int)$receiver['sponsor_id'];

    if (!isChairmanQualified($conn, $chairman_member_id)) {
        return [
            'awarded' => false,
            'message' => 'Sponsor is not Chairman qualified.'
        ];
    }

    $stmt = $conn->prepare("
        SELECT id
        FROM chairman_bonus_ledger
        WHERE source_bonus_ledger_id=?
        LIMIT 1
    ");
    $stmt->bind_param("i", $source_bonus_ledger_id);
    $stmt->execute();

    if ($stmt->get_result()->fetch_assoc()) {
        return [
            'awarded' => false,
            'message' => 'Chairman Bonus already exists for this source bonus.'
        ];
    }

    $percentage = 0.02;
    $amount = round($generation_bonus_amount * $percentage, 2);

    if ($amount <= 0) {
        return [
            'awarded' => false,
            'message' => 'Chairman Bonus amount is zero.'
        ];
    }

    $stmt = $conn->prepare("
        INSERT INTO chairman_bonus_ledger
        (member_id, from_member_id, source_bonus_ledger_id, source_generation_bonus_amount, percentage, amount)
        VALUES (?, ?, ?, ?, ?, ?)
    ");
    $stmt->bind_param(
        "iiiddd",
        $chairman_member_id,
        $generation_receiver_id,
        $source_bonus_ledger_id,
        $generation_bonus_amount,
        $percentage,
        $amount
    );

    if (!$stmt->execute()) {
        throw new Exception("Unable to insert Chairman Bonus ledger entry.");
    }

    $chairman_bonus_ledger_id = $conn->insert_id;
    $description = "2% Chairman Bonus from generation bonus of " . $receiver['username'];
    $bonus_ledger_id = addBonus($conn, $chairman_member_id, $amount, "chairman_bonus", $description);

    return [
        'awarded' => true,
        'chairman_bonus_ledger_id' => $chairman_bonus_ledger_id,
        'bonus_ledger_id' => $bonus_ledger_id,
        'amount' => $amount
    ];
}

function processGenerationBonuses($conn, $new_member_id, $sponsor_id, $new_member_package_id) {
    $new_member = getMemberPackage($conn, $new_member_id);

    if (!$new_member) {
        throw new Exception("Unable to load new member for generation bonus processing.");
    }

    $source_username = $new_member['username'];
    $source_package_name = $new_member['package_name'] ?? 'No Package';
    $uplines = getUpline($conn, $sponsor_id, 7);
    $generation_level = 2;
    $processed = [];

    foreach ($uplines as $upline_id) {
        if ($generation_level > 8) {
            break;
        }

        $upline = getMemberPackage($conn, $upline_id);

        if (!$upline) {
            $generation_level++;
            continue;
        }

        $bonus_amount = calculateGenerationBonusAmount((int)$upline['package_id'], $new_member_package_id);

        if ($bonus_amount <= 0) {
            $generation_level++;
            continue;
        }

        $description = "Generation level " . $generation_level . " bonus from " . $source_username . " (" . $source_package_name . ")";
        $bonus_ledger_id = addBonus($conn, $upline_id, $bonus_amount, "generation_bonus", $description);
        processChairmanBonus($conn, $upline_id, $bonus_ledger_id, $bonus_amount);

        $processed[] = [
            'level' => $generation_level,
            'member_id' => $upline_id,
            'bonus_ledger_id' => $bonus_ledger_id,
            'amount' => $bonus_amount
        ];

        $generation_level++;
    }

    return $processed;
}

function getProductBonusConfig($conn, $product_id) {
    ensureProductBonusSchema($conn);

    $stmt = $conn->prepare("
        SELECT id, name, personal_bonus, community_bonus, status
        FROM products
        WHERE id=?
        LIMIT 1
    ");
    $stmt->bind_param("i", $product_id);
    $stmt->execute();
    return $stmt->get_result()->fetch_assoc();
}

function getMonthlyProductPurchaseCount($conn, $member_id, $month = null) {
    ensureProductBonusSchema($conn);

    if ($month === null || $month === "") {
        $month = date("Y-m");
    }

    $stmt = $conn->prepare("
        SELECT COALESCE(SUM(pp.quantity), 0) AS total
        FROM product_purchases pp
        JOIN products p ON pp.product_id = p.id
        WHERE pp.member_id=?
          AND DATE_FORMAT(pp.created_at, '%Y-%m')=?
          AND p.id IN (1, 2)
          AND p.status='active'
    ");
    $stmt->bind_param("is", $member_id, $month);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();

    return $row ? (int)$row['total'] : 0;
}

function isCommunityBonusQualified($conn, $member_id) {
    return getMonthlyProductPurchaseCount($conn, $member_id) >= 2;
}

function processPersonalPurchaseBonus($conn, $member_id, $product_id, $quantity) {
    $product = getProductBonusConfig($conn, $product_id);

    if (!$product || $product['status'] !== 'active') {
        throw new Exception("Product is not active for personal purchase bonus.");
    }

    $quantity = (int)$quantity;

    if ($quantity <= 0) {
        throw new Exception("Product quantity must be greater than zero.");
    }

    $amount = round((float)$product['personal_bonus'] * $quantity, 2);

    if ($amount <= 0) {
        return [
            'awarded' => false,
            'amount' => 0.00
        ];
    }

    $description = "Personal purchase bonus from " . $product['name'] . " x " . $quantity;
    $bonus_ledger_id = addBonus($conn, $member_id, $amount, "personal_purchase_bonus", $description);

    return [
        'awarded' => true,
        'bonus_ledger_id' => $bonus_ledger_id,
        'amount' => $amount
    ];
}

function processCommunityBonus($conn, $member_id, $product_id, $quantity, $product_purchase_id = null, $source_product_code_id = null) {
    ensureProductBonusSchema($conn);

    $product = getProductBonusConfig($conn, $product_id);

    if (!$product || $product['status'] !== 'active') {
        throw new Exception("Product is not active for community purchase bonus.");
    }

    $quantity = (int)$quantity;

    if ($quantity <= 0) {
        throw new Exception("Product quantity must be greater than zero.");
    }

    $bonus = round((float)$product['community_bonus'] * $quantity, 2);

    if ($bonus <= 0) {
        return [];
    }

    $source_member = getMemberPackage($conn, $member_id);
    $source_username = $source_member ? $source_member['username'] : (string)$member_id;
    $uplines = getUpline($conn, $member_id, 8);
    $processed = [];

    foreach ($uplines as $level => $upline_id) {
        if (!isCommunityBonusQualified($conn, $upline_id)) {
            continue;
        }

        if ($source_product_code_id !== null) {
            $stmt = $conn->prepare("
                SELECT id
                FROM community_bonus_ledger
                WHERE source_product_code_id=? AND member_id=? AND level=?
                LIMIT 1
            ");
            $stmt->bind_param("iii", $source_product_code_id, $upline_id, $level);
            $stmt->execute();

            if ($stmt->get_result()->fetch_assoc()) {
                continue;
            }
        }

        $description = "Community purchase bonus Level " . $level . " from " . $source_username . " (" . $product['name'] . " x " . $quantity . ")";
        $bonus_ledger_id = addBonus($conn, $upline_id, $bonus, "community_purchase_bonus", $description);

        $stmt = $conn->prepare("
            INSERT INTO community_bonus_ledger
            (member_id, from_member_id, product_id, product_purchase_id, source_product_code_id, level, quantity, amount, bonus_ledger_id)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->bind_param(
            "iiiiiiidi",
            $upline_id,
            $member_id,
            $product_id,
            $product_purchase_id,
            $source_product_code_id,
            $level,
            $quantity,
            $bonus,
            $bonus_ledger_id
        );

        if (!$stmt->execute()) {
            throw new Exception("Unable to insert community purchase bonus ledger entry.");
        }

        $processed[] = [
            'level' => $level,
            'member_id' => $upline_id,
            'amount' => $bonus,
            'bonus_ledger_id' => $bonus_ledger_id,
            'community_bonus_ledger_id' => $conn->insert_id
        ];
    }

    return $processed;
}

function getBalance($conn, $member_id) {
    $stmt = $conn->prepare("
        SELECT COALESCE(SUM(
            CASE
                WHEN b.type='dominance_royalty_bonus' THEN
                    CASE
                        WHEN dr.id IS NOT NULL
                         AND dr.status='active'
                         AND dr.available_at <= NOW()
                        THEN b.amount
                        ELSE 0
                    END
                ELSE b.amount
            END
        ), 0) AS total
        FROM bonus_ledger b
        LEFT JOIN dominance_royalty_ledger dr ON dr.bonus_ledger_id = b.id
        WHERE b.member_id=?
    ");
    $stmt->bind_param("i", $member_id);
    $stmt->execute();
    $res = $stmt->get_result()->fetch_assoc();
    return isset($res['total']) ? (float)$res['total'] : 0;
}

function hasDominanceRoyaltySchedule($conn, $member_id) {
    $stmt = $conn->prepare("
        SELECT id
        FROM dominance_royalty_ledger
        WHERE member_id=? AND status<>'cancelled'
        LIMIT 1
    ");
    $stmt->bind_param("i", $member_id);
    $stmt->execute();
    return (bool)$stmt->get_result()->fetch_assoc();
}

function createDominanceRoyaltySchedule($conn, $member_id) {
    if (hasDominanceRoyaltySchedule($conn, $member_id)) {
        return [
            'created' => false,
            'message' => 'Dominance Royalty schedule already exists.'
        ];
    }

    $amount = 5000.00;
    $bonus_type = "dominance_royalty_bonus";
    $status = "active";

    for ($month_no = 1; $month_no <= 6; $month_no++) {
        $days = $month_no * 30;

        $date_result = $conn->query("SELECT DATE_ADD(NOW(), INTERVAL " . (int)$days . " DAY) AS available_at");

        if (!$date_result) {
            throw new Exception("Unable to compute Dominance Royalty release date.");
        }

        $available_at = $date_result->fetch_assoc()['available_at'];
        $description = "Dominance Royalty Bonus Month " . $month_no . " of 6. Available on " . $available_at;
        $bonus_ledger_id = addBonus($conn, $member_id, $amount, $bonus_type, $description);

        $stmt = $conn->prepare("
            INSERT INTO dominance_royalty_ledger
            (member_id, month_no, amount, bonus_type, bonus_ledger_id, available_at, status)
            VALUES (?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->bind_param(
            "iidisss",
            $member_id,
            $month_no,
            $amount,
            $bonus_type,
            $bonus_ledger_id,
            $available_at,
            $status
        );

        if (!$stmt->execute()) {
            throw new Exception("Unable to insert Dominance Royalty schedule.");
        }
    }

    return [
        'created' => true,
        'months' => 6,
        'total' => 30000.00
    ];
}

function getDominanceRoyaltySummary($conn, $member_id) {
    $stmt = $conn->prepare("
        SELECT
            COALESCE(SUM(amount), 0) AS total_royalty,
            COALESCE(SUM(CASE WHEN status='active' AND available_at <= NOW() THEN amount ELSE 0 END), 0) AS released_amount,
            COALESCE(SUM(CASE WHEN status='active' AND available_at > NOW() THEN amount ELSE 0 END), 0) AS pending_amount,
            SUM(CASE WHEN status='active' AND available_at <= NOW() THEN 1 ELSE 0 END) AS months_released,
            SUM(CASE WHEN status='active' AND available_at > NOW() THEN 1 ELSE 0 END) AS months_remaining,
            MIN(CASE WHEN status='active' AND available_at > NOW() THEN available_at ELSE NULL END) AS next_release_date,
            COUNT(*) AS total_months
        FROM dominance_royalty_ledger
        WHERE member_id=? AND status<>'cancelled'
    ");
    $stmt->bind_param("i", $member_id);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();

    $total_months = isset($row['total_months']) ? (int)$row['total_months'] : 0;
    $months_remaining = isset($row['months_remaining']) ? (int)$row['months_remaining'] : 0;
    $royalty_status = "Not qualified yet";

    if ($total_months > 0 && $months_remaining > 0) {
        $royalty_status = "Royalty schedule active";
    } elseif ($total_months > 0) {
        $royalty_status = "Royalty fully released";
    }

    return [
        'total_royalty' => isset($row['total_royalty']) ? (float)$row['total_royalty'] : 0,
        'released_amount' => isset($row['released_amount']) ? (float)$row['released_amount'] : 0,
        'pending_amount' => isset($row['pending_amount']) ? (float)$row['pending_amount'] : 0,
        'months_released' => isset($row['months_released']) ? (int)$row['months_released'] : 0,
        'months_remaining' => $months_remaining,
        'next_release_date' => $row['next_release_date'] ?? null,
        'total_months' => $total_months,
        'royalty_status' => $royalty_status
    ];
}

function getQualifiedCashbackDirects($conn, $member_id) {
    $package_ids = getVldPackageIds();
    $package_counts = [
        $package_ids['vision'] => 0,
        $package_ids['legacy'] => 0,
        $package_ids['dominance'] => 0
    ];

    $stmt = $conn->prepare("
        SELECT package_id, COUNT(*) AS total
        FROM members
        WHERE sponsor_id=? AND status='active'
        GROUP BY package_id
    ");
    $stmt->bind_param("i", $member_id);
    $stmt->execute();
    $result = $stmt->get_result();

    while ($row = $result->fetch_assoc()) {
        $package_id = (int)$row['package_id'];

        if (array_key_exists($package_id, $package_counts)) {
            $package_counts[$package_id] = (int)$row['total'];
        }
    }

    $vision_count = $package_counts[$package_ids['vision']];
    $legacy_count = $package_counts[$package_ids['legacy']];
    $dominance_count = $package_counts[$package_ids['dominance']];

    return [
        'vision' => $vision_count,
        'legacy' => $legacy_count,
        'dominance' => $dominance_count,
        'vision_legacy' => $vision_count + $legacy_count,
        'legacy_dominance' => $legacy_count + $dominance_count,
        'direct_dominance' => $dominance_count,
        'total_active' => $vision_count + $legacy_count + $dominance_count
    ];
}

function hasCashbackAlreadyClaimed($conn, $member_id) {
    $stmt = $conn->prepare("
        SELECT id
        FROM cashback_ledger
        WHERE member_id=? AND reward_type='cashback' AND status<>'cancelled'
        LIMIT 1
    ");
    $stmt->bind_param("i", $member_id);
    $stmt->execute();
    return (bool)$stmt->get_result()->fetch_assoc();
}

function hasDominanceAdvancementCredit($conn, $member_id) {
    $stmt = $conn->prepare("
        SELECT id
        FROM dominance_advancement_credits
        WHERE member_id=? AND status<>'cancelled'
        LIMIT 1
    ");
    $stmt->bind_param("i", $member_id);
    $stmt->execute();
    return (bool)$stmt->get_result()->fetch_assoc();
}

function hasAnyCashbackOrAdvancementReward($conn, $member_id) {
    $stmt = $conn->prepare("
        SELECT id
        FROM cashback_ledger
        WHERE member_id=? AND status<>'cancelled'
        LIMIT 1
    ");
    $stmt->bind_param("i", $member_id);
    $stmt->execute();
    return (bool)$stmt->get_result()->fetch_assoc();
}

function insertCashbackReward($conn, $member_id, $package_id, $qualification_type, $qualified_direct_count, $reward_amount, $remarks) {
    if (hasAnyCashbackOrAdvancementReward($conn, $member_id)) {
        return [
            'awarded' => false,
            'type' => '',
            'message' => 'Reward already exists.'
        ];
    }

    $reward_type = "cashback";
    $status = "active";
    $bonus_ledger_id = null;

    $stmt = $conn->prepare("
        INSERT INTO cashback_ledger
        (member_id, package_id, qualification_type, qualified_direct_count, reward_type, reward_amount, bonus_ledger_id, status)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?)
    ");
    $stmt->bind_param(
        "iisisdis",
        $member_id,
        $package_id,
        $qualification_type,
        $qualified_direct_count,
        $reward_type,
        $reward_amount,
        $bonus_ledger_id,
        $status
    );

    if (!$stmt->execute()) {
        throw new Exception("Unable to insert cashback ledger entry.");
    }

    $cashback_ledger_id = $conn->insert_id;
    $bonus_ledger_id = addBonus($conn, $member_id, $reward_amount, "cashback_bonus", $remarks);

    $stmt = $conn->prepare("UPDATE cashback_ledger SET bonus_ledger_id=? WHERE id=?");
    $stmt->bind_param("ii", $bonus_ledger_id, $cashback_ledger_id);

    if (!$stmt->execute()) {
        throw new Exception("Unable to link cashback bonus ledger entry.");
    }

    return [
        'awarded' => true,
        'type' => 'cashback',
        'cashback_ledger_id' => $cashback_ledger_id,
        'bonus_ledger_id' => $bonus_ledger_id,
        'amount' => $reward_amount
    ];
}

function processDominanceAdvancementCredit($conn, $member_id) {
    if (hasAnyCashbackOrAdvancementReward($conn, $member_id)) {
        return [
            'awarded' => false,
            'type' => '',
            'message' => 'Reward already exists.'
        ];
    }

    $member = getMemberPackage($conn, $member_id);

    if (!$member || $member['status'] !== 'active') {
        return [
            'awarded' => false,
            'type' => '',
            'message' => 'Member is not active.'
        ];
    }

    $package_ids = getVldPackageIds();
    $package_id = (int)$member['package_id'];

    if ($package_id !== $package_ids['vision'] && $package_id !== $package_ids['legacy']) {
        return [
            'awarded' => false,
            'type' => '',
            'message' => 'Member is not eligible for Dominance Advancement Credit.'
        ];
    }

    $directs = getQualifiedCashbackDirects($conn, $member_id);

    if ($directs['direct_dominance'] < 5) {
        return [
            'awarded' => false,
            'type' => '',
            'message' => 'Member does not have 5 active Direct Dominance referrals.'
        ];
    }

    $amount = getPackagePrice($conn, $package_ids['dominance']);
    $credit_status = "unused";

    $stmt = $conn->prepare("
        INSERT INTO dominance_advancement_credits (member_id, amount, status)
        VALUES (?, ?, ?)
    ");
    $stmt->bind_param("ids", $member_id, $amount, $credit_status);

    if (!$stmt->execute()) {
        throw new Exception("Unable to insert Dominance Advancement Credit.");
    }

    $credit_id = $conn->insert_id;
    $reward_type = "dominance_advancement_credit";
    $ledger_status = "active";
    $qualification_type = "direct_dominance";
    $bonus_ledger_id = null;

    $stmt = $conn->prepare("
        INSERT INTO cashback_ledger
        (member_id, package_id, qualification_type, qualified_direct_count, reward_type, reward_amount, bonus_ledger_id, status)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?)
    ");
    $stmt->bind_param(
        "iisisdis",
        $member_id,
        $package_id,
        $qualification_type,
        $directs['direct_dominance'],
        $reward_type,
        $amount,
        $bonus_ledger_id,
        $ledger_status
    );

    if (!$stmt->execute()) {
        throw new Exception("Unable to insert advancement credit ledger entry.");
    }

    return [
        'awarded' => true,
        'type' => 'dominance_advancement_credit',
        'credit_id' => $credit_id,
        'amount' => $amount
    ];
}

function processCashbackAndAdvancement($conn, $member_id) {
    $member = getMemberPackage($conn, $member_id);

    if (!$member || $member['status'] !== 'active') {
        return [
            'awarded' => false,
            'type' => '',
            'message' => 'Member is not active.'
        ];
    }

    if (hasAnyCashbackOrAdvancementReward($conn, $member_id)) {
        return [
            'awarded' => false,
            'type' => '',
            'message' => 'Reward already exists.'
        ];
    }

    $package_ids = getVldPackageIds();
    $package_id = (int)$member['package_id'];
    $directs = getQualifiedCashbackDirects($conn, $member_id);

    if (
        ($package_id === $package_ids['vision'] || $package_id === $package_ids['legacy']) &&
        $directs['direct_dominance'] >= 5
    ) {
        return processDominanceAdvancementCredit($conn, $member_id);
    }

    if ($package_id === $package_ids['vision'] && $directs['vision_legacy'] >= 5) {
        return insertCashbackReward(
            $conn,
            $member_id,
            $package_id,
            "vision_legacy_directs",
            $directs['vision_legacy'],
            getPackagePrice($conn, $package_ids['vision']),
            "Withdrawable Vision cashback bonus"
        );
    }

    if ($package_id === $package_ids['legacy'] && $directs['legacy_dominance'] >= 5) {
        return insertCashbackReward(
            $conn,
            $member_id,
            $package_id,
            "legacy_dominance_directs",
            $directs['legacy_dominance'],
            getPackagePrice($conn, $package_ids['legacy']),
            "Withdrawable Legacy cashback bonus"
        );
    }

    if ($package_id === $package_ids['dominance'] && $directs['direct_dominance'] >= 5) {
        $cashback_result = insertCashbackReward(
            $conn,
            $member_id,
            $package_id,
            "direct_dominance",
            $directs['direct_dominance'],
            getPackagePrice($conn, $package_ids['dominance']),
            "Withdrawable Dominance cashback bonus"
        );

        if ($cashback_result['awarded']) {
            $cashback_result['royalty'] = createDominanceRoyaltySchedule($conn, $member_id);
        }

        return $cashback_result;
    }

    return [
        'awarded' => false,
        'type' => '',
        'message' => 'Member is not qualified yet.'
    ];
}

function getCashbackLedgerReward($conn, $member_id, $reward_type) {
    $stmt = $conn->prepare("
        SELECT *
        FROM cashback_ledger
        WHERE member_id=? AND reward_type=? AND status<>'cancelled'
        ORDER BY id DESC
        LIMIT 1
    ");
    $stmt->bind_param("is", $member_id, $reward_type);
    $stmt->execute();
    return $stmt->get_result()->fetch_assoc();
}

function getDominanceAdvancementCredit($conn, $member_id) {
    $stmt = $conn->prepare("
        SELECT *
        FROM dominance_advancement_credits
        WHERE member_id=? AND status<>'cancelled'
        ORDER BY id DESC
        LIMIT 1
    ");
    $stmt->bind_param("i", $member_id);
    $stmt->execute();
    return $stmt->get_result()->fetch_assoc();
}

function getCashbackStatus($conn, $member_id) {
    $member = getMemberPackage($conn, $member_id);
    $directs = getQualifiedCashbackDirects($conn, $member_id);
    $cashback = getCashbackLedgerReward($conn, $member_id, "cashback");
    $advancement_ledger = getCashbackLedgerReward($conn, $member_id, "dominance_advancement_credit");
    $credit = getDominanceAdvancementCredit($conn, $member_id);
    $package_ids = getVldPackageIds();

    $qualified_direct_count = 0;
    $qualification_label = "Not qualified yet";
    $next_reward_label = "No reward available yet";
    $reward_amount = 0;

    if ($member) {
        $package_id = (int)$member['package_id'];

        if (($package_id === $package_ids['vision'] || $package_id === $package_ids['legacy']) && $directs['direct_dominance'] >= 5) {
            $qualified_direct_count = $directs['direct_dominance'];
            $qualification_label = "Qualified for Dominance Advancement Credit";
            $next_reward_label = "Non-withdrawable Upgrade Credit";
            $reward_amount = getPackagePrice($conn, $package_ids['dominance']);
        } elseif ($package_id === $package_ids['vision']) {
            $qualified_direct_count = $directs['vision_legacy'];
            $qualification_label = $qualified_direct_count >= 5 ? "Qualified for Vision Cashback" : "Needs 5 active Vision/Legacy directs";
            $next_reward_label = "Withdrawable Cashback";
            $reward_amount = getPackagePrice($conn, $package_ids['vision']);
        } elseif ($package_id === $package_ids['legacy']) {
            $qualified_direct_count = $directs['legacy_dominance'];
            $qualification_label = $qualified_direct_count >= 5 ? "Qualified for Legacy Cashback" : "Needs 5 active Legacy/Dominance directs";
            $next_reward_label = "Withdrawable Cashback";
            $reward_amount = getPackagePrice($conn, $package_ids['legacy']);
        } elseif ($package_id === $package_ids['dominance']) {
            $qualified_direct_count = $directs['direct_dominance'];
            $qualification_label = $qualified_direct_count >= 5 ? "Qualified for Dominance Cashback" : "Needs 5 active Direct Dominance";
            $next_reward_label = "Withdrawable Cashback";
            $reward_amount = getPackagePrice($conn, $package_ids['dominance']);
        }
    }

    if ($cashback) {
        $qualification_label = "Cashback earned";
        $next_reward_label = "Withdrawable Cashback";
        $reward_amount = (float)$cashback['reward_amount'];
    }

    if ($credit) {
        $qualification_label = $credit['status'] === "unused" ? "Upgrade credit available" : "Upgrade credit used";
        $next_reward_label = "Non-withdrawable Upgrade Credit";
        $reward_amount = (float)$credit['amount'];
    }

    return [
        'member' => $member,
        'directs' => $directs,
        'cashback' => $cashback,
        'advancement_ledger' => $advancement_ledger,
        'credit' => $credit,
        'qualified_direct_count' => $qualified_direct_count,
        'qualification_label' => $qualification_label,
        'next_reward_label' => $next_reward_label,
        'reward_amount' => $reward_amount,
        'has_unused_credit' => $credit && $credit['status'] === "unused"
    ];
}

function useDominanceAdvancementCreditForUpgrade($conn, $member_id) {
    $package_ids = getVldPackageIds();
    ensureMemberRankHistoryTable($conn);

    $conn->begin_transaction();

    try {
        $stmt = $conn->prepare("
            SELECT id, package_id, sponsor_id, status
            FROM members
            WHERE id=?
            LIMIT 1
            FOR UPDATE
        ");
        $stmt->bind_param("i", $member_id);
        $stmt->execute();
        $member = $stmt->get_result()->fetch_assoc();

        if (!$member) {
            throw new Exception("Member account was not found.");
        }

        if ($member['status'] !== "active") {
            throw new Exception("Only active members can use Dominance Advancement Credit.");
        }

        if ((int)$member['package_id'] === $package_ids['dominance']) {
            throw new Exception("Your account is already on the Dominance package.");
        }

        $stmt = $conn->prepare("
            SELECT id
            FROM dominance_advancement_credits
            WHERE member_id=? AND status='unused'
            LIMIT 1
            FOR UPDATE
        ");
        $stmt->bind_param("i", $member_id);
        $stmt->execute();
        $credit = $stmt->get_result()->fetch_assoc();

        if (!$credit) {
            throw new Exception("No unused Dominance Advancement Credit is available.");
        }

        $stmt = $conn->prepare("UPDATE members SET package_id=? WHERE id=?");
        $stmt->bind_param("ii", $package_ids['dominance'], $member_id);

        if (!$stmt->execute()) {
            throw new Exception("Unable to upgrade package.");
        }

        $used_status = "used";
        $stmt = $conn->prepare("UPDATE dominance_advancement_credits SET status=?, used_at=NOW() WHERE id=?");
        $stmt->bind_param("si", $used_status, $credit['id']);

        if (!$stmt->execute()) {
            throw new Exception("Unable to consume Dominance Advancement Credit.");
        }

        $stmt = $conn->prepare("
            UPDATE cashback_ledger
            SET status='used'
            WHERE member_id=? AND reward_type='dominance_advancement_credit' AND status='active'
        ");
        $stmt->bind_param("i", $member_id);

        if (!$stmt->execute()) {
            throw new Exception("Unable to update advancement ledger status.");
        }

        if (!empty($member['sponsor_id'])) {
            processTravelRankQualification($conn, (int)$member['sponsor_id']);
        }

        $conn->commit();

        return [
            'success' => true,
            'message' => 'Dominance Advancement Credit used successfully. Your package is now Dominance.'
        ];
    } catch (Throwable $e) {
        $conn->rollback();

        return [
            'success' => false,
            'message' => $e->getMessage()
        ];
    }
}
?>
