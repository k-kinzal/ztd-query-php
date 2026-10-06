<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Literal;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Contract\ParameterStyle;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\MySql\Dialect;
use SqlSemantics\Platform\MySql\Statement\Expression\Comparison;
use SqlSemantics\Platform\MySql\Statement\Literal\Parameter;
use SqlSemantics\Platform\MySql\Statement\Query\Select;
use SqlSemantics\Statement\Reference\Missing\UnboundParameter;
use SqlSemantics\Statement\Type\Dependent;
use SqlSemantics\Statement\Type\Nullability;

#[CoversClass(Parameter::class)]
#[Medium]
final class ParameterTest extends TestCase
{
    public function testDeriveScalarDependsOnTheBoundValue(): void
    {
        $operation = (new Semantics(Dialect::MySql))->analyze('SELECT a FROM t WHERE a = ?');
        $select = $operation->statement;
        self::assertInstanceOf(Select::class, $select);
        self::assertInstanceOf(Comparison::class, $select->where);
        $parameter = $select->where->right;
        self::assertInstanceOf(Parameter::class, $parameter);
        $fact = $operation->facts->scalar($parameter);

        self::assertSame('?', $parameter->marker);
        self::assertInstanceOf(Dependent::class, $fact->type);
        self::assertCount(1, $fact->type->missing);
        self::assertInstanceOf(UnboundParameter::class, $fact->type->missing[0]);
        self::assertSame('?', $fact->type->missing[0]->marker);
        self::assertSame('the value bound to parameter ?', $fact->type->missing[0]->describe());
        self::assertSame(Nullability::Dependent, $fact->nullability);
        self::assertNull($fact->resolution);
    }

    public function testDeriveScalarNamesANamedMarker(): void
    {
        $operation = (new Semantics(Dialect::MySql, null, null, ParameterStyle::Named))->analyze('SELECT a FROM t WHERE a = :id');
        $select = $operation->statement;
        self::assertInstanceOf(Select::class, $select);
        self::assertInstanceOf(Comparison::class, $select->where);
        $parameter = $select->where->right;
        self::assertInstanceOf(Parameter::class, $parameter);
        $fact = $operation->facts->scalar($parameter);

        self::assertSame(':id', $parameter->marker);
        self::assertInstanceOf(Dependent::class, $fact->type);
        self::assertSame('the value bound to parameter :id', $fact->type->missing[0]->describe());
    }

    public function testRenderWritesTheMarkerAsWritten(): void
    {
        self::assertSame('SELECT ?', (new Semantics(Dialect::MySql))->analyze('select ?')->toString());
        self::assertSame('SELECT a FROM t WHERE a = :id', (new Semantics(Dialect::MySql, null, null, ParameterStyle::Named))->analyze('SELECT a FROM t WHERE a = :id')->toString());
    }

    public function testRejectsAWordWithoutAColon(): void
    {
        $this->expectExceptionMessage('A parameter marker is a question mark or a colon and a name.');

        new Parameter('id');
    }

    public function testRejectsAColonWithoutAName(): void
    {
        $this->expectExceptionMessage('A parameter marker is a question mark or a colon and a name.');

        new Parameter(':');
    }

    public function testRejectsANumberedQuestionMark(): void
    {
        $this->expectExceptionMessage('A parameter marker is a question mark or a colon and a name.');

        new Parameter('?1');
    }
}
