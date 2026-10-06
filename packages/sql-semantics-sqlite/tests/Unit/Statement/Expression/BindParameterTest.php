<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Expression;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\Sqlite\Dialect;
use SqlSemantics\Platform\Sqlite\Statement\Expression\BindParameter;
use SqlSemantics\Platform\Sqlite\Statement\Expression\ParameterPrefix;
use SqlSemantics\Platform\Sqlite\Statement\Query\ResultColumn;
use SqlSemantics\Platform\Sqlite\Statement\Query\Select;
use SqlSemantics\Statement\Operation;
use SqlSemantics\Statement\Reference\Missing\UnboundParameter;
use SqlSemantics\Statement\Type\Dependent;
use SqlSemantics\Statement\Type\Nullability;

#[CoversClass(BindParameter::class)]
#[Medium]
final class BindParameterTest extends TestCase
{
    public function testMarkerJoinsThePrefixAndTheLabel(): void
    {
        self::assertSame('?', (new BindParameter(ParameterPrefix::Question))->marker());
        self::assertSame('?12', (new BindParameter(ParameterPrefix::Question, '12'))->marker());
        self::assertSame(':id', (new BindParameter(ParameterPrefix::Colon, 'id'))->marker());
        self::assertSame('@a::b(1)', (new BindParameter(ParameterPrefix::At, 'a::b(1)'))->marker());
        self::assertSame('$x', (new BindParameter(ParameterPrefix::Dollar, 'x'))->marker());
    }

    public function testMarkerReadsEveryWrittenPrefix(): void
    {
        $statement = (new Semantics(Dialect::Sqlite))->analyze('SELECT ?, ?7, :a, @b, $c')->statement;

        self::assertInstanceOf(Select::class, $statement);
        $parameters = array_map(static function (object $column): BindParameter {
            self::assertInstanceOf(ResultColumn::class, $column);
            self::assertInstanceOf(BindParameter::class, $column->expression);

            return $column->expression;
        }, $statement->columns);
        self::assertSame(['?', '?7', ':a', '@b', '$c'], array_map(static fn (BindParameter $parameter): string => $parameter->marker(), $parameters));
        self::assertSame(['', '7', 'a', 'b', 'c'], array_map(static fn (BindParameter $parameter): string => $parameter->label, $parameters));
        self::assertSame([ParameterPrefix::Question, ParameterPrefix::Question, ParameterPrefix::Colon, ParameterPrefix::At, ParameterPrefix::Dollar], array_map(static fn (BindParameter $parameter): ParameterPrefix => $parameter->prefix, $parameters));
    }

    public function testDeriveScalarDependsOnTheBoundValue(): void
    {
        $operation = (new Semantics(Dialect::Sqlite))->analyze('SELECT ?2', []);
        $statement = $operation->statement;

        self::assertInstanceOf(Select::class, $statement);
        self::assertInstanceOf(ResultColumn::class, $statement->columns[0]);
        $fact = $operation->facts->scalar($statement->columns[0]->expression);
        self::assertEquals(new Dependent([new UnboundParameter('?2')]), $fact->type);
        self::assertSame(Nullability::Dependent, $fact->nullability);
        self::assertNull($fact->resolution);
        self::assertSame(Nullability::Dependent, $operation->field(0)->nullability);
        self::assertSame([], $operation->facts->diagnostics);
    }

    public function testDeriveScalarNamesTheMarkerOfANamedParameter(): void
    {
        $operation = (new Semantics(Dialect::Sqlite))->analyze('SELECT :name', []);
        $type = $operation->field(0)->type;

        self::assertInstanceOf(Dependent::class, $type);
        self::assertInstanceOf(UnboundParameter::class, $type->missing[0]);
        self::assertSame(':name', $type->missing[0]->marker);
        self::assertSame('the value bound to parameter :name', $type->missing[0]->describe());
    }

    public function testRenderWritesTheMarkerAsWritten(): void
    {
        self::assertSame('SELECT ?, ?7, :a, @b, $c', (new Semantics(Dialect::Sqlite))->analyze('select ?, ?7, :a, @b, $c')->toString());
    }

    public function testRenderWritesANewlyBuiltParameter(): void
    {
        $semantics = new Semantics(Dialect::Sqlite);
        $operation = new Operation($semantics->context(), new Select([new ResultColumn(new BindParameter(ParameterPrefix::Question)), new ResultColumn(new BindParameter(ParameterPrefix::At, 'x'))]));

        self::assertSame('SELECT ?, @x', $operation->toString());
    }
}
