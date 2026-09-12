<?php

declare(strict_types=1);

namespace Tests\Unit\Platform;

use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\TestCase;
use Tests\Fake\FakeCopySupport;
use ZtdQuery\Schema\TableDefinition;

#[CoversNothing]
final class CopySupportTest extends TestCase
{
    public function testTableNameDropsTheSchemaARelationWasQualifiedWith(): void
    {
        self::assertSame('users', (new FakeCopySupport())->tableName('public.users'));
    }

    public function testTargetTakesEveryColumnOfTheTableWhereTheStatementNamedNone(): void
    {
        $definition = new TableDefinition(['id', 'name'], [], ['id'], [], []);

        $target = (new FakeCopySupport())->target('public.users', null, $definition);

        self::assertSame(['id', 'name'], $target->columns);
        self::assertSame('users', $target->tableName());
    }

    public function testTargetTakesTheColumnsTheStatementNamed(): void
    {
        $definition = new TableDefinition(['id', 'name'], [], ['id'], [], []);

        $target = (new FakeCopySupport())->target('users', 'name, id', $definition);

        self::assertSame(['name', 'id'], $target->columns);
    }

    public function testSelectSqlReadsTheColumnsTheStatementIsAbout(): void
    {
        $definition = new TableDefinition(['id', 'name'], [], ['id'], [], []);
        $support = new FakeCopySupport();

        $target = $support->target('users', null, $definition);

        self::assertSame('SELECT id, name FROM users', $support->selectSql($target));
    }

    public function testInsertSqlWritesOneGroupOfValuesPerRow(): void
    {
        $definition = new TableDefinition(['id'], [], ['id'], [], []);
        $support = new FakeCopySupport();

        $sql = $support->insertSql($support->target('users', null, $definition), 2, false);

        self::assertSame('INSERT INTO users (id) VALUES (?), (?)', $sql);
    }

    public function testInsertSqlSaysWhenAColumnTheDatabaseNumbersIsBeingWrittenAnyway(): void
    {
        $definition = new TableDefinition(['id'], [], ['id'], [], []);
        $support = new FakeCopySupport();

        $sql = $support->insertSql($support->target('users', null, $definition), 1, true);

        self::assertStringContainsString('OVERRIDING SYSTEM VALUE', $sql);
    }

    public function testEncodeRowWritesANullAsWhateverTheStatementSaidStandsForOne(): void
    {
        self::assertSame("1\tN", (new FakeCopySupport())->encodeRow([1, null], "\t", 'N'));
    }

    public function testDecodeRowReadsBackWhatEncodeRowWrote(): void
    {
        $support = new FakeCopySupport();

        $written = $support->encodeRow(['a', null], "\t", '\\N');

        self::assertSame(['a', null], $support->decodeRow($written, "\t", '\\N'));
    }

    public function testIsCopyStatementAnswersForTheStatementsThisIsAbout(): void
    {
        $support = new FakeCopySupport();

        self::assertTrue($support->isCopyStatement('COPY users TO STDOUT'));
        self::assertFalse($support->isCopyStatement('SELECT 1'));
    }
}
