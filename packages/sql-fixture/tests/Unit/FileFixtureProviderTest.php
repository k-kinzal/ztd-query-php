<?php

declare(strict_types=1);

namespace Tests\Unit;

use Faker\Factory;
use Faker\Generator;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use SqlFixture\FileFixtureProvider;
use SqlFixture\FixtureGenerator;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use SqlFixture\Platform\PlatformFactory;
use SqlFixture\Platform\MySql\MySqlSchemaParser;
use SqlFixture\Schema\ColumnDefinition;
use SqlFixture\Schema\TableSchema;
use SqlFixture\Schema\SchemaParseException;
use SqlFixture\Platform\MySql\MySqlTypeMapper;
use SqlFixture\Hydrator\ReflectionHydrator;
use Tests\Fixture\FileTestUser;

#[CoversClass(FileFixtureProvider::class)]
#[UsesClass(FixtureGenerator::class)]
#[UsesClass(PlatformFactory::class)]
#[UsesClass(MySqlSchemaParser::class)]
#[UsesClass(ColumnDefinition::class)]
#[UsesClass(TableSchema::class)]
#[UsesClass(SchemaParseException::class)]
#[UsesClass(MySqlTypeMapper::class)]
#[UsesClass(ReflectionHydrator::class)]
final class FileFixtureProviderTest extends TestCase
{
    #[Test]
    public function loadsSchemasFromDirectory(): void
    {
        $tempDir = (static function (): string {
            $dir = sys_get_temp_dir() . '/sql-fixture-test-' . uniqid();
            mkdir($dir, 0755, true);
            return $dir;
        })();
        try {
            file_put_contents(
                $tempDir . '/users.sql',
                'CREATE TABLE users (id INT PRIMARY KEY, name VARCHAR(255))'
            );

            $provider = new FileFixtureProvider((static function (): Generator {
                $faker = Factory::create();
                $faker->seed(12345);
                return $faker;
            })(), $tempDir);

            self::assertTrue($provider->hasTable('users'));
        } finally {
            (static function (string $dir): void {
                if (is_dir($dir)) {
                    $files = glob($dir . '/*');
                    if ($files !== false) {
                        array_map('unlink', $files);
                    } rmdir($dir);
                }
            })($tempDir);
        }
    }

    #[Test]
    public function fixtureGeneratesDataForLoadedTable(): void
    {
        $tempDir = (static function (): string {
            $dir = sys_get_temp_dir() . '/sql-fixture-test-' . uniqid();
            mkdir($dir, 0755, true);
            return $dir;
        })();
        try {
            file_put_contents(
                $tempDir . '/users.sql',
                'CREATE TABLE users (id INT PRIMARY KEY, name VARCHAR(255))'
            );

            $provider = new FileFixtureProvider((static function (): Generator {
                $faker = Factory::create();
                $faker->seed(12345);
                return $faker;
            })(), $tempDir);
            $data = $provider->fixture('users');

            self::assertArrayHasKey('id', $data);
            self::assertArrayHasKey('name', $data);
        } finally {
            (static function (string $dir): void {
                if (is_dir($dir)) {
                    $files = glob($dir . '/*');
                    if ($files !== false) {
                        array_map('unlink', $files);
                    } rmdir($dir);
                }
            })($tempDir);
        }
    }

    #[Test]
    public function fixtureWithOverrides(): void
    {
        $tempDir = (static function (): string {
            $dir = sys_get_temp_dir() . '/sql-fixture-test-' . uniqid();
            mkdir($dir, 0755, true);
            return $dir;
        })();
        try {
            file_put_contents(
                $tempDir . '/users.sql',
                'CREATE TABLE users (id INT, name VARCHAR(255))'
            );

            $provider = new FileFixtureProvider((static function (): Generator {
                $faker = Factory::create();
                $faker->seed(12345);
                return $faker;
            })(), $tempDir);
            $data = $provider->fixture('users', ['name' => 'Test']);

            self::assertSame('Test', $data['name']);
        } finally {
            (static function (string $dir): void {
                if (is_dir($dir)) {
                    $files = glob($dir . '/*');
                    if ($files !== false) {
                        array_map('unlink', $files);
                    } rmdir($dir);
                }
            })($tempDir);
        }
    }

