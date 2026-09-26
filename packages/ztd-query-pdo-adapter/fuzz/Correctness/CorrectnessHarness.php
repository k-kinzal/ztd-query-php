<?php

declare(strict_types=1);

namespace Fuzz\Correctness;

use Faker\Factory;
use Faker\Generator;
use PDO;
use RuntimeException;
use SqlFixture\Provider\FixtureProvider;
use ZtdQuery\Adapter\Pdo\ZtdPdo;
use ZtdQuery\Config\UnknownSchemaBehavior;
use ZtdQuery\Config\UnsupportedSqlBehavior;
use ZtdQuery\Config\ZtdConfig;

/**
 * @phpstan-type Row array<string, bool|float|int|string|null>
 */
final class CorrectnessHarness
{
    private PDO $rawPdo;
    private ?PhysicalDatabase $physical = null;
    private ?string $physicalSnapshot = null;
    private ?ZtdPdo $ztdPdo = null;
    private ?SchemaDefinition $currentSchema = null;
    private string $dsn;
    private string $user;
    private string $pass;
    private Generator $faker;
    private FixtureProvider $fixtureProvider;

    /** @var list<Row> */
    private array $fixtureRows = [];

    /**
     * Binds the instance to what it will work from.
     *
     * @param string $host
     * @param int $port
     * @param string $dbName
     * @param string $user
     * @param string $pass
     */
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
        $this->faker->addProvider(new FixedDateTimeProvider());
        $this->fixtureProvider = new FixtureProvider($this->faker);
    }

    /**
     * Answers a generated row the harness can write and compare.
     *
     * The generator answers whatever the column's type maps to; a row both
     * sides can be asked about holds nothing but scalars and nulls.
     *
     * @param string $createTableSql Declaration of the table to build a row for
     *
     * @return Row The row, keyed by column
     *
     * @throws RuntimeException When the generator answers something no comparison can read
     */
    public function fixtureRow(string $createTableSql): array
    {
        $row = [];
        foreach ($this->fixtureProvider->fixture($createTableSql) as $column => $value) {
            if ($value !== null && !is_scalar($value)) {
                throw new RuntimeException(sprintf('The fixture generator answered %s for column "%s", which no comparison can read.', get_debug_type($value), $column));
            }
            $row[$column] = $value;
        }

        return $row;
    }

    /**
     * Initialize equal oracle and shadow rows with distinct physical backing rows.
     *
     * @return list<Row> The fixture rows inserted
     */
    public function setup(SchemaDefinition $schema, int $seed, int $rowCount = 3): array
    {
        $this->currentSchema = $schema;
        $this->faker->seed($seed);

        $this->rawPdo->exec("DROP TABLE IF EXISTS `{$schema->name}`");
        $this->rawPdo->exec($schema->sql);

        $this->fixtureRows = [];
        for ($i = 0; $i < $rowCount; $i++) {
            $row = $this->fixtureRow($schema->sql);
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

        $backing = new PhysicalDatabase($this->rawPdo, $this->dsn, $this->user, $this->pass);
        $this->physical = $backing;
        $physical = $backing->connection();
        $ztd = ZtdPdo::fromPdo($physical, new ZtdConfig(UnsupportedSqlBehavior::Exception, UnknownSchemaBehavior::Exception));

        $this->ztdPdo = $ztd;
        $ztd->exec($schema->sql);
        $physical->exec($schema->sql);
        if ($this->fixtureRows !== []) {
            $this->insertRow($physical, $schema->name, $this->fixtureRows[0]);
        }
        $this->physicalSnapshot = $backing->snapshot();
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
                return "'" . addslashes($v) . "'";
            }, array_values($row));
            $sql = sprintf(
                'INSERT INTO `%s` (%s) VALUES (%s)',
                $schema->name,
                implode(', ', array_map(fn ($c) => "`$c`", $columns)),
                implode(', ', $values)
            );
            $ztd->exec($sql);
        }

        return $this->fixtureRows;
    }

    /**
     * Teardown.
     *
     * @throws OracleViolation When ZTD changed the backing catalog.
     *
     */
    public function teardown(): void
    {
        try {
            if ($this->physical !== null && $this->physicalSnapshot !== null && $this->physicalSnapshot !== $this->physical->snapshot()) {
                throw new OracleViolation('ZTD changed the physical backing catalog.');
            }
        } finally {
            if ($this->currentSchema !== null) {
                $this->rawPdo->exec("DROP TABLE IF EXISTS `{$this->currentSchema->name}`");
            }
            $this->physical?->close();
            $this->physical = null;
            $this->physicalSnapshot = null;
            $this->ztdPdo = null;
            $this->currentSchema = null;
            $this->fixtureRows = [];
        }
    }

    /**
     * Inspect the backing database independently of the oracle database.
     *
     * @throws RuntimeException When setup has not initialized the backing database.
     */
    public function getPhysicalPdo(): PDO
    {
        return $this->physical?->connection() ?? throw new RuntimeException('Physical database is not initialized.');
    }

    /**
     * Answers raw pdo.
     *
     * @return PDO
     */
    public function getRawPdo(): PDO
    {
        return $this->rawPdo;
    }

    /**
     * @throws RuntimeException
     */
    public function getZtdPdo(): ZtdPdo
    {
        if ($this->ztdPdo === null) {
            throw new RuntimeException('ZtdPdo not initialized. Call setup() first.');
        }
        return $this->ztdPdo;
    }

    /**
     * @return list<Row>
     */
    public function getFixtureRows(): array
    {
        return $this->fixtureRows;
    }

    /**
     * Answers current schema.
     *
     * @return ?SchemaDefinition
     */
    public function getCurrentSchema(): ?SchemaDefinition
    {
        return $this->currentSchema;
    }

    /**
     * Writes one fixture row into the table both sides read.
     *
     * @param PDO $pdo The pdo
     * @param string $table Table it belongs to
     * @param Row $row Row to read
     */
    public function insertRow(PDO $pdo, string $table, array $row): void
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
