<?php

declare(strict_types=1);

namespace SqlFixture\Provider;

use Faker\Provider\Base;
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
     *
     * @param string|null $dialect Dialect for this statement, or null for the provider's own
     * @param string|null $version Version tag for this statement, or null for the provider's own when the dialect is its own and the default of the dialect otherwise
     */
    protected function getSchema(string $createTableSql, ?string $dialect = null, ?string $version = null): TableSchema
    {
        $effectiveDialect = $dialect ?? $this->getDialect();
        $ownDialect = $effectiveDialect === $this->getDialect();
        $effectiveVersion = PlatformFactory::resolveVersion($effectiveDialect, $version ?? ($ownDialect ? $this->getVersion() : null))->tag;
        $cacheKey = md5($createTableSql . ':' . $effectiveDialect . ':' . $effectiveVersion);

        if (!isset($this->schemaCache[$cacheKey])) {
            $parser = ($ownDialect && $effectiveVersion === $this->getVersion())
                ? $this->getFixtureGenerator()->getSchemaParser()
                : PlatformFactory::createSchemaParser($effectiveDialect, $effectiveVersion);

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
     * Supplies the version tag of the release selected by the concrete provider.
     */
    abstract public function getVersion(): string;

    /**
     * Supplies the registry used by fixture plans.
     */
    abstract public function getSchemaResolver(): StaticSchemaResolver;
}
