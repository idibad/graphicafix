<?php
/**
 * core/anti_spam.php
 * Unified Enterprise Anti-Spam Security Shield for Graphicafix
 *
 * Provides multi-layer defense against automated bots, link spammers,
 * Cyrillic spam floods, disposable temporary emails, and submission bursts.
 */

if (!defined('GFX_ANTI_SPAM_SALT')) {
    define('GFX_ANTI_SPAM_SALT', 'gfx_sec_shield_2026_w9x8q2v!');
}

if (!defined('GFX_MIN_SUBMISSION_SECONDS')) {
    define('GFX_MIN_SUBMISSION_SECONDS', 3); // humans need >= 3s to fill
}

if (!defined('GFX_MAX_SUBMISSION_SECONDS')) {
    define('GFX_MAX_SUBMISSION_SECONDS', 86400); // 24 hours
}

// Ensure session is active
function gfx_ensure_session(): void {
    if (session_status() === PHP_SESSION_NONE) {
        $is_https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || (isset($_SERVER['SERVER_PORT']) && $_SERVER['SERVER_PORT'] == 443);
        session_set_cookie_params([
            'lifetime' => 86400,
            'path'     => '/',
            'domain'   => '',
            'secure'   => $is_https,
            'httponly' => true,
            'samesite' => 'Lax'
        ]);
        session_start();
    }
}

/**
 * Get or initialize CSRF Token
 */
