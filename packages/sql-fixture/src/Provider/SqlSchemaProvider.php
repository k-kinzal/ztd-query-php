<?php

declare(strict_types=1);

namespace SqlFixture\Provider;

use Faker\Provider\Base;
use SqlFixture\FixtureGenerator;
use SqlFixture\Platform\PlatformFactory;
use SqlFixture\Schema\StaticSchemaResolver;
use SqlFixture\Schema\TableSchema;

/**
 * Keeps the provider schema cache and its protected inheritance hook together.
 *
 * @visibility root
 */
abstract class SqlSchemaProvider extends Base
{
    /**
     * @var array<string, TableSchema> Schema cache by SQL hash
     */
    private array $schemaCache = [];

    /**
     * Get or parse schema from SQL.
     */
    protected function getSchema(string $createTableSql, ?string $dialect = null): TableSchema
    {
        $effectiveDialect = $dialect ?? $this->getDialect();
        $cacheKey = md5($createTableSql . ':' . $effectiveDialect);

        if (!isset($this->schemaCache[$cacheKey])) {
            $parser = ($effectiveDialect !== $this->getDialect())
                ? PlatformFactory::createSchemaParser($effectiveDialect)
                : $this->getFixtureGenerator()->getSchemaParser();

            $schema = $parser->parse($createTableSql);
            $this->schemaCache[$cacheKey] = $schema;
            $this->getSchemaResolver()->register($schema);
        }

        return $this->schemaCache[$cacheKey];
    }
    /**
     * Supplies the parser selected by the concrete provider.
     */
    abstract public function getFixtureGenerator(): FixtureGenerator;

    /**
     * Supplies the default dialect selected by the concrete provider.
     */
    abstract public function getDialect(): string;

    /**
     * Supplies the registry used by fixture plans.
     */
    abstract public function getSchemaResolver(): StaticSchemaResolver;
}
