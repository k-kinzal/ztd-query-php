<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Definition\Routine;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Model\Definition\Routine;
use SqlSemantics\Model\Relation\QualifiedName;

#[CoversClass(Routine\ZeroArgumentAggregate::class)]
#[\PHPUnit\Framework\Attributes\Medium]
final class ZeroArgumentAggregateTest extends TestCase
{
    public function testIdentityRetainsTheIdentifierPath(): void
    {
        $name = new QualifiedName(['app', 'f']);
        $target = new Routine\ZeroArgumentAggregate($name);
        self::assertSame($name, $target->name);
    }

    public function testIdentityRejectsMoreThanCatalogSchemaAndAggregate(): void
    {
        $this->expectException(\SqlSemantics\Model\Validation\InvalidStructure::class);
        new Routine\ZeroArgumentAggregate(new QualifiedName(['x', 'a', 'b', 'f']));
    }
}
