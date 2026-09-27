#!/usr/bin/env php
<?php

declare(strict_types=1);

use SqlSemantics\Core\Declarations;
use SqlSemantics\Core\SemanticException;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Statement\Declaration\ConstraintKind;
use SqlSemantics\Statement\Declaration\Nullability;
use Tests\Contract\DeclarationCorpus;

require dirname(__DIR__) . '/vendor/autoload.php';

$options = getopt('', ['dialect:', 'grammar:']);
$kind = $options['dialect'] ?? 'sqlite';
$dialect = match ($kind) {
    'mysql' => SqlSemantics\Platform\MySql\Dialect::MySql,
    'pg' => SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql,
    'sqlite' => SqlSemantics\Platform\Sqlite\Dialect::Sqlite,
    default => throw new RuntimeException('Use --dialect=mysql, pg, or sqlite.'),
};
$dsn = getenv('SEMANTICS_DSN') ?: ($kind === 'sqlite' ? 'sqlite::memory:' : throw new RuntimeException('Set SEMANTICS_DSN to a disposable test server.'));
$pdo = new PDO($dsn, getenv('SEMANTICS_USERNAME') ?: null, getenv('SEMANTICS_PASSWORD') ?: null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
$semantics = new Semantics($dialect, $options['grammar'] ?? null);
$namespace = 'semantics_compat_' . bin2hex(random_bytes(8));
$results = [];
try {
    if ($kind === 'mysql') {
        $pdo->exec('CREATE DATABASE ' . $namespace);
        $pdo->exec('USE ' . $namespace);
    } elseif ($kind === 'pg') {
        $pdo->exec('CREATE SCHEMA ' . $namespace);
        $pdo->exec('SET search_path TO ' . $namespace);
    }
    $pdo->exec('CREATE TABLE parent(id INT PRIMARY KEY)');
    foreach (DeclarationCorpus::cases() as $name => [$database, $sql, $accepted, $nullable, $automatic, $precision, $scale, $unique]) {
        if ($database !== $kind) {
            continue;
        }
        $pdo->exec('DROP TABLE IF EXISTS probe');
        $serverAccepts = true;
        try {
            $pdo->exec($sql);
        } catch (PDOException) {
            $serverAccepts = false;
        }
        $table = null;
        try {
            $statement = $semantics->analyze($sql, dependencies: [], declarations: Declarations::Partial);
            $table = $statement->resolution?->declarations[0] ?? throw new RuntimeException('Missing structured declaration: ' . $name);
        } catch (SemanticException) {
        }
        if ($serverAccepts !== $accepted || ($table !== null) !== $accepted) {
            throw new RuntimeException($name . ': acceptance differs; server=' . (int) $serverAccepts . ', semantics=' . (int) ($table !== null));
        }
        if ($table === null) {
            $results[$name] = 'rejected by both';
            continue;
        }
        $column = $table->columns[0];
        $actual = [$column->nullability === Nullability::MaybeNull, $column->autoIncrement, $column->type->effectiveNumericSize?->precision, $column->type->effectiveNumericSize?->scale, array_filter($table->constraints, static fn ($constraint): bool => $constraint->kind === ConstraintKind::Unique) !== []];
        $expected = [$nullable, $automatic, $precision, $scale, $unique];
        if ($actual !== $expected) {
            throw new RuntimeException($name . ': semantic facts differ from the corpus: ' . json_encode($actual));
        }
        if ($kind === 'sqlite') {
            $row = $pdo->query('PRAGMA table_xinfo(probe)')->fetch(PDO::FETCH_ASSOC);
            $native = [(int) $row['notnull'] === 0, false, null, null, false];
        } elseif ($kind === 'mysql') {
            $row = $pdo->query("SELECT * FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = 'probe' AND column_name = 'a'")->fetch(PDO::FETCH_ASSOC);
            $decimal = $row['DATA_TYPE'] === 'decimal';
            $native = [$row['IS_NULLABLE'] === 'YES', str_contains($row['EXTRA'], 'auto_increment'), $decimal ? (int) $row['NUMERIC_PRECISION'] : null, $decimal ? (int) $row['NUMERIC_SCALE'] : null, $pdo->query("SELECT COUNT(*) FROM information_schema.table_constraints WHERE constraint_schema = DATABASE() AND table_name = 'probe' AND constraint_type = 'UNIQUE'")->fetchColumn() > 0];
        } else {
            $row = $pdo->query("SELECT * FROM information_schema.columns WHERE table_schema = current_schema() AND table_name = 'probe' AND column_name = 'a'")->fetch(PDO::FETCH_ASSOC);
            $decimal = $row['data_type'] === 'numeric';
            $native = [$row['is_nullable'] === 'YES', $row['is_identity'] === 'YES' || str_starts_with($row['column_default'] ?? '', 'nextval('), $decimal && $row['numeric_precision'] !== null ? (int) $row['numeric_precision'] : null, $decimal && $row['numeric_scale'] !== null ? (((int) $row['numeric_scale'] ^ 1024) - 1024) : null, false];
        }
        if ($native !== $expected) {
            throw new RuntimeException($name . ': catalog facts differ from the corpus: ' . json_encode($native));
        }
        $results[$name] = 'catalog matches';
    }
    echo json_encode(['grammar' => $semantics->language()->version, 'cases' => count($results), 'results' => $results], JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR) . PHP_EOL;
} finally {
    if ($kind === 'mysql') {
        $pdo->exec('DROP DATABASE IF EXISTS ' . $namespace);
    } elseif ($kind === 'pg') {
        $pdo->exec('DROP SCHEMA IF EXISTS ' . $namespace . ' CASCADE');
    }
}
