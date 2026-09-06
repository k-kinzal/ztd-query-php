<?php

declare(strict_types=1);

use SqlFaker\Fuzz\Input\CorpusPreparation;
use SqlFaker\Fuzz\Run\FuzzSetup;

require dirname(__DIR__) . '/vendor/autoload.php';

$database = $argv[1] ?? 'sqlite';
$version = match ($database) {
    'mysql' => 'mysql-' . FuzzSetup::environment('MYSQL_VERSION', '8.4.7'),
    'pg' => 'pg-17.2',
    'sqlite' => 'sqlite-3.47.2',
    default => throw new InvalidArgumentException('Expected mysql, pg or sqlite.'),
};
$setup = new FuzzSetup($database, $version, false);
$directory = FuzzSetup::directory(__DIR__ . '/seeds/generated/' . $database . '/' . $setup->coverage->inventory()->fingerprint);
$reviewed = glob(__DIR__ . '/seeds/reviewed/' . $database . '/*.bin');
foreach ($reviewed === false ? [] : $reviewed as $seed) {
    copy($seed, $setup->corpus . '/' . basename($seed));
}
$report = (new CorpusPreparation())->prepare($setup, $directory);
file_put_contents($setup->reports . '/seed-inventory.json', json_encode($report, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));
$counts = array_count_values(array_column($report, 'status'));
fwrite(STDOUT, json_encode($counts, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR) . "\n");
