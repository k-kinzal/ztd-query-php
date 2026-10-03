<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Variable;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\MySql\Dialect;
use SqlSemantics\Platform\MySql\Statement\Expression\Comparison;
use SqlSemantics\Platform\MySql\Statement\Literal\NumberLiteral;
use SqlSemantics\Platform\MySql\Statement\Query\Select;
use SqlSemantics\Platform\MySql\Statement\Type\Integral;
use SqlSemantics\Platform\MySql\Statement\Type\Kind\IntegralKind;
use SqlSemantics\Platform\MySql\Statement\Variable\VariableAssignment;
use SqlSemantics\Statement\Declaration\Column;
use SqlSemantics\Statement\Declaration\Table;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Identifier\QualifiedName;
use SqlSemantics\Statement\Type\Dependent;
use SqlSemantics\Statement\Type\Known;
use SqlSemantics\Statement\Type\Nullability;

#[CoversClass(VariableAssignment::class)]
#[Medium]
final class VariableAssignmentTest extends TestCase
{
    public function testDeriveScalarHasTheFactsOfTheValue(): void
    {
        $semantics = new Semantics(Dialect::MySql);
        $table = new Table(new QualifiedName(new Name('t'), new Name('(current)')), $semantics->profile(), [
            new Column(new Name('a'), new Integral(IntegralKind::Int), Nullability::NotNull),
            new Column(new Name('b'), new Integral(IntegralKind::BigInt), Nullability::Nullable),
        ]);
        $operation = $semantics->analyze('SELECT @n := b, @m := a FROM t', [$table]);

        self::assertInstanceOf(Select::class, $operation->statement);
        self::assertInstanceOf(VariableAssignment::class, $operation->statement->items[0]->expression);
        $nullable = $operation->facts->scalar($operation->statement->items[0]->expression);
        self::assertInstanceOf(Known::class, $nullable->type);
        self::assertSame('BIGINT', $nullable->type->descriptor->name());
        self::assertSame(Nullability::Nullable, $nullable->nullability);
        self::assertNull($nullable->resolution);
        self::assertInstanceOf(VariableAssignment::class, $operation->statement->items[1]->expression);
        $notNull = $operation->facts->scalar($operation->statement->items[1]->expression);
        self::assertInstanceOf(Known::class, $notNull->type);
        self::assertSame('INT', $notNull->type->descriptor->name());
        self::assertSame(Nullability::NotNull, $notNull->nullability);
        self::assertNull($operation->field(0)->name);
        self::assertNull($operation->field(0)->column());
    }

    public function testDeriveScalarRecordsTheTargetAsMissingSessionState(): void
    {
        $operation = (new Semantics(Dialect::MySql))->analyze('SELECT @n := 5');

        self::assertInstanceOf(Select::class, $operation->statement);
        self::assertInstanceOf(VariableAssignment::class, $operation->statement->items[0]->expression);
        $target = $operation->facts->scalar($operation->statement->items[0]->expression->target);
        self::assertInstanceOf(Dependent::class, $target->type);
        self::assertSame('the session state: user variable @n', $target->type->missing[0]->describe());
        self::assertSame(Nullability::NotNull, $operation->facts->scalar($operation->statement->items[0]->expression)->nullability);
    }

    public function testDeriveScalarTakesEverythingRightOfTheOperatorAsTheValue(): void
    {
        $operation = (new Semantics(Dialect::MySql))->analyze('SELECT a FROM t WHERE a = @n := 5');

        self::assertInstanceOf(Select::class, $operation->statement);
        self::assertInstanceOf(Comparison::class, $operation->statement->where);
        self::assertInstanceOf(VariableAssignment::class, $operation->statement->where->right);
        self::assertSame('n', $operation->statement->where->right->target->name->value);
        self::assertInstanceOf(NumberLiteral::class, $operation->statement->where->right->value);
        self::assertSame('5', $operation->statement->where->right->value->text);
        self::assertSame('SELECT a FROM t WHERE a = @n := 5', $operation->toString());
    }

    public function testRenderWritesTheTargetTheOperatorAndTheValue(): void
    {
        $semantics = new Semantics(Dialect::MySql);

        self::assertSame('SELECT @n := 5', $semantics->analyze('select @n:=5')->toString());
        self::assertSame('SELECT @n := a FROM t', $semantics->analyze('SELECT @n := a FROM t')->toString());
    }

    public function testRenderIsTheSameInEveryGrammarGeneration(): void
    {
        self::assertSame('SELECT @n := 5', (new Semantics(Dialect::MySql, 'mysql-5.6.51'))->analyze('select @n := 5')->toString());
        self::assertSame('SELECT @n := 5', (new Semantics(Dialect::MySql, 'mysql-5.7.44'))->analyze('select @n := 5')->toString());
    }
}
