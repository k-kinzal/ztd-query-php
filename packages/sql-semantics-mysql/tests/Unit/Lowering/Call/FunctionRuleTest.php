<?php

declare(strict_types=1);

namespace Tests\Unit\Lowering\Call;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Contract\ParameterStyle;
use SqlSemantics\Lowering\Leaves;
use SqlSemantics\Platform\MySql\Lowering\Call\FunctionRule;
use SqlSemantics\Platform\MySql\Lowering\Lowering;
use SqlSemantics\Platform\MySql\Platform;
use SqlSemantics\Platform\MySql\Statement\Call\CharCall;
use SqlSemantics\Platform\MySql\Statement\Call\FunctionCall;
use SqlSemantics\Platform\MySql\Statement\Call\KeywordCall;
use SqlSemantics\Platform\MySql\Statement\Call\KeywordFunction;
use SqlSemantics\Platform\MySql\Statement\Call\Position;
use SqlSemantics\Platform\MySql\Statement\Call\Trim;
use SqlSemantics\Platform\MySql\Statement\Call\TrimSide;
use SqlSemantics\Platform\MySql\Statement\Literal\NumberLiteral;

#[CoversClass(FunctionRule::class)]
#[Small]
final class FunctionRuleTest extends TestCase
{
    public function testTrimOrderNamesTheSubjectBeforeItsRemovedOperand(): void
    {
        $platform = new Platform();
        $profile = $platform->profile('mysql-8.4.7', null, ParameterStyle::Native);
        $lowering = new Lowering($platform->productions($profile), new Leaves(), $profile);
        $rule = new FunctionRule($lowering);
        $tree = $platform->parser($profile)->parse("SELECT TRIM('x' FROM 'xy'), TRIM('x'), TRIM(BOTH FROM 'x'), LEFT('x',1)");
        self::assertSame([4, 2], $rule->trimOrder($lowering->form($tree->find('function_call_keyword')[0])));
        self::assertSame([2], $rule->trimOrder($lowering->form($tree->find('function_call_keyword')[1])));
        self::assertSame([4], $rule->trimOrder($lowering->form($tree->find('function_call_keyword')[2])));
        self::assertNull($rule->trimOrder($lowering->form($tree->find('function_call_keyword')[3])));
    }

    public function testTrimRetainsBothOperandRoles(): void
    {
        $platform = new Platform();
        $profile = $platform->profile('mysql-8.4.7', null, ParameterStyle::Native);
        $lowering = new Lowering($platform->productions($profile), new Leaves(), $profile);
        $node = $platform->parser($profile)->parse('SELECT TRIM(ROW(1,2) FROM (SELECT a FROM t))')->find('function_call_keyword')[0];
        $value = (new FunctionRule($lowering))->call($lowering->form($node));
        self::assertInstanceOf(\SqlSemantics\Platform\MySql\Statement\Routine\Program\Expression\Call\DeferredTrim::class, $value);
        self::assertInstanceOf(\SqlSemantics\Platform\MySql\Statement\Expression\Subquery\ScalarSubquery::class, $value->subject);
        self::assertInstanceOf(\SqlSemantics\Platform\MySql\Statement\Expression\RowOperand::class, $value->removed);
    }

    public function testClaimsTellsTheProductionsOfTheRule(): void
    {
        $platform = new Platform();
        $profile = $platform->profile('mysql-8.4.7', null, ParameterStyle::Native);
        $rule = new FunctionRule(new Lowering($platform->productions($profile), new Leaves(), $profile));

        self::assertTrue($rule->claims('function_call_keyword: TRIM ( expr )'));
        self::assertTrue($rule->claims('function_call_generic: IDENT_sys ( opt_udf_expr_list )'));
        self::assertFalse($rule->claims('function_call_nonkeyword: now'));
    }

    public function testGenericRefusesAnImpossibleNativeSignatureBeforeConstruction(): void
    {
        $platform = new Platform();
        $profile = $platform->profile('mysql-8.4.7', null, ParameterStyle::Native);
        $lowering = new Lowering($platform->productions($profile), new Leaves(), $profile);
        $node = $platform->parser($profile)->parse('SELECT abs()')->find('function_call_generic')[0];
        $this->expectException(\SqlSemantics\Construction\RejectedInput::class);
        $this->expectExceptionMessage("Incorrect parameter count in the call to native function 'abs'");

        (new FunctionRule($lowering))->generic($lowering->form($node));
    }