function gfx_get_csrf_token(): string {
    gfx_ensure_session();
    if (empty($_SESSION['_csrf_token'])) {
        $_SESSION['_csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['_csrf_token'];
}

/**
 * Generate HMAC-signed time trap token for a specific form
 */
function gfx_generate_time_token(string $form_name): string {
    gfx_ensure_session();
    $ts = time();
    $sid = session_id();
    $signature = hash_hmac('sha256', "{$ts}|{$form_name}|{$sid}", GFX_ANTI_SPAM_SALT);
    return "{$ts}.{$signature}";
}

/**
 * Verify HMAC-signed time trap token
 * Returns [bool $valid, string $error]
 */
function gfx_verify_time_token(string $token_str, string $form_name, int $min_seconds = GFX_MIN_SUBMISSION_SECONDS, int $max_seconds = GFX_MAX_SUBMISSION_SECONDS): array {
    gfx_ensure_session();
    if (empty($token_str) || strpos($token_str, '.') === false) {
        return [false, 'Security validation token missing or invalid.'];
    }

    [$ts_str, $signature] = explode('.', $token_str, 2);
    $ts = intval($ts_str);
    if ($ts <= 0) {
        return [false, 'Invalid security timestamp.'];
    }

    $sid = session_id();
    $expected = hash_hmac('sha256', "{$ts}|{$form_name}|{$sid}", GFX_ANTI_SPAM_SALT);
    if (!hash_equals($expected, $signature)) {
        return [false, 'Security signature verification failed.'];
    }

    $elapsed = time() - $ts;
    if ($elapsed < $min_seconds) {
        return [false, 'Form submitted too quickly. Please take a moment to review before submitting.'];
    }
    if ($elapsed > $max_seconds) {
        return [false, 'Form session has expired. Please refresh the page and try again.'];
    }

    return [true, ''];
}

/**
 * Render anti-spam hidden inputs to embed inside any public HTML form
 */
function gfx_render_anti_spam_fields(string $form_name): string {
    $csrf = gfx_get_csrf_token();
    $time_token = gfx_generate_time_token($form_name);

    $html = <<<HTML
    <!-- Anti-Spam Security Shield -->
    <input type="hidden" name="_csrf_token" value="{$csrf}">
    <input type="hidden" name="_time_token" value="{$time_token}">
    <input type="hidden" name="_form_time" id="gfxFormTime_{$form_name}" value="">
    <!-- Honeypot trap fields for automated bots -->
    <div style="position:absolute;left:-9999px;top:-9999px;width:1px;height:1px;overflow:hidden;opacity:0;pointer-events:none;" aria-hidden="true" tabindex="-1">
        <label for="_hp_website_title_{$form_name}">Do not fill this field</label>
        <input type="text" name="_hp_website_title" id="_hp_website_title_{$form_name}" value="" autocomplete="off" tabindex="-1">
        <input type="text" name="_hp_company_fax" value="" autocomplete="off" tabindex="-1">
        <input type="email" name="_hp_secondary_email" value="" autocomplete="off" tabindex="-1">
        <input type="text" name="_hp_website" id="_hp_website" value="" autocomplete="off" tabindex="-1">
    </div>
    <script>
    (function(){
        var ft = document.getElementById('gfxFormTime_{$form_name}');
        if(ft) ft.value = Math.floor(Date.now() / 1000);
    })();
    </script>
HTML;
    return $html;
}

/**
 * Check if any honeypot trap field was filled
 */
function gfx_check_honeypots(): bool {
    $traps = [
        '_hp_website_title',
        '_hp_company_fax',
        '_hp_secondary_email',
        '_hp_website',
        '_honeypot'
    ];
    foreach ($traps as $trap) {
        if (!empty($_POST[$trap])) {
            return false; // Trapped! Honeypot filled by bot
        }
    }
    return true; // Clean
}

/**
 * Get Client IP Address Safely
 */
function gfx_get_client_ip(): string {
    $candidates = [
        $_SERVER['HTTP_CF_CONNECTING_IP'] ?? null,
        $_SERVER['HTTP_X_FORWARDED_FOR'] ?? null,
        $_SERVER['REMOTE_ADDR'] ?? null,
    ];

    foreach ($candidates as $candidate) {
        if (!empty($candidate)) {
            $ips = explode(',', $candidate);
            $ip = trim($ips[0]);
            if (filter_var($ip, FILTER_VALIDATE_IP)) {
                return $ip;
            }
        }
    }
    return '0.0.0.0';
}

/**
 * Check IP Rate Limiting
 */
function gfx_check_rate_limit($conn, string $form_name, int $max_attempts = 5, int $window_seconds = 600): array {
    $ip = gfx_get_client_ip();
    $ip_hash = hash('sha256', $ip . GFX_ANTI_SPAM_SALT);

    // Session fallback check
    gfx_ensure_session();
    if (!isset($_SESSION['_gfx_rate_limit'])) {
        $_SESSION['_gfx_rate_limit'] = [];
    }
    $now = time();
    $recent = array_filter($_SESSION['_gfx_rate_limit'][$form_name] ?? [], function($t) use ($now, $window_seconds) {
        return ($now - $t) < $window_seconds;
    });
    if (count($recent) >= $max_attempts) {
        return [false, 'Too many submissions from this browser. Please wait a few minutes before trying again.'];
    }

    // Database rate limit check if $conn available
    if ($conn instanceof mysqli && @$conn->ping()) {
        try {
            // Prune old records older than 24 hours
            $conn->query("DELETE FROM form_submissions_log WHERE submitted_at < DATE_SUB(NOW(), INTERVAL 24 HOUR)");

            $stmt = $conn->prepare("SELECT COUNT(*) AS cnt FROM form_submissions_log WHERE ip_hash = ? AND form_name = ? AND submitted_at >= DATE_SUB(NOW(), INTERVAL ? SECOND)");
            if ($stmt) {
                $stmt->bind_param("ssi", $ip_hash, $form_name, $window_seconds);
                $stmt->execute();
                $res = $stmt->get_result();
                $count = $res ? ($res->fetch_assoc()['cnt'] ?? 0) : 0;
                $stmt->close();

                if ($count >= $max_attempts) {
                    return [false, 'Too many submissions received from your IP address. Please wait a few minutes before trying again.'];
                }
            }
        } catch (Throwable $e) {
            // Gracefully ignore DB errors and rely on session check
        }
    }

    return [true, ''];
}

/**
 * Log successful or blocked submission
 */
function gfx_log_submission($conn, string $form_name): void {
    $ip = gfx_get_client_ip();
    $ip_hash = hash('sha256', $ip . GFX_ANTI_SPAM_SALT);

    // Record in session
    gfx_ensure_session();
    $_SESSION['_gfx_rate_limit'][$form_name][] = time();

    // Record in DB
    if ($conn instanceof mysqli && @$conn->ping()) {
        try {
            $stmt = $conn->prepare("INSERT INTO form_submissions_log (ip_hash, form_name, submitted_at) VALUES (?, ?, NOW())");
            if ($stmt) {
                $stmt->bind_param("ss", $ip_hash, $form_name);
                $stmt->execute();
                $stmt->close();
            }
        } catch (Throwable $e) {
            // Ignore DB logging error
        }
    }
}

/**
 * Heuristic & Content Anti-Spam Analyzer
 * Inspects form fields for Cyrillic floods, spam links, disposable emails, and malicious patterns.
 * Returns [bool $clean, string $reason]
 */
function gfx_analyze_content(array $fields): array {
    // 1. Cyrillic / Russian spam script detection
    // Graphicafix operates in Pakistan and the UK; automated Cyrillic text in public forms is spam.
    foreach ($fields as $key => $val) {
        if (!is_string($val)) continue;
        if (preg_match('/[\p{Cyrillic}]/u', $val)) {
            return [false, 'Characters from unsupported alphabets are not accepted in submission.'];
        }
    }

    // 2. URL injection in identity fields (Name, Phone, Company)
    $identity_fields = ['name', 'client_name', 'student_name', 'applicant_name', 'company', 'phone', 'client_phone', 'student_phone'];
    foreach ($identity_fields as $f) {
        if (isset($fields[$f]) && is_string($fields[$f])) {
            $val = trim($fields[$f]);
            if (preg_match('/(https?:\/\/|www\.|\b[a-z0-9-]+\.(com|ru|xyz|top|online|net|org|biz|info|site)\b)/i', $val)) {
                return [false, 'Links or website addresses are not allowed in the name, company, or phone fields.'];
            }
        }
    }

    // 3. Email domain validation & Disposable Email Blocker
    if (!empty($fields['email']) && is_string($fields['email'])) {
        $email = strtolower(trim($fields['email']));
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return [false, 'Please enter a valid email address.'];
        }

        $email_domain = substr(strrchr($email, "@"), 1);
        $blocked_domains = [
            'smaqty.com', 'educomb.us', 'mail.net', 'mailinator.com', 'tempmail.com',
            'guerrillamail.com', '10minutemail.com', 'trashmail.com', 'sharklasers.com',
            'yopmail.com', 'dispostable.com', 'fakeinbox.com', 'throwawaymail.com',
            'getairmail.com', 'burnermail.io', 'temp-mail.org', 'inboxkitten.com',
            'crazymailing.com', 'mohmal.com', 'generator.email', 'fakemail.net',
            'emailondeck.com', 'tempail.com', 'nada.ltd', 'dropmail.me', 'mytemp.email',
            'tempinbox.com', 'disposablemail.com', 'boximail.com', 'trashmail.net',
            'burnermail.com', 'fakemailgenerator.com', 'maildrop.cc'
        ];

        if (in_array($email_domain, $blocked_domains, true)) {
            return [false, 'Temporary or disposable email addresses are not accepted. Please provide a standard email.'];
        }
    }

    // 4. Message / Description URL & Shortener abuse check
    $content_fields = ['message', 'description', 'project_description', 'bio', 'suggestions', 'course_feedback'];
    foreach ($content_fields as $f) {
        if (!empty($fields[$f]) && is_string($fields[$f])) {
            $msg = $fields[$f];

            // Block common URL shorteners used by bots
            if (preg_match('/(tinyurl\.com|bit\.ly|t\.co|cutt\.ly|is\.gd|rb\.gy|clck\.ru|v\.gd|goo\.gl|ow\.ly|buff\.ly|shorte\.st|adf\.ly)/i', $msg)) {
                return [false, 'Shortened URLs are not allowed in the project or inquiry description.'];
            }

            // Limit maximum number of URLs in a single inquiry to 2
            $url_count = preg_match_all('/https?:\/\//i', $msg);
            if ($url_count > 2) {
                return [false, 'Too many external links detected. Please describe your project in text.'];
            }
        }
    }

    // 5. Spam Keyword / Bot Phrases Filter
    $spam_phrases = [
        'casino online', 'free crypto', 'crypto giveaway', 'viagra', 'cialis',
        'great-value bags at wholesale prices', 'shop now →', 'exclusive prize',
        'winner selected', 'lottery winner', 'whatsapp marketing software'
    ];
    $combined_text = strtolower(implode(' ', array_filter($fields, 'is_string')));
    foreach ($spam_phrases as $phrase) {
        if (strpos($combined_text, $phrase) !== false) {
            return [false, 'Message was flagged by automated promotional spam detection.'];
        }
    }

    return [true, ''];
}

/**
 * Master Verification Pipeline
 * Validates rate limit, honeypots, CSRF, time trap, and content heuristics.
 *
 * @param mysqli|null $conn Database connection
 * @param string $form_name Form identifier (e.g. 'contact', 'project_request', 'subscribe')
 * @param array $fields Form payload array
 * @param array $options Configuration overrides
 * @return array ['success' => bool, 'error' => string, 'is_bot' => bool]
 */
function gfx_verify_form_submission($conn, string $form_name, array $fields = [], array $options = []): array {
    $require_csrf = $options['require_csrf'] ?? true;
    $require_time = $options['require_time'] ?? true;
    $max_attempts = $options['max_attempts'] ?? 5;
    $window_secs  = $options['window_seconds'] ?? 600; // 10 minutes

    // 1. Check Rate Limit first
    [$rl_pass, $rl_err] = gfx_check_rate_limit($conn, $form_name, $max_attempts, $window_secs);
    if (!$rl_pass) {
        return ['success' => false, 'error' => $rl_err, 'is_bot' => false];
    }

    // 2. Check Honeypots
    if (!gfx_check_honeypots()) {
        return ['success' => false, 'error' => 'Automated submission detected.', 'is_bot' => true];
    }

    // 3. Check CSRF Token
    if ($require_csrf) {
        gfx_ensure_session();
        $submitted_csrf = trim($_POST['_csrf_token'] ?? '');
        $session_csrf   = trim($_SESSION['_csrf_token'] ?? '');

        if (empty($submitted_csrf) || empty($session_csrf) || !hash_equals($session_csrf, $submitted_csrf)) {
            return ['success' => false, 'error' => 'Security token invalid or expired. Please refresh the page.', 'is_bot' => true];
        }
    }

    // 4. Check Time Trap
    if ($require_time) {
        $time_token = trim($_POST['_time_token'] ?? '');
        if (!empty($time_token)) {
            [$time_pass, $time_err] = gfx_verify_time_token($time_token, $form_name);
            if (!$time_pass) {
                return ['success' => false, 'error' => $time_err, 'is_bot' => true];
            }
        } else {
            // Check fallback JS timestamp if _time_token wasn't rendered
            $js_time = intval($_POST['_form_time'] ?? 0);
            if ($js_time > 0) {
                $elapsed = time() - $js_time;
                if ($elapsed < GFX_MIN_SUBMISSION_SECONDS) {
                    return ['success' => false, 'error' => 'Form submitted too quickly. Please take a moment.', 'is_bot' => true];
                }
            } else {
                return ['success' => false, 'error' => 'Security verification missing. Please enable JavaScript and refresh.', 'is_bot' => true];
            }
        }
    }

    // 5. Check Content Heuristics
    [$content_pass, $content_err] = gfx_analyze_content($fields);
    if (!$content_pass) {
        return ['success' => false, 'error' => $content_err, 'is_bot' => true];
    }

    // Log the successful pass
    gfx_log_submission($conn, $form_name);

    return ['success' => true, 'error' => '', 'is_bot' => false];
}
