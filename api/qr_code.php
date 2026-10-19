<?php
/**
 * Standalone Pure-PHP QR Code Generator for Graphicafix
 * Generates crisp, high-resolution QR codes locally with zero external network requests.
 */

require_once __DIR__ . '/../core/config.php';
require_once __DIR__ . '/../core/qrcode.php';

// Disable error reporting output in image response
error_reporting(0);
@ini_set('display_errors', '0');

$emp_id = trim($_GET['id'] ?? ($_GET['emp'] ?? ''));
$direct_data = trim($_GET['data'] ?? ($_GET['url'] ?? ''));
$is_download = isset($_GET['download']) && $_GET['download'] == '1';

$target_url = '';
$canonical_id = 'employee';

if (!empty($direct_data)) {
    $target_url = $direct_data;
    $canonical_id = 'qr_' . substr(md5($direct_data), 0, 8);
} elseif (!empty($emp_id)) {
    $canonical_id = $emp_id;
    // Look up canonical employee ID if possible
    try {
        if ($conn) {
            $stmt = $conn->prepare("SELECT id, employee_id, member_name FROM team WHERE employee_id = ? OR id = ? LIMIT 1");
            if ($stmt) {
                $stmt->bind_param("ss", $emp_id, $emp_id);
                $stmt->execute();
                $res = $stmt->get_result();
                if ($res && $row = $res->fetch_assoc()) {
                    $canonical_id = !empty($row['employee_id']) ? $row['employee_id'] : ('EMP' . str_pad($row['id'], 4, '0', STR_PAD_LEFT));
                }
                $stmt->close();
            }
        }
    } catch (Throwable $e) {
        // Fallback to emp_id directly if DB query fails
    }
    $target_url = BASE_URL . 'verify-employee.php?id=' . urlencode($canonical_id);
} else {
    header("HTTP/1.0 400 Bad Request");
    exit("Missing employee ID or data parameter");
}

// Clean filename
$safe_name = preg_replace('/[^a-zA-Z0-9_-]/', '', $canonical_id);
if (empty($safe_name)) $safe_name = 'qrcode';

// Check cache
$cache_dir = __DIR__ . '/../uploads/qrcodes/';
if (!is_dir($cache_dir)) {
    @mkdir($cache_dir, 0777, true);
}
$cache_file = $cache_dir . $safe_name . '.png';

if (file_exists($cache_file) && (time() - filemtime($cache_file) < 86400 * 30)) {
    header('Content-Type: image/png');
    header('Content-Length: ' . filesize($cache_file));
    header('Cache-Control: public, max-age=2592000');
    if ($is_download) {
        header('Content-Disposition: attachment; filename="' . $safe_name . '_QR.png"');
    }
    readfile($cache_file);
    exit;
}

// Generate QR Code via local engine
try {
    $qr = QRCode::getMinimumQRCode($target_url, QR_ERROR_CORRECT_LEVEL_M);

    // 1. Preferred: GD PNG image (high resolution)
    if (function_exists('imagecreatetruecolor') && function_exists('imagepng')) {
        $im = $qr->createImage(8, 12);

        // Save to cache
        if (is_dir($cache_dir) && is_writable($cache_dir)) {
            @imagepng($im, $cache_file);
        }

        header('Content-Type: image/png');
        header('Cache-Control: public, max-age=2592000');
        if ($is_download) {
            header('Content-Disposition: attachment; filename="' . $safe_name . '_QR.png"');
        }
        imagepng($im);
        imagedestroy($im);
        exit;
    }

    // 2. Vector SVG fallback if GD is somehow disabled
    $mod_count = $qr->getModuleCount();
    $margin = 4;
    $view_size = $mod_count + ($margin * 2);

    $svg = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
    $svg .= '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 ' . $view_size . ' ' . $view_size . '" width="300" height="300" shape-rendering="crispEdges">';
    $svg .= '<rect width="100%" height="100%" fill="#ffffff"/>';
    for ($r = 0; $r < $mod_count; $r++) {
        for ($c = 0; $c < $mod_count; $c++) {
            if ($qr->isDark($r, $c)) {
                $svg .= '<rect x="' . ($c + $margin) . '" y="' . ($r + $margin) . '" width="1" height="1" fill="#000000"/>';
            }
        }
    }
    $svg .= '</svg>';

    header('Content-Type: image/svg+xml');
    if ($is_download) {
        header('Content-Disposition: attachment; filename="' . $safe_name . '_QR.svg"');
    }
    echo $svg;
    exit;

} catch (Throwable $e) {
    header("HTTP/1.0 500 Internal Server Error");
    exit("QR Generation Error: " . $e->getMessage());
}
