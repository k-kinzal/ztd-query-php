<?php

declare(strict_types=1);

namespace Tests\Unit\Lowering\Leaf;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlParser\Lexer\Token;
use SqlParser\Parser\Node;
use SqlSemantics\Contract\ParameterStyle;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Lowering\Leaves;
use SqlSemantics\Platform\MySql\Dialect;
use SqlSemantics\Platform\MySql\Lowering\Leaf\VariableRule;
use SqlSemantics\Platform\MySql\Lowering\Lowering;
use SqlSemantics\Platform\MySql\Platform;
use SqlSemantics\Platform\MySql\Statement\Literal\NumberLiteral;
use SqlSemantics\Platform\MySql\Statement\Query\Select;
use SqlSemantics\Platform\MySql\Statement\Query\SelectExpression;
use SqlSemantics\Platform\MySql\Statement\Variable\SystemVariable;
use SqlSemantics\Platform\MySql\Statement\Variable\UserVariable;
use SqlSemantics\Platform\MySql\Statement\Variable\VariableAssignment;
use SqlSemantics\Platform\MySql\Statement\Variable\VariableScope;

#[CoversClass(VariableRule::class)]
#[Medium]
final class VariableRuleTest extends TestCase
{
    public function testVariableLowersUserAndSystemVariablesOfEveryGeneration(): void
    {
        $modern = (new Semantics(Dialect::MySql, 'mysql-8.4.7'))->analyze('SELECT @v, @@GLOBAL.autocommit, @@autocommit, @@hot_cache.key_buffer_size');
        $legacy = (new Semantics(Dialect::MySql, 'mysql-5.6.51'))->analyze('SELECT @v, @@GLOBAL.autocommit, @@autocommit, @@hot_cache.key_buffer_size');

        self::assertInstanceOf(\SqlSemantics\Platform\MySql\Statement\QueryRoot::class, $modern);
        self::assertInstanceOf(Select::class, $modern->query);
        self::assertInstanceOf(\SqlSemantics\Platform\MySql\Statement\QueryRoot::class, $legacy);
        self::assertInstanceOf(Select::class, $legacy->query);
        $item0 = $modern->query->items[0];
        self::assertInstanceOf(SelectExpression::class, $item0);
        self::assertInstanceOf(UserVariable::class, $item0->expression);
        $item1 = $modern->query->items[1];
        self::assertInstanceOf(SelectExpression::class, $item1);
        self::assertInstanceOf(SystemVariable::class, $item1->expression);
        self::assertSame(VariableScope::Global, $item1->expression->scope);
        $item3 = $legacy->query->items[3];
        self::assertInstanceOf(SelectExpression::class, $item3);
        self::assertInstanceOf(SystemVariable::class, $item3->expression);
        self::assertSame('hot_cache', $item3->expression->instance?->value);
        self::assertSame('SELECT @v, @@GLOBAL.autocommit, @@autocommit, @@hot_cache.key_buffer_size', $modern->toString());
        self::assertSame($modern->toString(), $legacy->toString());
    }

