<?php

use Illuminate\Contracts\Http\Kernel;
use Illuminate\Http\Request;

$barrierId = getenv('LARISSAMA_TEST_BARRIER_ID');
$idempotencyKey = getenv('LARISSAMA_TEST_IDEMPOTENCY_KEY');
$token = getenv('LARISSAMA_TEST_TOKEN');
$transactionType = getenv('LARISSAMA_TEST_TRANSACTION_TYPE');
$body = getenv('LARISSAMA_TEST_PAYLOAD');
$dropResponseAfterSuccess = getenv('LARISSAMA_TEST_DROP_RESPONSE_AFTER_SUCCESS') === '1';
$usesBarrier = is_string($barrierId) && $barrierId !== '';

if (
    ($usesBarrier && preg_match('/^[a-f0-9-]{36}$/', $barrierId) !== 1)
    || ! is_string($idempotencyKey)
    || ! is_string($token)
    || ! in_array($transactionType, ['penjualan', 'pembelian'], true)
    || ! is_string($body)
) {
    fwrite(STDERR, "Konfigurasi worker test tidak lengkap.\n");
    exit(2);
}

require __DIR__.'/../../vendor/autoload.php';
$app = require __DIR__.'/../../bootstrap/app.php';
$kernel = $app->make(Kernel::class);
$endpoint = match ($transactionType) {
    'penjualan' => 'penjualans',
    'pembelian' => 'pembelians',
};
$uri = '/api/v1/'.$endpoint;
$request = Request::create($uri, 'POST', [], [], [], [
    'HTTP_ACCEPT' => 'application/json',
    'HTTP_AUTHORIZATION' => 'Bearer '.$token,
    'HTTP_CONTENT_TYPE' => 'application/json',
    'HTTP_HOST' => 'localhost',
    'HTTP_IDEMPOTENCY_KEY' => $idempotencyKey,
    'REMOTE_ADDR' => '127.0.0.1',
    'REQUEST_METHOD' => 'POST',
    'REQUEST_URI' => $uri,
    'SERVER_PROTOCOL' => 'HTTP/1.1',
    'CONTENT_TYPE' => 'application/json',
], $body);

if ($usesBarrier) {
    $barrierFile = sys_get_temp_dir().'/larissama-kernel-barrier-'.$barrierId.'.'.getmypid();
    file_put_contents($barrierFile, '1', LOCK_EX);
    $deadline = microtime(true) + 5;

    do {
        $arrivals = glob(sys_get_temp_dir().'/larissama-kernel-barrier-'.$barrierId.'.*') ?: [];

        if (count($arrivals) >= 2) {
            break;
        }

        usleep(20_000);
    } while (microtime(true) < $deadline);

    if (count($arrivals) < 2) {
        echo json_encode(['status' => 503, 'code' => 'TEST_BARRIER_TIMEOUT'], JSON_THROW_ON_ERROR);
        exit(0);
    }
}

$requestStartedAt = microtime(true);
$response = $kernel->handle($request);
$requestFinishedAt = microtime(true);

if ($dropResponseAfterSuccess && $response->getStatusCode() === 201) {
    exit(97);
}

$content = json_decode($response->getContent(), true);

echo json_encode([
    'status' => $response->getStatusCode(),
    'data' => $content['data'] ?? null,
    'code' => $content['code'] ?? null,
    'request_started_at' => $requestStartedAt,
    'request_finished_at' => $requestFinishedAt,
    'worker_pid' => getmypid(),
], JSON_THROW_ON_ERROR);

$kernel->terminate($request, $response);
