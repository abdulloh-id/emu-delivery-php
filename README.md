# EMU Delivery PHP SDK

A modern, PSR-4 compliant, framework-agnostic PHP SDK for integrating with the EMU Express Delivery API in Uzbekistan.

[![PHP Version](https://img.shields.io/badge/php-%3E%3D8.1-8892BF.svg)](https://php.net)
[![License](https://img.shields.io/badge/license-MIT-blue.svg)](LICENSE)

## Features

- **Framework Agnostic**: Works seamlessly with Laravel, Symfony, Yii, or plain PHP applications.
- **Decoupled Architecture**: Repository pattern (`EmuStorageInterface`) allows custom database adapters (PDO, Eloquent, Doctrine).
- **Secure**: Parameterized SQL queries via PDO prevent SQL injection vulnerabilities.
- **Transaction Safe**: Uses transaction-bound table refreshes (`DELETE FROM`) to protect data integrity.
- **Type Safe**: Supports modern PHP 8.1+ features, strict types, and robust exception handling.

---

## Requirements

- PHP `8.1` or higher
- `ext-curl`
- `ext-pdo`
- `ext-simplexml`

---

## Installation

Install the package via Composer:

```bash
composer require abdulloh-id/emu-delivery-php