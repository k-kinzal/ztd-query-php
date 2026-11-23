<?php

declare(strict_types=1);

namespace Tests\Unit\Ir\Aggregation;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\PostgreSql\Ir\Aggregation\Aggregate;
use SqlSemantics\Platform\PostgreSql\Ir\Aggregation\AggregateOperator;
use SqlSemantics\Platform\PostgreSql\Ir\Aggregation\GroupedComparison;
use SqlSemantics\Platform\PostgreSql\Ir\Aggregation\GroupedSelect;
use SqlSemantics\Platform\PostgreSql\Ir\Aggregation\HavingLowering;
use SqlSemantics\Platform\PostgreSql\Ir\Expression\WideIntegerLiteral;
use SqlSemantics\Platform\PostgreSql\Semantics;

#[CoversClass(HavingLowering::class)]
#[Medium]
final class HavingLoweringTest extends TestCase
{
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
