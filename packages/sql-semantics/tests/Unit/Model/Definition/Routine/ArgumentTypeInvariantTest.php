<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Definition\Routine;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Definition\Routine;
use SqlSemantics\Model\Relation\QualifiedName;

#[CoversClass(Routine\ArgumentTypeInvariant::class)]
final class ArgumentTypeInvariantTest extends TestCase
{
    public function testValidateRejectsInferredTypes(): void
    {
        $this->expectException(\SqlSemantics\Model\Validation\InvalidStructure::class);
        Routine\ArgumentTypeInvariant::validate(\SqlSemantics\Type\TypeDescriptor::builtin(Dialect::PostgreSql, 'unknown'));
    }

    public function testSameComparesColumnReferencesByIdentity(): void
    {
        $left = new Routine\ColumnTypeReference(new QualifiedName(['t', 'id']), null);
        $right = new Routine\ColumnTypeReference(new QualifiedName(['t', 'id']), null);
        self::assertTrue(Routine\ArgumentTypeInvariant::same($left, $right));
        self::assertFalse(Routine\ArgumentTypeInvariant::same($left, new Routine\ColumnTypeReference(new QualifiedName(['u', 'id']), null)));
        self::assertFalse(Routine\ArgumentTypeInvariant::same($left, \SqlSemantics\Type\TypeDescriptor::builtin(Dialect::PostgreSql, 'integer')));
    }

    public function testSameComparesDeclaredTypeStructure(): void
    {
        $left = \SqlSemantics\Type\TypeDescriptor::builtin(Dialect::PostgreSql, 'integer');
        self::assertTrue(Routine\ArgumentTypeInvariant::same($left, \SqlSemantics\Type\TypeDescriptor::builtin(Dialect::PostgreSql, 'integer')));
        self::assertFalse(Routine\ArgumentTypeInvariant::same($left, \SqlSemantics\Type\TypeDescriptor::builtin(Dialect::PostgreSql, 'text')));
    }

}
