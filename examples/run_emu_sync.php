<?php

require_once __DIR__ . '/../vendor/autoload.php';

use AbdullohId\EmuDelivery\EmuClient;
use AbdullohId\EmuDelivery\EmuSync;
use AbdullohId\EmuDelivery\Repositories\PdoEmuRepository;
use AbdullohId\EmuDelivery\Exceptions\EmuException;

// Lightweight .env reader for local CLI runs
$envFile = __DIR__ . '/../.env';
if (file_exists($envFile)) {
    $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '' || str_starts_with($line, '#')) {
            continue;
        }
        list($name, $value) = explode('=', $line, 2) + [null, null];
        if ($name && $value !== null) {
            $_ENV[trim($name)] = trim($value);
            putenv(trim($name) . '=' . trim($value));
        }
    }
}

// Helper to resolve variables across CLI flags, getenv(), and $_ENV
$getConf = function (string $key, $optionsVal = null) {
    if ($optionsVal !== null && $optionsVal !== false) {
        return $optionsVal;
    }
    $envVal = getenv($key);
    return $envVal !== false ? $envVal : ($_ENV[$key] ?? null);
};

// Parse CLI flags
$options = getopt('', [
    'host::',
    'dbname::',
    'user::',
    'pass::',
    'emu-login::',
    'emu-pass::',
    'emu-extra::',
]);

// 1. Resolve DB Credentials
$dbHost = $getConf('DB_HOST', $options['host'] ?? null);
$dbName = $getConf('DB_DATABASE', $options['dbname'] ?? null);
$dbUser = $getConf('DB_USERNAME', $options['user'] ?? null);
$dbPass = $getConf('DB_PASSWORD', $options['pass'] ?? '') ?? '';

// Guard clause for missing DB settings
if (!$dbHost || !$dbName || !$dbUser) {
    echo "Error: Missing database credentials.\n\n";
    echo "Usage:\n";
    echo "  php examples/run_emu_sync.php --host=127.0.0.1 --dbname=my_db --user=root --pass=secret\n";
    echo "Or copy .env.example to .env and define your database settings.\n";
    exit(1);
}

// 2. Resolve EMU Credentials
$emuLogin = $getConf('EMU_LOGIN', $options['emu-login'] ?? null);
$emuPass  = $getConf('EMU_PASS', $options['emu-pass'] ?? null);
$emuExtra = $getConf('EMU_EXTRA', $options['emu-extra'] ?? null);

if ($emuLogin && $emuPass && $emuExtra) {
    EmuClient::configure($emuLogin, $emuPass, (int)$emuExtra);
}

try {
    echo "Connecting to database `{$dbName}` on `{$dbHost}`...\n";
    $pdo = new PDO("mysql:host={$dbHost};dbname={$dbName};charset=utf8mb4", $dbUser, $dbPass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);

    $repository = new PdoEmuRepository($pdo);
    $sync = new EmuSync($repository);

    echo "Fetching town list...\n";
    $towns = EmuSync::getTownList();
    $sync->updateRegionList($towns);
    $sync->updateTownList($towns);
    echo "Towns and regions updated successfully!\n";

    echo "Fetching PVZ list...\n";
    $pvzList = EmuSync::getPvzList();
    $sync->updatePvzList($pvzList);
    echo "PVZ list updated successfully!\n";

} catch (EmuException $e) {
    echo "SDK Error: " . $e->getMessage() . "\n";
    exit(1);
} catch (PDOException $e) {
    echo "Database Error: " . $e->getMessage() . "\n";
    exit(1);
} catch (Exception $e) {
    echo "General Error: " . $e->getMessage() . "\n";
    exit(1);
}