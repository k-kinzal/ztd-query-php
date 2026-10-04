<?php

declare(strict_types=1);

namespace Tests\Unit\Lowering\Routine;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlParser\PostgreSql\PostgreSqlParser;
use SqlSemantics\Contract\GrammarRelease;
use SqlSemantics\Contract\LanguageProfile;
use SqlSemantics\Lowering\Leaves;
use SqlSemantics\Platform\PostgreSql\Lowering\Lowering;
use SqlSemantics\Platform\PostgreSql\Lowering\Routine\SignatureRule;
use SqlSemantics\Platform\PostgreSql\Platform;
use SqlSemantics\Platform\PostgreSql\Statement\Routine\ParameterMode;
use SqlSemantics\Platform\PostgreSql\Statement\Routine\Signature\OperatorArity;

#[CoversClass(SignatureRule::class)]
#[Small]
final class SignatureRuleTest extends TestCase
{
    public function testFunctionLowersEachForm(): void
    {
        $lowering = new Lowering((new Platform())->productions(new LanguageProfile(GrammarRelease::PostgreSql172)), new Leaves(), GrammarRelease::PostgreSql172);
        $tree = (new PostgreSqlParser('pg-17.2'))->parse('DROP FUNCTION f(int), left, s.g');
        $rule = new SignatureRule($lowering);
        self::assertSame(['f', 'left', 'g'], [$rule->function($tree->find('function_with_argtypes')[0])->name->last()->value, $rule->function($tree->find('function_with_argtypes')[1])->name->last()->value, $rule->function($tree->find('function_with_argtypes')[2])->name->last()->value]);
    }

    public function testFunctionRejectsAnotherNonterminal(): void
    {
        $lowering = new Lowering((new Platform())->productions(new LanguageProfile(GrammarRelease::PostgreSql172)), new Leaves(), GrammarRelease::PostgreSql172);
        $tree = (new PostgreSqlParser('pg-17.2'))->parse('DROP TABLE t');
        $this->expectExceptionMessage('No semantic rule is implemented for: any_name: ColId');
        (new SignatureRule($lowering))->function($tree->find('any_name')[0]);
    }

    public function testFunctionsLowersTheList(): void
    {
        $lowering = new Lowering((new Platform())->productions(new LanguageProfile(GrammarRelease::PostgreSql172)), new Leaves(), GrammarRelease::PostgreSql172);
        $tree = (new PostgreSqlParser('pg-17.2'))->parse('DROP FUNCTION f, g, h');
        self::assertCount(3, (new SignatureRule($lowering))->functions($tree->find('function_with_argtypes_list')[0]));
    }

    public function testArgumentsLowersAnEmptyList(): void
    {
        $lowering = new Lowering((new Platform())->productions(new LanguageProfile(GrammarRelease::PostgreSql172)), new Leaves(), GrammarRelease::PostgreSql172);
        $tree = (new PostgreSqlParser('pg-17.2'))->parse('DROP FUNCTION f()');
        self::assertSame([], (new SignatureRule($lowering))->arguments($tree->find('func_args')[0]));
    }

    public function testParameterKeepsTheOrderOfNameAndMode(): void
    {
        $lowering = new Lowering((new Platform())->productions(new LanguageProfile(GrammarRelease::PostgreSql172)), new Leaves(), GrammarRelease::PostgreSql172);
        $tree = (new PostgreSqlParser('pg-17.2'))->parse('DROP FUNCTION f(a IN int, OUT b int, VARIADIC int[], c int)');
        $rule = new SignatureRule($lowering);
        $first = $rule->parameter($tree->find('func_arg')[0]);
        $second = $rule->parameter($tree->find('func_arg')[1]);
        self::assertSame([true, ParameterMode::In, false, ParameterMode::Out], [$first->nameFirst, $first->mode, $second->nameFirst, $second->mode]);
        self::assertSame([ParameterMode::Variadic, 'c'], [$rule->parameter($tree->find('func_arg')[2])->mode, $rule->parameter($tree->find('func_arg')[3])->name?->value]);
    }

