<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Operator;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\PostgreSql\Dialect;
use SqlSemantics\Platform\PostgreSql\Statement\Name\DottedName;
use SqlSemantics\Platform\PostgreSql\Statement\Name\OperatorName;
use SqlSemantics\Platform\PostgreSql\Statement\Object\Attribute\Attribute;
use SqlSemantics\Platform\PostgreSql\Statement\Object\Attribute\AttributeChange;
use SqlSemantics\Platform\PostgreSql\Statement\Object\Attribute\Known\OperatorAttribute;
use SqlSemantics\Platform\PostgreSql\Statement\Operator\AlterOperator;
use SqlSemantics\Platform\PostgreSql\Statement\Routine\Problem\RoutineProblem;
use SqlSemantics\Platform\PostgreSql\Statement\Routine\Problem\RoutineProblemKind;
use SqlSemantics\Platform\PostgreSql\Statement\Routine\Signature\OperatorArity;
use SqlSemantics\Platform\PostgreSql\Statement\Routine\Signature\OperatorSignature;
use SqlSemantics\Platform\PostgreSql\Statement\Type\Designation\NamedDesignation;
use SqlSemantics\Platform\PostgreSql\Statement\Type\TypeName;
use SqlSemantics\Statement\Identifier\Name;

#[CoversClass(AlterOperator::class)]
#[Medium]
final class AlterOperatorTest extends TestCase
{
    public function testDeriveStatementReportsAPostfixOperator(): void
    {
        $operation = (new Semantics(Dialect::PostgreSql))->analyze('ALTER OPERATOR ! (int4, NONE) SET (restrict = NONE)');
        self::assertEquals([new RoutineProblem(RoutineProblemKind::PostfixOperator)], $operation->facts->diagnostics);
    }

    public function testRenderWritesTheAttributes(): void
    {
        $operation = (new Semantics(Dialect::PostgreSql))->analyze('ALTER OPERATOR = (int4, int4) SET (restrict = NONE, join = eqjoinsel, hashes)');
        self::assertSame('ALTER OPERATOR = (int4, int4) SET (restrict = NONE, join = eqjoinsel, hashes)', $operation->toString());
    }

    public function testRejectsNoAttribute(): void
    {
        $this->expectExceptionMessage('ALTER OPERATOR ... SET changes at least one attribute.');
        new AlterOperator(new OperatorSignature(new OperatorName(new Name('=')), OperatorArity::Binary, [new TypeName(new NamedDesignation(new DottedName([new Name('int4')]))), new TypeName(new NamedDesignation(new DottedName([new Name('int4')])))]), []);
    }

    public function testRejectsAnAttributeOfAnotherCommand(): void
    {
        $this->expectExceptionMessage('ALTER OPERATOR reads the attributes it can change.');
        new AlterOperator(new OperatorSignature(new OperatorName(new Name('=')), OperatorArity::Binary, [new TypeName(new NamedDesignation(new DottedName([new Name('int4')]))), new TypeName(new NamedDesignation(new DottedName([new Name('int4')])))]), [new AttributeChange(new Attribute(new Name('sort1'), OperatorAttribute::Sort1))]);
    }

    public function testDeriveStatementReportsTheAttributesTheCommandRefuses(): void
    {
        $operation = (new Semantics(Dialect::PostgreSql, 'pg-16.6'))->analyze('ALTER OPERATOR = (int4, int4) SET (commutator = =, leftarg = int4, sort1 = <)');
        self::assertSame([
            'operator attribute "commutator" cannot be changed',
            'operator attribute "leftarg" cannot be changed',
            'operator attribute "sort1" not recognized',
        ], array_map(static fn ($problem): string => $problem->message(), $operation->facts->diagnostics));
    }
}
