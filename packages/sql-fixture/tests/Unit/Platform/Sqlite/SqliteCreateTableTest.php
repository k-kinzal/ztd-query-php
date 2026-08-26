<?php

declare(strict_types=1);

namespace Tests\Unit\Platform\Sqlite;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use SqlFixture\Platform\Sqlite\SqliteCreateTable;

#[CoversClass(SqliteCreateTable::class)]
final class SqliteCreateTableTest extends TestCase
{
    public function testNormalizedRemovesCommentsAndPutsTheStatementOnOneLine(): void
    {
        self::assertSame(
            'CREATE TABLE users ( id INTEGER )',
            (new SqliteCreateTable())->normalized("CREATE TABLE users ( -- a note\n  id INTEGER /* another */\n)"),
        );
    }

    #[DataProvider('providerStatement')]
    public function testTableNameAnswersTheTableTheStatementCreates(string $sql, ?string $expected): void
    {
        self::assertSame($expected, (new SqliteCreateTable())->tableName($sql));
    }

    /**
     * @return iterable<string, array{string, string|null}>
     */
    public static function providerStatement(): iterable
    {
        yield 'bare' => ['CREATE TABLE users (id INT)', 'users'];
        yield 'quoted' => ['CREATE TABLE "users" (id INT)', 'users'];
        yield 'if not exists' => ['CREATE TABLE IF NOT EXISTS users (id INT)', 'users'];
        yield 'schema qualified' => ['CREATE TABLE main.users (id INT)', 'users'];
        yield 'not a create table' => ['SELECT 1', null];
    }

    public function testColumnsBlockAnswersWhatIsBetweenTheOutermostParentheses(): void
    {
        self::assertSame(
            'id INT, name TEXT',
            (new SqliteCreateTable())->columnsBlock('CREATE TABLE users (id INT, name TEXT)'),
        );
    }

    public function testColumnsBlockAnswersNothingForAStatementWithNoBody(): void
    {
        self::assertNull((new SqliteCreateTable())->columnsBlock('CREATE TABLE users'));
    }

    public function testDefinitionsSplitOnTheCommasBetweenDeclarations(): void
    {
        self::assertSame(
            ['id INT', 'name TEXT'],
            (new SqliteCreateTable())->definitions('id INT, name TEXT'),
        );
    }

    public function testDefinitionsKeepACommaInsideParenthesesWithItsDeclaration(): void
    {
        self::assertSame(
            ['amount DECIMAL(10, 2)', 'CHECK (a > 1, b < 2)'],
            (new SqliteCreateTable())->definitions('amount DECIMAL(10, 2), CHECK (a > 1, b < 2)'),
        );
    }

    #[DataProvider('providerConstraint')]
    public function testIsTableConstraintTellsAConstraintFromAColumn(string $definition, bool $expected): void
    {
        self::assertSame($expected, (new SqliteCreateTable())->isTableConstraint($definition));
    }

    /**
     * @return iterable<string, array{string, bool}>
     */
    public static function providerConstraint(): iterable
    {
        yield 'primary key' => ['PRIMARY KEY (id)', true];
        yield 'foreign key' => ['FOREIGN KEY (a) REFERENCES b(c)', true];
        yield 'unique' => ['UNIQUE (a)', true];
        yield 'check' => ['CHECK (a > 1)', true];
        yield 'named constraint' => ['CONSTRAINT c UNIQUE (a)', true];
        yield 'a column' => ['id INTEGER', false];
        yield 'a column named like one' => ['checked INTEGER', false];
    }

    public function testPrimaryKeysAnswersTheColumnsATableLevelKeyNames(): void
    {
        self::assertSame(
            ['shop_id', 'no'],
            (new SqliteCreateTable())->primaryKeys('shop_id INT, no INT, PRIMARY KEY ("shop_id", `no`)'),
        );
    }

    public function testPrimaryKeysAnswersNothingWhereNoTableLevelKeyIsDeclared(): void
    {
        self::assertSame([], (new SqliteCreateTable())->primaryKeys('id INTEGER PRIMARY KEY'));
    }
}
