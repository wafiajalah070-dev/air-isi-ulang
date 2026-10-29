<?php
require 'config.php';

// Terima notifikasi dari Midtrans
$json = file_get_contents('php://input');
$data = json_decode($json, true);

if (!$data) exit('Invalid payload');

$orderId       = $data['order_id']           ?? '';
$txStatus      = $data['transaction_status'] ?? '';
$fraudStatus   = $data['fraud_status']       ?? '';
$signatureKey  = $data['signature_key']      ?? '';

// Verifikasi signature
$statusCode    = $data['status_code']        ?? '';
$grossAmount   = $data['gross_amount']       ?? '';
$expectedSig   = hash('sha512', $orderId . $statusCode . $grossAmount . MIDTRANS_SERVER_KEY);

if ($signatureKey !== $expectedSig) {
    http_response_code(403);
    exit('Invalid signature');
}

// Tentukan status pembayaran
$newStatus = null;
if ($txStatus === 'capture') {
    $newStatus = $fraudStatus === 'accept' ? 'paid' : 'failed';
} elseif ($txStatus === 'settlement') {
    $newStatus = 'paid';
} elseif (in_array($txStatus, ['cancel', 'deny', 'failure'])) {
    $newStatus = 'failed';
} elseif ($txStatus === 'expire') {
    $newStatus = 'expired';
} elseif ($txStatus === 'pending') {
    $newStatus = 'pending';
}

if ($newStatus && $orderId) {
    $pdo->prepare("UPDATE penjualan SET payment_status = ? WHERE order_id = ?")
        ->execute([$newStatus, $orderId]);
}

http_response_code(200);
echo 'OK';
