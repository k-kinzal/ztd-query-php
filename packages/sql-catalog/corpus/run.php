<?php

declare(strict_types=1);

namespace Corpus\Runner;

use PDO;
use PDOStatement;

/**
 * Runs every corpus program against an in-memory SQLite database through a PDO
 * that records what the driver is asked to do, and prints the recording as JSON.
 *
 * The recording is the ground truth the conformance tests check the static
 * analysis against: whatever this script saw, the catalog has to describe.
 */
final class Recording
{
    /** @var list<array{sql: string, positional: list<mixed>, named: array<string, mixed>, source: string}> */
    public static array $observations = [];

    public static string $source = '';

    public static function add(string $sql, array $positional, array $named): void
    {
        self::$observations[] = [
            'sql' => $sql,
            'positional' => array_values($positional),
            'named' => $named,
            'source' => self::$source,
        ];
    }
}

final class RecordingStatement extends PDOStatement
{
    /** @var array<string|int, mixed> */
    private array $bound = [];

    protected function __construct()
    {
    }

    public function bindValue(string|int $param, mixed $value, int $type = PDO::PARAM_STR): bool
    {
        $this->bound[$param] = $value;

        return parent::bindValue($param, $value, $type);
    }

    public function execute(?array $params = null): bool
    {
        $given = $params ?? $this->bound;
        $positional = [];
        $named = [];
        foreach ($given as $key => $value) {
            if (is_int($key)) {
                $positional[] = $value;
                continue;
            }
            $named[ltrim((string) $key, ':')] = $value;
        }
        Recording::add($this->queryString, $positional, $named);

        return $params === null ? parent::execute() : parent::execute($params);
    }
}

final class RecordingPdo extends PDO
{
    public function query(string $query, ?int $fetchMode = null, mixed ...$fetchModeArgs): PDOStatement|false
    {
        Recording::add($query, [], []);

        return parent::query($query);
    }

    public function exec(string $statement): int|false
    {
        Recording::add($statement, [], []);

        return parent::exec($statement);
    }
}

const SCHEMA = [
    "CREATE TABLE users (id INTEGER PRIMARY KEY, name TEXT NOT NULL, status TEXT NOT NULL DEFAULT 'active', created_at TEXT)",
    'CREATE TABLE orders (id INTEGER PRIMARY KEY, user_id INTEGER NOT NULL, total REAL NOT NULL)',
];

$programs = glob(__DIR__ . '/app/*.php');
sort($programs);
foreach ($programs as $program) {
    require_once $program;
}

$_GET['name'] = 'Ada';
$_GET['id'] = '1';

$database = new RecordingPdo('sqlite::memory:');
$database->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$database->setAttribute(PDO::ATTR_STATEMENT_CLASS, [RecordingStatement::class, []]);
foreach (SCHEMA as $statement) {
    $database->exec($statement);
}
Recording::$observations = [];

foreach ($programs as $program) {
    $namespace = 'Corpus\\' . basename($program, '.php') . '\\run';
    Recording::$source = 'corpus/app/' . basename($program);
    $namespace($database);
}

echo json_encode(['observations' => Recording::$observations], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES), "\n";
