<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Definition\Routine;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Definition\Routine;
use SqlSemantics\Model\Relation\QualifiedName;

#[CoversClass(Routine\OrdinaryAggregate::class)]
#[\PHPUnit\Framework\Attributes\Medium]
final class OrdinaryAggregateTest extends TestCase
{
    public function testSignatureRequiresAggregatedInputs(): void
    {
        $argument = new Routine\AggregateParameter(\SqlSemantics\Type\TypeDescriptor::builtin(Dialect::PostgreSql, 'integer'));
        $target = new Routine\OrdinaryAggregate(new QualifiedName(['f']), [$argument]);
        self::assertSame([$argument], $target->parameters);
    }

    public function testSignatureRejectsMoreThanCatalogSchemaAndAggregate(): void
    {
        $this->expectException(\SqlSemantics\Model\Validation\InvalidStructure::class);
        new Routine\OrdinaryAggregate(new QualifiedName(['x', 'a', 'b', 'f']), [new Routine\AggregateParameter(\SqlSemantics\Type\TypeDescriptor::builtin(Dialect::PostgreSql, 'integer'))]);
    }
}
