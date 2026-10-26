<?php
/**
 * admin/print_notice.php
 * Official Clean Notice Print & PDF Generation
 * Clean layout: Graphicafix logo on top left, date, subject, description, and signature place.
 */

error_reporting(0);
ini_set('display_errors', '0');

require_once __DIR__ . '/../core/config.php';

// Auth check
if (empty($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$notice_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
if (!$notice_id) {
    die('Notice ID is required.');
}

if (!$conn) {
    die('Database connection error.');
}

$stmt = $conn->prepare("SELECT * FROM notices WHERE notice_id = ?");
$stmt->bind_param("i", $notice_id);
$stmt->execute();
$notice = $stmt->get_result()->fetch_assoc();

if (!$notice) {
    die('Notice not found or deleted.');
}

// Prepare Notice Data
$title       = trim($notice['notice_title'] ?? 'Official Notice');
$content     = trim(!empty($notice['content']) ? $notice['content'] : ($notice['notice_body'] ?? ''));
$rawDate     = !empty($notice['date']) ? $notice['date'] : ($notice['created_at'] ?? date('Y-m-d'));
$displayDate = date('F d, Y', strtotime($rawDate));
$postedBy    = !empty($notice['created_by']) ? htmlspecialchars($notice['created_by']) : 'Administration';
$refCode     = 'GFX/NOT/' . date('Y', strtotime($rawDate)) . '/' . str_pad($notice_id, 4, '0', STR_PAD_LEFT);

// Logo path & base64 encoding for reliable rendering
$logoPath = __DIR__ . '/../assets/images/logo.png';
$logoSrc  = '../assets/images/logo.png';
$logoBase64 = '';
if (file_exists($logoPath)) {
    $logoBase64 = 'data:image/png;base64,' . base64_encode(file_get_contents($logoPath));
    $logoSrc = $logoBase64;
}

$autoPrint = isset($_GET['auto']) && $_GET['auto'] == '1';

// ── Check if direct mPDF generation is requested (?pdf=1 or ?dl=1) ───────────
if (isset($_GET['pdf']) || isset($_GET['dl'])) {
    require_once __DIR__ . '/../vendor/autoload.php';

    $mpdf = new \Mpdf\Mpdf([
        'mode'          => 'utf-8',
        'format'        => 'A4',
        'margin_left'   => 20,
        'margin_right'  => 20,
        'margin_top'    => 20,
        'margin_bottom' => 20,
        'tempDir'       => sys_get_temp_dir() . '/mpdf'
    ]);

    $mpdf->SetTitle('Official Notice - ' . $title);
    $mpdf->SetAuthor('Graphicafix');

    $html = '
    <!DOCTYPE html>
    <html>
    <head>
    <meta charset="utf-8">
    <style>
        body {
            font-family: Helvetica, Arial, sans-serif;
            color: #1e293b;
            font-size: 13.5px;
            line-height: 1.7;
        }
        table { width: 100%; border-collapse: collapse; }
        .header-table {
            border-bottom: 2px solid #024442;
            padding-bottom: 15px;
            margin-bottom: 30px;
        }
        .header-table td { vertical-align: middle; }
        .meta-text {
            text-align: right;
            font-size: 12px;
            color: #475569;
        }
        .meta-text .doc-badge {
            font-size: 13px;
            font-weight: bold;
            color: #024442;
            letter-spacing: 1px;
            text-transform: uppercase;
        }
        .meta-text .doc-date {
            font-size: 13px;
            font-weight: bold;
            color: #0f172a;
            margin-top: 4px;
        }
        .meta-text .doc-ref {
            font-size: 11px;
            color: #64748b;
            margin-top: 2px;
        }
        .subject-box {
            background-color: #f8fafc;
            border-left: 4px solid #024442;
            padding: 12px 18px;
            margin-bottom: 28px;
        }
        .subject-label {
            font-size: 10px;
            font-weight: bold;
            color: #64748b;
            text-transform: uppercase;
            letter-spacing: 1px;
            margin-bottom: 4px;
        }
        .subject-title {
            font-size: 16px;
            font-weight: bold;
            color: #0f172a;
            line-height: 1.35;
        }
        .notice-body {
            font-size: 13.5px;
            color: #334155;
            line-height: 1.8;
            margin-bottom: 60px;
        }
        .signature-table {
            margin-top: 50px;
            page-break-inside: avoid;
        }
        .signature-table td {
            vertical-align: bottom;
        }
        .sign-line {
            width: 220px;
            border-top: 1.5px solid #0f172a;
            margin-bottom: 8px;
        }
        .sign-title {
            font-size: 13px;
            font-weight: bold;
            color: #0f172a;
        }
        .sign-sub {
            font-size: 11px;
            color: #64748b;
        }
        .footer-note {
            margin-top: 40px;
            border-top: 1px solid #e2e8f0;
            padding-top: 12px;
            font-size: 10px;
            color: #94a3b8;
            text-align: center;
        }
    </style>
    </head>
    <body>
        <table class="header-table">
            <tr>
                <td style="width: 50%;">
                    <img src="' . $logoBase64 . '" style="height: 48px;" alt="Graphicafix" />
                </td>
                <td style="width: 50%;" class="meta-text">
                    <div class="doc-badge">OFFICIAL NOTICE</div>
                    <div class="doc-date">Date: ' . $displayDate . '</div>
                    <div class="doc-ref">Ref: ' . $refCode . '</div>
                </td>
            </tr>
        </table>

        <div class="subject-box">
            <div class="subject-label">Subject</div>
            <div class="subject-title">' . htmlspecialchars($title) . '</div>
        </div>

        <div class="notice-body">
            ' . nl2br(htmlspecialchars($content)) . '
        </div>

        <table class="signature-table">
            <tr>
                <td style="width: 60%; font-size: 11.5px; color: #64748b;">
                    <strong>Issued By:</strong> ' . $postedBy . '<br>
                    <strong>Entity:</strong> Graphicafix Digital Solutions<br>
                    <strong>Status:</strong> Approved & Released
                </td>
                <td style="width: 40%; text-align: center;">
                    <div style="display: inline-block; text-align: center;">
                        <div class="sign-line"></div>
                        <div class="sign-title">Authorized Signatory</div>
                        <div class="sign-sub">Graphicafix Management</div>
                        <div class="sign-sub" style="margin-top:4px;">Date: ____________________</div>
                    </div>
                </td>
            </tr>
        </table>

        <div class="footer-note">
            Graphicafix &bull; Official Communication &bull; Confidential & Intended for Official Recipients Only
        </div>
    </body>
    </html>';

    $mpdf->WriteHTML($html);
    $safeName = preg_replace('/[^a-zA-Z0-9_\-]/', '_', $title);
    $fileName = 'Notice_' . $safeName . '_' . $notice_id . '.pdf';
    
    $dest = isset($_GET['dl']) ? 'D' : 'I';
    $mpdf->Output($fileName, $dest);
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Official Notice: <?= htmlspecialchars($title) ?> — Graphicafix</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * {
            box-sizing: border-box;
            -webkit-print-color-adjust: exact !important;
            print-color-adjust: exact !important;
        }

        body {
            margin: 0;
            padding: 0;
            background-color: #f1f5f9;
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
            color: #1e293b;
            -webkit-font-smoothing: antialiased;
        }

        /* ── Sticky Action Toolbar (Screen only) ── */
        .no-print-toolbar {
            position: sticky;
            top: 0;
            z-index: 9999;
            background: #024442;
            color: #ffffff;
            padding: 12px 24px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            box-shadow: 0 4px 16px rgba(0, 0, 0, 0.15);
        }

        .toolbar-info {
            display: flex;
            align-items: center;
            gap: 12px;
            font-size: 13.5px;
            font-weight: 600;
        }

        .toolbar-info .badge-notice {
            background: rgba(255, 255, 255, 0.2);
            padding: 3px 9px;
            border-radius: 4px;
            font-size: 11.5px;
            letter-spacing: 0.5px;
            text-transform: uppercase;
        }

        .toolbar-actions {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .btn-tb {
            display: inline-flex;
            align-items: center;
            gap: 7px;
            padding: 8px 16px;
            border-radius: 7px;
            font-size: 13px;
            font-weight: 600;
            text-decoration: none;
            cursor: pointer;
            border: none;
            transition: all 0.2s ease;
        }

        .btn-tb-primary {
            background: #ffffff;
            color: #024442;
        }

        .btn-tb-primary:hover {
            background: #f8fafc;
            transform: translateY(-1px);
        }

        .btn-tb-pdf {
            background: #0ea5e9;
            color: #ffffff;
        }

        .btn-tb-pdf:hover {
            background: #0284c7;
        }

        .btn-tb-secondary {
            background: rgba(255, 255, 255, 0.15);
            color: #ffffff;
        }

        .btn-tb-secondary:hover {
            background: rgba(255, 255, 255, 0.25);
        }

        /* ── Printable Paper (A4 size simulator) ── */
        .paper-wrapper {
            padding: 36px 16px 60px 16px;
            display: flex;
            justify-content: center;
        }

        .paper-sheet {
            background: #ffffff;
            width: 100%;
            max-width: 820px;
            min-height: 1050px;
            padding: 60px 70px 60px 70px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.08);
            border-radius: 4px;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            position: relative;
        }

        /* ── Header: Logo on Top Left, Date & Ref on Right ── */
        .doc-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            padding-bottom: 22px;
            border-bottom: 2px solid #024442;
        }

        .doc-logo-box img {
            max-height: 52px;
            width: auto;
            display: block;
        }

        .doc-meta-box {
            text-align: right;
        }

        .doc-meta-badge {
            font-size: 13px;
            font-weight: 800;
            color: #024442;
            letter-spacing: 1px;
            text-transform: uppercase;
        }

        .doc-meta-date {
            font-size: 14px;
            font-weight: 700;
            color: #0f172a;
            margin-top: 4px;
        }

        .doc-meta-ref {
            font-size: 11.5px;
            color: #64748b;
            margin-top: 2px;
            font-weight: 500;
        }

        /* ── Subject Block ── */
        .doc-subject-container {
            margin-top: 36px;
            margin-bottom: 26px;
            background: #f8fafc;
            border-left: 4px solid #024442;
            padding: 14px 20px;
            border-radius: 0 6px 6px 0;
        }

        .doc-subject-label {
            font-size: 11px;
            font-weight: 700;
            color: #64748b;
            text-transform: uppercase;
            letter-spacing: 1px;
            margin-bottom: 4px;
        }

        .doc-subject-title {
            font-size: 19px;
            font-weight: 800;
            color: #0f172a;
            line-height: 1.4;
            letter-spacing: -0.2px;
        }

        /* ── Description / Body ── */
        .doc-body-container {
            font-size: 15px;
            line-height: 1.85;
            color: #334155;
            flex-grow: 1;
            margin-bottom: 50px;
        }

        .doc-body-container p {
            margin: 0 0 16px 0;
        }

        /* ── Sign Place ── */
        .doc-signature-section {
            margin-top: 40px;
            padding-top: 24px;
            display: flex;
            justify-content: space-between;
            align-items: flex-end;
            page-break-inside: avoid;
        }

        .doc-issuer-details {
            font-size: 12.5px;
            color: #64748b;
            line-height: 1.6;
        }

        .doc-issuer-details strong {
            color: #0f172a;
        }

        .doc-sign-box {
            text-align: center;
            min-width: 240px;
        }

        .doc-sign-line {
            width: 220px;
            border-top: 1.5px solid #0f172a;
            margin: 0 auto 10px auto;
        }

        .doc-sign-title {
            font-size: 13.5px;
            font-weight: 700;
            color: #0f172a;
        }

        .doc-sign-org {
            font-size: 12px;
            color: #64748b;
            margin-top: 2px;
        }

        .doc-sign-date {
            font-size: 11.5px;
            color: #64748b;
            margin-top: 6px;
        }

        /* ── Document Footer ── */
        .doc-footer {
            margin-top: 40px;
            padding-top: 14px;
            border-top: 1px dashed #cbd5e1;
            display: flex;
            justify-content: space-between;
            align-items: center;
            font-size: 11px;
            color: #94a3b8;
            page-break-inside: avoid;
        }

        /* ── Print Media Optimization ── */
        @media print {
            @page {
                size: A4 portrait;
                margin: 16mm 18mm 16mm 18mm;
            }

            body {
                background: #ffffff !important;
                margin: 0 !important;
                padding: 0 !important;
                color: #000000 !important;
            }

            .no-print-toolbar {
                display: none !important;
            }

            .paper-wrapper {
                padding: 0 !important;
                display: block !important;
            }

            .paper-sheet {
                box-shadow: none !important;
                border-radius: 0 !important;
                max-width: 100% !important;
                padding: 0 !important;
                min-height: auto !important;
            }

            .doc-header {
                border-bottom: 2px solid #000000 !important;
            }

            .doc-meta-badge {
                color: #000000 !important;
            }

            .doc-subject-container {
                background: #f8fafc !important;
                border-left: 4px solid #000000 !important;
                padding: 12px 18px !important;
            }

            .doc-subject-title {
                color: #000000 !important;
            }

            .doc-body-container {
                color: #111111 !important;
                font-size: 11.5pt !important;
                line-height: 1.8 !important;
            }

            .doc-sign-line {
                border-top: 1.5px solid #000000 !important;
            }

            .doc-signature-section {
                page-break-inside: avoid !important;
                margin-top: 60px !important;
            }

            .doc-footer {
                border-top: 1px solid #cccccc !important;
            }
        }
    </style>
</head>
<body>

    <!-- Top Action Toolbar (Visible on screen, hidden on print) -->
    <div class="no-print-toolbar">
        <div class="toolbar-info">
            <span class="badge-notice"><i class="fas fa-bullhorn"></i> Official Notice</span>
            <span><?= htmlspecialchars($title) ?></span>
        </div>
        <div class="toolbar-actions">
            <button onclick="window.print()" class="btn-tb btn-tb-primary" title="Print or save as clean PDF">
                <i class="fas fa-print"></i> Print to PDF
            </button>
            <a href="print_notice.php?id=<?= $notice_id ?>&pdf=1" class="btn-tb btn-tb-pdf" title="Generate direct PDF using mPDF">
                <i class="fas fa-file-pdf"></i> Download PDF
            </a>
            <a href="notice_details.php?id=<?= $notice_id ?>" class="btn-tb btn-tb-secondary" title="Return to notice details">
                <i class="fas fa-arrow-left"></i> Back
            </a>
        </div>
    </div>

    <!-- Printable Paper Sheet -->
    <div class="paper-wrapper">
        <div class="paper-sheet">

            <!-- Top Header: Logo on Left, Date & Ref on Right -->
            <div class="doc-header">
                <div class="doc-logo-box">
                    <img src="<?= $logoSrc ?>" alt="Graphicafix Logo">
                </div>
                <div class="doc-meta-box">
                    <div class="doc-meta-badge">Official Notice</div>
                    <div class="doc-meta-date"><?= $displayDate ?></div>
                    <div class="doc-meta-ref">Ref: <?= htmlspecialchars($refCode) ?></div>
                </div>
            </div>

            <!-- Subject Block -->
            <div class="doc-subject-container">
                <div class="doc-subject-label">Subject / Purpose</div>
                <div class="doc-subject-title"><?= htmlspecialchars($title) ?></div>
            </div>

            <!-- Description / Body Content -->
            <div class="doc-body-container">
                <?php if (!empty($content)): ?>
                    <p><?= nl2br(htmlspecialchars($content)) ?></p>
                <?php else: ?>
                    <p style="font-style:italic;color:#94a3b8;">No additional details specified for this notice.</p>
                <?php endif; ?>
            </div>

            <!-- Sign Place -->
            <div class="doc-signature-section">
                <div class="doc-issuer-details">
                    <div><strong>Issued By:</strong> <?= $postedBy ?></div>
                    <div><strong>Department:</strong> Administration & Operations</div>
                    <div><strong>Organization:</strong> Graphicafix Digital Solutions</div>
                    <div><strong>Ref ID:</strong> <?= htmlspecialchars($refCode) ?></div>
                </div>

                <div class="doc-sign-box">
                    <div class="doc-sign-line"></div>
                    <div class="doc-sign-title">Authorized Signatory</div>
                    <div class="doc-sign-org">Graphicafix Management</div>
                    <div class="doc-sign-date">Date: ________________________</div>
                </div>
            </div>

            <!-- Footer -->
            <div class="doc-footer">
                <div>Graphicafix &bull; Official Communication</div>
                <div>Issued on <?= $displayDate ?></div>
                <div>Page 1 of 1</div>
            </div>

        </div>
    </div>

    <?php if ($autoPrint): ?>
    <script>
        // Trigger print dialog automatically when invoked via "Print" button
        window.addEventListener('load', function() {
            setTimeout(function() {
                window.print();
            }, 350);
        });
    </script>
    <?php endif; ?>

</body>
</html>
