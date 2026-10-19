<?php
// core/task_email_helper.php — Reusable well-designed HTML email generator and sender for tasks

if (!function_exists('sendTaskEmail')) {
    function sendTaskEmail($to, $subject, $heading, $bodyHtml, $taskId = 0) {
        if (empty($to) || !filter_var($to, FILTER_VALIDATE_EMAIL)) {
            return false;
        }

        $siteUrl = 'https://graphicafix.com';
        $btnHtml = '';
        if ($taskId > 0) {
            $taskUrl = $siteUrl . '/admin/task_details.php?id=' . intval($taskId);
            $btnHtml = "
            <div style='margin: 32px 0 20px 0; text-align: center;'>
                <a href='{$taskUrl}' style='background: linear-gradient(135deg, #024442 0%, #035e5b 100%); color: #ffffff; text-decoration: none; padding: 14px 32px; border-radius: 8px; font-weight: 700; font-size: 14.5px; display: inline-block; box-shadow: 0 4px 14px rgba(2,68,66,0.25); letter-spacing: 0.3px;'>View Task in Dashboard &rarr;</a>
            </div>";
        }

        $fullHtml = "<!DOCTYPE html>
<html>
<head>
    <meta charset='utf-8'>
    <meta name='viewport' content='width=device-width, initial-scale=1.0'>
    <title>" . htmlspecialchars($heading) . "</title>
</head>
<body style='margin:0; padding:0; background-color:#f4f6f8; font-family:-apple-system, BlinkMacSystemFont, \"Segoe UI\", Roboto, Helvetica, Arial, sans-serif;'>
    <div style='max-width:600px; margin:40px auto; background-color:#ffffff; border-radius:16px; overflow:hidden; box-shadow:0 12px 36px rgba(0,0,0,0.08); border:1px solid #e2e8f0;'>
        <!-- Header -->
        <div style='background: linear-gradient(135deg, #024442 0%, #035e5b 100%); padding:36px 40px; text-align:center; border-bottom:3px solid #95d438;'>
            <div style='margin-bottom:12px;'>
                <img src='{$siteUrl}/assets/images/logo.png' alt='Graphicafix' style='max-height:56px; border:0;'>
            </div>
            <h1 style='margin:0; color:#ffffff; font-size:22px; font-weight:800; letter-spacing:-0.3px;'>" . htmlspecialchars($heading) . "</h1>
        </div>
        
        <!-- Body Content -->
        <div style='padding:40px 40px 24px 40px; font-size:15px; color:#334155; line-height:1.7;'>
            {$bodyHtml}
            {$btnHtml}
        </div>
        
        <!-- Footer -->
        <div style='background-color:#f8fafc; padding:24px 40px; text-align:center; border-top:1px solid #e2e8f0;'>
            <p style='margin:0 0 6px 0; font-size:12.5px; color:#475569; font-weight:700;'>Graphicafix Project &amp; Task Management System</p>
            <p style='margin:0; font-size:12px; color:#94a3b8;'>You received this notification because you are associated with this task on <a href='{$siteUrl}' style='color:#024442; text-decoration:none; font-weight:600;'>Graphicafix</a>.</p>
        </div>
    </div>
</body>
</html>";

        $headers  = "MIME-Version: 1.0\r\n";
        $headers .= "Content-type: text/html; charset=UTF-8\r\n";
        $headers .= "From: Graphicafix Tasks <noreply@graphicafix.com>\r\n";
        $headers .= "Reply-To: noreply@graphicafix.com\r\n";
        $headers .= "X-Mailer: PHP/" . phpversion();

        return @mail($to, $subject, $fullHtml, $headers);
    }
}

if (!function_exists('sendClientEmail')) {
    function sendClientEmail($to, $subject, $heading, $bodyHtml, $projectId = 0) {
        if (empty($to) || !filter_var($to, FILTER_VALIDATE_EMAIL)) {
            return false;
        }

        $siteUrl = 'https://graphicafix.com';
        $btnHtml = '';
        if ($projectId > 0) {
            $projectUrl = $siteUrl . '/admin/client_projects.php?id=' . intval($projectId);
            $btnHtml = "
            <div style='margin: 32px 0 20px 0; text-align: center;'>
                <a href='{$projectUrl}' style='background: linear-gradient(135deg, #024442 0%, #035e5b 100%); color: #ffffff; text-decoration: none; padding: 14px 32px; border-radius: 8px; font-weight: 700; font-size: 14.5px; display: inline-block; box-shadow: 0 4px 14px rgba(2,68,66,0.25); letter-spacing: 0.3px;'>View Project Details &rarr;</a>
            </div>";
        }

        $fullHtml = "<!DOCTYPE html>
<html>
<head>
    <meta charset='utf-8'>
    <meta name='viewport' content='width=device-width, initial-scale=1.0'>
    <title>" . htmlspecialchars($heading) . "</title>
</head>
<body style='margin:0; padding:0; background-color:#f4f6f8; font-family:-apple-system, BlinkMacSystemFont, \"Segoe UI\", Roboto, Helvetica, Arial, sans-serif;'>
    <div style='max-width:600px; margin:40px auto; background-color:#ffffff; border-radius:16px; overflow:hidden; box-shadow:0 12px 36px rgba(0,0,0,0.08); border:1px solid #e2e8f0;'>
        <!-- Header -->
        <div style='background: linear-gradient(135deg, #024442 0%, #035e5b 100%); padding:36px 40px; text-align:center; border-bottom:3px solid #95d438;'>
            <div style='margin-bottom:12px;'>
                <img src='{$siteUrl}/assets/images/logo.png' alt='Graphicafix' style='max-height:56px; border:0;'>
            </div>
            <h1 style='margin:0; color:#ffffff; font-size:22px; font-weight:800; letter-spacing:-0.3px;'>" . htmlspecialchars($heading) . "</h1>
        </div>
        
        <!-- Body Content -->
        <div style='padding:40px 40px 24px 40px; font-size:15px; color:#334155; line-height:1.7;'>
            {$bodyHtml}
            {$btnHtml}
        </div>
        
        <!-- Footer -->
        <div style='background-color:#f8fafc; padding:24px 40px; text-align:center; border-top:1px solid #e2e8f0;'>
            <p style='margin:0 0 6px 0; font-size:12.5px; color:#475569; font-weight:700;'>Graphicafix Client Portal</p>
            <p style='margin:0; font-size:12px; color:#94a3b8;'>You received this notification because you are a client of <a href='{$siteUrl}' style='color:#024442; text-decoration:none; font-weight:600;'>Graphicafix</a>.</p>
        </div>
    </div>
</body>
</html>";

        $headers  = "MIME-Version: 1.0\r\n";
        $headers .= "Content-type: text/html; charset=UTF-8\r\n";
        $headers .= "From: Graphicafix Portal <noreply@graphicafix.com>\r\n";
        $headers .= "Reply-To: noreply@graphicafix.com\r\n";
        $headers .= "X-Mailer: PHP/" . phpversion();

        return @mail($to, $subject, $fullHtml, $headers);
    }
}

