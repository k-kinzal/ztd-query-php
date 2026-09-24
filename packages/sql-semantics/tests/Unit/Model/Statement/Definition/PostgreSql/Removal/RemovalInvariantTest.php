<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Statement\Definition\PostgreSql\Removal;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Relation\QualifiedName;
use SqlSemantics\Model\Statement\Definition\PostgreSql\Removal\RemovalInvariant;
use SqlSemantics\Model\Validation\InvalidStructure;
use SqlSemantics\SchemaBuilder;

#[CoversClass(RemovalInvariant::class)]
#[Medium]
final class RemovalInvariantTest extends TestCase
{
    public function testNamesRejectsAnOverQualifiedName(): void
    {
        $origin = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('SELECT 1')->origin;
        RemovalInvariant::names($origin, [new QualifiedName(['app', 'c'])], 2);
        $this->expectException(InvalidStructure::class);
        RemovalInvariant::names($origin, [new QualifiedName(['db', 'app', 'c'])], 2);
    }

    public function testIdentifiersRejectsAnEmptyIdentifier(): void
    {
        $origin = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('SELECT 1')->origin;
        RemovalInvariant::identifiers($origin, ['app']);
        $this->expectException(InvalidStructure::class);
        RemovalInvariant::identifiers($origin, ['app', '']);
    }

    public function testDialectRejectsAnotherDatabaseLanguage(): void
    {
        $this->expectException(InvalidStructure::class);
        RemovalInvariant::dialect((new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind('SELECT 1')->origin);
    }
}
