<?php

declare(strict_types=1);

namespace Tests\Unit\Ir\Mutation;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Ir\BooleanLiteral;
use SqlSemantics\Platform\MySql\Ir\ColumnReference;
use SqlSemantics\Platform\MySql\Ir\Mutation\Delete;
use SqlSemantics\Platform\MySql\Ir\Mutation\DeleteLowering;
use SqlSemantics\Platform\MySql\Ir\Predicate\IntegerComparison;
use SqlSemantics\Platform\MySql\Ir\Predicate\IntegerOperator;
use SqlSemantics\Platform\MySql\Semantics;

#[CoversClass(DeleteLowering::class)]
#[Medium]
final class DeleteLoweringTest extends TestCase
{
    public function testLowerResolvesTheQualifiedTargetAndItsPredicateSlot(): void
    {
        $semantics = new Semantics();
        $context = $semantics->context(['CREATE TABLE app.accounts (id INTEGER, delta INTEGER)']);
        $delete = $semantics->analyze('DELETE FROM app.accounts WHERE accounts.delta < 0', $context);

        self::assertInstanceOf(Delete::class, $delete);
        self::assertSame('delta', $delete->target->columns[0]->value);
        self::assertInstanceOf(IntegerComparison::class, $delete->where);
        self::assertSame(IntegerOperator::Less, $delete->where->operator);
        self::assertEquals(new ColumnReference(0), $delete->where->left);
    }

    public function testPredicatePreservesConstantFalse(): void
    {
        $semantics = new Semantics();
        $context = $semantics->context(['CREATE TABLE app.accounts (id INTEGER)']);
        $delete = $semantics->analyze('DELETE FROM app.accounts WHERE FALSE', $context);

        self::assertInstanceOf(Delete::class, $delete);
        self::assertInstanceOf(BooleanLiteral::class, $delete->where);
        self::assertFalse($delete->where->value);
    }
}
