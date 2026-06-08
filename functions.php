<?php
include 'config.php';

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

function processCommunityBonus($conn, $member_id, $quantity) {
    $uplines = getUpline($conn, $member_id, 8);

    foreach ($uplines as $level => $upline_id) {
        $bonus = $quantity * 5;

        $stmt = $conn->prepare("INSERT INTO community_bonus_ledger (member_id, from_member_id, level, amount) VALUES (?, ?, ?, ?)");
        $stmt->bind_param("iiid", $upline_id, $member_id, $level, $bonus);

        if (!$stmt->execute()) {
            throw new Exception("Unable to insert community bonus ledger entry.");
        }

        addBonus($conn, $upline_id, $bonus, "community", "Level $level bonus from member $member_id");
    }
}

function getBalance($conn, $member_id) {
    $stmt = $conn->prepare("SELECT SUM(amount) as total FROM bonus_ledger WHERE member_id=?");
    $stmt->bind_param("i", $member_id);
    $stmt->execute();
    $res = $stmt->get_result()->fetch_assoc();
    return isset($res['total']) ? (float)$res['total'] : 0;
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
        return insertCashbackReward(
            $conn,
            $member_id,
            $package_id,
            "direct_dominance",
            $directs['direct_dominance'],
            getPackagePrice($conn, $package_ids['dominance']),
            "Withdrawable Dominance cashback bonus"
        );
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

    $conn->begin_transaction();

    try {
        $stmt = $conn->prepare("
            SELECT id, package_id, status
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
