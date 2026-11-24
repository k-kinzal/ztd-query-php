<?php

declare(strict_types=1);

namespace Tests\Unit\Ir\Schema\Index\Scope;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\PostgreSql\Ir\Schema\Index\IndexRebuildMode;
use SqlSemantics\Platform\PostgreSql\Ir\Schema\Index\Scope\ReindexSchema;
use SqlSemantics\Platform\PostgreSql\Ir\Schema\Index\Scope\ReindexSchemaLowering;
use SqlSemantics\Platform\PostgreSql\Semantics;

#[CoversClass(ReindexSchemaLowering::class)]
#[Medium]
final class ReindexSchemaLoweringTest extends TestCase
{
    public function testLowerKeepsTheExplicitApplicationSchemaEvenWhenEmpty(): void
    {
        $semantics = new Semantics();
        $context = $semantics->context(['CREATE SCHEMA "reporting.data"', 'CREATE SCHEMA decoy']);
        $value = $semantics->analyze('REINDEX (CONCURRENTLY FALSE) SCHEMA CONCURRENTLY "reporting.data"', $context);

        self::assertInstanceOf(ReindexSchema::class, $value);
        self::assertSame('reporting.data', $value->schema->value);
        self::assertSame(IndexRebuildMode::Concurrent, $value->mode);
        self::assertSame('REINDEX SCHEMA CONCURRENTLY "reporting.data"', $value->toString());
    }
}
