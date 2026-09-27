<?php

declare(strict_types=1);

namespace SqlFixture\Provider;

use Faker\Generator;
use SqlFixture\Fixture;
use SqlFixture\Fixture\FixtureSet;
use SqlFixture\Fixture\PlanGenerator;
use SqlFixture\Fixture\TableOverrides;
use SqlFixture\Hydrator\HydratorInterface;
use SqlFixture\Plan\FixturePlan;
use SqlFixture\Provider;
use SqlFixture\Schema\SchemaParserInterface;
use SqlFixture\Schema\StaticSchemaResolver;
use SqlFixture\Schema\TableSchema;
use SqlFixture\TypeMapper\TypeMapperInterface;
use SqlFixture\Version\ServerVersion;

/**
 * Faker provider that generates fixtures from CREATE TABLE SQL statements.
 *
 * The dialect and, optionally, the version tag select the release the
 * statements are read for; omitting the version tag uses the default of the
 * dialect.
 *
 * @visibility public
 * @example Read statements as a MySQL 8.0 server does
 *     $provider = new \SqlFixture\Provider\FixtureProvider(\Faker\Factory::create(), dialect: 'mysql', version: 'mysql-8.0.44');
 *     $provider->getVersion() // => 'mysql-8.0.44'
 * @example Generate a row while leaving the auto-increment key to the database
 *     $provider = new \SqlFixture\Provider\FixtureProvider(\Faker\Factory::create());
 *     $provider->fixture('CREATE TABLE users (id INT AUTO_INCREMENT PRIMARY KEY, name VARCHAR(30))', ['name' => 'Alice']) // => ['name' => 'Alice']
 *
 * @example Generate linked rows from registered schemas
 *     $provider = new \SqlFixture\Provider\FixtureProvider(\Faker\Factory::create());
 *     $provider->registerSchema('CREATE TABLE users (id INT PRIMARY KEY)');
 *     $provider->registerSchema('CREATE TABLE posts (user_id INT)');
 *     $rows = $provider->fixtures('users.id < posts.user_id', ['users' => ['id' => 9], 'posts' => 2]);
 *     $rows->rows('posts') // => [['user_id' => 9], ['user_id' => 9]]
 */
class FixtureProvider extends SqlSchemaProvider
{
    private FixtureGenerator $fixtureGenerator;
    private string $dialect;
    private ServerVersion $version;
    private Generator $faker;
    private StaticSchemaResolver $schemaResolver;

    /**
     * @param string $dialect SQL dialect: 'mysql', 'pgsql' or 'sqlite'
     * @param string|null $version Version tag such as 'mysql-8.4.7', or null for the default of the dialect
     *
     * @throws Exception\UnsupportedDriverException If the dialect is not supported
     * @throws \SqlFixture\Version\UnsupportedVersionException If the version tag is not a supported release of the dialect
     */
    public function __construct(
        Generator $faker,
        ?TypeMapperInterface $typeMapper = null,
        ?HydratorInterface $hydrator = null,
        ?SchemaParserInterface $schemaParser = null,
        string $dialect = PlatformFactory::DRIVER_MYSQL,
        ?string $version = null,
    ) {
        parent::__construct($faker);
        $this->faker = $faker;
        $this->dialect = $dialect;
        $this->version = PlatformFactory::resolveVersion($dialect, $version);
        $this->schemaResolver = new StaticSchemaResolver();

        $typeMapper ??= PlatformFactory::createTypeMapper($dialect);
        $schemaParser ??= PlatformFactory::createSchemaParser($dialect, $this->version->tag);

        $this->fixtureGenerator = new FixtureGenerator($faker, $typeMapper, $hydrator, $schemaParser);
    }

    /**
     * Generate a fixture from a CREATE TABLE SQL statement.
     *
     * @template T of object
     * @param string $createTableSql CREATE TABLE SQL statement
     * @param array<string, mixed> $overrides Override values
     * @param class-string<T>|null $className Deserialization target class
     * @param string|null $dialect SQL dialect for this specific call (overrides constructor default)
     * @param string|null $version Version tag for this specific call (overrides constructor default; the default of the dialect when only the dialect is overridden)
     * @return ($className is null ? array<string, mixed> : T)
     */
    public function fixture(
        string $createTableSql,
        array $overrides = [],
        ?string $className = null,
        ?string $dialect = null,
        ?string $version = null,
    ): array|object {
        $schema = $this->getSchema($createTableSql, $dialect, $version);
        return $this->fixtureGenerator->generate($schema, $overrides, $className);
    }

    /**
     * Generate the rows a plan describes.
     *
     * Every table the plan names has to have been registered, either through
     * registerSchema() or by generating a fixture from its DDL first.
     *
     * @param FixturePlan|string $plan A plan, or the relation syntax for one
     * @param array<string, int|array<mixed>|TableOverrides> $overrides Table name => what to override
     */
    public function fixtures(FixturePlan|string $plan, array $overrides = []): FixtureSet
    {
        $generator = new PlanGenerator($this->schemaResolver, $this->fixtureGenerator, $this->faker);

        return $generator->generate(FixturePlan::from($plan), $overrides);
    }

    /**
     * Make a table available to fixtures() under its own name.
     */
    public function registerSchema(string $createTableSql): TableSchema
    {
        return $this->getSchema($createTableSql);
    }

    /**
     * The registry backing fixtures().
     */
    public function getSchemaResolver(): StaticSchemaResolver
    {
        return $this->schemaResolver;
    }

    /**
     * Get the underlying fixture generator.
     */
    public function getFixtureGenerator(): FixtureGenerator
    {
        return $this->fixtureGenerator;
    }

    /**
     * Get the default dialect.
     */
    public function getDialect(): string
    {
        return $this->dialect;
    }

    /**
     * Get the version tag of the release statements are read for.
     */
    public function getVersion(): string
    {
        return $this->version->tag;
    }
}
