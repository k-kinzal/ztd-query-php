<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Definition\Database\PostgreSql;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Definition\Database\PostgreSql\SchemaInvariant;
use SqlSemantics\Model\Validation\InvalidStructure;
use SqlSemantics\SchemaBuilder;

#[CoversClass(SchemaInvariant::class)]
#[Medium]
final class SchemaInvariantTest extends TestCase
{
    public function testElementsAcceptsCreationAndGrantCommands(): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::PostgreSql))->build());
        $select = $binder->bind('SELECT 1');
        SchemaInvariant::elements($select->origin, false, [$binder->bind('CREATE TABLE t (a int)'), $binder->bind('GRANT SELECT ON t TO bob', strict: false)]);
        $this->expectException(InvalidStructure::class);
        SchemaInvariant::elements($select->origin, false, [$select]);
    }

    public function testElementsRejectsElementsWhenTheSchemaMayExist(): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::PostgreSql))->build());
        $this->expectException(InvalidStructure::class);
        SchemaInvariant::elements($binder->bind('SELECT 1')->origin, true, [$binder->bind('CREATE TABLE t (a int)')]);
    }
}
