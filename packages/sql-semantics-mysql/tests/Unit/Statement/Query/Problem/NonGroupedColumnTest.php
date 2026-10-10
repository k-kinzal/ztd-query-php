<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Query\Problem;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Contract\GrammarRelease;
use SqlSemantics\Platform\MySql\Statement\Query\Problem\GroupingRule;
use SqlSemantics\Platform\MySql\Statement\Query\Problem\NonGroupedColumn;

#[CoversClass(NonGroupedColumn::class)]
#[Small]
final class NonGroupedColumnTest extends TestCase
{
    public function testMessageUsesTheMySql56GroupingDiagnostics(): void
    {
        self::assertSame('Mixing of GROUP columns (MIN(),MAX(),COUNT(),...) with no GROUP columns is illegal if there is no GROUP BY clause', (new NonGroupedColumn(GroupingRule::WithoutGroupBy, false, 1, 'd.t.a', release: GrammarRelease::MySql5651))->message());
        self::assertSame("'d.t.a' isn't in GROUP BY", (new NonGroupedColumn(GroupingRule::NotDetermined, false, 1, 'd.t.a', release: GrammarRelease::MySql5651))->message());
    }

    public function testMessageNamesTheHavingCondition(): void
    {
        self::assertSame("In aggregated query without GROUP BY, expression #1 of HAVING clause contains nonaggregated column 'd.t.a'; this is incompatible with sql_mode=only_full_group_by", (new NonGroupedColumn(GroupingRule::WithoutGroupBy, false, 1, 'd.t.a', true))->message());
    }

    public function testMessageIsThatOfTheServerForEachRule(): void
    {
        self::assertSame("Expression #2 of ORDER BY clause is not in GROUP BY clause and contains nonaggregated column 'd.t.b' which is not functionally dependent on columns in GROUP BY clause; this is incompatible with sql_mode=only_full_group_by", (new NonGroupedColumn(GroupingRule::NotDetermined, true, 2, 'd.t.b'))->message());
        self::assertSame("In aggregated query without GROUP BY, expression #1 of SELECT list contains nonaggregated column 'd.t.a'; this is incompatible with sql_mode=only_full_group_by", (new NonGroupedColumn(GroupingRule::WithoutGroupBy, false, 1, 'd.t.a'))->message());
        self::assertSame("Expression #1 of ORDER BY clause is not in SELECT list, references column 'd.t.b' which is not in SELECT list; this is incompatible with DISTINCT", (new NonGroupedColumn(GroupingRule::NotSelected, true, 1, 'd.t.b'))->message());
    }
}
