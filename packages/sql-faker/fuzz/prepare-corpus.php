<?php

declare(strict_types=1);

namespace SqlFaker\Fuzz;

use Faker\Factory;
use InvalidArgumentException;
use LogicException;
use RuntimeException;
use SqlFaker;
use SqlFaker\Coverage\GeneratorRevision;
use SqlFaker\Coverage\GrammarCoverage;
use SqlFaker\Grammar\Derivation\GenerationPlan;
use SqlFaker\Grammar\Derivation\ProductionWitness;
use SqlFaker\Grammar\GenerationException;
use SqlFaker\Grammar\LexicalException;
use SqlFaker\MySqlProvider;
use SqlFaker\PostgreSqlProvider;
use SqlFaker\SqliteProvider;

require dirname(__DIR__) . '/vendor/autoload.php';

$database = $argv[1] ?? 'sqlite';
$faker = Factory::create();
$coverage = new GrammarCoverage();
$mysqlVersion = 'mysql-' . (getenv('MYSQL_VERSION') !== false ? getenv('MYSQL_VERSION') : '8.4.7');
[$provider, $lexical] = match ($database) {
    'mysql' => [new MySqlProvider($faker, $mysqlVersion, $coverage), new SqlFaker\MySql\LexicalGrammar($faker, $mysqlVersion)],
    'pg' => [new PostgreSqlProvider($faker, 'pg-17.2', $coverage), new SqlFaker\PostgreSql\LexicalGrammar($faker, 'pg-17.2')],
    'sqlite' => [new SqliteProvider($faker, 'sqlite-3.47.2', $coverage), new SqlFaker\Sqlite\LexicalGrammar($faker, 'sqlite-3.47.2')],
    default => throw new InvalidArgumentException('Expected mysql, pg or sqlite.'),
};
$inventory = $coverage->inventory();
$minimum = $provider->minimumExpansionBudget();
$key = hash('sha256', $inventory->fingerprint . ':' . GeneratorRevision::current() . ':5000');
$directory = __DIR__ . '/seeds/generated/' . $database . '/' . $key;
$corpus = __DIR__ . '/corpus/' . $database;
foreach ([$directory, $corpus] as $path) {
    if (!is_dir($path) && !mkdir($path, 0777, true)) {
        throw new RuntimeException('Cannot create corpus directory: ' . $path);
    }
}

if (!is_file($directory . '/inventory.json')) {
    $search = new ProductionWitness($inventory->grammar, $lexical->supports(...));
    $report = [];
    foreach ($inventory->grammar->ruleMap as $name => $rule) {
        foreach ($rule->alternatives as $ordinal => $production) {
            $id = $inventory->id($name, $production, $ordinal);
            if (!$inventory->entries[$id]['rootReachable']) {
                $report[$id] = ['status' => 'outside-root'];
                continue;
            }
            $witness = $search->find($inventory->root, $name, $ordinal);
            if ($witness === null || $witness->cost > 5000) {
                $report[$id] = ['status' => $witness === null ? 'no-lexically-supported-witness' : 'exceeds-budget',
                    'minimumCost' => $witness?->cost];
                continue;
            }
            $input = $search->encode($witness);
            $file = hash('sha256', $input) . '.bin';
            if (file_put_contents($directory . '/' . $file, $input) !== strlen($input)) {
                throw new RuntimeException('Cannot save initial input: ' . $file);
            }
            try {
                $provider->generate(GenerationPlan::fromBytes($input, $minimum));
                if (!in_array($id, $coverage->lastGeneration()['reachedIds'] ?? [], true)) {
                    throw new LogicException('Initial input did not reach its intended production: ' . $id);
                }
                $status = 'witness-verified';
            } catch (GenerationException|LexicalException $failure) {
                $status = 'generation-failure: ' . $failure->getMessage();
            }
            $report[$id] = ['status' => $status, 'minimumCost' => $witness->cost, 'file' => $file];
        }
    }
    $json = json_encode($report, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR);
    if (file_put_contents($directory . '/inventory.json', $json) !== strlen($json)) {
        throw new RuntimeException('Cannot save initial-input inventory.');
    }
    fwrite(STDOUT, json_encode(array_count_values(array_column($report, 'status')), JSON_THROW_ON_ERROR) . "\n");
}
foreach ([$directory, __DIR__ . '/seeds/reviewed/' . $database] as $source) {
    $seeds = glob($source . '/*.bin');
    foreach ($seeds === false ? [] : $seeds as $seed) {
        if (!copy($seed, $corpus . '/' . basename($seed))) {
            throw new RuntimeException('Cannot copy initial input: ' . $seed);
        }
    }
}
