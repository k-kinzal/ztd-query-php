<?php

declare(strict_types=1);

namespace Tests\Unit\Ir\Aggregation;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\PostgreSql\Ir\Aggregation\Aggregate;
use SqlSemantics\Platform\PostgreSql\Ir\Aggregation\AggregateBinding;
use SqlSemantics\Platform\PostgreSql\Ir\Aggregation\AggregateOperator;
use SqlSemantics\Platform\PostgreSql\Ir\Aggregation\GroupedComparison;
use SqlSemantics\Platform\PostgreSql\Ir\Aggregation\GroupedSelect;
use SqlSemantics\Platform\PostgreSql\Ir\Aggregation\HavingLowering;
use SqlSemantics\Platform\PostgreSql\Ir\Expression\WideIntegerLiteral;
use SqlSemantics\Platform\PostgreSql\Ir\IntegerLiteral;
use SqlSemantics\Platform\PostgreSql\Ir\SourceBinding;
use SqlSemantics\Platform\PostgreSql\Semantics;
use SqlSemantics\Platform\PostgreSql\Statement\Expression\BinaryOperation;
use SqlSemantics\Platform\PostgreSql\Statement\Query\Select;
use SqlSemantics\Testing\PostgreSql\Inspection;

#[CoversClass(HavingLowering::class)]
#[Medium]
final class HavingLoweringTest extends TestCase
{
    public function testOperandLowersAnInt4Literal(): void
    {
        $inspection = new Inspection();
        $declaration = $inspection->inspect('CREATE TABLE public.events (id int)');
        $statement = $inspection->inspect('SELECT count(*) FROM public.events HAVING count(*) > 2', [$declaration]);
        self::assertInstanceOf(Select::class, $statement->statement);
        self::assertInstanceOf(BinaryOperation::class, $statement->statement->having);
        $binding = $statement->binding();
        $value = (new HavingLowering())->operand(
            $statement->statement->having->right,
            new SourceBinding($binding, $statement->statement->from),
            new AggregateBinding($binding->context),
        );

        self::assertInstanceOf(IntegerLiteral::class, $value);
        self::assertSame('2', $value->decimal);
    }

    public function testLowerBindsAnAggregateOmittedFromTheOutput(): void
    {
        $semantics = new Semantics();
        $context = $semantics->context(['CREATE TABLE public.events (id int, amount int)']);
        $query = $semantics->analyze(
            'SELECT id FROM public.events GROUP BY id HAVING sum(amount) > 2147483651',
            $context,
        );

        self::assertInstanceOf(GroupedSelect::class, $query);
        self::assertInstanceOf(GroupedComparison::class, $query->having);
        self::assertInstanceOf(Aggregate::class, $query->having->left);
        self::assertSame(AggregateOperator::Sum, $query->having->left->operator);
        $argument = $query->having->left->argument;
        self::assertNotNull($argument);
        self::assertSame('amount', $query->source->columns[$argument->slot]->value);
        self::assertInstanceOf(WideIntegerLiteral::class, $query->having->right);
        self::assertSame('2147483651', $query->having->right->decimal);
    }
}
