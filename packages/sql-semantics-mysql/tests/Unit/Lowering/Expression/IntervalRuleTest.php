<?php

declare(strict_types=1);

namespace Tests\Unit\Lowering\Expression;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Contract\ParameterStyle;
use SqlSemantics\Lowering\Leaves;
use SqlSemantics\Platform\MySql\Lowering\Expression\IntervalRule;
use SqlSemantics\Platform\MySql\Lowering\Lowering;
use SqlSemantics\Platform\MySql\Platform;
use SqlSemantics\Platform\MySql\Statement\Expression\IntervalUnit;

#[CoversClass(IntervalRule::class)]
#[Medium]
final class IntervalRuleTest extends TestCase
{
    public function testUnitLowersSimpleAndCompoundUnits(): void
    {
        $platform = new Platform();
        $profile = $platform->profile('mysql-5.6.51', null, ParameterStyle::Native);
        $lowering = new Lowering($platform->productions($profile), new Leaves(), $profile);
        $rule = new IntervalRule($lowering);

        self::assertSame(IntervalUnit::MinuteMicrosecond, $rule->unit($platform->parser($profile)->parse("SELECT 1 + INTERVAL '1' MINUTE_MICROSECOND")->find('interval')[0]));
        self::assertSame(IntervalUnit::SqlTsiDay, $rule->unit($platform->parser($profile)->parse('SELECT 1 + INTERVAL 1 SQL_TSI_DAY')->find('interval')[0]));
    }
    public function testArithmeticKeepsTheDateQuantityAndUnitOfADeferredInstruction(): void
    {
        $semantics = new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\MySql\Dialect::MySql);
        $operation = $semantics->analyze('CREATE PROCEDURE p() DO INTERVAL ((1,2)) DAY + 3');
        self::assertSame('CREATE PROCEDURE p() DO INTERVAL ((1, 2)) DAY + 3', $operation->toString());
        self::assertInstanceOf(\SqlSemantics\Platform\MySql\Statement\Statement::class, $operation);
        self::assertSame([], $operation->diagnostics());
    }
    /**
     * @return iterable<string, array{string, string}>
     */
    public static function providerAliasStatements(): iterable
    {
        foreach (\SqlSemantics\Contract\GrammarRelease::cases() as $release) {
            if ($release->database() !== 'mysql') {
                continue;
            }
            foreach (['SECOND', 'MINUTE', 'HOUR', 'DAY', 'WEEK', 'MONTH', 'QUARTER', 'YEAR'] as $unit) {
                yield $release->value . '-' . $unit => [substr($release->value, 6), "SELECT TIMESTAMPADD(SQL_TSI_$unit, 1, DATE '2024-01-01'), EXTRACT(SQL_TSI_$unit FROM '2024-01-01')"];
            }
            yield $release->value . '-event' => [substr($release->value, 6), 'ALTER EVENT _sqlfaker_identifier ON SCHEDULE EVERY ((((USER())))) SQL_TSI_WEEK'];
        }
    }

    #[DataProvider('providerAliasStatements')]
    public function testUnitPreservesAliasesInEveryGrammarRelease(string $release, string $sql): void
    {
        $semantics = new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\MySql\Dialect::MySql, $release);
        self::assertSame($sql, $semantics->analyze($sql)->toString());
    }
}
