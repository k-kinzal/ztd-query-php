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

#[CoversClass(\SqlSemantics\Serialization\Definition\Foreign\ForeignServers::class)]
#[Medium]
final class ForeignServersTest extends TestCase
{
    public function testWriteReturnsNullForUnrelatedStatements(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('SELECT 1');
        self::assertNull(\SqlSemantics\Serialization\Definition\Foreign\ForeignServers::write($statement));
    }

    #[TestWith(['CREATE SERVER remote FOREIGN DATA WRAPPER fdw'])]
    #[TestWith(["CREATE SERVER remote TYPE 'sql' VERSION 'v1' FOREIGN DATA WRAPPER fdw OPTIONS (host 'local')"])]
    #[TestWith(['ALTER SERVER remote VERSION NULL'])]
    #[TestWith(["ALTER SERVER remote VERSION 'v2' OPTIONS (SET host 'elsewhere')"])]
    #[TestWith(["ALTER SERVER remote OPTIONS (DROP host, ADD host 'other')"])]
    public function testWriteReconstructsStableNativeOperands(string $sql): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::PostgreSql))->build());
        $first = $binder->bind($sql);
        $second = $binder->bind($first->toString());
        self::assertSame($first::class, $second::class);
        self::assertSame($first->toString(), $second->toString());
        self::assertSame($first->kind, $second->kind);
    }
}
