
<?php

require __DIR__ . '/../config/bootstrap.php';

require_role('student');

// Get payment ID
$pid = (int)($_GET['payment'] ?? 0);

// Find pending M-Pesa payment belonging to logged-in student
$stmt = $pdo->prepare("
    SELECT *
    FROM payments
    WHERE id = ?
    AND user_id = ?
    AND provider = 'mpesa'
    AND status = 'pending'
");

$stmt->execute([$pid, current_user()['id']]);
$p = $stmt->fetch();

if (!$p) {
    exit('Payment not found.');
}

// Get and normalize student's phone number
$phone = preg_replace('/\D/', '', current_user()['phone'] ?? '');

if (str_starts_with($phone, '0')) {
    $phone = '254' . substr($phone, 1);
} elseif (
    str_starts_with($phone, '7') ||
    str_starts_with($phone, '1')
) {
    $phone = '254' . $phone;
}

// Validate Kenyan mobile number
if (!preg_match('/^254[17]\d{8}$/', $phone)) {
    exit('Add a valid Kenyan mobile number to your profile before using M-Pesa.');
}

// Load Daraja credentials
$key = envv('MPESA_CONSUMER_KEY');
$secret = envv('MPESA_CONSUMER_SECRET');
$passkey = envv('MPESA_PASSKEY');
$callback = envv('MPESA_CALLBACK_URL');

if (
    !$key ||
    str_contains($key, 'REPLACE') ||
    !$secret ||
    str_contains($secret, 'REPLACE') ||
    !$passkey ||
    str_contains($passkey, 'REPLACE') ||
    !str_starts_with($callback, 'https://')
) {
    exit('Configure Daraja sandbox credentials and an HTTPS ngrok callback URL in .env.');
}

// Select Daraja environment
$host = envv('MPESA_ENV', 'sandbox') === 'production'
    ? 'https://api.safaricom.co.ke'
    : 'https://sandbox.safaricom.co.ke';

// Authenticate with Daraja
$ch = curl_init(
    $host . '/oauth/v1/generate?grant_type=client_credentials'
);

curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_USERPWD => $key . ':' . $secret,
    CURLOPT_TIMEOUT => 30
]);

$tokenRaw = curl_exec($ch);
$tokenData = json_decode((string)$tokenRaw, true);
$tokenError = curl_error($ch);

curl_close($ch);

$token = $tokenData['access_token'] ?? '';

if (!$token) {
    exit('Could not authenticate with Daraja. Check credentials. ' . $tokenError);
}

// Generate STK Push password
$timestamp = date('YmdHis');

$shortcode = envv('MPESA_SHORTCODE');

$password = base64_encode(
    $shortcode . $passkey . $timestamp
);

// Prepare STK Push request
$body = [
    'BusinessShortCode' => $shortcode,
    'Password' => $password,
    'Timestamp' => $timestamp,
    'TransactionType' => envv(
        'MPESA_TRANSACTION_TYPE',
        'CustomerPayBillOnline'
    ),
    'Amount' => (int)$p['amount_kes'],
    'PartyA' => $phone,
    'PartyB' => $shortcode,
    'PhoneNumber' => $phone,
    'CallBackURL' => $callback,
    'AccountReference' => $p['reference'],
    'TransactionDesc' => 'Course enrollment'
];

// Send STK Push request
$ch = curl_init(
    $host . '/mpesa/stkpush/v1/processrequest'
);

curl_setopt_array($ch, [
    CURLOPT_POST => true,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HTTPHEADER => [
        'Authorization: Bearer ' . $token,
        'Content-Type: application/json'
    ],
    CURLOPT_POSTFIELDS => json_encode($body),
    CURLOPT_TIMEOUT => 30
]);

$raw = curl_exec($ch);

$code = curl_getinfo($ch, CURLINFO_HTTP_CODE);

$curlError = curl_error($ch);

curl_close($ch);

$data = json_decode((string)$raw, true);

// Handle successful STK Push request
if (
    $code >= 200 &&
    $code < 300 &&
    !empty($data['CheckoutRequestID'])
) {

    $up = $pdo->prepare("
        UPDATE payments
        SET provider_reference = ?,
            provider_payload = ?
        WHERE id = ?
    ");

    $up->execute([
        $data['CheckoutRequestID'],
        json_encode($data),
        $pid
    ]);

    $_SESSION['mpesa_waiting'] = $pid;

    flash(
        'M-Pesa prompt sent. Complete the prompt on your phone, then refresh your dashboard.'
    );

    redirect('student/dashboard.php');
}

// Handle failed STK Push request
http_response_code(502);

$error = $data['errorMessage']
    ?? $data['ResponseDescription']
    ?? $curlError
    ?? 'Unknown provider response';

exit('M-Pesa STK Push request failed: ' . e($error));

