<?php

declare(strict_types=1);

namespace Tests\Unit;

use Faker\Factory;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use SqlFixture\FileFixtureProvider;
use SqlFixture\FixtureGenerator;
use SqlFixture\Hydrator\ReflectionHydrator;
use SqlFixture\Platform\MySql\MySqlSchemaParser;
use SqlFixture\Platform\MySql\MySqlTypeMapper;
use SqlFixture\Platform\PlatformFactory;
use SqlFixture\Schema\ColumnDefinition;
use SqlFixture\Schema\SchemaParseException;
use SqlFixture\Schema\TableSchema;

#[CoversClass(FileFixtureProvider::class)]
#[UsesClass(FixtureGenerator::class)]
#[UsesClass(PlatformFactory::class)]
#[UsesClass(MySqlSchemaParser::class)]
#[UsesClass(ColumnDefinition::class)]
#[UsesClass(TableSchema::class)]
#[UsesClass(SchemaParseException::class)]
#[UsesClass(MySqlTypeMapper::class)]
#[UsesClass(ReflectionHydrator::class)]
#[CoversClass(\SqlFixture\Provider\DdlDirectory::class)]
#[CoversClass(\SqlFixture\Provider\DdlFile::class)]
#[UsesClass(\SqlFixture\Hydrator\HydrationException::class)]
#[UsesClass(\SqlFixture\Hydrator\HydratorInterface::class)]
#[UsesClass(\SqlFixture\Platform\MySql\MySqlSchemaFetcher::class)]
#[UsesClass(\SqlFixture\Platform\PostgreSql\PostgreSqlSchemaFetcher::class)]
#[UsesClass(\SqlFixture\Platform\PostgreSql\PostgreSqlSchemaParser::class)]
#[UsesClass(\SqlFixture\Platform\PostgreSql\PostgreSqlTypeMapper::class)]
#[UsesClass(\SqlFixture\Platform\Sqlite\SqliteSchemaFetcher::class)]
#[UsesClass(\SqlFixture\Platform\Sqlite\SqliteSchemaParser::class)]
#[UsesClass(\SqlFixture\Platform\Sqlite\SqliteTypeMapper::class)]
#[UsesClass(\SqlFixture\Schema\SchemaFetcherInterface::class)]
#[UsesClass(\SqlFixture\Schema\SchemaParserInterface::class)]
#[UsesClass(\SqlFixture\TypeMapper\TypeMapperInterface::class)]
#[UsesClass(\SqlFixture\Fixture\RowGeneration::class)]
#[UsesClass(\SqlFixture\Fixture\Validation\OverrideValidator::class)]
#[UsesClass(\SqlFixture\Hydrator\Reflection\ConstructorHydration::class)]
#[UsesClass(\SqlFixture\Hydrator\Reflection\PropertyHydration::class)]
#[UsesClass(\SqlFixture\Hydrator\Reflection\PropertyNames::class)]
#[UsesClass(\SqlFixture\Hydrator\Reflection\ValueConversion::class)]
#[UsesClass(\SqlFixture\InvalidOverrideException::class)]
#[UsesClass(\SqlFixture\Platform\MySql\Schema\ColumnParser::class)]
#[UsesClass(\SqlFixture\Platform\MySql\Schema\CreateTableQuery::class)]
#[UsesClass(\SqlFixture\Platform\MySql\Schema\DefaultExpression::class)]
#[UsesClass(\SqlFixture\Platform\MySql\Schema\DefinitionIntegrity::class)]
#[UsesClass(\SqlFixture\Platform\MySql\Schema\IdentifierQuoter::class)]
#[UsesClass(\SqlFixture\Platform\MySql\Schema\TableDefinition::class)]
#[UsesClass(\SqlFixture\Platform\MySql\Schema\TypeParameters::class)]
#[UsesClass(\SqlFixture\Platform\MySql\Value\ColumnGenerator::class)]
#[UsesClass(\SqlFixture\Platform\MySql\Value\DecimalGenerator::class)]
#[UsesClass(\SqlFixture\Platform\MySql\Value\GeometryGenerator::class)]
#[UsesClass(\SqlFixture\Platform\MySql\Value\IntegerGenerator::class)]
#[UsesClass(\SqlFixture\Platform\MySql\Value\NumericGenerator::class)]
#[UsesClass(\SqlFixture\Platform\MySql\Value\SpatialGenerator::class)]
#[UsesClass(\SqlFixture\Platform\MySql\Value\StringGenerator::class)]
#[UsesClass(\SqlFixture\Platform\MySql\Value\TextGenerator::class)]
#[UsesClass(\SqlFixture\Platform\PostgreSql\Schema\CatalogColumn::class)]
#[UsesClass(\SqlFixture\Platform\PostgreSql\Schema\CatalogDdl::class)]
#[UsesClass(\SqlFixture\Platform\PostgreSql\Schema\CatalogQuery::class)]
#[UsesClass(\SqlFixture\Platform\PostgreSql\Schema\CatalogSchema::class)]
#[UsesClass(\SqlFixture\Platform\PostgreSql\Schema\ColumnParser::class)]
#[UsesClass(\SqlFixture\Platform\PostgreSql\Schema\DefaultExpression::class)]
#[UsesClass(\SqlFixture\Platform\PostgreSql\Schema\DefinitionList::class)]
#[UsesClass(\SqlFixture\Platform\PostgreSql\Schema\TableSyntax::class)]
#[UsesClass(\SqlFixture\Platform\PostgreSql\Schema\TypeDeclaration::class)]
#[UsesClass(\SqlFixture\Platform\PostgreSql\Value\ColumnGenerator::class)]
#[UsesClass(\SqlFixture\Platform\PostgreSql\Value\DecimalGenerator::class)]
#[UsesClass(\SqlFixture\Platform\PostgreSql\Value\NumericGenerator::class)]
#[UsesClass(\SqlFixture\Platform\PostgreSql\Value\StringGenerator::class)]
#[UsesClass(\SqlFixture\Platform\PostgreSql\Value\StructuredGenerator::class)]
#[UsesClass(\SqlFixture\Platform\PostgreSql\Value\TemporalGenerator::class)]
#[UsesClass(\SqlFixture\Platform\Sqlite\Schema\ColumnParser::class)]
#[UsesClass(\SqlFixture\Platform\Sqlite\Schema\CreateTableQuery::class)]
#[UsesClass(\SqlFixture\Platform\Sqlite\Schema\DefaultExpression::class)]
#[UsesClass(\SqlFixture\Platform\Sqlite\Schema\DefinitionList::class)]
#[UsesClass(\SqlFixture\Platform\Sqlite\Schema\PragmaColumn::class)]
#[UsesClass(\SqlFixture\Platform\Sqlite\Schema\PragmaSchema::class)]
#[UsesClass(\SqlFixture\Platform\Sqlite\Schema\TableSyntax::class)]
#[UsesClass(\SqlFixture\Platform\Sqlite\Schema\TypeDeclaration::class)]
#[UsesClass(\SqlFixture\Platform\Sqlite\Value\ColumnGenerator::class)]
#[UsesClass(\SqlFixture\Platform\Sqlite\Value\TypeAffinity::class)]
#[UsesClass(\SqlFixture\Schema\DefinitionSegments::class)]
#[UsesClass(\SqlFixture\Schema\TypeShape::class)]
#[UsesClass(\SqlFixture\TypeMapper\ParagraphGenerator::class)]
#[UsesClass(\SqlFixture\Platform\MySql\Schema\TableDefinitionInput::class)]
#[UsesClass(\SqlFixture\Hydrator\Exception\ClassNotFoundException::class)]
#[UsesClass(\SqlFixture\Hydrator\Exception\MissingConstructorArgumentException::class)]
#[UsesClass(\SqlFixture\Fixture\Exception\UnknownOverrideColumnException::class)]
#[UsesClass(\SqlFixture\Fixture\Exception\NullOverrideException::class)]
#[UsesClass(\SqlFixture\Fixture\Exception\GeneratedColumnOverrideException::class)]
#[UsesClass(\SqlFixture\Schema\Exception\InvalidSqlException::class)]
#[UsesClass(\SqlFixture\Schema\Exception\ExpectedCreateTableException::class)]
#[UsesClass(\SqlFixture\Schema\Exception\MissingColumnDefinitionsException::class)]
#[UsesClass(\SqlFixture\TypeMapper\IntegerRange::class)]
#[UsesClass(\SqlFixture\TypeMapper\IntegerWidth::class)]
#[UsesClass(\SqlFixture\TypeMapper\DecimalRange::class)]
#[UsesClass(\SqlFixture\Hydrator\Reflection\ConversionTarget::class)]
final class FileFixtureProviderTest extends TestCase
{
    #[Test]
    public function testLoadsSchemasFromDirectory(): void
    {
        $tempDir = sys_get_temp_dir() . '/sql-fixture-test-' . uniqid();
        mkdir($tempDir, 0755, true);
        try {
            file_put_contents(
                $tempDir . '/users.sql',
                'CREATE TABLE users (id INT PRIMARY KEY, name VARCHAR(255))'
            );

            $faker = Factory::create();
            $faker->seed(12345);
            $provider = new FileFixtureProvider($faker, $tempDir);

            self::assertTrue($provider->hasTable('users'));
        } finally {
            unlink($tempDir . '/users.sql');
            rmdir($tempDir);
        }
    }

