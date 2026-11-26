<?php

declare(strict_types=1);

namespace Tests\Unit\Ir\Mutation;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\PostgreSql\Ir\BooleanLiteral;
use SqlSemantics\Platform\PostgreSql\Ir\ColumnReference;
use SqlSemantics\Platform\PostgreSql\Ir\Mutation\Delete;
use SqlSemantics\Platform\PostgreSql\Ir\Mutation\DeleteLowering;
use SqlSemantics\Platform\PostgreSql\Ir\Predicate\IntegerComparison;
use SqlSemantics\Platform\PostgreSql\Ir\Predicate\IntegerOperator;
use SqlSemantics\Platform\PostgreSql\Semantics;

#[CoversClass(DeleteLowering::class)]
#[Medium]
final class DeleteLoweringTest extends TestCase
{
    public function testLowerResolvesTheQualifiedTargetAndItsPredicateSlot(): void
    {
        $semantics = new Semantics();
        $context = $semantics->context(['CREATE TABLE public.accounts (id INTEGER, delta INTEGER)']);
        $delete = $semantics->analyze('DELETE FROM public.accounts WHERE accounts.delta < 0', $context);

        self::assertInstanceOf(Delete::class, $delete);
        self::assertSame('delta', $delete->target->columns[0]->value);
        self::assertInstanceOf(IntegerComparison::class, $delete->where);
        self::assertSame(IntegerOperator::Less, $delete->where->operator);
        self::assertEquals(new ColumnReference(0), $delete->where->left);
    }

    public function testPredicatePreservesConstantFalse(): void
    {
        $semantics = new Semantics();
        $context = $semantics->context(['CREATE TABLE public.accounts (id INTEGER)']);
        $delete = $semantics->analyze('DELETE FROM public.accounts WHERE FALSE', $context);

        self::assertInstanceOf(Delete::class, $delete);
        self::assertInstanceOf(BooleanLiteral::class, $delete->where);
        self::assertFalse($delete->where->value);
    }
}