    public function testModeKeepsTheSpellingOfInOut(): void
    {
        $lowering = new Lowering((new Platform())->productions(new LanguageProfile(GrammarRelease::PostgreSql172)), new Leaves(), GrammarRelease::PostgreSql172);
        $tree = (new PostgreSqlParser('pg-17.2'))->parse('DROP FUNCTION f(IN OUT int, INOUT int)');
        self::assertSame([ParameterMode::InAndOut, ParameterMode::InOut], [(new SignatureRule($lowering))->mode($tree->find('arg_class')[0]), (new SignatureRule($lowering))->mode($tree->find('arg_class')[1])]);
    }

    public function testAggregateLowersNameAndArguments(): void
    {
        $lowering = new Lowering((new Platform())->productions(new LanguageProfile(GrammarRelease::PostgreSql172)), new Leaves(), GrammarRelease::PostgreSql172);
        $tree = (new PostgreSqlParser('pg-17.2'))->parse('DROP AGGREGATE s.a(*)');
        self::assertTrue((new SignatureRule($lowering))->aggregate($tree->find('aggregate_with_argtypes')[0])->arguments->star());
    }

    public function testAggregatesLowersTheList(): void
    {
        $lowering = new Lowering((new Platform())->productions(new LanguageProfile(GrammarRelease::PostgreSql172)), new Leaves(), GrammarRelease::PostgreSql172);
        $tree = (new PostgreSqlParser('pg-17.2'))->parse('DROP AGGREGATE a(*), b(int)');
        self::assertCount(2, (new SignatureRule($lowering))->aggregates($tree->find('aggregate_with_argtypes_list')[0]));
    }

    public function testAggregateArgumentsLowersOrderedSets(): void
    {
        $lowering = new Lowering((new Platform())->productions(new LanguageProfile(GrammarRelease::PostgreSql172)), new Leaves(), GrammarRelease::PostgreSql172);
        $tree = (new PostgreSqlParser('pg-17.2'))->parse('DROP AGGREGATE a(ORDER BY int), b(text ORDER BY int, int)');
        $rule = new SignatureRule($lowering);
        $first = $rule->aggregateArguments($tree->find('aggr_args')[0]);
        $second = $rule->aggregateArguments($tree->find('aggr_args')[1]);
        self::assertSame([0, 1, 1, 2], [count($first->direct), count($first->ordered ?? []), count($second->direct), count($second->ordered ?? [])]);
    }

    public function testAggregateListLowersTheArguments(): void
    {
        $lowering = new Lowering((new Platform())->productions(new LanguageProfile(GrammarRelease::PostgreSql172)), new Leaves(), GrammarRelease::PostgreSql172);
        $tree = (new PostgreSqlParser('pg-17.2'))->parse('DROP AGGREGATE a(int, text)');
        self::assertCount(2, (new SignatureRule($lowering))->aggregateList($tree->find('aggr_args_list')[0]));
    }

    public function testOperatorKeepsHowTheTypesAreWritten(): void
    {
        $lowering = new Lowering((new Platform())->productions(new LanguageProfile(GrammarRelease::PostgreSql172)), new Leaves(), GrammarRelease::PostgreSql172);
        $tree = (new PostgreSqlParser('pg-17.2'))->parse('DROP OPERATOR + (int, int), - (NONE, int), ! (int, NONE)');
        $rule = new SignatureRule($lowering);
        self::assertSame([OperatorArity::Binary, OperatorArity::Prefix, OperatorArity::Postfix], [$rule->operator($tree->find('operator_with_argtypes')[0])->arity, $rule->operator($tree->find('operator_with_argtypes')[1])->arity, $rule->operator($tree->find('operator_with_argtypes')[2])->arity]);
    }

    public function testOperatorsLowersTheList(): void
    {
        $lowering = new Lowering((new Platform())->productions(new LanguageProfile(GrammarRelease::PostgreSql172)), new Leaves(), GrammarRelease::PostgreSql172);
        $tree = (new PostgreSqlParser('pg-17.2'))->parse('DROP OPERATOR + (int, int), - (NONE, int)');
        self::assertCount(2, (new SignatureRule($lowering))->operators($tree->find('operator_with_argtypes_list')[0]));
    }
}
