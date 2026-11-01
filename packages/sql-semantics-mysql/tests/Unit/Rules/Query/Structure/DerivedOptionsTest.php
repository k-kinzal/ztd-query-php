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
        $inner = new Select([SelectOption::SmallResult], [new SelectExpression(new NumberLiteral('1'))]);
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
        $last = new Select([$option], [new SelectExpression(new NumberLiteral('2'))]);

        self::assertSame("Incorrect usage/placement of '{$option->value}'", (new DerivedOptions())->problem(new \SqlSemantics\Platform\MySql\Statement\Query\ParenthesizedQuery($last))?->message());
    }

    /**
     * @return iterable<string, array{GrammarRelease, list<SelectOption>, string|null}>
     */
    public static function providerBlocks(): iterable
    {
        yield '5.6 names the first written restricted modifier' => [GrammarRelease::MySql5651, [SelectOption::All, SelectOption::Distinct, SelectOption::NoCache], "Incorrect usage/placement of 'SQL_NO_CACHE'"];
        yield '5.6 keeps written order over priority' => [GrammarRelease::MySql5651, [SelectOption::NoCache, SelectOption::HighPriority], "Incorrect usage/placement of 'SQL_NO_CACHE'"];
        yield '5.6 accepts other modifiers' => [GrammarRelease::MySql5651, [SelectOption::SmallResult, SelectOption::Distinct], null];
        yield '5.7 names a cache modifier written last first' => [GrammarRelease::MySql5744, [SelectOption::HighPriority, SelectOption::NoCache], "Incorrect usage/placement of 'SQL_NO_CACHE'"];
        yield '5.7 names the priority modifier before an earlier cache modifier' => [GrammarRelease::MySql5744, [SelectOption::NoCache, SelectOption::HighPriority], "Incorrect usage/placement of 'HIGH_PRIORITY'"];
        yield '5.7 reports ALL with DISTINCT before an earlier cache modifier' => [GrammarRelease::MySql5744, [SelectOption::NoCache, SelectOption::All, SelectOption::Distinct], 'Incorrect usage of ALL and DISTINCT'];
        yield '5.7 names a cache modifier written last before ALL with DISTINCT' => [GrammarRelease::MySql5744, [SelectOption::All, SelectOption::Distinct, SelectOption::NoCache], "Incorrect usage/placement of 'SQL_NO_CACHE'"];
        yield '8.4 accepts SQL_NO_CACHE anywhere' => [GrammarRelease::MySql847, [SelectOption::NoCache, SelectOption::NoCache], null];
        yield '8.4 names the modifiers in priority order' => [GrammarRelease::MySql847, [SelectOption::CalcFoundRows, SelectOption::BufferResult, SelectOption::HighPriority], "Incorrect usage/placement of 'HIGH_PRIORITY'"];
    }

    /**
     * @param list<SelectOption> $options The modifiers of the block in written order
     */
    #[DataProvider('providerBlocks')]
    public function testBlockFollowsTheOrderEachReleaseChecks(GrammarRelease $release, array $options, ?string $message): void
    {
        self::assertSame($message, (new DerivedOptions())->block($options, $release)?->message());
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
