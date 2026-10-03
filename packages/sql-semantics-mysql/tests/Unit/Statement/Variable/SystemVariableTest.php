<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Variable;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\MySql\Dialect;
use SqlSemantics\Platform\MySql\Statement\Query\Select;
use SqlSemantics\Platform\MySql\Statement\Query\SelectExpression;
use SqlSemantics\Platform\MySql\Statement\Variable\SystemVariable;
use SqlSemantics\Platform\MySql\Statement\Variable\VariableScope;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Operation;
use SqlSemantics\Statement\Type\Dependent;
use SqlSemantics\Statement\Type\Nullability;

#[CoversClass(SystemVariable::class)]
#[Medium]
final class SystemVariableTest extends TestCase
{
    public function testDeriveScalarNamesTheVariableAsMissingSessionState(): void
    {
        $operation = (new Semantics(Dialect::MySql))->analyze('SELECT @@GLOBAL.sort_buffer_size');

        self::assertInstanceOf(Select::class, $operation->statement);
        self::assertInstanceOf(SystemVariable::class, $operation->statement->items[0]->expression);
        $fact = $operation->facts->scalar($operation->statement->items[0]->expression);
        self::assertInstanceOf(Dependent::class, $fact->type);
        self::assertSame('the session state: system variable @@GLOBAL.sort_buffer_size', $fact->type->missing[0]->describe());
        self::assertSame(Nullability::Dependent, $fact->nullability);
        self::assertNull($fact->resolution);
        self::assertNull($operation->field(0)->name);
    }

    public function testDeriveScalarNamesTheInstanceOfAStructuredVariable(): void
    {
        $operation = (new Semantics(Dialect::MySql))->analyze('SELECT @@SESSION.innodb.x');

        self::assertInstanceOf(Select::class, $operation->statement);
        self::assertInstanceOf(SystemVariable::class, $operation->statement->items[0]->expression);
        self::assertSame(VariableScope::Session, $operation->statement->items[0]->expression->scope);
        self::assertSame('innodb', $operation->statement->items[0]->expression->instance?->value);
        self::assertSame('x', $operation->statement->items[0]->expression->name->value);
        $fact = $operation->facts->scalar($operation->statement->items[0]->expression);
        self::assertInstanceOf(Dependent::class, $fact->type);
        self::assertSame('the session state: system variable @@SESSION.innodb.x', $fact->type->missing[0]->describe());
    }

    public function testDeriveScalarNamesAnUnscopedVariableWithoutAScope(): void
    {
        $operation = (new Semantics(Dialect::MySql))->analyze('SELECT @@autocommit');

        self::assertInstanceOf(Select::class, $operation->statement);
        self::assertInstanceOf(SystemVariable::class, $operation->statement->items[0]->expression);
        self::assertNull($operation->statement->items[0]->expression->scope);
        self::assertNull($operation->statement->items[0]->expression->instance);
        $fact = $operation->facts->scalar($operation->statement->items[0]->expression);
        self::assertInstanceOf(Dependent::class, $fact->type);
        self::assertSame('the session state: system variable @@autocommit', $fact->type->missing[0]->describe());
    }

    public function testRenderWritesTheScopeTheInstanceAndTheNameWithoutSpaces(): void
    {
        $semantics = new Semantics(Dialect::MySql);

        self::assertSame('SELECT @@GLOBAL.sort_buffer_size', $semantics->analyze('select @@global.sort_buffer_size')->toString());
        self::assertSame('SELECT @@SESSION.innodb.x', $semantics->analyze('SELECT @@session.innodb.x')->toString());
        self::assertSame('SELECT @@innodb.x', $semantics->analyze('SELECT @@innodb.x')->toString());
        self::assertSame('SELECT @@autocommit', $semantics->analyze('SELECT @@autocommit')->toString());
    }

    public function testRenderSpellsLocalAsSession(): void
    {
        $operation = (new Semantics(Dialect::MySql))->analyze('SELECT @@LOCAL.x');

        self::assertInstanceOf(Select::class, $operation->statement);
        self::assertInstanceOf(SystemVariable::class, $operation->statement->items[0]->expression);
        self::assertSame(VariableScope::Session, $operation->statement->items[0]->expression->scope);
        self::assertSame('SELECT @@SESSION.x', $operation->toString());
    }

    public function testRenderQuotesANameThatIsAReservedWord(): void
    {
        self::assertSame('SELECT @@`select`', (new Semantics(Dialect::MySql))->analyze('SELECT @@`select`')->toString());
    }

    public function testRenderBuildsSqlFromAnExplicitStructure(): void
    {
        $semantics = new Semantics(Dialect::MySql);
        $variable = new SystemVariable(new Name('x'), VariableScope::Global, new Name('innodb'));
        $operation = new Operation($semantics->context(), new Select([new SelectExpression($variable)]));

        self::assertSame('SELECT @@GLOBAL.innodb.x', $operation->toString());
        self::assertSame(Nullability::Dependent, $operation->facts->scalar($variable)->nullability);
    }

    public function testRenderIsTheSameInEveryGrammarGeneration(): void
    {
        self::assertSame('SELECT @@GLOBAL.x', (new Semantics(Dialect::MySql, 'mysql-5.6.51'))->analyze('select @@global.x')->toString());
        self::assertSame('SELECT @@GLOBAL.x', (new Semantics(Dialect::MySql, 'mysql-5.7.44'))->analyze('select @@global.x')->toString());
    }
}
