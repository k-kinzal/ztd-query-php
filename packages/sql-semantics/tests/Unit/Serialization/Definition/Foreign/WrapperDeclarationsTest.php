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

#[CoversClass(\SqlSemantics\Serialization\Definition\Foreign\WrapperDeclarations::class)]
#[Medium]
final class WrapperDeclarationsTest extends TestCase
{
    public function testWriteReturnsNullForAnotherOperation(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('SELECT 1');
        self::assertNull(\SqlSemantics\Serialization\Definition\Foreign\WrapperDeclarations::write($statement));
    }

    #[TestWith(['CREATE FOREIGN DATA WRAPPER fdw'])]
    #[TestWith(['CREATE FOREIGN DATA WRAPPER fdw HANDLER app.h VALIDATOR app.v'])]
    #[TestWith(['ALTER FOREIGN DATA WRAPPER fdw NO HANDLER'])]
    #[TestWith(['ALTER FOREIGN DATA WRAPPER fdw VALIDATOR app.v'])]
    #[TestWith(["ALTER FOREIGN DATA WRAPPER fdw OPTIONS (SET x 'value', DROP y)"])]
    public function testWriteIsStableAcrossBindingAndSerialization(string $sql): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::PostgreSql))->build());
        $first = $binder->bind($sql);
        $second = $binder->bind($first->toString());
        self::assertSame($first::class, $second::class);
        self::assertSame($first->toString(), $second->toString());
    }

}