    #[Test]
    public function testFixtureGeneratesDataForLoadedTable(): void
    {
        $tempDir = sys_get_temp_dir() . '/sql-fixture-test-' . uniqid();
        mkdir($tempDir, 0755, true);
        try {
            file_put_contents(
                $tempDir . '/users.sql',
                'CREATE TABLE users (id INT PRIMARY KEY, name VARCHAR(255))'
            );

            $faker = Factory::create();
            $faker->seed(12345);
            $provider = new FileFixtureProvider($faker, $tempDir);
            $data = $provider->fixture('users');

            self::assertArrayHasKey('id', $data);
            self::assertArrayHasKey('name', $data);
        } finally {
            unlink($tempDir . '/users.sql');
            rmdir($tempDir);
        }
    }

    #[Test]
    public function testFixtureWithOverrides(): void
    {
        $tempDir = sys_get_temp_dir() . '/sql-fixture-test-' . uniqid();
        mkdir($tempDir, 0755, true);
        try {
            file_put_contents(
                $tempDir . '/users.sql',
                'CREATE TABLE users (id INT, name VARCHAR(255))'
            );

            $faker = Factory::create();
            $faker->seed(12345);
            $provider = new FileFixtureProvider($faker, $tempDir);
            $data = $provider->fixture('users', ['name' => 'Test']);

            self::assertSame('Test', $data['name']);
        } finally {
            unlink($tempDir . '/users.sql');
            rmdir($tempDir);
        }
    }

