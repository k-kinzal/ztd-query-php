<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Write;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Statement\InsertStatement;
use SqlSemantics\Model\Write\InsertMode;
use SqlSemantics\SchemaBuilder;

#[CoversClass(InsertMode::class)]
#[Medium]
final class InsertModeTest extends TestCase
{
    public function testRepresentsInsertionAndReplacement(): void
    {
        self::assertSame(['INSERT', 'REPLACE'], array_column(InsertMode::cases(), 'value'));
    }

    #[TestWith([Dialect::MySql, 'INSERT INTO t VALUES(1)', InsertMode::Insert, 'INSERT INTO `t` VALUES (1)'])]
    #[TestWith([Dialect::MySql, 'REPLACE INTO t VALUES(1)', InsertMode::Replace, 'REPLACE INTO `t` VALUES (1)'])]
    #[TestWith([Dialect::Sqlite, 'REPLACE INTO t VALUES(1)', InsertMode::Replace, 'REPLACE INTO "main"."t" VALUES (1)'])]
    #[TestWith([Dialect::Sqlite, 'INSERT OR REPLACE INTO t VALUES(1)', InsertMode::Insert, 'INSERT OR REPLACE INTO "main"."t" VALUES (1)'])]
    public function testBindsTheModeFromTheStatementKeyword(Dialect $dialect, string $sql, InsertMode $mode, string $expected): void
    {
        $statement = (new Binder((new SchemaBuilder($dialect))->build('CREATE TABLE t(id INTEGER)')))->bind($sql);
        self::assertInstanceOf(InsertStatement::class, $statement);
        self::assertSame($mode, $statement->mode);
        self::assertSame($expected, (new \SqlSemantics\SimpleSerializer())->serialize($statement));
    }
}
