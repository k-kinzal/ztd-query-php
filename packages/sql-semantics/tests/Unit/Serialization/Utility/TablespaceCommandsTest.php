<?php

declare(strict_types=1);

namespace Tests\Unit\Serialization\Utility;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\SchemaBuilder;
use SqlSemantics\Serialization\Utility\TablespaceCommands;

#[CoversClass(TablespaceCommands::class)]
#[Medium]
final class TablespaceCommandsTest extends TestCase
{
    #[TestWith(["CREATE TABLESPACE t OWNER bob LOCATION '/x' WITH (seq_page_cost = 1)", 'CREATE TABLESPACE "t" OWNER "bob" LOCATION \'/x\' WITH ("seq_page_cost" = 1)'])]
    #[TestWith(["ALTER TABLESPACE t SET (random_page_cost = '2')", 'ALTER TABLESPACE "t" SET ("random_page_cost" = \'2\')'])]
    #[TestWith(['ALTER TABLESPACE t RESET (a.b, c)', 'ALTER TABLESPACE "t" RESET("a"."b", "c")'])]
    #[TestWith(['DROP TABLESPACE IF EXISTS t', 'DROP TABLESPACE IF EXISTS "t"'])]
    public function testWriteProducesAFixedPointOfBinding(string $sql, string $expected): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::PostgreSql))->build());
        self::assertSame($expected, TablespaceCommands::write($binder->bind($sql))?->toString());
        self::assertSame($expected, (new \SqlSemantics\SimpleSerializer())->serialize($binder->bind($expected)));
    }

    public function testWriteReturnsNullForOtherOperations(): void
    {
        self::assertNull(TablespaceCommands::write((new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('SELECT 1')));
    }

    public function testTargetNamesTheAlteredTablespace(): void
    {
        self::assertSame('ALTER TABLESPACE "t"', TablespaceCommands::target('t')->toString());
    }
}
