<?php

declare(strict_types=1);

namespace Tests\Unit\Rules\Query\Structure;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Contract\GrammarRelease;
use SqlSemantics\Platform\MySql\Rules\Query\Structure\DerivedOptions;
use SqlSemantics\Platform\MySql\Statement\Literal\NumberLiteral;
use SqlSemantics\Platform\MySql\Statement\Query\Select;
use SqlSemantics\Platform\MySql\Statement\Query\SelectExpression;
use SqlSemantics\Platform\MySql\Statement\Query\SelectOption;
use SqlSemantics\Platform\MySql\Statement\Query\Set\SetOperation;
use SqlSemantics\Platform\MySql\Statement\Query\Set\SetOperator;

#[CoversClass(DerivedOptions::class)]
#[Small]
final class DerivedOptionsTest extends TestCase
{
    public function testBlocksVisitsSetOperandsInWrittenOrder(): void
    {
        $first = new Select([], [new SelectExpression(new NumberLiteral('1'))]);
        $last = new Select([], [new SelectExpression(new NumberLiteral('2'))]);
        $query = new SetOperation($first, SetOperator::Union, null, $last);

        self::assertSame([$first, $last], (new DerivedOptions())->blocks($query));
    }

    public function testBlocksStopsAtNestedExpressionQueries(): void
    {
        $inner = new Select([SelectOption::HighPriority], [new SelectExpression(new NumberLiteral('1'))]);
        $outer = new Select([], [new SelectExpression(new \SqlSemantics\Platform\MySql\Statement\Expression\Subquery\ScalarSubquery($inner))]);

        self::assertSame([$outer], (new DerivedOptions())->blocks($outer));
        self::assertNull((new DerivedOptions())->problem($outer));
    }

    /**
     * @return iterable<string, array{SelectOption}>
     */
    public static function providerOptions(): iterable
    {
        yield 'priority' => [SelectOption::HighPriority];
        yield 'buffer' => [SelectOption::BufferResult];
        yield 'found rows' => [SelectOption::CalcFoundRows];
        yield 'legacy cache' => [SelectOption::Cache];
    }

    #[DataProvider('providerOptions')]
    public function testProblemFindsARestrictedOptionInALaterOperand(SelectOption $option): void
    {
        $first = new Select([], [new SelectExpression(new NumberLiteral('1'))]);
        $last = new Select([$option], [new SelectExpression(new NumberLiteral('2'))]);

        self::assertSame("Incorrect usage/placement of '{$option->value}'", (new DerivedOptions())->problem(new SetOperation($first, SetOperator::Union, null, $last))?->message());
    }

    public function testProblemPreservesVersionedQuantifierPrecedence(): void
    {
        $query = new Select([SelectOption::All, SelectOption::Distinct, SelectOption::HighPriority], [new SelectExpression(new NumberLiteral('1'))]);

        self::assertSame("Incorrect usage/placement of 'HIGH_PRIORITY'", (new DerivedOptions())->problem($query, GrammarRelease::MySql5651)?->message());
        self::assertSame('Incorrect usage of ALL and DISTINCT', (new DerivedOptions())->problem($query, GrammarRelease::MySql5744)?->message());
        self::assertSame('Incorrect usage of ALL and DISTINCT', (new DerivedOptions())->problem($query, GrammarRelease::MySql847)?->message());
    }

    public function testProblemLeavesLegacyQuantifiersWithoutARestrictedOptionConstructible(): void
    {
        $query = new Select([SelectOption::All, SelectOption::Distinct], [new SelectExpression(new NumberLiteral('1'))]);

        self::assertNull((new DerivedOptions())->problem($query));
    }
}
