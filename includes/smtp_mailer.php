<?php
/**
 * Sudarshan Yuvak Mandal - Enterprise Native SMTP Client
 * High-performance Gmail & TLS/SSL SMTP Delivery Service
 */

declare(strict_types=1);

require_once __DIR__ . '/../config/otp_config.php';

class SmtpMailer {

    /**
     * Send Real OTP Email via Gmail / TLS SMTP (Strict Production Delivery)
     */
    public static function sendOTPEmail(string $toEmail, string $otpCode, string $purposeName, string $userName = 'Member'): array {
        $subject = "Sudarshan Yuvak Mandal - Your OTP Code is {$otpCode}";
        
        $htmlBody = "
        <div style='font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto; border: 1px solid #e2e8f0; border-radius: 12px; overflow: hidden; background: #ffffff;'>
            <div style='background: linear-gradient(135deg, #DA4D12 0%, #FF9933 100%); padding: 24px; text-align: center; color: #ffffff;'>
                <h2 style='margin: 0; font-size: 24px;'>Sudarshan Yuvak Mandal</h2>
                <p style='margin: 4px 0 0 0; font-size: 14px; opacity: 0.9;'>Sheri No.1, Ranchhod Nagar Society, Bhathena, Surat</p>
            </div>
            <div style='padding: 32px; text-align: center;'>
                <p style='font-size: 16px; color: #334155; margin-top: 0;'>Namaste <strong>" . htmlspecialchars($userName) . "</strong>,</p>
                <p style='font-size: 15px; color: #64748b;'>Use the following One-Time Password (OTP) to complete your <strong>" . htmlspecialchars($purposeName) . "</strong> request:</p>
                <div style='background: #fff7ed; border: 2px dashed #ff9933; border-radius: 12px; padding: 20px; display: inline-block; margin: 20px 0;'>
                    <span style='font-size: 36px; font-weight: 800; letter-spacing: 8px; color: #da4d12;'>" . htmlspecialchars($otpCode) . "</span>
                </div>
                <p style='font-size: 14px; color: #94a3b8;'>This OTP is valid for <strong>5 minutes</strong>. Please do not share this code with anyone.</p>
            </div>
            <div style='background: #f8fafc; padding: 16px; text-align: center; font-size: 12px; color: #94a3b8; border-top: 1px solid #e2e8f0;'>
                &copy; " . date('Y') . " Sudarshan Yuvak Mandal, Surat. All rights reserved.
            </div>
        </div>";

        return self::sendSocketMail(
            SMTP_HOST,
            SMTP_PORT,
            SMTP_ENCRYPTION,
            SMTP_USERNAME,
            SMTP_PASSWORD,
            SMTP_FROM_EMAIL,
            SMTP_FROM_NAME,
            $toEmail,
            $subject,
            $htmlBody
        );
    }

    /**
     * Send Real Admin Notification Email for New Member Request (Expense / Income / Booking / Donation)
     */
    public static function sendAdminNotificationEmail(string $adminEmail, array $requestData): array {
        $subject = "Sudarshan Yuvak Mandal - New Member Request Submitted: " . $requestData['title'];
        
        $privacyLabel = ($requestData['is_hidden'] == 1) ? "<span style='color: #d97706; font-weight: bold;'>Private / Hidden Request</span>" : "<span style='color: #059669; font-weight: bold;'>Public Request</span>";

        $htmlBody = "
        <div style='font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto; border: 1px solid #e2e8f0; border-radius: 12px; overflow: hidden; background: #ffffff;'>
            <div style='background: linear-gradient(135deg, #DA4D12 0%, #FF9933 100%); padding: 20px; text-align: center; color: #ffffff;'>
                <h3 style='margin: 0; font-size: 20px;'>New Member Request Pending Review</h3>
                <p style='margin: 4px 0 0 0; font-size: 13px; opacity: 0.9;'>Sudarshan Yuvak Mandal, Surat</p>
            </div>
            <div style='padding: 24px; color: #334155;'>
                <p style='font-size: 15px; margin-top: 0;'>A member has submitted a new request for Admin review and approval:</p>
                <table style='width: 100%; border-collapse: collapse; margin: 16px 0; font-size: 14px;'>
                    <tr style='border-bottom: 1px solid #f1f5f9;'><td style='padding: 8px 0; color: #64748b; width: 120px;'>Submitter:</td><td style='padding: 8px 0; font-weight: bold;'>" . htmlspecialchars($requestData['member_name']) . " (" . htmlspecialchars($requestData['member_email']) . ")</td></tr>
                    <tr style='border-bottom: 1px solid #f1f5f9;'><td style='padding: 8px 0; color: #64748b;'>Request Type:</td><td style='padding: 8px 0; font-weight: bold; text-transform: uppercase;'>" . htmlspecialchars($requestData['request_type']) . " (" . htmlspecialchars($requestData['category']) . ")</td></tr>
                    <tr style='border-bottom: 1px solid #f1f5f9;'><td style='padding: 8px 0; color: #64748b;'>Title:</td><td style='padding: 8px 0; font-weight: bold;'>" . htmlspecialchars($requestData['title']) . "</td></tr>
                    <tr style='border-bottom: 1px solid #f1f5f9;'><td style='padding: 8px 0; color: #64748b;'>Amount:</td><td style='padding: 8px 0; font-weight: bold; color: #da4d12;'>₹ " . number_format((float)$requestData['amount'], 2) . "</td></tr>
                    <tr style='border-bottom: 1px solid #f1f5f9;'><td style='padding: 8px 0; color: #64748b;'>Event Date:</td><td style='padding: 8px 0;'>" . htmlspecialchars($requestData['event_date']) . "</td></tr>
                    <tr style='border-bottom: 1px solid #f1f5f9;'><td style='padding: 8px 0; color: #64748b;'>Visibility:</td><td style='padding: 8px 0;'>" . $privacyLabel . "</td></tr>
                    <tr><td style='padding: 8px 0; color: #64748b;'>Description:</td><td style='padding: 8px 0;'>" . htmlspecialchars($requestData['description'] ?? 'N/A') . "</td></tr>
                </table>
                <div style='text-align: center; margin-top: 24px;'>
                    <p style='font-size: 13px; color: #64748b;'>Please log into the Mandal Admin Portal to approve or reject this request.</p>
                </div>
            </div>
        </div>";

        return self::sendSocketMail(
            SMTP_HOST,
            SMTP_PORT,
            SMTP_ENCRYPTION,
            SMTP_USERNAME,
            SMTP_PASSWORD,
            SMTP_FROM_EMAIL,
            SMTP_FROM_NAME,
            $adminEmail,
            $subject,
            $htmlBody
        );
    }