    #[Test]
    public function testFixtureWithHydration(): void
    {
        $target = new class (0, '') {
            public function __construct(
                public readonly int $id,
                public readonly string $name,
            ) {
            }
        };
        $tempDir = sys_get_temp_dir() . '/sql-fixture-test-' . uniqid();
        mkdir($tempDir, 0755, true);
        try {
            file_put_contents(
                $tempDir . '/users.sql',
                'CREATE TABLE users (id INT, name VARCHAR(255))'
            );

            $faker = Factory::create();
            $faker->seed(12345);
            $provider = new FileFixtureProvider($faker, $tempDir);
            $user = $provider->fixture('users', ['id' => 1, 'name' => 'Test'], $target::class);

            self::assertSame(1, $user->id);
            self::assertSame('Test', $user->name);
        } finally {
            unlink($tempDir . '/users.sql');
            rmdir($tempDir);
        }
    }

    #[Test]
    public function testThrowsExceptionForNonExistentTable(): void
    {
        $tempDir = sys_get_temp_dir() . '/sql-fixture-test-' . uniqid();
        mkdir($tempDir, 0755, true);
        try {
            $faker = Factory::create();
            $faker->seed(12345);
            $provider = new FileFixtureProvider($faker, $tempDir);

            $this->expectException(RuntimeException::class);
            $this->expectExceptionMessage('Schema not found');
            $provider->fixture('nonexistent');
        } finally {
            rmdir($tempDir);
        }
    }

