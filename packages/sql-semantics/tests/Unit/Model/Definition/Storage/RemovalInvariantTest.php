<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Definition\Storage;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Definition\Storage\RemovalInvariant;
use SqlSemantics\Model\Validation\InvalidStructure;
use SqlSemantics\SchemaBuilder;

#[CoversClass(RemovalInvariant::class)]
#[Medium]
final class RemovalInvariantTest extends TestCase
{
    public function testTargetRejectsAnEmptyEngineName(): void
    {
        $origin = (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind('SELECT 1')->origin;
        $this->expectException(InvalidStructure::class);
        RemovalInvariant::target($origin, 'store', '');
    }

    public function testUndoRejectsAnOlderRelease(): void
    {
        $origin = (new Binder((new SchemaBuilder(Dialect::MySql, grammarVersion: 'mysql-5.6.51'))->build()))->bind('SELECT 1')->origin;
        $this->expectException(InvalidStructure::class);
        RemovalInvariant::undo($origin);
    }
}
