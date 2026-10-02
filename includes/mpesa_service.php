<?php
/**
 * M-Pesa Daraja API Service
 * Handles OAuth token generation and STK Push initiation.
 */

class MpesaService
{
    private $consumerKey;
    private $consumerSecret;
    private $shortcode;
    private $passkey;
    private $callbackUrl;
    private $environment; // 'sandbox' or 'production'

    public function __construct()
    {
        // Load from environment variables (set these in Render/.env)
        $this->consumerKey    = getenv('MPESA_CONSUMER_KEY') ?: '';
        $this->consumerSecret = getenv('MPESA_CONSUMER_SECRET') ?: '';
        $this->shortcode      = getenv('MPESA_SHORTCODE') ?: '';
        $this->passkey        = getenv('MPESA_PASSKEY') ?: '';
        $this->callbackUrl    = getenv('MPESA_CALLBACK_URL') ?: '';
        $this->environment    = getenv('MPESA_ENVIRONMENT') ?: 'sandbox';
    }

    /**
     * Get the base URL for the API
     */
    private function baseUrl()
    {
        return $this->environment === 'production'
            ? 'https://api.safaricom.co.ke'
            : 'https://sandbox.safaricom.co.ke';
    }

    /**
     * Generate OAuth access token
     */
    public function getAccessToken()
    {
        $url = $this->baseUrl() . '/oauth/v1/generate?grant_type=client_credentials';
        $credentials = base64_encode($this->consumerKey . ':' . $this->consumerSecret);

        $curl = curl_init();
        curl_setopt_array($curl, [
            CURLOPT_URL            => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER     => [
                'Authorization: Basic ' . $credentials,
            ],
            CURLOPT_TIMEOUT        => 30,
        ]);

        $response = curl_exec($curl);
        $httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        $error    = curl_error($curl);
        curl_close($curl);

        if ($error) {
            error_log('M-Pesa OAuth cURL error: ' . $error);
            return null;
        }

        $data = json_decode($response, true);
        if ($httpCode !== 200 || !isset($data['access_token'])) {
            error_log('M-Pesa OAuth failed: HTTP ' . $httpCode . ' — ' . $response);
            return null;
        }

        return $data['access_token'];
    }

    /**
     * Initiate STK Push (Lipa Na M-Pesa Online)
     *
     * @param string $phone    Customer phone in 2547XXXXXXXX format
     * @param int    $amount   Amount in KES (whole numbers only)
     * @param string $reference Account reference (max 12 chars)
     * @param string $description Transaction description
     * @return array|null      Response with CheckoutRequestID or null on failure
     */
    public function stkPush($phone, $amount, $reference, $description = 'Payment')
    {
        $accessToken = $this->getAccessToken();
        if (!$accessToken) {
            return null;
        }

        $url = $this->baseUrl() . '/mpesa/stkpush/v1/processrequest';

        // Generate timestamp and password
        $timestamp = date('YmdHis');
        $password  = base64_encode($this->shortcode . $this->passkey . $timestamp);

        $payload = [
            'BusinessShortCode' => $this->shortcode,
            'Password'          => $password,
            'Timestamp'         => $timestamp,
            'TransactionType'   => 'CustomerPayBillOnline',
            'Amount'            => (int) ceil($amount),
            'PartyA'            => $phone,
            'PartyB'            => $this->shortcode,
            'PhoneNumber'       => $phone,
            'CallBackURL'       => $this->callbackUrl,
            'AccountReference'  => substr($reference, 0, 12),
            'TransactionDesc'   => substr($description, 0, 13),
        ];

        $curl = curl_init();
        curl_setopt_array($curl, [
            CURLOPT_URL            => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => json_encode($payload),
            CURLOPT_HTTPHEADER     => [
                'Content-Type: application/json',
                'Authorization: Bearer ' . $accessToken,
            ],
            CURLOPT_TIMEOUT        => 30,
        ]);

        $response = curl_exec($curl);
        $httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        $error    = curl_error($curl);
        curl_close($curl);

        if ($error) {
            error_log('M-Pesa STK Push cURL error: ' . $error);
            return null;
        }

        $data = json_decode($response, true);
        if ($httpCode !== 200) {
            error_log('M-Pesa STK Push failed: HTTP ' . $httpCode . ' — ' . $response);
            return null;
        }

        return $data;
    }

    /**
     * Normalize phone number to 2547XXXXXXXX format
     */
    public static function normalizePhone($phone)
    {
        $phone = preg_replace('/[^0-9]/', '', $phone);
        if (strpos($phone, '254') === 0) {
            return $phone;
        }
        if (strpos($phone, '0') === 0) {
            return '254' . substr($phone, 1);
        }
        return '254' . $phone;
    }
}