    public function testUserLowersEverySpellingOfAUserVariableName(): void
    {
        $operation = (new Semantics(Dialect::MySql, 'mysql-8.4.7'))->analyze("SELECT @a AS a, @`b c` AS b, @'d' AS d, @\"e\" AS e, @localhost AS f");

        self::assertInstanceOf(\SqlSemantics\Platform\MySql\Statement\QueryRoot::class, $operation);
        self::assertInstanceOf(Select::class, $operation->query);
        $item1 = $operation->query->items[1];
        self::assertInstanceOf(SelectExpression::class, $item1);
        self::assertInstanceOf(UserVariable::class, $item1->expression);
        self::assertSame('b c', $item1->expression->name->value);
        self::assertSame('SELECT @a AS a, @`b c` AS b, @\'d\' AS d, @\'e\' AS e, @localhost AS f', $operation->toString());
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('providerVariableSpellings')]
    public function testUserPreservesUnquotedVariableNames(string $release, string $sql): void
    {
        $statement = (new Semantics(Dialect::MySql, $release))->analyze($sql);
        self::assertSame($sql, $statement->toString());
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function providerVariableSpellings(): iterable
    {
        foreach (\SqlSemantics\Contract\GrammarRelease::cases() as $release) {
            if ($release->database() !== 'mysql') {
                continue;
            }
            foreach (['B.a', 'a.b.c', '.', '1.2', '$.a', '`é.a`'] as $name) {
                foreach (['GET DIAGNOSTICS @' . $name . ' = NUMBER', 'SELECT @' . $name . ' AS v', 'SET @' . $name . ' = 7'] as $sql) {
                    yield $release->value . ' ' . $sql => [$release->value, $sql];
                }
            }
        }
    }

    public function testAssignmentLowersAnAssignmentInsideAnExpression(): void
    {
        $modern = (new Semantics(Dialect::MySql, 'mysql-8.4.7'))->analyze('SELECT @v := 1');
        $legacy = (new Semantics(Dialect::MySql, 'mysql-5.6.51'))->analyze('SELECT @v := 1');

        self::assertInstanceOf(\SqlSemantics\Platform\MySql\Statement\QueryRoot::class, $modern);
        self::assertInstanceOf(Select::class, $modern->query);
        $item0 = $modern->query->items[0];
        self::assertInstanceOf(SelectExpression::class, $item0);
        self::assertInstanceOf(VariableAssignment::class, $item0->expression);
        self::assertSame('v', $item0->expression->target->name->value);
        self::assertInstanceOf(NumberLiteral::class, $item0->expression->value);
        self::assertSame('SELECT @v := 1', $modern->toString());
        self::assertSame('SELECT @v := 1', $legacy->toString());
    }

    public function testLegacySystemLowersTheScopeAndComponentOfMySql5(): void
    {
        $operation = (new Semantics(Dialect::MySql, 'mysql-5.7.44'))->analyze('SELECT @@SESSION.sql_mode AS a, @@LOCAL.hot_cache.key_buffer_size AS b');

        self::assertInstanceOf(\SqlSemantics\Platform\MySql\Statement\QueryRoot::class, $operation);
        self::assertInstanceOf(Select::class, $operation->query);
        $item0 = $operation->query->items[0];
        self::assertInstanceOf(SelectExpression::class, $item0);
        self::assertInstanceOf(SystemVariable::class, $item0->expression);
        self::assertSame(VariableScope::Session, $item0->expression->scope);
        $item1 = $operation->query->items[1];
        self::assertInstanceOf(SelectExpression::class, $item1);
        self::assertInstanceOf(SystemVariable::class, $item1->expression);
        self::assertSame(VariableScope::Local, $item1->expression->scope);
        self::assertSame('key_buffer_size', $item1->expression->name->value);
        self::assertSame('SELECT @@SESSION.sql_mode AS a, @@LOCAL.hot_cache.key_buffer_size AS b', $operation->toString());
    }

    public function testSystemLowersTheSettableVariableForms(): void
    {
        $platform = new Platform();
        $profile = $platform->profile('mysql-8.4.7', null, ParameterStyle::Native);
        $rule = new VariableRule(new Lowering($platform->productions($profile), new Leaves(), $profile));
        $ident = static fn (string $text): Node => new Node('ident', 0, [new Node('IDENT_sys', 0, [new Token(0, 'IDENT', $text, 0)])]);
        $lvalue = static fn (string $text): Node => new Node('lvalue_ident', 0, [new Node('IDENT_sys', 0, [new Token(0, 'IDENT', $text, 0)])]);
        $dot = new Token(0, '.', '.', 0);
        $plain = $rule->system(new Node('lvalue_variable', 0, [$lvalue('autocommit')]), VariableScope::Persist, true);
        $component = $rule->system(new Node('lvalue_variable', 1, [$lvalue('hot_cache'), $dot, $ident('key_buffer_size')]), null, true);
        $default = $rule->system(new Node('lvalue_variable', 2, [new Token(0, 'DEFAULT_SYM', 'DEFAULT', 0), $dot, $ident('key_buffer_size')]), VariableScope::Global, true);

        self::assertSame('autocommit', $plain->name->value);
        self::assertSame(VariableScope::Persist, $plain->scope);
        self::assertSame('hot_cache', $component->instance?->value);
        self::assertSame('default', $default->instance?->value);
        self::assertSame(VariableScope::Global, $default->scope);
    }

    public function testScopeLowersEveryScopeKeyword(): void
    {
        $platform = new Platform();
        $profile = $platform->profile('mysql-8.4.7', null, ParameterStyle::Native);
        $rule = new VariableRule(new Lowering($platform->productions($profile), new Leaves(), $profile));
        $dot = new Token(0, '.', '.', 0);

        self::assertNull($rule->scope(new Node('opt_set_var_ident_type', 0, [])));
        self::assertSame(VariableScope::Persist, $rule->scope(new Node('opt_set_var_ident_type', 1, [new Token(0, 'PERSIST_SYM', 'PERSIST', 0), $dot])));
        self::assertSame(VariableScope::PersistOnly, $rule->scope(new Node('opt_set_var_ident_type', 2, [new Token(0, 'PERSIST_ONLY_SYM', 'PERSIST_ONLY', 0), $dot])));
        self::assertSame(VariableScope::Global, $rule->scope(new Node('opt_rvalue_system_variable_type', 1, [new Token(0, 'GLOBAL_SYM', 'GLOBAL', 0), $dot])));
        self::assertSame(VariableScope::Local, $rule->scope(new Node('opt_rvalue_system_variable_type', 2, [new Token(0, 'LOCAL_SYM', 'LOCAL', 0), $dot])));
    }

    public function testUnknownFollowsTheStructuredNameRulesOfEachRelease(): void
    {
        $platform = new Platform();
        $modern = new VariableRule(new Lowering($platform->productions($platform->profile('mysql-8.4.7', null, ParameterStyle::Native)), new Leaves(), $platform->profile('mysql-8.4.7', null, ParameterStyle::Native)));
        $legacy = new VariableRule(new Lowering($platform->productions($platform->profile('mysql-5.7.44', null, ParameterStyle::Native)), new Leaves(), $platform->profile('mysql-5.7.44', null, ParameterStyle::Native)));

        self::assertNull($modern->unknown(new \SqlSemantics\Statement\Identifier\Name('autocommit'), null, false));
        self::assertSame('missing', $modern->unknown(new \SqlSemantics\Statement\Identifier\Name('missing'), null, true)?->name);
        self::assertNull($modern->unknown(new \SqlSemantics\Statement\Identifier\Name('key_buffer_size'), new \SqlSemantics\Statement\Identifier\Name('hot_cache'), false));
        self::assertNull($modern->unknown(new \SqlSemantics\Statement\Identifier\Name('x'), new \SqlSemantics\Statement\Identifier\Name('innodb'), false));
        self::assertInstanceOf(\SqlSemantics\Platform\MySql\Statement\Variable\Problem\UnstructuredVariable::class, $legacy->unknown(new \SqlSemantics\Statement\Identifier\Name('autocommit'), new \SqlSemantics\Statement\Identifier\Name('innodb'), false));
        self::assertSame('x', $legacy->unknown(new \SqlSemantics\Statement\Identifier\Name('x'), new \SqlSemantics\Statement\Identifier\Name('innodb'), false)?->name);
    }

    public function testScopedAnswersTheScopeTheCatalogDoesNotGive(): void
    {
        $platform = new Platform();
        $modern = new VariableRule(new Lowering($platform->productions($platform->profile('mysql-8.4.7', null, ParameterStyle::Native)), new Leaves(), $platform->profile('mysql-8.4.7', null, ParameterStyle::Native)));
        $legacy = new Lowering($platform->productions($platform->profile('mysql-5.7.44', null, ParameterStyle::Native)), new Leaves(), $platform->profile('mysql-5.7.44', null, ParameterStyle::Native));
        $global = new \SqlSemantics\Statement\Identifier\Name('max_connections');
        $session = new \SqlSemantics\Statement\Identifier\Name('sql_log_bin');

        self::assertSame("Variable 'max_connections' is a GLOBAL variable", $modern->scoped($global, null, VariableScope::Session, false)?->message());
        self::assertSame("Variable 'max_connections' is a GLOBAL variable and should be set with SET GLOBAL", $modern->scoped($global, null, VariableScope::Local, true)?->message());
        self::assertSame("Variable 'sql_log_bin' is a SESSION variable", $modern->scoped($session, null, VariableScope::Global, false)?->message());
        self::assertSame("Variable 'sql_log_bin' is a SESSION variable and can't be used with SET GLOBAL", $modern->scoped($session, null, VariableScope::Global, true)?->message());
        self::assertNull($modern->scoped($global, null, VariableScope::Global, false));
        self::assertNull($modern->scoped($global, null, null, false));
        self::assertNull((new VariableRule($legacy))->scoped($global, null, VariableScope::Session, false));
        self::assertNotNull((new VariableRule($legacy))->scoped($global, null, VariableScope::Session, true));
        self::assertNull((new VariableRule($legacy->inProgram(new \SqlSemantics\Statement\Identifier\QualifiedName(new \SqlSemantics\Statement\Identifier\Name('p')))))->scoped($global, null, VariableScope::Session, false));
    }
}
