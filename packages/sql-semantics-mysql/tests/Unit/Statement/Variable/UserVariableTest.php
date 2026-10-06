<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Variable;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\MySql\Dialect;
use SqlSemantics\Platform\MySql\Statement\Query\Select;
use SqlSemantics\Platform\MySql\Statement\Query\SelectExpression;
use SqlSemantics\Platform\MySql\Statement\Variable\UserVariable;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Operation;
use SqlSemantics\Statement\Type\Dependent;
use SqlSemantics\Statement\Type\Nullability;

#[CoversClass(UserVariable::class)]
#[Medium]
final class UserVariableTest extends TestCase
{
    public function testDeriveScalarNamesTheVariableAsMissingSessionState(): void
    {
        $operation = (new Semantics(Dialect::MySql))->analyze('SELECT @total');

        self::assertInstanceOf(Select::class, $operation->statement);
        $item0 = $operation->statement->items[0];
        self::assertInstanceOf(SelectExpression::class, $item0);
        self::assertInstanceOf(UserVariable::class, $item0->expression);
        $fact = $operation->facts->scalar($item0->expression);
        self::assertInstanceOf(Dependent::class, $fact->type);
        self::assertSame('the session state: user variable @total', $fact->type->missing[0]->describe());
        self::assertSame(Nullability::Dependent, $fact->nullability);
        self::assertNull($fact->resolution);
        self::assertSame('@total', $operation->field(0)->name?->value);
    }

    public function testDeriveScalarKeepsTheNameAsWritten(): void
    {
        $operation = (new Semantics(Dialect::MySql))->analyze('SELECT @Total');

        self::assertInstanceOf(Select::class, $operation->statement);
        $item0 = $operation->statement->items[0];
        self::assertInstanceOf(SelectExpression::class, $item0);
        self::assertInstanceOf(UserVariable::class, $item0->expression);
        self::assertSame('Total', $item0->expression->name->value);
        $fact = $operation->facts->scalar($item0->expression);
        self::assertInstanceOf(Dependent::class, $fact->type);
        self::assertSame('the session state: user variable @Total', $fact->type->missing[0]->describe());
    }

    public function testRenderWritesTheAtSignGluedToTheName(): void
    {
        $semantics = new Semantics(Dialect::MySql);

        self::assertSame('SELECT @Total', $semantics->analyze('select @Total')->toString());
        self::assertSame('SELECT a FROM t WHERE a = @total', $semantics->analyze('SELECT a FROM t WHERE a = @total')->toString());
    }

    public function testRenderQuotesANameThatNeedsIt(): void
    {
        $semantics = new Semantics(Dialect::MySql);

        self::assertSame('SELECT @`my var` AS v', $semantics->analyze("SELECT @'my var' AS v")->toString());
        self::assertSame('SELECT @x AS v', $semantics->analyze('SELECT @`x` AS v')->toString());
        self::assertSame("SELECT @'my var'", $semantics->analyze("SELECT @'my var'")->toString());
    }

    public function testRenderBuildsSqlFromAnExplicitStructure(): void
    {
        $semantics = new Semantics(Dialect::MySql);
        $variable = new UserVariable(new Name('my var'));
        $operation = new Operation($semantics->context(), new Select([], [new SelectExpression($variable)]));

        self::assertSame('SELECT @`my var`', $operation->toString());
        self::assertSame(Nullability::Dependent, $operation->facts->scalar($variable)->nullability);
    }
}
