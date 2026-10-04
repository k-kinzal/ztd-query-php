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
        $modern = (new Semantics(Dialect::MySql, 'mysql-8.4.7'))->analyze('SELECT @v, @@GLOBAL.x, @@x, @@innodb.y');
        $legacy = (new Semantics(Dialect::MySql, 'mysql-5.6.51'))->analyze('SELECT @v, @@GLOBAL.x, @@x, @@innodb.y');

        self::assertInstanceOf(Select::class, $modern->statement);
        self::assertInstanceOf(Select::class, $legacy->statement);
        self::assertInstanceOf(UserVariable::class, $modern->statement->items[0]->expression);
        self::assertInstanceOf(SystemVariable::class, $modern->statement->items[1]->expression);
        self::assertSame(VariableScope::Global, $modern->statement->items[1]->expression->scope);
        self::assertInstanceOf(SystemVariable::class, $legacy->statement->items[3]->expression);
        self::assertSame('innodb', $legacy->statement->items[3]->expression->instance?->value);
        self::assertSame('SELECT @v, @@GLOBAL.x, @@x, @@innodb.y', $modern->toString());
        self::assertSame($modern->toString(), $legacy->toString());
    }

    public function testUserLowersEverySpellingOfAUserVariableName(): void
    {
        $operation = (new Semantics(Dialect::MySql, 'mysql-8.4.7'))->analyze("SELECT @a, @`b c`, @'d', @\"e\", @localhost");

        self::assertInstanceOf(Select::class, $operation->statement);
        self::assertInstanceOf(UserVariable::class, $operation->statement->items[1]->expression);
        self::assertSame('b c', $operation->statement->items[1]->expression->name->value);
        self::assertSame('SELECT @a, @`b c`, @d, @e, @localhost', $operation->toString());
    }

    public function testAssignmentLowersAnAssignmentInsideAnExpression(): void
    {
        $modern = (new Semantics(Dialect::MySql, 'mysql-8.4.7'))->analyze('SELECT @v := 1');
        $legacy = (new Semantics(Dialect::MySql, 'mysql-5.6.51'))->analyze('SELECT @v := 1');

        self::assertInstanceOf(Select::class, $modern->statement);
        self::assertInstanceOf(VariableAssignment::class, $modern->statement->items[0]->expression);
        self::assertSame('v', $modern->statement->items[0]->expression->target->name->value);
        self::assertInstanceOf(NumberLiteral::class, $modern->statement->items[0]->expression->value);
        self::assertSame('SELECT @v := 1', $modern->toString());
        self::assertSame('SELECT @v := 1', $legacy->toString());
    }

    public function testLegacySystemLowersTheScopeAndComponentOfMySql5(): void
    {
        $operation = (new Semantics(Dialect::MySql, 'mysql-5.7.44'))->analyze('SELECT @@SESSION.sql_mode, @@LOCAL.innodb.x');

        self::assertInstanceOf(Select::class, $operation->statement);
        self::assertInstanceOf(SystemVariable::class, $operation->statement->items[0]->expression);
        self::assertSame(VariableScope::Session, $operation->statement->items[0]->expression->scope);
        self::assertInstanceOf(SystemVariable::class, $operation->statement->items[1]->expression);
        self::assertSame(VariableScope::Session, $operation->statement->items[1]->expression->scope);
        self::assertSame('x', $operation->statement->items[1]->expression->name->value);
        self::assertSame('SELECT @@SESSION.sql_mode, @@SESSION.innodb.x', $operation->toString());
    }

    public function testSystemLowersTheSettableVariableForms(): void
    {
        $platform = new Platform();
        $profile = $platform->profile('mysql-8.4.7', null, ParameterStyle::Native);
        $rule = new VariableRule(new Lowering($platform->productions($profile), new Leaves(), $profile));
        $ident = static fn (string $text): Node => new Node('ident', 0, [new Node('IDENT_sys', 0, [new Token(0, 'IDENT', $text, 0)])]);
        $lvalue = static fn (string $text): Node => new Node('lvalue_ident', 0, [new Node('IDENT_sys', 0, [new Token(0, 'IDENT', $text, 0)])]);
        $dot = new Token(0, '.', '.', 0);
        $plain = $rule->system(new Node('lvalue_variable', 0, [$lvalue('x')]), VariableScope::Persist);
        $component = $rule->system(new Node('lvalue_variable', 1, [$lvalue('innodb'), $dot, $ident('x')]), null);
        $default = $rule->system(new Node('lvalue_variable', 2, [new Token(0, 'DEFAULT_SYM', 'DEFAULT', 0), $dot, $ident('x')]), VariableScope::Global);

        self::assertSame('x', $plain->name->value);
        self::assertSame(VariableScope::Persist, $plain->scope);
        self::assertSame('innodb', $component->instance?->value);
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
        self::assertSame(VariableScope::Session, $rule->scope(new Node('opt_rvalue_system_variable_type', 2, [new Token(0, 'LOCAL_SYM', 'LOCAL', 0), $dot])));
    }
}
