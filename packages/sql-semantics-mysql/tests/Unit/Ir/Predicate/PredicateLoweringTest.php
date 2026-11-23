<?php

declare(strict_types=1);

namespace Tests\Unit\Ir\Predicate;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Ir\From\TextColumnReference;
use SqlSemantics\Platform\MySql\Ir\Predicate\IntegerComparison;
use SqlSemantics\Platform\MySql\Ir\Predicate\LogicalPredicate;
use SqlSemantics\Platform\MySql\Ir\Predicate\NullTest;
use SqlSemantics\Platform\MySql\Ir\Predicate\PredicateLowering;
use SqlSemantics\Platform\MySql\Ir\SourceBinding;
use SqlSemantics\Platform\MySql\Statement\Expression\NullTest as ParsedNullTest;
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

    public function testNullTestUsesTheNativeVarcharSlot(): void
    {
        $inspection = new Inspection();
        $table = $inspection->inspect('CREATE TABLE app.t (label VARCHAR(8) COLLATE utf8mb4_bin)');
        $query = $inspection->inspect('SELECT 1 FROM app.t WHERE label IS NOT NULL', [$table]);
        self::assertInstanceOf(Select::class, $query->statement);
        self::assertInstanceOf(ParsedNullTest::class, $query->statement->where);
        $binding = new SourceBinding($query->binding(), $query->statement->from);

        $test = (new PredicateLowering())->nullTest($query->statement->where, $binding);

        self::assertInstanceOf(TextColumnReference::class, $test->operand);
        self::assertTrue($test->negated);
    }
}
