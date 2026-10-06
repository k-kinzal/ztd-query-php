<?php

declare(strict_types=1);

namespace Tests\Unit\Lowering\Utility;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlParser\PostgreSql\PostgreSqlParser;
use SqlSemantics\Contract\GrammarRelease;
use SqlSemantics\Contract\LanguageProfile;
use SqlSemantics\Lowering\Leaves;
use SqlSemantics\Platform\PostgreSql\Lowering\Lowering;
use SqlSemantics\Platform\PostgreSql\Lowering\Utility\OptionRule;
use SqlSemantics\Platform\PostgreSql\Platform;
use SqlSemantics\Platform\PostgreSql\Statement\Literal\SignedNumber;
use SqlSemantics\Platform\PostgreSql\Statement\Literal\StringConstant;
use SqlSemantics\Platform\PostgreSql\Statement\Option\Toggle;
use SqlSemantics\Platform\PostgreSql\Statement\Option\Word;
use SqlSemantics\Platform\PostgreSql\Statement\Utility\Maintenance\OptionKeyword;
use SqlSemantics\Statement\Identifier\Name;

#[CoversClass(OptionRule::class)]
#[Medium]
final class OptionRuleTest extends TestCase
{
    public function testOptionsLowersTheListInOrder(): void
    {
        $rule = new OptionRule(new Lowering((new Platform())->productions(new LanguageProfile(GrammarRelease::PostgreSql172)), new Leaves(), GrammarRelease::PostgreSql172));
        $options = $rule->options((new PostgreSqlParser('pg-17.2'))->parse("VACUUM (FULL, PARALLEL 2, TRUNCATE 'on')")->find('utility_option_list')[0]);
        self::assertSame(['full', 'parallel', 'truncate'], array_map(static fn (\SqlSemantics\Platform\PostgreSql\Statement\Utility\Maintenance\UtilityOption $option): string => $option->option(), $options));
    }

    public function testNameLowersAWordOrAKeyword(): void
    {
        $rule = new OptionRule(new Lowering((new Platform())->productions(new LanguageProfile(GrammarRelease::PostgreSql172)), new Leaves(), GrammarRelease::PostgreSql172));
        $names = (new PostgreSqlParser('pg-17.2'))->parse('EXPLAIN (ANALYSE, FORMAT JSON, Costs) SELECT 1')->find('utility_option_name');
        self::assertSame(OptionKeyword::Analyse, $rule->name($names[0]));
        self::assertSame(OptionKeyword::Format, $rule->name($names[1]));
        self::assertEquals(new Name('costs'), $rule->name($names[2]));
    }

    public function testArgumentLowersEachValue(): void
    {
        $rule = new OptionRule(new Lowering((new Platform())->productions(new LanguageProfile(GrammarRelease::PostgreSql172)), new Leaves(), GrammarRelease::PostgreSql172));
        $arguments = (new PostgreSqlParser('pg-17.2'))->parse("VACUUM (a on, b x, c 'y', d -1, e)")->find('utility_option_arg');
        self::assertSame(Toggle::On, $rule->argument($arguments[0]));
        self::assertInstanceOf(Word::class, $rule->argument($arguments[1]));
        self::assertInstanceOf(StringConstant::class, $rule->argument($arguments[2]));
        self::assertInstanceOf(SignedNumber::class, $rule->argument($arguments[3]));
        self::assertNull($rule->argument($arguments[4]));
    }

    public function testKeywordKeepsTheSpelling(): void
    {
        $rule = new OptionRule(new Lowering((new Platform())->productions(new LanguageProfile(GrammarRelease::PostgreSql172)), new Leaves(), GrammarRelease::PostgreSql172));
        self::assertSame(
            [OptionKeyword::Analyze, OptionKeyword::Analyse],
            [$rule->keyword((new PostgreSqlParser('pg-17.2'))->parse('ANALYZE')->find('analyze_keyword')[0]), $rule->keyword((new PostgreSqlParser('pg-17.2'))->parse('ANALYSE')->find('analyze_keyword')[0])],
        );
    }

    public function testAnalyzeNamesTheAnalyzeOption(): void
    {
        $rule = new OptionRule(new Lowering((new Platform())->productions(new LanguageProfile(GrammarRelease::PostgreSql172)), new Leaves(), GrammarRelease::PostgreSql172));
        $option = $rule->analyze((new PostgreSqlParser('pg-17.2'))->parse('VACUUM ANALYSE')->find('analyze_keyword')[0]);
        self::assertSame([OptionKeyword::Analyse, null], [$option->name, $option->argument]);
    }

    public function testWordNamesAnOptionWithoutValue(): void
    {
        $rule = new OptionRule(new Lowering((new Platform())->productions(new LanguageProfile(GrammarRelease::PostgreSql172)), new Leaves(), GrammarRelease::PostgreSql172));
        self::assertSame(['verbose', null], [$rule->word('verbose')->option(), $rule->word('verbose')->argument]);
    }

    public function testWordsLowersOnlyTheWordsWritten(): void
    {
        $semantics = new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql);
        self::assertSame(['VACUUM FREEZE ANALYZE', 'VACUUM FULL VERBOSE'], [$semantics->analyze('VACUUM FREEZE ANALYZE')->toString(), $semantics->analyze('VACUUM FULL VERBOSE')->toString()]);
    }
}