    #[Test]
    public function testThrowsExceptionForNonExistentDirectory(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('not a directory');
        $faker = Factory::create();
        $faker->seed(12345);
        new FileFixtureProvider($faker, '/nonexistent/path');
    }

    #[Test]
    public function testHasTableReturnsFalseForNonExistent(): void
    {
        $tempDir = sys_get_temp_dir() . '/sql-fixture-test-' . uniqid();
        mkdir($tempDir, 0755, true);
        try {
            $faker = Factory::create();
            $faker->seed(12345);
            $provider = new FileFixtureProvider($faker, $tempDir);
            self::assertFalse($provider->hasTable('nonexistent'));
        } finally {
            rmdir($tempDir);
        }
    }

    #[Test]
    public function testGetTableNames(): void
    {
        $tempDir = sys_get_temp_dir() . '/sql-fixture-test-' . uniqid();
        mkdir($tempDir, 0755, true);
        try {
            file_put_contents(
                $tempDir . '/users.sql',
                'CREATE TABLE users (id INT)'
            );
            file_put_contents(
                $tempDir . '/posts.sql',
                'CREATE TABLE posts (id INT)'
            );

            $faker = Factory::create();
            $faker->seed(12345);
            $provider = new FileFixtureProvider($faker, $tempDir);
            $names = $provider->getTableNames();

            self::assertContains('users', $names);
            self::assertContains('posts', $names);
        } finally {
            unlink($tempDir . '/users.sql');
            unlink($tempDir . '/posts.sql');
            rmdir($tempDir);
        }
    }

    #[Test]
    public function testRegisterSchema(): void
    {
        $tempDir = sys_get_temp_dir() . '/sql-fixture-test-' . uniqid();
        mkdir($tempDir, 0755, true);
        try {
            $faker = Factory::create();
            $faker->seed(12345);
            $provider = new FileFixtureProvider($faker, $tempDir);
            $provider->registerSchema('CREATE TABLE dynamic (id INT, value TEXT)');

            self::assertTrue($provider->hasTable('dynamic'));
            $data = $provider->fixture('dynamic');
            self::assertArrayHasKey('id', $data);
        } finally {
            rmdir($tempDir);
        }
    }

    #[Test]
    public function testGetFixtureGenerator(): void
    {
        $tempDir = sys_get_temp_dir() . '/sql-fixture-test-' . uniqid();
        mkdir($tempDir, 0755, true);
        try {
            $faker = Factory::create();
            $faker->seed(12345);
            $provider = new FileFixtureProvider($faker, $tempDir);
            self::assertSame(['id' => 7], $provider->getFixtureGenerator()->generate(new TableSchema('sample', ['id' => new ColumnDefinition('id', 'INT')]), ['id' => 7]));
        } finally {
            rmdir($tempDir);
        }
    }

    #[Test]
    public function testSkipsInvalidSqlFiles(): void
    {
        $tempDir = sys_get_temp_dir() . '/sql-fixture-test-' . uniqid();
        mkdir($tempDir, 0755, true);
        try {
            file_put_contents($tempDir . '/invalid.sql', 'NOT VALID SQL');
            file_put_contents(
                $tempDir . '/valid.sql',
                'CREATE TABLE valid (id INT)'
            );

            $faker = Factory::create();
            $faker->seed(12345);
            $provider = new FileFixtureProvider($faker, $tempDir);

            self::assertTrue($provider->hasTable('valid'));
            self::assertFalse($provider->hasTable('invalid'));
        } finally {
            unlink($tempDir . '/invalid.sql');
            unlink($tempDir . '/valid.sql');
            rmdir($tempDir);
        }
    }

    #[Test]
    public function testHandlesCommentsInSqlFiles(): void
    {
        $tempDir = sys_get_temp_dir() . '/sql-fixture-test-' . uniqid();
        mkdir($tempDir, 0755, true);
        try {
            $sql = <<<'SQL'
                -- This is a comment
                /* Multi-line
                   comment */
                CREATE TABLE with_comments (id INT PRIMARY KEY)
                SQL;

            file_put_contents($tempDir . '/with_comments.sql', $sql);

            $faker = Factory::create();
            $faker->seed(12345);
            $provider = new FileFixtureProvider($faker, $tempDir);
            self::assertTrue($provider->hasTable('with_comments'));
        } finally {
            unlink($tempDir . '/with_comments.sql');
            rmdir($tempDir);
        }
    }

