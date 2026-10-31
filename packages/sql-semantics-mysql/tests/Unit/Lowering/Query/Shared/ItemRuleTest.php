<?php

declare(strict_types=1);

namespace Tests\Unit\Lowering\Query\Shared;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\MySql\Dialect;
use SqlSemantics\Platform\MySql\Lowering\Query\Shared\ItemRule;
use SqlSemantics\Platform\MySql\Statement\Name\AliasMark;
use SqlSemantics\Platform\MySql\Statement\Query\Select;
use SqlSemantics\Platform\MySql\Statement\Query\SelectExpression;

#[CoversClass(ItemRule::class)]
#[Medium]
final class ItemRuleTest extends TestCase
{
    public function testOptionsLowersEveryOption(): void
    {
        self::assertSame('SELECT STRAIGHT_JOIN HIGH_PRIORITY DISTINCT SQL_SMALL_RESULT SQL_BIG_RESULT SQL_BUFFER_RESULT SQL_CALC_FOUND_ROWS SQL_NO_CACHE a FROM t', (new Semantics(Dialect::MySql, 'mysql-5.6.51'))->analyze('select straight_join high_priority distinct sql_small_result sql_big_result sql_buffer_result sql_calc_found_rows sql_no_cache a from t')->toString());
        self::assertSame('SELECT ALL a FROM t', (new Semantics(Dialect::MySql, 'mysql-8.4.7'))->analyze('select all a from t')->toString());
    }

    public function testItemsLowersTheStarAndTheItems(): void
    {
        self::assertSame('SELECT *, t.*, db.t.*, a FROM t', (new Semantics(Dialect::MySql, 'mysql-8.4.7'))->analyze('select *, t.*, db.t.*, a from t')->toString());
        self::assertSame('SELECT *, t.* FROM t', (new Semantics(Dialect::MySql, 'mysql-5.6.51'))->analyze('select *, t.* from t')->toString());
    }

    public function testItemLowersOneItem(): void
    {
        self::assertSame('SELECT a + 1 b FROM t', (new Semantics(Dialect::MySql, 'mysql-5.6.51'))->analyze('select a + 1 b from t')->toString());
    }

    public function testAliasLowersNamesAndStrings(): void
    {
        self::assertSame('SELECT a \'x\', b AS `y` FROM t', (new Semantics(Dialect::MySql, 'mysql-5.7.44'))->analyze('select a \'x\', b as `y` from t')->toString());
        self::assertSame('SELECT a AS \'x\' FROM t', (new Semantics(Dialect::MySql, 'mysql-8.4.7'))->analyze('select a as \'x\' from t')->toString());
    }

    public function testExpressionKeepsTheLayoutOfAnItemNamedAfterItsText(): void
    {
        $operation = (new Semantics(Dialect::MySql))->analyze('SELECT 1+1, 1 + 1, a, 2 AS b FROM t');

        self::assertInstanceOf(\SqlSemantics\Platform\MySql\Statement\QueryRoot::class, $operation);
        self::assertInstanceOf(Select::class, $operation->query);
        self::assertSame(['1+1', null, null, null], array_map(static fn (object $item): ?string => $item instanceof SelectExpression ? $item->layout?->text() : null, $operation->query->items));
        self::assertSame('SELECT 1+1, 1 + 1, a, 2 AS b FROM t', $operation->toString());
    }

    public function testResolvedKeepsAStoredScalarOccurrenceBesideADeferredProjection(): void
    {
        self::assertSame('CREATE PROCEDURE p() SELECT 1+1, (2, 3)', (new Semantics(Dialect::MySql))->analyze('CREATE PROCEDURE p() SELECT 1+1, (2,3)')->toString());
    }

