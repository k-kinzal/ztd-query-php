<?php

declare(strict_types=1);

namespace Tests\Unit\Ir\From;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Ir\BooleanLiteral;
use SqlSemantics\Platform\MySql\Ir\ColumnReference;
use SqlSemantics\Platform\MySql\Ir\From\OccurrenceScope;
use SqlSemantics\Platform\MySql\Ir\Identifier;
use SqlSemantics\Platform\MySql\Ir\IntegerLiteral;
use SqlSemantics\Platform\MySql\Ir\Predicate\IntegerComparison;
use SqlSemantics\Platform\MySql\Ir\Predicate\IntegerOperator;
use SqlSemantics\Platform\MySql\Ir\Predicate\LogicalOperator;
use SqlSemantics\Platform\MySql\Ir\Predicate\LogicalPredicate;
use SqlSemantics\Platform\MySql\Ir\TableSource;

#[CoversClass(OccurrenceScope::class)]
#[Small]
final class OccurrenceScopeTest extends TestCase
{
    public function testPredicateRejectsAValidColumnNumberAtAnInvisibleOccurrence(): void
    {
        $table = new TableSource(new Identifier('ir_fixture'), new Identifier('a'), [new Identifier('id')]);
        $outside = new IntegerComparison(IntegerOperator::from('='), new IntegerLiteral('1'), new ColumnReference(0, 1));
        $this->expectExceptionMessage('must belong to a visible input occurrence');

        (new OccurrenceScope())->predicate($outside, [$table]);
    }

    public function testCheckTreeCountsBothOccurrencesOfTheSameChild(): void
    {
        $operand = new BooleanLiteral(true);
        $condition = new LogicalPredicate(LogicalOperator::And, $operand, $operand);

        self::assertSame(3, (new OccurrenceScope())->checkTree($condition, [], 'invalid slot', 0, 0));
    }
}