    public function testCallLowersKeywordAndGenericCalls(): void
    {
        $platform = new Platform();
        $profile = $platform->profile('mysql-8.4.7', null, ParameterStyle::Native);
        $lowering = new Lowering($platform->productions($profile), new Leaves(), $profile);
        $list = $platform->parser($profile)->parse("SELECT LEFT(a, 2), TRIM(BOTH FROM a), CHAR(65 USING ascii), POSITION('b' IN a), f(a AS x, 1), db.g()")->find('select_item_list')[0];
        $rule = new FunctionRule($lowering);
        $left = $rule->call($lowering->form($list->find('function_call_keyword')[0]));
        $trim = $rule->call($lowering->form($list->find('function_call_keyword')[1]));
        $char = $rule->call($lowering->form($list->find('function_call_keyword')[2]));
        $position = $rule->call($lowering->form($list->find('function_call_nonkeyword')[0]));
        $generic = $rule->call($lowering->form($list->find('function_call_generic')[0]));
        $qualified = $rule->call($lowering->form($list->find('function_call_generic')[1]));

        self::assertInstanceOf(KeywordCall::class, $left);
        self::assertSame(KeywordFunction::Left, $left->function);
        self::assertInstanceOf(Trim::class, $trim);
        self::assertSame(TrimSide::Both, $trim->side);
        self::assertInstanceOf(CharCall::class, $char);
        self::assertSame('ascii', $char->charset?->name?->value);
        self::assertInstanceOf(Position::class, $position);
        self::assertInstanceOf(FunctionCall::class, $generic);
        self::assertSame('x', $generic->arguments[0]->alias?->value);
        self::assertInstanceOf(FunctionCall::class, $qualified);
        self::assertSame('db', $qualified->schema?->value);
    }

    public function testCallReportsAProductionOutsideTheRule(): void
    {
        $platform = new Platform();
        $profile = $platform->profile('mysql-8.4.7', null, ParameterStyle::Native);
        $lowering = new Lowering($platform->productions($profile), new Leaves(), $profile);
        $node = $platform->parser($profile)->parse('SELECT NOW()')->find('function_call_nonkeyword')[0];
        $this->expectExceptionMessage('No semantic rule is implemented for: ');

        (new FunctionRule($lowering))->call($lowering->form($node));
    }

    public function testKeywordLowersTheArgumentsAtTheirPositions(): void
    {
        $platform = new Platform();
        $profile = $platform->profile('mysql-8.4.7', null, ParameterStyle::Native);
        $lowering = new Lowering($platform->productions($profile), new Leaves(), $profile);
        $node = $platform->parser($profile)->parse('SELECT INTERVAL(1, 2, 3, 4)')->find('function_call_keyword')[0];
        $call = (new FunctionRule($lowering))->keyword($lowering->form($node), KeywordFunction::Interval, [2, 4], 6);

        self::assertCount(4, $call->arguments);
    }

    public function testNiladicAcceptsOptionalParentheses(): void
    {
        $platform = new Platform();
        $profile = $platform->profile('mysql-8.4.7', null, ParameterStyle::Native);
        $lowering = new Lowering($platform->productions($profile), new Leaves(), $profile);
        $node = $platform->parser($profile)->parse('SELECT CURRENT_USER')->find('function_call_keyword')[0];
        self::assertSame([], (new FunctionRule($lowering))->niladic($lowering->form($node), KeywordFunction::CurrentUser)->arguments);
    }

    public function testExpressionLowersTheExpressionAtAPosition(): void
    {
        $platform = new Platform();
        $profile = $platform->profile('mysql-8.4.7', null, ParameterStyle::Native);
        $lowering = new Lowering($platform->productions($profile), new Leaves(), $profile);
        $node = $platform->parser($profile)->parse('SELECT LEFT(1, 2)')->find('function_call_keyword')[0];
        self::assertInstanceOf(NumberLiteral::class, (new FunctionRule($lowering))->expression($lowering->form($node), 2));
    }

    public function testArgumentsLowersAnEmptyList(): void
    {
        $platform = new Platform();
        $profile = $platform->profile('mysql-8.4.7', null, ParameterStyle::Native);
        $lowering = new Lowering($platform->productions($profile), new Leaves(), $profile);
        $node = $platform->parser($profile)->parse('SELECT f()')->find('opt_udf_expr_list')[0];
        self::assertSame([], (new FunctionRule($lowering))->arguments($node));
    }

