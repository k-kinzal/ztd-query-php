<?php

declare(strict_types=1);

namespace Fuzz\Correctness;

use Faker\Factory;
use Faker\Generator;
use mysqli;
use mysqli_result;
use SqlFixture\FixtureProvider;
use ZtdQuery\Adapter\Mysqli\ZtdMysqli;
use ZtdQuery\Config\UnknownSchemaBehavior;
use ZtdQuery\Config\UnsupportedSqlBehavior;
use ZtdQuery\Config\ZtdConfig;

final class MysqliCorrectnessHarness
{
    private mysqli $rawMysqli;
    private ?ZtdMysqli $ztdMysqli = null;
    private ?SchemaDefinition $currentSchema = null;
    private string $host;
    private int $port;
    private string $dbName;
    private string $user;
    private string $pass;
    private Generator $faker;
    private FixtureProvider $fixtureProvider;

    /** @var array<int, array<string, mixed>> */
    private array $fixtureRows = [];

    public function __construct(string $host, int $port, string $dbName, string $user, string $pass)
    {
        $this->host = $host;
        $this->port = $port;
        $this->dbName = $dbName;
        $this->user = $user;
        $this->pass = $pass;
        $this->rawMysqli = new mysqli($host, $user, $pass, $dbName, $port);
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

        $this->rawMysqli->query("DROP TABLE IF EXISTS `{$schema->name}`");
        $this->rawMysqli->query($schema->sql);

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
            $this->insertRow($this->rawMysqli, $schema->name, $row);
        }

        $this->ztdMysqli = new ZtdMysqli(
            $this->host,
            $this->user,
            $this->pass,
            $this->dbName,
            $this->port,
            null,
            new ZtdConfig(UnsupportedSqlBehavior::Ignore, UnknownSchemaBehavior::Exception)
        );

        $this->ztdMysqli->query($schema->sql);
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
                assert(is_string($v));
                return "'" . addslashes($v) . "'";
            }, array_values($row));
            $sql = sprintf(
                'INSERT INTO `%s` (%s) VALUES (%s)',
                $schema->name,
                implode(', ', array_map(fn ($c) => "`$c`", $columns)),
                implode(', ', $values)
            );
            $this->ztdMysqli->query($sql);
        }

        return $this->fixtureRows;
    }

    public function teardown(): void
    {
        if ($this->currentSchema !== null) {
            $this->rawMysqli->query("DROP TABLE IF EXISTS `{$this->currentSchema->name}`");
        }
        $this->ztdMysqli = null;
        $this->currentSchema = null;
        $this->fixtureRows = [];
    }

    public function getRawMysqli(): mysqli
    {
        return $this->rawMysqli;
    }

    public function getZtdMysqli(): ZtdMysqli
    {
        if ($this->ztdMysqli === null) {
            throw new \RuntimeException('ZtdMysqli not initialized. Call setup() first.');
        }
        return $this->ztdMysqli;
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
    private function insertRow(mysqli $mysqli, string $table, array $row): void
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
        $types = str_repeat('s', count($values));
        $stmt = $mysqli->prepare($sql);
        assert($stmt !== false);
        $stmt->bind_param($types, ...$values);
        $stmt->execute();
        $stmt->close();
    }
}
