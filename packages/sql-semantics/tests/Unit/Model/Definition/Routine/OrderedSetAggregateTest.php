<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Definition\Routine;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Definition\Routine;
use SqlSemantics\Model\Relation\QualifiedName;

#[CoversClass(Routine\OrderedSetAggregate::class)]
final class OrderedSetAggregateTest extends TestCase
{
    public function testSignatureKeepsDirectAndOrderedArgumentsSeparate(): void
    {
        $type = \SqlSemantics\Type\TypeDescriptor::builtin(Dialect::PostgreSql, 'integer');
        $direct = new Routine\AggregateParameter($type, name: 'direct');
        $ordered = new Routine\AggregateParameter($type, name: 'ordered');
        $target = new Routine\OrderedSetAggregate(new QualifiedName(['f']), [$direct], [$ordered]);
        self::assertSame([$direct], $target->direct);
        self::assertSame([$ordered], $target->ordered);
    }

    public function testVariadicDirectInputRequiresMatchingVariadicOrderedInput(): void
    {
        $type = \SqlSemantics\Type\TypeDescriptor::builtin(Dialect::PostgreSql, 'integer');
        $argument = new Routine\AggregateParameter($type, Routine\AggregateInputMode::Variadic);
        $target = new Routine\OrderedSetAggregate(new QualifiedName(['f']), [$argument], [$argument]);
        self::assertSame([$argument], $target->ordered);
        $this->expectException(\SqlSemantics\Model\Validation\InvalidStructure::class);
        new Routine\OrderedSetAggregate(new QualifiedName(['f']), [$argument], [new Routine\AggregateParameter($type)]);
    }

    public function testVariadicSignatureRejectsDifferentTypes(): void
    {
        $direct = new Routine\AggregateParameter(\SqlSemantics\Type\TypeDescriptor::builtin(Dialect::PostgreSql, 'integer'), Routine\AggregateInputMode::Variadic);
        $ordered = new Routine\AggregateParameter(\SqlSemantics\Type\TypeDescriptor::builtin(Dialect::PostgreSql, 'text'), Routine\AggregateInputMode::Variadic);
        $this->expectException(\SqlSemantics\Model\Validation\InvalidStructure::class);
        new Routine\OrderedSetAggregate(new QualifiedName(['f']), [$direct], [$ordered]);
    }

    public function testVariadicSignatureRejectsMultipleOrderedInputs(): void
    {
        $argument = new Routine\AggregateParameter(\SqlSemantics\Type\TypeDescriptor::builtin(Dialect::PostgreSql, 'integer'), Routine\AggregateInputMode::Variadic);
        $this->expectException(\SqlSemantics\Model\Validation\InvalidStructure::class);
        new Routine\OrderedSetAggregate(new QualifiedName(['f']), [$argument], [$argument, $argument]);
    }

}
