<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use ZtdQuery\Platform\Postgres\Dialect\PgSqlColumnTypeMapper;
use ZtdQuery\Platform\Postgres\Dialect\PgSqlLexerProfile;
use ZtdQuery\Platform\Postgres\Parse\PgSqlForeignKeyDefinitionParser;
use ZtdQuery\Platform\Postgres\Parse\PgSqlPartitionParser;
use ZtdQuery\Platform\Postgres\Parse\PgSqlSchemaParser;
use ZtdQuery\Platform\Postgres\PgSqlReflectedSchema;
use ZtdQuery\Schema\Key\PartialUniqueIndex;
use ZtdQuery\Schema\TableDefinition;
use ZtdQuery\Schema\TableDefinitionRegistry;
use ZtdQuery\Schema\ViewDefinition;

#[CoversClass(PgSqlReflectedSchema::class)]
#[UsesClass(PgSqlSchemaParser::class)]
#[UsesClass(PgSqlColumnTypeMapper::class)]
#[UsesClass(PgSqlLexerProfile::class)]
#[UsesClass(PgSqlForeignKeyDefinitionParser::class)]
#[UsesClass(PgSqlPartitionParser::class)]
final class PgSqlReflectedSchemaTest extends TestCase
{
    public function testTablesReadsEachDeclarationIntoADefinition(): void
    {
        $registry = (new PgSqlReflectedSchema())->tables(
            ['users' => 'CREATE TABLE users (id integer PRIMARY KEY)'],
            [],
            ['keys' => [], 'relations' => []],
        );

        self::assertInstanceOf(TableDefinition::class, $registry->get('users'));
    }

    public function testTablesLeavesOutADeclarationNothingCouldBeReadFrom(): void
    {
        $registry = (new PgSqlReflectedSchema())->tables(
            ['users' => 'NOT A DECLARATION'],
            [],
            ['keys' => [], 'relations' => []],
        );

        self::assertNull($registry->get('users'));
    }

    public function testAddPartialUniqueIndexesWritesThemOntoTheTableTheyAreOn(): void
    {
        $registry = (new PgSqlReflectedSchema())->tables(
            ['users' => 'CREATE TABLE users (id integer, email text)'],
            [],
            ['keys' => [], 'relations' => []],
        );

        (new PgSqlReflectedSchema())->addPartialUniqueIndexes(
            $registry,
            ['users' => ['users_email' => new PartialUniqueIndex('users_email', ['email'], 'id IS NOT NULL')]],
        );

        self::assertNotSame([], $registry->get('users')->partialUniqueIndexes ?? []);
    }

    public function testAddPartialUniqueIndexesLeavesATableNothingDeclared(): void
    {
        $registry = new TableDefinitionRegistry();

        (new PgSqlReflectedSchema())->addPartialUniqueIndexes(
            $registry,
            ['logs' => ['logs_a' => new PartialUniqueIndex('logs_a', ['a'], 'a IS NOT NULL')]],
        );

        self::assertNull($registry->get('logs'));
    }

    public function testAddPartitioningLeavesATableNothingDeclared(): void
    {
        $registry = new TableDefinitionRegistry();

        (new PgSqlReflectedSchema())->addPartitioning($registry, ['keys' => [], 'relations' => []]);

        self::assertNull($registry->get('logs'));
    }

    public function testViewsReadsEveryViewTheDatabaseDeclares(): void
    {
        $views = (new PgSqlReflectedSchema())->views(
            ['active_users' => new ViewDefinition('SELECT * FROM users', [])],
        );

        self::assertTrue($views->has('active_users'));
    }
}
