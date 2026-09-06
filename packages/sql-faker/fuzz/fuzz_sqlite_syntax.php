<?php

declare(strict_types=1);

use SqlFaker\Fuzz\Run\FuzzRegistration;
use SqlFaker\Fuzz\Run\FuzzSetup;
use SqlFaker\Fuzz\Target\SqliteSyntaxCheck;

$pdo = new PDO('sqlite::memory:', options: [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
$setup = new FuzzSetup('sqlite', 'sqlite-3.47.2');
$check = new SqliteSyntaxCheck();
$attribute = $pdo->getAttribute(PDO::ATTR_SERVER_VERSION);
$databaseVersion = is_string($attribute) ? $attribute : 'unknown';
/**
 * @var PhpFuzzer\Config $config
 */
FuzzRegistration::register($config, $setup, $check->verify(...), $databaseVersion);
