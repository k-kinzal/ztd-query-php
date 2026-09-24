<?php

declare(strict_types=1);

namespace Tests\Unit\Serialization\Definition\TypeSystem;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\SchemaBuilder;
use SqlSemantics\Serialization\Definition\TypeSystem\Aggregates;

#[CoversClass(Aggregates::class)]
#[Medium]
final class AggregatesTest extends TestCase
{
    public function testWriteUsesTheSignatureSyntax(): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::PostgreSql))->build());
        self::assertSame('CREATE OR REPLACE AGGREGATE "a"(*)(SFUNC = "f", STYPE = bigint)', Aggregates::write($binder->bind('CREATE OR REPLACE AGGREGATE a (basetype = "any", sfunc = f, stype = int8)'))?->toString());
        self::assertNull(Aggregates::write($binder->bind('SELECT 1')));
    }
}
