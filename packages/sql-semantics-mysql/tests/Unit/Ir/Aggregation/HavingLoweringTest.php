<?php

declare(strict_types=1);

namespace Tests\Unit\Ir\Aggregation;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Ir\Aggregation\Aggregate;
use SqlSemantics\Platform\MySql\Ir\Aggregation\AggregateBinding;
use SqlSemantics\Platform\MySql\Ir\Aggregation\AggregateOperator;
use SqlSemantics\Platform\MySql\Ir\Aggregation\GroupedComparison;
use SqlSemantics\Platform\MySql\Ir\Aggregation\GroupedSelect;
use SqlSemantics\Platform\MySql\Ir\Aggregation\HavingLowering;
use SqlSemantics\Platform\MySql\Ir\IntegerLiteral;
use SqlSemantics\Platform\MySql\Ir\Predicate\RangeIntegerLiteral;
use SqlSemantics\Platform\MySql\Ir\SourceBinding;
use SqlSemantics\Platform\MySql\Semantics;
use SqlSemantics\Platform\MySql\Statement\Expression\Comparison;
use SqlSemantics\Platform\MySql\Statement\Query\Select;
use SqlSemantics\Testing\MySql\Inspection;

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
        self::assertInstanceOf(Comparison::class, $statement->statement->having);
        $binding = $statement->binding();
        $value = (new HavingLowering())->operand(
            $statement->statement->having->right,
            $binding,
            new SourceBinding($binding, $statement->statement->from),
            new AggregateBinding(),
            [],
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
        self::assertInstanceOf(RangeIntegerLiteral::class, $query->having->right);
        self::assertSame('2147483651', $query->having->right->decimal);
    }
}