    /**
     * Native PHP Socket SMTP Transport Engine
     */
    private static function sendSocketMail(
        string $host,
        int $port,
        string $encryption,
        string $username,
        string $password,
        string $fromEmail,
        string $fromName,
        string $toEmail,
        string $subject,
        string $htmlBody
    ): array {
        if (empty($username) || empty($password)) {
            return [
                'success' => false,
                'mode' => 'disabled',
                'message' => 'SMTP credentials not configured in .env'
            ];
        }

        $prefix = ($encryption === 'ssl') ? 'ssl://' : '';
        $socket = @fsockopen($prefix . $host, $port, $errno, $errstr, 10);

        if (!$socket) {
            return ['success' => false, 'message' => "Could not connect to {$host}:{$port}. Error: {$errstr}"];
        }

        stream_set_timeout($socket, 10);

        $readResponse = function() use ($socket): string {
            $response = '';
            while ($line = fgets($socket, 512)) {
                $response .= $line;
                if (isset($line[3]) && $line[3] === ' ') break;
            }
            return $response;
        };

        $sendCommand = function(string $cmd) use ($socket, $readResponse): string {
            fputs($socket, $cmd . "\r\n");
            return $readResponse();
        };

        $resp = $readResponse(); // Initial banner
        $resp = $sendCommand("EHLO " . gethostname());

        if ($encryption === 'tls') {
            $resp = $sendCommand("STARTTLS");
            if (strpos($resp, '220') === false) {
                fclose($socket);
                return ['success' => false, 'message' => "STARTTLS failed: {$resp}"];
            }
            if (!stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT | STREAM_CRYPTO_METHOD_TLSv1_3_CLIENT)) {
                fclose($socket);
                return ['success' => false, 'message' => "TLS encryption handshake failed."];
            }
            $resp = $sendCommand("EHLO " . gethostname());
        }

        $resp = $sendCommand("AUTH LOGIN");
        if (strpos($resp, '334') === false) {
            fclose($socket);
            return ['success' => false, 'message' => "AUTH LOGIN command rejected: {$resp}"];
        }

        $resp = $sendCommand(base64_encode($username));
        if (strpos($resp, '334') === false) {
            fclose($socket);
            return ['success' => false, 'message' => "SMTP Username rejected by {$host}: {$resp}"];
        }

        $resp = $sendCommand(base64_encode($password));
        if (strpos($resp, '235') === false) {
            fclose($socket);
            return ['success' => false, 'message' => "SMTP Authentication failed. Please check your Gmail App Password."];
        }

        $resp = $sendCommand("MAIL FROM: <{$fromEmail}>");
        if (strpos($resp, '250') === false) {
            fclose($socket);
            return ['success' => false, 'message' => "MAIL FROM failed: {$resp}"];
        }

        $resp = $sendCommand("RCPT TO: <{$toEmail}>");
        if (strpos($resp, '250') === false) {
            fclose($socket);
            return ['success' => false, 'message' => "RCPT TO failed for {$toEmail}: {$resp}"];
        }

        $resp = $sendCommand("DATA");
        if (strpos($resp, '354') === false) {
            fclose($socket);
            return ['success' => false, 'message' => "DATA command rejected: {$resp}"];
        }

        $headers = [
            "MIME-Version: 1.0",
            "Content-Type: text/html; charset=UTF-8",
            "From: {$fromName} <{$fromEmail}>",
            "To: {$toEmail}",
            "Subject: {$subject}",
            "Date: " . date('r'),
            "X-Mailer: Sudarshan Yuvak Mandal SMTP Mailer"
        ];

        $messageContent = implode("\r\n", $headers) . "\r\n\r\n" . $htmlBody . "\r\n.";
        $resp = $sendCommand($messageContent);
        $sendCommand("QUIT");
        fclose($socket);

        if (strpos($resp, '250') !== false) {
            return ['success' => true, 'message' => 'Email sent successfully via Gmail SMTP.'];
        } else {
            return ['success' => false, 'message' => "Failed to deliver email: {$resp}"];
        }
    }
}
