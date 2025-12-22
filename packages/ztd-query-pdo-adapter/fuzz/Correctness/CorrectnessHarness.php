<?php

declare(strict_types=1);

namespace Fuzz\Correctness;

use Faker\Factory;
use Faker\Generator;
use PDO;
use SqlFixture\FixtureProvider;
use ZtdQuery\Adapter\Pdo\ZtdPdo;
use ZtdQuery\Config\UnknownSchemaBehavior;
use ZtdQuery\Config\UnsupportedSqlBehavior;
use ZtdQuery\Config\ZtdConfig;

final class CorrectnessHarness
{
    private PDO $rawPdo;
    private ?ZtdPdo $ztdPdo = null;
    private ?SchemaDefinition $currentSchema = null;
    private string $dsn;
    private string $user;
    private string $pass;
    private Generator $faker;
    private FixtureProvider $fixtureProvider;

    /** @var array<int, array<string, mixed>> */
    private array $fixtureRows = [];

    public function __construct(string $host, int $port, string $dbName, string $user, string $pass)
    {
        $this->dsn = "mysql:host=$host;port=$port;dbname=$dbName;charset=utf8mb4";
        $this->user = $user;
        $this->pass = $pass;
        $this->rawPdo = new PDO($this->dsn, $user, $pass, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
        $this->faker = Factory::create();
        $this->fixtureProvider = new FixtureProvider($this->faker);
    }

    /**
     * Set up both connections with the same schema and data.
     *
     * @return array<int, array<string, mixed>> The fixture rows inserted
     */
    public function setup(SchemaDefinition $schema, int $seed, int $rowCount = 3): array
    {
        $this->currentSchema = $schema;
        $this->faker->seed($seed);

        $this->rawPdo->exec("DROP TABLE IF EXISTS `{$schema->name}`");
        $this->rawPdo->exec($schema->sql);

        $this->fixtureRows = [];
        for ($i = 0; $i < $rowCount; $i++) {
            $row = $this->fixtureProvider->fixture($schema->sql);
            if (count($schema->primaryKeys) === 1 && $schema->primaryKeys[0] === 'id') {
                $row['id'] = $i + 1;
            }
            if ($schema->name === 'composite_pk') {
                $row['order_id'] = $i + 1;
                $row['product_id'] = ($i + 1) * 10;
            }
            $this->fixtureRows[] = $row;
        }

        foreach ($this->fixtureRows as $row) {
            $this->insertRow($this->rawPdo, $schema->name, $row);
        }

        $this->ztdPdo = new ZtdPdo($this->dsn, $this->user, $this->pass, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ], new ZtdConfig(UnsupportedSqlBehavior::Ignore, UnknownSchemaBehavior::Exception));

        $this->ztdPdo->exec($schema->sql);
        foreach ($this->fixtureRows as $row) {
            $columns = array_keys($row);
            $values = array_map(function ($v) {
                if ($v === null) {
                    return 'NULL';
                }
                if (is_int($v) || is_float($v)) {
                    return (string) $v;
                }
                if (is_bool($v)) {
                    return $v ? '1' : '0';
                }
                return "'" . addslashes(is_scalar($v) ? $v : '') . "'";
            }, array_values($row));
            $sql = sprintf(
                'INSERT INTO `%s` (%s) VALUES (%s)',
                $schema->name,
                implode(', ', array_map(fn ($c) => "`$c`", $columns)),
                implode(', ', $values)
            );
            $this->ztdPdo->exec($sql);
        }

        return $this->fixtureRows;
    }

    public function teardown(): void
    {
        if ($this->currentSchema !== null) {
            $this->rawPdo->exec("DROP TABLE IF EXISTS `{$this->currentSchema->name}`");
        }
        $this->ztdPdo = null;
        $this->currentSchema = null;
        $this->fixtureRows = [];
    }

    public function getRawPdo(): PDO
    {
        return $this->rawPdo;
    }

    public function getZtdPdo(): ZtdPdo
    {
        if ($this->ztdPdo === null) {
            throw new \RuntimeException('ZtdPdo not initialized. Call setup() first.');
        }
        return $this->ztdPdo;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getFixtureRows(): array
    {
        return $this->fixtureRows;
    }

    public function getCurrentSchema(): ?SchemaDefinition
    {
        return $this->currentSchema;
    }

    /**
     * @param array<string, mixed> $row
     */
    private function insertRow(PDO $pdo, string $table, array $row): void
    {
        $columns = array_keys($row);
        $placeholders = array_fill(0, count($columns), '?');
        $sql = sprintf(
            'INSERT INTO `%s` (%s) VALUES (%s)',
            $table,
            implode(', ', array_map(fn ($c) => "`$c`", $columns)),
            implode(', ', $placeholders)
        );
        $values = array_map(function ($v) {
            if (is_bool($v)) {
                return $v ? 1 : 0;
            }
            return $v;
        }, array_values($row));
        $stmt = $pdo->prepare($sql);
        $stmt->execute($values);
    }
}
