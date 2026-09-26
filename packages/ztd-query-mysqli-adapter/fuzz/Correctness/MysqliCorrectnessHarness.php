<?php

declare(strict_types=1);

namespace Fuzz\Correctness;

use Faker\Factory;
use Faker\Generator;
use mysqli;
use RuntimeException;
use SqlFixture\Provider\FixtureProvider;
use ZtdQuery\Adapter\Mysqli\ZtdMysqli;
use ZtdQuery\Config\UnknownSchemaBehavior;
use ZtdQuery\Config\UnsupportedSqlBehavior;
use ZtdQuery\Config\ZtdConfig;

/**
 * Resets native and simulated tables to the same synthetic fixture state.
 */
final class MysqliCorrectnessHarness
{
    private mysqli $rawMysqli;
    private ?mysqli $physical = null;
    private ?string $physicalNamespace = null;
    private ?string $physicalSnapshot = null;
    private ?ZtdMysqli $ztdMysqli = null;
    private ?SchemaDefinition $currentSchema = null;
    private string $host;
    private int $port;
    private string $dbName;
    private string $user;
    private string $pass;
    private Generator $faker;
    private FixtureProvider $fixtureProvider;


    /**
     * Bind the connection, schema generator and deterministic input dependencies.
     */
    public function __construct(string $host, int $port, string $dbName, string $user, string $pass)
    {
        $this->host = $host;
        $this->port = $port;
        $this->dbName = $dbName;
        $this->user = $user;
        $this->pass = $pass;
        $this->rawMysqli = new mysqli($host, $user, $pass, $dbName, $port);
        $this->faker = Factory::create();
        $this->faker->addProvider(new FixedDateTimeProvider());
        $this->fixtureProvider = new FixtureProvider($this->faker);
    }

    /**
     * Initialize equal oracle and shadow rows with distinct physical backing rows.
     *
     * @return array<int, array<string, mixed>> The fixture rows inserted
     */
    public function setup(SchemaDefinition $schema, int $seed, int $rowCount = 3): array
    {
        $this->currentSchema = $schema;
        $this->faker->seed($seed);

        $this->rawMysqli->query("DROP TABLE IF EXISTS `{$schema->name}`");
        $this->rawMysqli->query($schema->sql);

        $fixtureRows = [];
        for ($i = 0; $i < $rowCount; $i++) {
            $row = $this->fixtureProvider->fixture($schema->sql);
            if (count($schema->primaryKeys) === 1 && $schema->primaryKeys[0] === 'id') {
                $row['id'] = $i + 1;
            }
            if ($schema->name === 'composite_pk') {
                $row['order_id'] = $i + 1;
                $row['product_id'] = ($i + 1) * 10;
            }
            $fixtureRows[] = $row;
        }

        foreach ($fixtureRows as $row) {
            (new FixtureRowWriter())->insertRow($this->rawMysqli, $schema->name, $row);
        }

        $physical = new mysqli($this->host, $this->user, $this->pass, $this->dbName, $this->port);
        $namespace = 'ztd_fuzz_' . bin2hex(random_bytes(8));
        $physical->query('CREATE DATABASE `' . $namespace . '`');
        $physical->select_db($namespace);
        $physical->set_charset('utf8mb4');
        $this->physical = $physical;
        $this->physicalNamespace = $namespace;
        $this->ztdMysqli = ZtdMysqli::fromMysqli($physical, new ZtdConfig(UnsupportedSqlBehavior::Exception, UnknownSchemaBehavior::Exception));
        $this->ztdMysqli->query($schema->sql);
        $physical->query($schema->sql);
        if ($fixtureRows !== []) {
            (new FixtureRowWriter())->insertRow($physical, $schema->name, $fixtureRows[0]);
        }
        $this->physicalSnapshot = PhysicalSnapshot::capture($physical);
        foreach ($fixtureRows as $row) {
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

        return $fixtureRows;
    }

    /**
     * Remove the active physical table and discard its simulated session.
     *
     * @throws OracleViolation When the physical catalog was modified.
     */
    public function teardown(): void
    {
        try {
            if ($this->physical !== null && $this->physicalSnapshot !== null && $this->physicalSnapshot !== PhysicalSnapshot::capture($this->physical)) {
                throw new OracleViolation('ZTD changed the physical backing catalog.');
            }
        } finally {
            if ($this->physicalNamespace !== null) {
                $this->rawMysqli->query('DROP DATABASE `' . $this->physicalNamespace . '`');
            }
            if ($this->currentSchema !== null) {
                $this->rawMysqli->query("DROP TABLE IF EXISTS `{$this->currentSchema->name}`");
            }
            $this->physical = null;
            $this->physicalNamespace = null;
            $this->physicalSnapshot = null;
            $this->ztdMysqli = null;
            $this->currentSchema = null;
        }
    }

    /**
     * Return the native connection that serves as the differential oracle.
     */
    public function getRawMysqli(): mysqli
    {
        return $this->rawMysqli;
    }

    /**
     * Return the adapter initialized for the current scenario.
     *
     * @throws RuntimeException If setup has not created a session.
     */
    public function getZtdMysqli(): ZtdMysqli
    {
        if ($this->ztdMysqli === null) {
            throw new RuntimeException('ZtdMysqli not initialized. Call setup() first.');
        }
        return $this->ztdMysqli;
    }


    /**
     * Return the schema owned by the active scenario, if any.
     */
    public function getCurrentSchema(): ?SchemaDefinition
    {
        return $this->currentSchema;
    }


}
