<?php

declare(strict_types=1);

namespace Tests\Unit\Rules\Query;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\MySql\Dialect;
use SqlSemantics\Platform\MySql\Rules\Query\SelectOptions;
use SqlSemantics\Statement\Fact\Diagnostic;

#[CoversClass(SelectOptions::class)]
#[Medium]
final class SelectOptionsTest extends TestCase
{
    public function testRaiseRetainsQuantifierConflictsInModernDeferredSelections(): void
    {
        $operation = (new Semantics(Dialect::MySql, 'mysql-8.4.7'))->analyze('CREATE PROCEDURE p() SELECT ALL DISTINCT ROW(1,2)');

        self::assertContains('Incorrect usage of ALL and DISTINCT', array_map(static fn (Diagnostic $diagnostic): string => $diagnostic->message(), $operation->diagnostics()));
    }

    public function testRaiseLeavesLegacyExpressionQuantifiersToTheirQueryRole(): void
    {
        $operation = (new Semantics(Dialect::MySql, 'mysql-5.6.51'))->analyze('SELECT (SELECT ALL DISTINCT 1)');

        self::assertSame([], $operation->diagnostics());
    }

    public function testNoticesWarnsOfEveryDeprecatedModifierInWrittenOrder(): void
    {
        $query = new \SqlSemantics\Platform\MySql\Statement\Query\Select([\SqlSemantics\Platform\MySql\Statement\Query\SelectOption::NoCache, \SqlSemantics\Platform\MySql\Statement\Query\SelectOption::CalcFoundRows, \SqlSemantics\Platform\MySql\Statement\Query\SelectOption::NoCache], [new \SqlSemantics\Platform\MySql\Statement\Query\SelectExpression(new \SqlSemantics\Platform\MySql\Statement\Literal\NumberLiteral('1'))]);

        self::assertCount(3, (new SelectOptions())->notices($query, \SqlSemantics\Contract\GrammarRelease::MySql847));
        self::assertCount(2, (new SelectOptions())->notices($query, \SqlSemantics\Contract\GrammarRelease::MySql5744));
        self::assertSame([], (new SelectOptions())->notices($query, \SqlSemantics\Contract\GrammarRelease::MySql5651));
    }

    public function testConflictComparesWithTheFirstCacheModifierIn56AndTheOneJustBeforeIn57(): void
    {
        $rules = new SelectOptions();
        $separated = [\SqlSemantics\Platform\MySql\Statement\Query\SelectOption::NoCache, \SqlSemantics\Platform\MySql\Statement\Query\SelectOption::All, \SqlSemantics\Platform\MySql\Statement\Query\SelectOption::Cache];
        $adjacent = [\SqlSemantics\Platform\MySql\Statement\Query\SelectOption::Distinct, \SqlSemantics\Platform\MySql\Statement\Query\SelectOption::NoCache, \SqlSemantics\Platform\MySql\Statement\Query\SelectOption::NoCache];

        $legacy = $rules->conflict($separated, \SqlSemantics\Contract\GrammarRelease::MySql5651);
        $modern = $rules->conflict($adjacent, \SqlSemantics\Contract\GrammarRelease::MySql5744);

        self::assertNotNull($legacy);
        self::assertSame([2, 'Incorrect usage of SQL_NO_CACHE and SQL_CACHE'], [$legacy[0], $legacy[1]->message()]);
        self::assertNull($rules->conflict($separated, \SqlSemantics\Contract\GrammarRelease::MySql5744));
        self::assertNotNull($modern);
        self::assertSame([2, "Option 'SQL_NO_CACHE' used twice in statement"], [$modern[0], $modern[1]->message()]);
        self::assertNull($rules->conflict($adjacent, \SqlSemantics\Contract\GrammarRelease::MySql847));
    }

    public function testPlacementFindsTheFirstModifierMySql56RefusesOutsideTheFirstBlock(): void
    {
        $rules = new SelectOptions();

        self::assertSame(1, $rules->placement([\SqlSemantics\Platform\MySql\Statement\Query\SelectOption::Distinct, \SqlSemantics\Platform\MySql\Statement\Query\SelectOption::NoCache, \SqlSemantics\Platform\MySql\Statement\Query\SelectOption::HighPriority]));
        self::assertNull($rules->placement([\SqlSemantics\Platform\MySql\Statement\Query\SelectOption::Distinct, \SqlSemantics\Platform\MySql\Statement\Query\SelectOption::SmallResult]));
    }

    public function testRaiseReportsAConflictOfAConstructedBlockUnderALegacyReleaseOnly(): void
    {
        $query = new \SqlSemantics\Platform\MySql\Statement\Query\Select([\SqlSemantics\Platform\MySql\Statement\Query\SelectOption::NoCache, \SqlSemantics\Platform\MySql\Statement\Query\SelectOption::NoCache], [new \SqlSemantics\Platform\MySql\Statement\Query\SelectExpression(new \SqlSemantics\Platform\MySql\Statement\Literal\NumberLiteral('1'))]);
        $legacy = new \SqlSemantics\Construction\Derivation((new Semantics(Dialect::MySql, 'mysql-5.7.44'))->context());
        $modern = new \SqlSemantics\Construction\Derivation((new Semantics(Dialect::MySql))->context());
        (new SelectOptions())->raise($query, $legacy);
        (new SelectOptions())->raise($query, $modern);

        self::assertSame(["Option 'SQL_NO_CACHE' used twice in statement"], array_map(static fn (Diagnostic $diagnostic): string => $diagnostic->message(), $legacy->facts()->diagnostics));
        self::assertCount(2, $legacy->facts()->warnings);
        self::assertSame([], $modern->facts()->diagnostics);
    }

    public function testRaiseSeparatesTheModifiersOf57ByAnotherOneAndNotThoseOf56(): void
    {
        $modern = (new Semantics(Dialect::MySql, 'mysql-5.7.44'))->analyze('SELECT SQL_NO_CACHE ALL SQL_CACHE 1');
        self::assertSame([], $modern->diagnostics());

        try {
            (new Semantics(Dialect::MySql, 'mysql-5.6.51'))->analyze('SELECT SQL_NO_CACHE ALL SQL_CACHE 1');
            self::fail('MySQL 5.6 compares the modifier with the first cache modifier of the block.');
        } catch (\SqlSemantics\Construction\RejectedInput $failure) {
            self::assertSame('Incorrect usage of SQL_NO_CACHE and SQL_CACHE', $failure->problem->message());
            self::assertTrue($failure->aborts);
        }
    }
}
