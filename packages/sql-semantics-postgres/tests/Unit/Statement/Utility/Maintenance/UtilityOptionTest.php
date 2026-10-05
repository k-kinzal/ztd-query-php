<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Utility\Maintenance;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\PostgreSql\Dialect;
use SqlSemantics\Platform\PostgreSql\Statement\Literal\IntegerConstant;
use SqlSemantics\Platform\PostgreSql\Statement\Literal\NumericConstant;
use SqlSemantics\Platform\PostgreSql\Statement\Literal\SignedNumber;
use SqlSemantics\Platform\PostgreSql\Statement\Literal\StringConstant;
use SqlSemantics\Platform\PostgreSql\Statement\Option\Toggle;
use SqlSemantics\Platform\PostgreSql\Statement\Option\Word;
use SqlSemantics\Platform\PostgreSql\Statement\Utility\Maintenance\OptionKeyword;
use SqlSemantics\Platform\PostgreSql\Statement\Utility\Maintenance\UtilityOption;
use SqlSemantics\Statement\Identifier\Name;

#[CoversClass(UtilityOption::class)]
#[Medium]
final class UtilityOptionTest extends TestCase
{
    public function testOptionAnswersTheNameOrTheKeywordOption(): void
    {
        self::assertSame(['costs', 'analyze'], [(new UtilityOption(new Name('costs')))->option(), (new UtilityOption(OptionKeyword::Analyse))->option()]);
    }

    public function testTextAnswersTheValueAsTheServerReceivesIt(): void
    {
        self::assertSame(
            [null, 'json', 'a b', 'on', '-12', '1.50', '2.0e-3'],
            [
                (new UtilityOption(new Name('x')))->text(),
                (new UtilityOption(new Name('x'), new Word(new Name('json'))))->text(),
                (new UtilityOption(new Name('x'), new StringConstant('a b')))->text(),
                (new UtilityOption(new Name('x'), Toggle::On))->text(),
                (new UtilityOption(new Name('x'), new SignedNumber(true, new IntegerConstant('12'))))->text(),
                (new UtilityOption(new Name('x'), new SignedNumber(false, new NumericConstant('1', '50'))))->text(),
                (new UtilityOption(new Name('x'), new SignedNumber(false, new NumericConstant('2', '0', '-3'))))->text(),
            ],
        );
    }

    public function testDeriveClauseRecordsNoProblemForAValue(): void
    {
        self::assertSame([], (new Semantics(Dialect::PostgreSql))->analyze("VACUUM (TRUNCATE 'on', PARALLEL 2, INDEX_CLEANUP auto) t")->facts->diagnostics);
    }

    public function testRenderQuotesFormatOnlyBeforeJson(): void
    {
        $semantics = new Semantics(Dialect::PostgreSql);
        self::assertSame(
            ['EXPLAIN (FORMAT json) SELECT 1', 'EXPLAIN ("format" json) SELECT 1', 'EXPLAIN (format xml) SELECT 1', 'EXPLAIN (costs FALSE, "analyze") SELECT 1'],
            [$semantics->analyze('EXPLAIN (FORMAT JSON) SELECT 1')->toString(), $semantics->analyze('EXPLAIN ("format" json) SELECT 1')->toString(), $semantics->analyze('EXPLAIN ("format" xml) SELECT 1')->toString(), $semantics->analyze('EXPLAIN (COSTS false, "analyze") SELECT 1')->toString()],
        );
    }
}
