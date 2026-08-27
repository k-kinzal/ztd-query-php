<?php

declare(strict_types=1);

namespace Tests\Unit\Platform\PostgreSql;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use SqlFixture\Platform\PostgreSql\PostgreSqlCreateTable;

#[CoversClass(PostgreSqlCreateTable::class)]
final class PostgreSqlCreateTableTest extends TestCase
{
    public function testNormalizedRemovesCommentsAndPutsTheStatementOnOneLine(): void
    {
        self::assertSame(
            'CREATE TABLE users ( id INT )',
            (new PostgreSqlCreateTable())->normalized("CREATE TABLE users ( -- a note\n  id INT /* another */\n)"),
        );
    }

    #[DataProvider('providerStatement')]
    public function testTableNameDropsTheSchemaAndAnswersTheTable(string $sql, ?string $expected): void
    {
        self::assertSame($expected, (new PostgreSqlCreateTable())->tableName($sql));
    }

    /**
     * @return iterable<string, array{string, string|null}>
     */
    public static function providerStatement(): iterable
    {
        yield 'bare' => ['CREATE TABLE users (id INT)', 'users'];
        yield 'quoted' => ['CREATE TABLE "users" (id INT)', 'users'];
        yield 'if not exists' => ['CREATE TABLE IF NOT EXISTS users (id INT)', 'users'];
        yield 'schema qualified' => ['CREATE TABLE public.users (id INT)', 'users'];
        yield 'not a create table' => ['SELECT 1', null];
    }

    public function testColumnsBlockAnswersWhatIsBetweenTheOutermostParentheses(): void
    {
        self::assertSame(
            'id INT, name TEXT',
            (new PostgreSqlCreateTable())->columnsBlock('CREATE TABLE users (id INT, name TEXT)'),
        );
    }

    public function testColumnsBlockAnswersNothingForAStatementWithNoBody(): void
    {
        self::assertNull((new PostgreSqlCreateTable())->columnsBlock('CREATE TABLE users'));
    }

    public function testDefinitionsKeepACommaInsideParenthesesWithItsDeclaration(): void
    {
        self::assertSame(
            ['amount NUMERIC(10, 2)', 'name TEXT'],
            (new PostgreSqlCreateTable())->definitions('amount NUMERIC(10, 2), name TEXT'),
        );
    }

    #[DataProvider('providerConstraint')]
    public function testIsTableConstraintTellsAConstraintFromAColumn(string $definition, bool $expected): void
    {
        self::assertSame($expected, (new PostgreSqlCreateTable())->isTableConstraint($definition));
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
        yield 'exclude, which is postgres own' => ['EXCLUDE USING gist (a WITH =)', true];
        yield 'a column' => ['id INTEGER', false];
    }

    public function testPrimaryKeysAnswersTheColumnsATableLevelKeyNames(): void
    {
        self::assertSame(
            ['shop_id', 'no'],
            (new PostgreSqlCreateTable())->primaryKeys('shop_id INT, no INT, PRIMARY KEY ("shop_id", no)'),
        );
    }

    public function testPrimaryKeysAnswersNothingWhereNoTableLevelKeyIsDeclared(): void
    {
        self::assertSame([], (new PostgreSqlCreateTable())->primaryKeys('id SERIAL PRIMARY KEY'));
    }
    public function testNormalizedTakesABlockCommentOutOfTheStatement(): void
    {
        $normalized = (new PostgreSqlCreateTable())->normalized("CREATE TABLE t (\n  id INTEGER /* the key */\n)");

        self::assertSame('CREATE TABLE t ( id INTEGER )', $normalized);
    }

    public function testNormalizedLeavesNoSpaceAtEitherEndOfTheStatement(): void
    {
        $normalized = (new PostgreSqlCreateTable())->normalized('   CREATE TABLE t (id INTEGER)   ');

        self::assertSame('CREATE TABLE t (id INTEGER)', $normalized);
    }

    public function testColumnsBlockAnswersNothingWhereTheBracketsCloseBeforeTheyOpen(): void
    {
        self::assertNull((new PostgreSqlCreateTable())->columnsBlock(') CREATE TABLE t ('));
    }

    public function testColumnsBlockAnswersNothingWhereTheBracketsAreEmptyOfEachOther(): void
    {
        self::assertNull((new PostgreSqlCreateTable())->columnsBlock('CREATE TABLE t )('));
    }

    public function testDefinitionsLeaveNoSpaceAtEitherEndOfADeclaration(): void
    {
        $definitions = (new PostgreSqlCreateTable())->definitions('  id INTEGER  ,  name TEXT  ');

        self::assertSame(['id INTEGER', 'name TEXT'], $definitions);
    }

    public function testDefinitionsDropATrailingCommaThatDeclaresNothing(): void
    {
        $definitions = (new PostgreSqlCreateTable())->definitions('id INTEGER,   ');

        self::assertSame(['id INTEGER'], $definitions);
    }

    public function testIsTableConstraintIgnoresTheSpaceBeforeAConstraint(): void
    {
        self::assertTrue((new PostgreSqlCreateTable())->isTableConstraint('   PRIMARY KEY (id)'));
    }

    public function testIsTableConstraintReadsAConstraintWrittenInLowerCase(): void
    {
        self::assertTrue((new PostgreSqlCreateTable())->isTableConstraint('primary key (id)'));
    }

    public function testIsTableConstraintCallsAColumnThatMentionsAKeyLaterAColumn(): void
    {
        self::assertFalse((new PostgreSqlCreateTable())->isTableConstraint('id INTEGER PRIMARY KEY'));
    }

    public function testPrimaryKeysReadsAKeyWrittenInLowerCase(): void
    {
        self::assertSame(['id'], (new PostgreSqlCreateTable())->primaryKeys('id INTEGER, primary key (id)'));
    }
}