    #[Test]
    public function testHandlesEmptySqlFile(): void
    {
        $tempDir = sys_get_temp_dir() . '/sql-fixture-test-' . uniqid();
        mkdir($tempDir, 0755, true);
        try {
            file_put_contents($tempDir . '/empty.sql', '');
            file_put_contents(
                $tempDir . '/valid.sql',
                'CREATE TABLE valid (id INT)'
            );

            $faker = Factory::create();
            $faker->seed(12345);
            $provider = new FileFixtureProvider($faker, $tempDir);
            self::assertTrue($provider->hasTable('valid'));
        } finally {
            unlink($tempDir . '/empty.sql');
            unlink($tempDir . '/valid.sql');
            rmdir($tempDir);
        }
    }

    #[Test]
    public function testHandlesCommentOnlySqlFile(): void
    {
        $tempDir = sys_get_temp_dir() . '/sql-fixture-test-' . uniqid();
        mkdir($tempDir, 0755, true);
        try {
            file_put_contents($tempDir . '/comments_only.sql', "-- just comments\n/* block */");
            file_put_contents(
                $tempDir . '/valid.sql',
                'CREATE TABLE valid (id INT)'
            );

            $faker = Factory::create();
            $faker->seed(12345);
            $provider = new FileFixtureProvider($faker, $tempDir);
            self::assertFalse($provider->hasTable('comments_only'));
        } finally {
            unlink($tempDir . '/comments_only.sql');
            unlink($tempDir . '/valid.sql');
            rmdir($tempDir);
        }
    }

    #[Test]
    public function testTableNameIsCaseInsensitive(): void
    {
        $tempDir = sys_get_temp_dir() . '/sql-fixture-test-' . uniqid();
        mkdir($tempDir, 0755, true);
        try {
            file_put_contents(
                $tempDir . '/MyTable.sql',
                'CREATE TABLE MyTable (id INT)'
            );

            $faker = Factory::create();
            $faker->seed(12345);
            $provider = new FileFixtureProvider($faker, $tempDir);

            self::assertTrue($provider->hasTable('mytable'));
            self::assertTrue($provider->hasTable('MYTABLE'));
            self::assertTrue($provider->hasTable('MyTable'));
        } finally {
            unlink($tempDir . '/MyTable.sql');
            rmdir($tempDir);
        }
    }

    #[Test]
    public function testFixtureWithMixedCaseTableName(): void
    {
        $tempDir = sys_get_temp_dir() . '/sql-fixture-test-' . uniqid();
        mkdir($tempDir, 0755, true);
        try {
            file_put_contents(
                $tempDir . '/Users.sql',
                'CREATE TABLE Users (id INT, name VARCHAR(255))'
            );

            $faker = Factory::create();
            $faker->seed(12345);
            $provider = new FileFixtureProvider($faker, $tempDir);
            $data = $provider->fixture('USERS');

            self::assertArrayHasKey('id', $data);
            self::assertArrayHasKey('name', $data);
        } finally {
            unlink($tempDir . '/Users.sql');
            rmdir($tempDir);
        }
    }

    #[Test]
    public function testRegisterSchemaWithMixedCaseFixture(): void
    {
        $tempDir = sys_get_temp_dir() . '/sql-fixture-test-' . uniqid();
        mkdir($tempDir, 0755, true);
        try {
            $faker = Factory::create();
            $faker->seed(12345);
            $provider = new FileFixtureProvider($faker, $tempDir);
            $provider->registerSchema('CREATE TABLE MyItems (id INT, value TEXT)');

            $data = $provider->fixture('MYITEMS');

            self::assertArrayHasKey('id', $data);
            self::assertArrayHasKey('value', $data);
        } finally {
            rmdir($tempDir);
        }
    }
}
