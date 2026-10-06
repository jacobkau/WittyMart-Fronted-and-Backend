<?php
// ============================================
// SERVER-SIDE EMAILJS SENDER
// Sends emails via EmailJS REST API from PHP,
// bypassing any client-side DNS/adblocker issues.
// ============================================

class EmailJsMailer
{
    private $publicKey;
    private $privateKey;   // optional but recommended for server-side calls
    private $serviceId;
    private $templateId;

    public function __construct()
    {
        $this->publicKey  = getenv('EMAILJS_PUBLIC_KEY')  ?: '';
        $this->privateKey = getenv('EMAILJS_PRIVATE_KEY') ?: '';
        $this->serviceId  = getenv('EMAILJS_SERVICE_ID')  ?: '';
        $this->templateId = getenv('EMAILJS_TEMPLATE_ID') ?: '';
    }

    public function send(array $templateParams): bool
    {
        if (!$this->publicKey || !$this->serviceId || !$this->templateId) {
            error_log('EmailJS server send: missing env vars');
            return false;
        }

        $url = 'https://api.emailjs.com/api/v1.0/email/send';

        $payload = [
            'service_id'      => $this->serviceId,
            'template_id'     => $this->templateId,
            'user_id'         => $this->publicKey,
            'template_params' => $templateParams,
        ];

        // Optional: if you have the private key, include it (more secure)
        if ($this->privateKey) {
            $payload['accessToken'] = $this->privateKey;
        }

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => json_encode($payload),
            CURLOPT_HTTPHEADER     => [
                'Content-Type: application/json',
                'Origin: https://wittymart.onrender.com',
            ],
            CURLOPT_TIMEOUT        => 20,
            CURLOPT_CONNECTTIMEOUT => 8,
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlErr  = curl_error($ch);
        curl_close($ch);

        if ($curlErr) {
            error_log('EmailJS server send cURL error: ' . $curlErr);
            return false;
        }

        if ($httpCode < 200 || $httpCode >= 300) {
            error_log("EmailJS server send failed: HTTP {$httpCode} — {$response}");
            return false;
        }

        return true;
    }
}