    #[Test]
    public function fixtureWithHydration(): void
    {
        $tempDir = (static function (): string {
            $dir = sys_get_temp_dir() . '/sql-fixture-test-' . uniqid();
            mkdir($dir, 0755, true);
            return $dir;
        })();
        try {
            file_put_contents(
                $tempDir . '/users.sql',
                'CREATE TABLE users (id INT, name VARCHAR(255))'
            );

            $provider = new FileFixtureProvider((static function (): Generator {
                $faker = Factory::create();
                $faker->seed(12345);
                return $faker;
            })(), $tempDir);
            $user = $provider->fixture('users', ['id' => 1, 'name' => 'Test'], FileTestUser::class);

            self::assertInstanceOf(FileTestUser::class, $user);
            self::assertSame(1, $user->id);
            self::assertSame('Test', $user->name);
        } finally {
            (static function (string $dir): void {
                if (is_dir($dir)) {
                    $files = glob($dir . '/*');
                    if ($files !== false) {
                        array_map('unlink', $files);
                    } rmdir($dir);
                }
            })($tempDir);
        }
    }

    #[Test]
    public function throwsExceptionForNonExistentTable(): void
    {
        $tempDir = (static function (): string {
            $dir = sys_get_temp_dir() . '/sql-fixture-test-' . uniqid();
            mkdir($dir, 0755, true);
            return $dir;
        })();
        try {
            $provider = new FileFixtureProvider((static function (): Generator {
                $faker = Factory::create();
                $faker->seed(12345);
                return $faker;
            })(), $tempDir);

            $this->expectException(RuntimeException::class);
            $this->expectExceptionMessage('Schema not found');
            $provider->fixture('nonexistent');
        } finally {
            (static function (string $dir): void {
                if (is_dir($dir)) {
                    $files = glob($dir . '/*');
                    if ($files !== false) {
                        array_map('unlink', $files);
                    } rmdir($dir);
                }
            })($tempDir);
        }
    }

    #[Test]
    public function throwsExceptionForNonExistentDirectory(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('not a directory');
        new FileFixtureProvider((static function (): Generator {
            $faker = Factory::create();
            $faker->seed(12345);
            return $faker;
        })(), '/nonexistent/path');
    }

    #[Test]
    public function hasTableReturnsFalseForNonExistent(): void
    {
        $tempDir = (static function (): string {
            $dir = sys_get_temp_dir() . '/sql-fixture-test-' . uniqid();
            mkdir($dir, 0755, true);
            return $dir;
        })();
        try {
            $provider = new FileFixtureProvider((static function (): Generator {
                $faker = Factory::create();
                $faker->seed(12345);
                return $faker;
            })(), $tempDir);
            self::assertFalse($provider->hasTable('nonexistent'));
        } finally {
            (static function (string $dir): void {
                if (is_dir($dir)) {
                    $files = glob($dir . '/*');
                    if ($files !== false) {
                        array_map('unlink', $files);
                    } rmdir($dir);
                }
            })($tempDir);
        }
    }

    #[Test]
    public function getTableNames(): void
    {
        $tempDir = (static function (): string {
            $dir = sys_get_temp_dir() . '/sql-fixture-test-' . uniqid();
            mkdir($dir, 0755, true);
            return $dir;
        })();
        try {
            file_put_contents(
                $tempDir . '/users.sql',
                'CREATE TABLE users (id INT)'
            );
            file_put_contents(
                $tempDir . '/posts.sql',
                'CREATE TABLE posts (id INT)'
            );

            $provider = new FileFixtureProvider((static function (): Generator {
                $faker = Factory::create();
                $faker->seed(12345);
                return $faker;
            })(), $tempDir);
            $names = $provider->getTableNames();

            self::assertContains('users', $names);
            self::assertContains('posts', $names);
        } finally {
            (static function (string $dir): void {
                if (is_dir($dir)) {
                    $files = glob($dir . '/*');
                    if ($files !== false) {
                        array_map('unlink', $files);
                    } rmdir($dir);
                }
            })($tempDir);
        }
    }

