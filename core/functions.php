<?php
// LOGIN LOGS

function logLogin($conn, $user_id, $status) {
    try {
        // Get IP and User Agent
        $ip_address = $_SERVER['REMOTE_ADDR'] ?? 'Unknown';
        $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown';

        // Auto-fix table if login_logs has PRIMARY key without AUTO_INCREMENT
        static $login_logs_checked = false;
        if (!$login_logs_checked) {
            $login_logs_checked = true;
            @$conn->query("ALTER TABLE login_logs MODIFY id INT AUTO_INCREMENT");
        }

        $stmt = $conn->prepare("INSERT INTO login_logs (user_id, login_time, ip_address, user_agent, status) VALUES (?, NOW(), ?, ?, ?)");
        if ($stmt) {
            $stmt->bind_param("isss", $user_id, $ip_address, $user_agent, $status);
            $stmt->execute();
            $stmt->close();
        }
    } catch (Throwable $e) {
        // Silently catch so database log errors never break the login process
        error_log("logLogin non-fatal: " . $e->getMessage());
    }
}

function notifyClientOnProjectProgress($conn, $project_id, $progress) {
    // Get project details and client email
    $stmt = $conn->prepare("SELECT p.project_name, p.client_id, c.client_name, c.email as client_email 
                            FROM projects p 
                            LEFT JOIN clients c ON p.client_id = c.id 
                            WHERE p.id = ?");
    $stmt->bind_param("i", $project_id);
    $stmt->execute();
    $project = $stmt->get_result()->fetch_assoc();
    if (!$project) return;

    $project_name = $project['project_name'];
    $client_name = $project['client_name'];
    $client_id = $project['client_id'];
    
    // Collect all recipient emails
    $recipients = [];
    if (!empty($project['client_email']) && filter_var($project['client_email'], FILTER_VALIDATE_EMAIL)) {
        $recipients[] = $project['client_email'];
    }
    
    // Get emails of main client users (role = 'client') linked to this client
    if ($client_id) {
        $user_stmt = $conn->prepare("SELECT email FROM users WHERE client_id = ? AND role = 'client' AND (status = 'active' OR status IS NULL)");
        $user_stmt->bind_param("i", $client_id);
        $user_stmt->execute();
        $users = $user_stmt->get_result();
        while ($u = $users->fetch_assoc()) {
            if (!empty($u['email']) && filter_var($u['email'], FILTER_VALIDATE_EMAIL) && !in_array($u['email'], $recipients)) {
                $recipients[] = $u['email'];
            }
        }
    }

    if (empty($recipients)) return;

    // Load task_email_helper.php if not already loaded to get sendClientEmail
    require_once __DIR__ . '/task_email_helper.php';

    $subject = "Project Update: " . $project_name . " is now " . $progress . "% completed";
    $heading = "Project Progress Update";
    
    $bodyHtml = "
    <p style='margin: 0 0 20px 0; font-size: 16px; font-weight: 600; color: #0f172a;'>Hello,</p>
    <p style='margin: 0 0 24px 0; color: #475569;'>We have updated the progress for project <strong style='color:#0f172a;'>" . htmlspecialchars($project_name) . "</strong>.</p>
    
    <div style='background: #f8fafc; border-radius: 12px; padding: 24px; border: 1px solid #e2e8f0; margin-bottom: 24px;'>
        <div style='font-size: 14px; color: #64748b; font-weight: 600; margin-bottom: 8px;'>Current Progress:</div>
        <div style='display: flex; align-items: center; gap: 15px;'>
            <div style='font-size: 28px; font-weight: 800; color: #024442;'>" . intval($progress) . "%</div>
            <div style='flex-grow: 1; background: #e2e8f0; height: 12px; border-radius: 6px; overflow: hidden; margin-top: 4px;'>
                <div style='background: #024442; width: " . intval($progress) . "%; height: 100%;'></div>
            </div>
        </div>
    </div>
    <p style='margin: 20px 0 0 0; color: #475569;'>Log in to your client dashboard to view active tasks, download invoices, or upload feedback/requirements.</p>
    ";

    foreach ($recipients as $to_email) {
        sendClientEmail($to_email, $subject, $heading, $bodyHtml, $project_id);
    }
}

function recalculateProjectProgress($conn, $project_id) {
    // Project progress is manually controlled by Admin from the admin panel, not auto-calculated from tasks
    return;
}

function numberToWords($number) {
    $hyphen      = ' ';
    $conjunction = ' and ';
    $separator   = ' ';
    $negative    = 'negative ';
    $decimal     = ' point ';
    $dictionary  = array(
        0                   => 'Zero',
        1                   => 'One',
        2                   => 'Two',
        3                   => 'Three',
        4                   => 'Four',
        5                   => 'Five',
        6                   => 'Six',
        7                   => 'Seven',
        8                   => 'Eight',
        9                   => 'Nine',
        10                  => 'Ten',
        11                  => 'Eleven',
        12                  => 'Twelve',
        13                  => 'Thirteen',
        14                  => 'Fourteen',
        15                  => 'Fifteen',
        16                  => 'Sixteen',
        17                  => 'Seventeen',
        18                  => 'Eighteen',
        19                  => 'Nineteen',
        20                  => 'Twenty',
        30                  => 'Thirty',
        40                  => 'Forty',
        50                  => 'Fifty',
        60                  => 'Sixty',
        70                  => 'Seventy',
        80                  => 'Eighty',
        90                  => 'Ninety',
        100                 => 'Hundred',
        1000                => 'Thousand',
        1000000             => 'Million',
        1000000000          => 'Billion',
        1000000000000       => 'Trillion',
        1000000000000000    => 'Quadrillion',
        1000000000000000000 => 'Quintillion'
    );

    if (!is_numeric($number)) {
        return false;
    }

    if (($number >= 0 && (int) $number < 0) || (int) $number < 0 - PHP_INT_MAX) {
        return false;
    }

    if ($number < 0) {
        return $negative . numberToWords(abs($number));
    }

    $string = null;
    $fraction = null;

    if (strpos($number, '.') !== false) {
        list($number, $fraction) = explode('.', $number);
    }

    switch (true) {
        case $number < 21:
            $string = $dictionary[$number];
            break;
        case $number < 100:
            $tens   = ((int) ($number / 10)) * 10;
            $units  = $number % 10;
            $string = $dictionary[$tens];
            if ($units) {
                $string .= $hyphen . $dictionary[$units];
            }
            break;
        case $number < 1000:
            $hundreds  = $number / 100;
            $remainder = $number % 100;
            $string = $dictionary[(int) $hundreds] . ' ' . $dictionary[100];
            if ($remainder) {
                $string .= $conjunction . numberToWords($remainder);
            }
            break;
        default:
            $baseUnit = pow(1000, floor(log($number, 1000)));
            $numBaseUnits = (int) ($number / $baseUnit);
            $remainder = $number % $baseUnit;
            $string = numberToWords($numBaseUnits) . ' ' . $dictionary[$baseUnit];
            if ($remainder) {
                $string .= $remainder < 100 ? $conjunction : $separator;
                $string .= numberToWords($remainder);
            }
            break;
    }

    return $string;
}

function amountToWords($amount, $currencyCode) {
    $amount = round($amount, 2);
    $whole = (int)$amount;
    $cents = round(($amount - $whole) * 100);
    
    $currencyNames = array(
        'USD' => 'US Dollars',
        'PKR' => 'Pakistani Rupees',
        'EUR' => 'Euros',
        'GBP' => 'British Pounds',
        'AED' => 'UAE Dirhams'
    );
    
    $currencyName = isset($currencyNames[$currencyCode]) ? $currencyNames[$currencyCode] : $currencyCode;
    $wholeWords = numberToWords($whole);
    if ($wholeWords === false || $wholeWords === '') {
        $wholeWords = 'Zero';
    }
    
    if ($cents == 0) {
        return $currencyName . ' ' . $wholeWords . ' Only.';
    } else {
        $centsString = str_pad($cents, 2, '0', STR_PAD_LEFT) . '/100';
        return $currencyName . ' ' . $wholeWords . ' and ' . $centsString . ' Only.';
    }
}
?>