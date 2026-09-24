<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Statement;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Diagnostic;
use SqlSemantics\Model\Statement\Origin;
use SqlSemantics\Model\Validation\InvalidStructure;
use SqlSemantics\SchemaBuilder;

#[CoversClass(Origin::class)]
#[Medium]
final class OriginTest extends TestCase
{
    public function testRetainsProvenanceWithoutABindingContext(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::Sqlite))->build()))->bind('SELECT 1');
        $diagnostic = new Diagnostic('custom', 'A custom note', $statement->source);
        $origin = new Origin('s7', $statement->source, Dialect::Sqlite, [$diagnostic]);
        self::assertSame('s7', $origin->scopeId);
        self::assertSame($statement->source, $origin->source);
        self::assertSame(Dialect::Sqlite, $origin->dialect);
        self::assertSame([$diagnostic], $origin->diagnostics);
        self::assertNull($origin->context);
    }

    public function testBindingAttachesTheTransformationContext(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('SELECT missing', strict: false);
        self::assertSame($statement->scopeId, $statement->origin->scopeId);
        self::assertSame($statement->diagnostics, $statement->origin->diagnostics);
        self::assertSame(Dialect::PostgreSql, $statement->origin->dialect);
        self::assertNotNull($statement->origin->context);
    }

    public function testRejectsAnEmptyScopeIdentity(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::Sqlite))->build()))->bind('SELECT 1');
        $this->expectException(InvalidStructure::class);
        new Origin('', $statement->source, Dialect::Sqlite);
    }
}