    #[Test]
    public function registerSchema(): void
    {
        $tempDir = (static function (): string {
            $dir = sys_get_temp_dir() . '/sql-fixture-test-' . uniqid();
            mkdir($dir, 0755, true);
            return $dir;
        })();
        try {
            $provider = new FileFixtureProvider((static function (): Generator {
                $faker = Factory::create();
                $faker->seed(12345);
                return $faker;
            })(), $tempDir);
            $provider->registerSchema('CREATE TABLE dynamic (id INT, value TEXT)');

            self::assertTrue($provider->hasTable('dynamic'));
            $data = $provider->fixture('dynamic');
            self::assertArrayHasKey('id', $data);
        } finally {
            (static function (string $dir): void {
                if (is_dir($dir)) {
                    $files = glob($dir . '/*');
                    if ($files !== false) {
                        array_map('unlink', $files);
                    } rmdir($dir);
                }
            })($tempDir);
        }
    }

    #[Test]
    public function getFixtureGenerator(): void
    {
        $tempDir = (static function (): string {
            $dir = sys_get_temp_dir() . '/sql-fixture-test-' . uniqid();
            mkdir($dir, 0755, true);
            return $dir;
        })();
        try {
            $provider = new FileFixtureProvider((static function (): Generator {
                $faker = Factory::create();
                $faker->seed(12345);
                return $faker;
            })(), $tempDir);
            self::assertInstanceOf(FixtureGenerator::class, $provider->getFixtureGenerator());
        } finally {
            (static function (string $dir): void {
                if (is_dir($dir)) {
                    $files = glob($dir . '/*');
                    if ($files !== false) {
                        array_map('unlink', $files);
                    } rmdir($dir);
                }
            })($tempDir);
        }
    }

    #[Test]
    public function skipsInvalidSqlFiles(): void
    {
        $tempDir = (static function (): string {
            $dir = sys_get_temp_dir() . '/sql-fixture-test-' . uniqid();
            mkdir($dir, 0755, true);
            return $dir;
        })();
        try {
            file_put_contents($tempDir . '/invalid.sql', 'NOT VALID SQL');
            file_put_contents(
                $tempDir . '/valid.sql',
                'CREATE TABLE valid (id INT)'
            );

            $provider = new FileFixtureProvider((static function (): Generator {
                $faker = Factory::create();
                $faker->seed(12345);
                return $faker;
            })(), $tempDir);

            self::assertTrue($provider->hasTable('valid'));
            self::assertFalse($provider->hasTable('invalid'));
        } finally {
            (static function (string $dir): void {
                if (is_dir($dir)) {
                    $files = glob($dir . '/*');
                    if ($files !== false) {
                        array_map('unlink', $files);
                    } rmdir($dir);
                }
            })($tempDir);
        }
    }

    #[Test]
    public function handlesCommentsInSqlFiles(): void
    {
        $tempDir = (static function (): string {
            $dir = sys_get_temp_dir() . '/sql-fixture-test-' . uniqid();
            mkdir($dir, 0755, true);
            return $dir;
        })();
        try {
            $sql = <<<'SQL'
                -- This is a comment
                /* Multi-line
                   comment */
                CREATE TABLE with_comments (id INT PRIMARY KEY)
                SQL;

            file_put_contents($tempDir . '/with_comments.sql', $sql);

            $provider = new FileFixtureProvider((static function (): Generator {
                $faker = Factory::create();
                $faker->seed(12345);
                return $faker;
            })(), $tempDir);
            self::assertTrue($provider->hasTable('with_comments'));
        } finally {
            (static function (string $dir): void {
                if (is_dir($dir)) {
                    $files = glob($dir . '/*');
                    if ($files !== false) {
                        array_map('unlink', $files);
                    } rmdir($dir);
                }
            })($tempDir);
        }
    }

    #[Test]
    public function handlesEmptySqlFile(): void
    {
        $tempDir = (static function (): string {
            $dir = sys_get_temp_dir() . '/sql-fixture-test-' . uniqid();
            mkdir($dir, 0755, true);
            return $dir;
        })();
        try {
            file_put_contents($tempDir . '/empty.sql', '');
            file_put_contents(
                $tempDir . '/valid.sql',
                'CREATE TABLE valid (id INT)'
            );

            $provider = new FileFixtureProvider((static function (): Generator {
                $faker = Factory::create();
                $faker->seed(12345);
                return $faker;
            })(), $tempDir);
            self::assertTrue($provider->hasTable('valid'));
        } finally {
            (static function (string $dir): void {
                if (is_dir($dir)) {
                    $files = glob($dir . '/*');
                    if ($files !== false) {
                        array_map('unlink', $files);
                    } rmdir($dir);
                }
            })($tempDir);
        }
    }