    public function testArgumentLowersTheAliasOfMySql57(): void
    {
        $platform = new Platform();
        $profile = $platform->profile('mysql-5.7.44', null, ParameterStyle::Native);
        $lowering = new Lowering($platform->productions($profile), new Leaves(), $profile);
        $node = $platform->parser($profile)->parse('SELECT f(a b)')->find('udf_expr')[0];
        self::assertSame('b', (new FunctionRule($lowering))->argument($node)->alias?->value);
    }

    public function testArgumentValueRetainsARowWithoutMakingItScalar(): void
    {
        $platform = new Platform();
        $profile = $platform->profile('mysql-8.4.7', null, ParameterStyle::Native);
        $rule = new FunctionRule(new Lowering($platform->productions($profile), new Leaves(), $profile));
        $row = new \SqlSemantics\Platform\MySql\Statement\Expression\Row([new NumberLiteral('1'), new NumberLiteral('2')]);
        self::assertSame($row, $rule->argumentValue($row)->expression);
    }

    public function testComposeChoosesTheStoredArgumentRole(): void
    {
        $platform = new Platform();
        $profile = $platform->profile('mysql-8.4.7', null, ParameterStyle::Native);
        $rule = new FunctionRule(new Lowering($platform->productions($profile), new Leaves(), $profile));
        $row = new \SqlSemantics\Platform\MySql\Statement\Expression\Row([new NumberLiteral('1'), new NumberLiteral('2')]);
        $call = $rule->compose(new \SqlSemantics\Statement\Identifier\Name('ABS'), [$rule->argumentValue($row)]);
        self::assertInstanceOf(\SqlSemantics\Platform\MySql\Statement\Routine\Program\Expression\DeferredCall::class, $call);
    }

    public function testPositionRetainsDeferredDependenciesOfAnUnusedCte(): void
    {
        $semantics = new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\MySql\Dialect::MySql);
        $operation = $semantics->analyze('WITH x AS (SELECT POSITION(ROW(1,2) IN 3)) SELECT 7');
        self::assertSame($operation->toString(), $semantics->analyze($operation->toString())->toString());
        self::assertSame([], $operation->facts->diagnostics);
    }

    public function testCharacterPreservesItsCharsetAndNonScalarDependencies(): void
    {
        $operation = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\MySql\Dialect::MySql))->analyze('WITH c AS (SELECT CHAR(7, ROW(1,2) USING utf8mb4)) SELECT 9');
        self::assertSame('WITH c AS (SELECT CHAR(7, ROW(1, 2) USING utf8mb4)) SELECT 9', $operation->toString());
        self::assertSame([], $operation->facts->diagnostics);
    }

    public function testQualifiedRetainsAnInvalidNameAndItsNestedSignature(): void
    {
        $platform = new Platform();
        $profile = $platform->profile('mysql-8.4.7', null, ParameterStyle::Native);
        $lowering = new Lowering($platform->productions($profile), new Leaves(), $profile);
        $node = $platform->parser($profile)->parse('SELECT d.``(ABS())')->find('function_call_generic')[0];
        $call = (new FunctionRule($lowering))->qualified($lowering->form($node));
        self::assertInstanceOf(\SqlSemantics\Platform\MySql\Statement\Routine\Program\Expression\DeferredCall::class, $call);
        self::assertSame(['', 'd'], [$call->name->value, $call->schema?->value]);
        self::assertInstanceOf(\SqlSemantics\Platform\MySql\Statement\Routine\Program\Expression\Call\DormantCall::class, $call->arguments[0]->expression);
    }

    public function testArgumentRulesPreservesContextAndRetainsNestedConditions(): void
    {
        $platform = new Platform();
        $profile = $platform->profile('mysql-8.4.7', null, ParameterStyle::Native);
        $lowering = new Lowering($platform->productions($profile), new Leaves(), $profile);
        $rule = new FunctionRule($lowering);
        $retained = $rule->argumentRules(new \SqlSemantics\Statement\Identifier\Name(''));
        self::assertSame($rule, $rule->argumentRules(new \SqlSemantics\Statement\Identifier\Name('f')));
        $node = $platform->parser($profile)->parse('SELECT ABS()')->find('function_call_generic')[0];
        self::assertInstanceOf(\SqlSemantics\Platform\MySql\Statement\Routine\Program\Expression\Call\DormantCall::class, $retained->generic($lowering->form($node)));
        self::assertSame($retained, $retained->argumentRules(new \SqlSemantics\Statement\Identifier\Name('')));
    }

}