    public function testMarkAnswersWhetherAnAliasIsWrittenAfterAs(): void
    {
        $operation = (new Semantics(Dialect::MySql))->analyze("SELECT 1 x, 2 AS y, 3 'z', 4 FROM t");

        self::assertInstanceOf(\SqlSemantics\Platform\MySql\Statement\QueryRoot::class, $operation);
        self::assertInstanceOf(Select::class, $operation->query);
        self::assertSame([AliasMark::Bare, AliasMark::As, AliasMark::Bare, AliasMark::As], array_map(static fn (object $item): AliasMark => $item instanceof SelectExpression ? $item->mark : AliasMark::Equals, $operation->query->items));
    }

    /**
     * @return iterable<string, array{string, string, class-string, string, int}>
     */
    public static function providerLegacyRefusals(): iterable
    {
        yield '5.7 repeats a cache modifier' => ['mysql-5.7.44', 'SELECT SQL_CACHE SQL_CACHE SQL_NO_CACHE 1', \SqlSemantics\Platform\MySql\Statement\Query\Problem\SelectOptionConflict::class, "Option 'SQL_CACHE' used twice in statement", 26];
        yield '5.6 compares with the first cache modifier' => ['mysql-5.6.51', 'SELECT SQL_NO_CACHE ALL SQL_CACHE 1', \SqlSemantics\Platform\MySql\Statement\Query\Problem\SelectOptionConflict::class, 'Incorrect usage of SQL_NO_CACHE and SQL_CACHE', 33];
        yield '5.6 refuses the placement before the conflict' => ['mysql-5.6.51', 'SELECT 1 UNION SELECT SQL_CACHE SQL_NO_CACHE 2', \SqlSemantics\Platform\MySql\Statement\Query\Problem\Option\RestrictedSelectOption::class, "Incorrect usage/placement of 'SQL_CACHE'", 31];
        yield '5.6 refuses the first written restricted modifier' => ['mysql-5.6.51', 'SELECT 1 UNION SELECT ALL DISTINCT HIGH_PRIORITY 2', \SqlSemantics\Platform\MySql\Statement\Query\Problem\Option\RestrictedSelectOption::class, "Incorrect usage/placement of 'HIGH_PRIORITY'", 48];
        yield '5.6 refuses a cache modifier of a derived table' => ['mysql-5.6.51', 'SELECT * FROM (SELECT SQL_NO_CACHE 1) d', \SqlSemantics\Platform\MySql\Statement\Query\Problem\Option\RestrictedSelectOption::class, "Incorrect usage/placement of 'SQL_NO_CACHE'", 34];
    }

    /**
     * @param class-string $class The refused problem
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('providerLegacyRefusals')]
    public function testPlacedRefusesLegacyModifiersWhileParsingTheBlock(string $release, string $sql, string $class, string $message, int $end): void
    {
        try {
            (new Semantics(Dialect::MySql, $release))->analyze($sql);
            self::fail('The legacy release refuses the modifier while parsing.');
        } catch (\SqlSemantics\Construction\RejectedInput $failure) {
            self::assertInstanceOf($class, $failure->problem);
            self::assertSame($message, $failure->problem->message());
            self::assertSame($end, $failure->end);
            self::assertTrue($failure->aborts);
        }
    }

    public function testPlacedAcceptsTheModifiersOfTheFirstBlockAndSeparatedCacheModifiersIn57(): void
    {
        self::assertSame('SELECT SQL_CACHE 1 UNION SELECT 2', (new Semantics(Dialect::MySql, 'mysql-5.6.51'))->analyze('SELECT SQL_CACHE 1 UNION SELECT 2')->toString());
        self::assertSame('SELECT SQL_NO_CACHE ALL SQL_CACHE 1', (new Semantics(Dialect::MySql, 'mysql-5.7.44'))->analyze('SELECT SQL_NO_CACHE ALL SQL_CACHE 1')->toString());
        self::assertSame('SELECT SQL_NO_CACHE SQL_NO_CACHE 1', (new Semantics(Dialect::MySql))->analyze('SELECT SQL_NO_CACHE SQL_NO_CACHE 1')->toString());
    }
}