    #[Test]
    public function handlesCommentOnlySqlFile(): void
    {
        $tempDir = (static function (): string {
            $dir = sys_get_temp_dir() . '/sql-fixture-test-' . uniqid();
            mkdir($dir, 0755, true);
            return $dir;
        })();
        try {
            file_put_contents($tempDir . '/comments_only.sql', "-- just comments\n/* block */");
            file_put_contents(
                $tempDir . '/valid.sql',
                'CREATE TABLE valid (id INT)'
            );

            $provider = new FileFixtureProvider((static function (): Generator {
                $faker = Factory::create();
                $faker->seed(12345);
                return $faker;
            })(), $tempDir);
            self::assertFalse($provider->hasTable('comments_only'));
        } finally {
            (static function (string $dir): void {
                if (is_dir($dir)) {
                    $files = glob($dir . '/*');
                    if ($files !== false) {
                        array_map('unlink', $files);
                    } rmdir($dir);
                }
            })($tempDir);
        }
    }

    #[Test]
    public function tableNameIsCaseInsensitive(): void
    {
        $tempDir = (static function (): string {
            $dir = sys_get_temp_dir() . '/sql-fixture-test-' . uniqid();
            mkdir($dir, 0755, true);
            return $dir;
        })();
        try {
            file_put_contents(
                $tempDir . '/MyTable.sql',
                'CREATE TABLE MyTable (id INT)'
            );

            $provider = new FileFixtureProvider((static function (): Generator {
                $faker = Factory::create();
                $faker->seed(12345);
                return $faker;
            })(), $tempDir);

            self::assertTrue($provider->hasTable('mytable'));
            self::assertTrue($provider->hasTable('MYTABLE'));
            self::assertTrue($provider->hasTable('MyTable'));
        } finally {
            (static function (string $dir): void {
                if (is_dir($dir)) {
                    $files = glob($dir . '/*');
                    if ($files !== false) {
                        array_map('unlink', $files);
                    } rmdir($dir);
                }
            })($tempDir);
        }
    }

    #[Test]
    public function fixtureWithMixedCaseTableName(): void
    {
        $tempDir = (static function (): string {
            $dir = sys_get_temp_dir() . '/sql-fixture-test-' . uniqid();
            mkdir($dir, 0755, true);
            return $dir;
        })();
        try {
            file_put_contents(
                $tempDir . '/Users.sql',
                'CREATE TABLE Users (id INT, name VARCHAR(255))'
            );

            $provider = new FileFixtureProvider((static function (): Generator {
                $faker = Factory::create();
                $faker->seed(12345);
                return $faker;
            })(), $tempDir);
            $data = $provider->fixture('USERS');

            self::assertArrayHasKey('id', $data);
            self::assertArrayHasKey('name', $data);
        } finally {
            (static function (string $dir): void {
                if (is_dir($dir)) {
                    $files = glob($dir . '/*');
                    if ($files !== false) {
                        array_map('unlink', $files);
                    } rmdir($dir);
                }
            })($tempDir);
        }
    }

    #[Test]
    public function registerSchemaWithMixedCaseFixture(): void
    {
        $tempDir = (static function (): string {
            $dir = sys_get_temp_dir() . '/sql-fixture-test-' . uniqid();
            mkdir($dir, 0755, true);
            return $dir;
        })();
        try {
            $provider = new FileFixtureProvider((static function (): Generator {
                $faker = Factory::create();
                $faker->seed(12345);
                return $faker;
            })(), $tempDir);
            $provider->registerSchema('CREATE TABLE MyItems (id INT, value TEXT)');

            $data = $provider->fixture('MYITEMS');

            self::assertArrayHasKey('id', $data);
            self::assertArrayHasKey('value', $data);
        } finally {
            (static function (string $dir): void {
                if (is_dir($dir)) {
                    $files = glob($dir . '/*');
                    if ($files !== false) {
                        array_map('unlink', $files);
                    } rmdir($dir);
                }
            })($tempDir);
        }
    }
}
