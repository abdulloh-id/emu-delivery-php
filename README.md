# MeaSoft Delivery PHP SDK

MeaSoft Delivery PHP SDK is a lightweight, framework-agnostic client for the MeaSoft (CourierExe) logistics API.

While built to support any courier service running on the MeaSoft platform across 8+ countries, it includes out-of-the-box defaults for regional providers like EMU Express in Uzbekistan.

[![PHP Version](https://img.shields.io/badge/php-%3E%3D8.1-8892BF.svg)](https://php.net)
[![License](https://img.shields.io/badge/license-MIT-blue.svg)](LICENSE)

## Requirements

- PHP 8.1+
- Extensions: `ext-curl`, `ext-pdo`, `ext-simplexml`

## Installation

```bash
composer require abdulloh-id/measoft-delivery-php
```

Import `database/schema.sql` into your MySQL/MariaDB database if using the default PDO repository.

## Configuration

Configure credentials once at application boot:

```php
use AbdullohId\MeasoftDelivery\MeasoftClient;

MeasoftClient::configure(
    login: 'your_login',
    password: 'your_password',
    extra: 123
);
```

## Quick Usage

### Fetching & Syncing Data

```php
use AbdullohId\MeasoftDelivery\MeasoftSync;
use AbdullohId\MeasoftDelivery\Repositories\PdoMeasoftRepository;

$pdo = new PDO('mysql:host=127.0.0.1;dbname=your_db;charset=utf8mb4', 'root', '');
$sync = new MeasoftSync(new PdoMeasoftRepository($pdo));

// Sync regions & towns
$towns = MeasoftSync::getTownList();
$sync->updateRegionList($towns);
$sync->updateTownList($towns);

// Sync pickup points (PVZ)
$pvzList = MeasoftSync::getPvzList();
$sync->updatePvzList($pvzList);
```

## Running Sync via CLI

Copy `.env.example` to `.env` in your root folder, then execute:

```bash
php examples/run_measoft_sync.php
```

Or pass database flags directly:

```bash
php examples/run_measoft_sync.php --host=127.0.0.1 --dbname=my_db --user=root --pass=secret
```

## Constraints

- `MeasoftClient::configure()` stores credentials and the country code as **static, process-wide state**. It is intended for **single-tenant, single-country** usage: call it once at application boot and do not reconfigure it with different values mid-request.
- Do **not** call `configure()` with different credentials or country codes across tenants or requests within the same long-running PHP process (e.g. Swoole, RoadRunner, persistent queue workers). Doing so will overwrite the active configuration for any other code running in that process.
- Multi-country support (`Country` constants + `countryCode` param) covers syncing town/region data and setting a receiver's country on `createOrder()` — it does not add cross-border pricing logic to `calculateCost()`/`calculateCostPvz()`, since sender and receiver are assumed to be in the same configured country.

## Custom Storage Adapters

Implement `AbdullohId\MeasoftDelivery\Contracts\MeasoftStorageInterface` to build custom ORM persistence layers (e.g., Laravel Eloquent, Doctrine).

## License

This project is licensed under the MIT License - see the [LICENSE](LICENSE) file for details.