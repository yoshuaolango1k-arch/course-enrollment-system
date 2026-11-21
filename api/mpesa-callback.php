
<?php

require __DIR__ . '/../config/bootstrap.php';

header('Content-Type: application/json');

// Receive Safaricom callback
$body = json_decode(file_get_contents('php://input'), true);

$result = $body['Body']['stkCallback'] ?? null;

if (!$result) {
    http_response_code(400);
    echo json_encode([
        'ResultCode' => 1,
        'ResultDesc' => 'Invalid callback'
    ]);
    exit;
}

// Extract callback information
$checkout = (string)($result['CheckoutRequestID'] ?? '');
$merchant = (string)($result['MerchantRequestID'] ?? '');
$resultCode = (int)($result['ResultCode'] ?? -1);
$resultDesc = (string)($result['ResultDesc'] ?? 'Unknown result');

if ($checkout === '') {
    http_response_code(400);
    echo json_encode([
        'ResultCode' => 1,
        'ResultDesc' => 'Missing checkout reference'
    ]);
    exit;
}

// Find matching payment
$stmt = $pdo->prepare("
    SELECT *
    FROM payments
    WHERE provider = 'mpesa'
    AND provider_reference = ?
");

$stmt->execute([$checkout]);
$payment = $stmt->fetch();

if (!$payment) {
    echo json_encode([
        'ResultCode' => 0,
        'ResultDesc' => 'Accepted'
    ]);
    exit;
}

// Extract original initialization response
$init = json_decode(
    (string)$payment['provider_payload'],
    true
) ?? [];

$initMerchant = (string)(
    $init['MerchantRequestID'] ?? ''
);

// Extract callback metadata
$metadata = [];

foreach (
    ($result['CallbackMetadata']['Item'] ?? []) as $item
) {
    if (isset($item['Name'])) {
        $metadata[$item['Name']] = $item['Value'] ?? null;
    }
}

$amount = (float)($metadata['Amount'] ?? 0);

$receipt = trim(
    (string)($metadata['MpesaReceiptNumber'] ?? '')
);

// Verify successful payment
$success =
    $resultCode === 0 &&
    $merchant !== '' &&
    $merchant === $initMerchant &&
    $amount === (float)$payment['amount_kes'] &&
    $receipt !== '';

try {

    $pdo->beginTransaction();

    // Lock payment to prevent duplicate processing
    $lock = $pdo->prepare("
        SELECT *
        FROM payments
        WHERE id = ?
        FOR UPDATE
    ");

    $lock->execute([$payment['id']]);
    $current = $lock->fetch();

    if (
        $current &&
        !in_array($current['status'], ['success', 'failed'], true)
    ) {

        $status = $success ? 'success' : 'failed';

        // Save payment result
        $update = $pdo->prepare("
            UPDATE payments
            SET
                status = ?,
                provider_receipt = ?,
                provider_payload = ?,
                verified_at = IF(? = 'success', NOW(), verified_at)
            WHERE id = ?
        ");

        $update->execute([
            $status,
            $receipt !== '' ? $receipt : null,
            json_encode($body),
            $status,
            $payment['id']
        ]);

        // Activate enrollment only after verified success
        if ($success) {

            $enrollment = $pdo->prepare("
                INSERT INTO enrollments
                    (user_id, course_id, payment_id, status)
                VALUES (?, ?, ?, 'active')
                ON DUPLICATE KEY UPDATE
                    status = 'active',
                    payment_id = VALUES(payment_id)
            ");

            $enrollment->execute([
                $payment['user_id'],
                $payment['course_id'],
                $payment['id']
            ]);
        }
    }

    $pdo->commit();

} catch (Throwable $e) {

    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    error_log('M-Pesa callback processing error: ' . $e->getMessage());

    http_response_code(500);

    echo json_encode([
        'ResultCode' => 1,
        'ResultDesc' => 'Processing error'
    ]);

    exit;
}

// Acknowledge callback
echo json_encode([
    'ResultCode' => 0,
    'ResultDesc' => 'Accepted'
]);

