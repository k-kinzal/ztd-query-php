<?php

declare(strict_types=1);

namespace Tests\Unit\Ir\Predicate;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Ir\Predicate\IntegerComparison;
use SqlSemantics\Platform\MySql\Ir\Predicate\LogicalPredicate;
use SqlSemantics\Platform\MySql\Ir\Predicate\NullTest;
use SqlSemantics\Platform\MySql\Ir\Predicate\PredicateLowering;
use SqlSemantics\Platform\MySql\Ir\SourceBinding;
use SqlSemantics\Platform\MySql\Statement\Query\Select;
use SqlSemantics\Testing\MySql\Inspection;

#[CoversClass(PredicateLowering::class)]
#[Small]
final class PredicateLoweringTest extends TestCase
{
    public function testLowerResolvesSignedPredicates(): void
    {
        $operation = (new Inspection())->inspect('SELECT 1 WHERE (1 < 2)');
        $select = $operation->statement;
        self::assertInstanceOf(Select::class, $select);
        $bound = $operation->binding();

        $predicate = (new PredicateLowering())->lower($select->where, new SourceBinding($bound, null));
        self::assertInstanceOf(IntegerComparison::class, $predicate);
        self::assertSame('<', $predicate->operator->value);
    }

    public function testLowerValueResolvesNestedTruth(): void
    {
        $operation = (new Inspection())->inspect('SELECT 1 WHERE (NOT NULL) IS NULL');
        $select = $operation->statement;
        self::assertInstanceOf(Select::class, $select);
        self::assertNotNull($select->where);
        $bound = $operation->binding();

        $predicate = (new PredicateLowering())->lowerValue($select->where, new SourceBinding($bound, null));
        self::assertInstanceOf(NullTest::class, $predicate);
        self::assertInstanceOf(LogicalPredicate::class, $predicate->operand);
        self::assertSame('NOT', $predicate->operand->operator->value);
    }
}
