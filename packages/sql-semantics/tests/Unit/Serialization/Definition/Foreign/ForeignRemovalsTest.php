<?php

declare(strict_types=1);

namespace Tests\Unit\Serialization\Definition\Foreign;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\SchemaBuilder;

#[CoversClass(\SqlSemantics\Serialization\Definition\Foreign\ForeignRemovals::class)]
#[Medium]
final class ForeignRemovalsTest extends TestCase
{
    public function testWriteReturnsNullForUnrelatedStatements(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('SELECT 1');
        self::assertNull(\SqlSemantics\Serialization\Definition\Foreign\ForeignRemovals::write($statement));
    }

    #[TestWith(['DROP SERVER IF EXISTS a, b CASCADE'])]
    #[TestWith(['DROP FOREIGN DATA WRAPPER IF EXISTS a, b RESTRICT'])]
    public function testWriteReconstructsStableNativeOperands(string $sql): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::PostgreSql))->build());
        $first = $binder->bind($sql);
        $second = $binder->bind($first->toString());
        self::assertSame($first::class, $second::class);
        self::assertSame($first->toString(), $second->toString());
        self::assertSame($first->kind, $second->kind);
    }

    #[TestWith(['DROP SERVER IF EXISTS s CASCADE', 'DROP SERVER IF EXISTS "s" CASCADE'])]
    #[TestWith(['DROP SERVER s', 'DROP SERVER "s"'])]
    #[TestWith(['DROP FOREIGN DATA WRAPPER w, v RESTRICT', 'DROP FOREIGN DATA WRAPPER "w", "v" RESTRICT'])]
    public function testWriteSpellsExistenceAndBehavior(string $sql, string $expected): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind($sql);
        self::assertSame($expected, \SqlSemantics\Serialization\Definition\Foreign\ForeignRemovals::write($statement)?->toString());
    }
}
