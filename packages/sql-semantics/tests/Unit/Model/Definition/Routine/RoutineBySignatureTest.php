<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Definition\Routine;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Definition\Routine;
use SqlSemantics\Model\Relation\QualifiedName;

#[CoversClass(Routine\RoutineBySignature::class)]
#[\PHPUnit\Framework\Attributes\Medium]
final class RoutineBySignatureTest extends TestCase
{
    public function testEmptySignatureMeansZeroArguments(): void
    {
        $target = new Routine\RoutineBySignature(new QualifiedName(['f']), []);
        self::assertSame([], $target->parameters);
    }

    public function testSignatureKeepsOutputArgumentsAndTheirOrder(): void
    {
        $type = \SqlSemantics\Type\TypeDescriptor::builtin(Dialect::PostgreSql, 'integer');
        $input = new Routine\RoutineParameter($type, Routine\ParameterMode::Input);
        $output = new Routine\RoutineParameter($type, Routine\ParameterMode::Output);
        $target = new Routine\RoutineBySignature(new QualifiedName(['f']), [$input, $output]);
        self::assertSame([$input, $output], $target->parameters);
    }

    public function testSignatureRejectsMoreThanCatalogSchemaAndRoutine(): void
    {
        $this->expectException(\SqlSemantics\Model\Validation\InvalidStructure::class);
        new Routine\RoutineBySignature(new QualifiedName(['x', 'a', 'b', 'f']), []);
    }
}
