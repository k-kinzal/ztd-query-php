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
use SqlSemantics\Platform\MySql\Statement\Variable\Problem\UnknownSystemVariable;
use SqlSemantics\Platform\MySql\Statement\Variable\SystemVariable;
use SqlSemantics\Platform\MySql\Statement\Variable\VariableScope;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Operation;
use SqlSemantics\Statement\Type\Dependent;
use SqlSemantics\Statement\Type\Known;
use SqlSemantics\Statement\Type\Nullability;

#[CoversClass(SystemVariable::class)]
#[Medium]
final class SystemVariableTest extends TestCase
{
    public function testDeriveScalarTypesAReadAsTheReleaseDefinesTheVariable(): void
    {
        $operation = (new Semantics(Dialect::MySql))->analyze('SELECT @@GLOBAL.sort_buffer_size');

        self::assertInstanceOf(Select::class, $operation->statement);
        $item0 = $operation->statement->items[0];
        self::assertInstanceOf(SelectExpression::class, $item0);
        self::assertInstanceOf(SystemVariable::class, $item0->expression);
        $fact = $operation->facts->scalar($item0->expression);
        self::assertInstanceOf(Known::class, $fact->type);
        self::assertSame('BIGINT', $fact->type->descriptor->name());
        self::assertSame(Nullability::Nullable, $fact->nullability);
        self::assertNull($fact->resolution);
        self::assertSame('@@GLOBAL.sort_buffer_size', $operation->field(0)->name?->value);
    }

    public function testDeriveScalarNamesTheInstanceOfAStructuredVariable(): void
    {
        $operation = (new Semantics(Dialect::MySql))->analyze('SELECT @@SESSION.innodb.x');

        self::assertInstanceOf(Select::class, $operation->statement);
        $item0 = $operation->statement->items[0];
        self::assertInstanceOf(SelectExpression::class, $item0);
        self::assertInstanceOf(SystemVariable::class, $item0->expression);
        self::assertSame(VariableScope::Session, $item0->expression->scope);
        self::assertSame('innodb', $item0->expression->instance?->value);
        self::assertSame('x', $item0->expression->name->value);
        $fact = $operation->facts->scalar($item0->expression);
        self::assertInstanceOf(Dependent::class, $fact->type);
        self::assertSame('the session state: system variable @@SESSION.innodb.x', $fact->type->missing[0]->describe());
    }

    public function testDeriveScalarTypesAnUnscopedReadAsTheSessionValue(): void
    {
        $operation = (new Semantics(Dialect::MySql))->analyze('SELECT @@autocommit');

        self::assertInstanceOf(Select::class, $operation->statement);
        $item0 = $operation->statement->items[0];
        self::assertInstanceOf(SelectExpression::class, $item0);
        self::assertInstanceOf(SystemVariable::class, $item0->expression);
        self::assertNull($item0->expression->scope);
        self::assertNull($item0->expression->instance);
        $fact = $operation->facts->scalar($item0->expression);
        self::assertInstanceOf(Known::class, $fact->type);
        self::assertSame([1, false], [$fact->type->descriptor instanceof \SqlSemantics\Platform\MySql\Statement\Type\Resolved\Domain ? $fact->type->descriptor->length : 0, $fact->type->descriptor instanceof \SqlSemantics\Platform\MySql\Statement\Type\Resolved\Domain && $fact->type->descriptor->unsigned]);
    }

    public function testRenderWritesTheScopeTheInstanceAndTheNameWithoutSpaces(): void
    {
        $semantics = new Semantics(Dialect::MySql);

        self::assertSame('SELECT @@GLOBAL.sort_buffer_size AS v', $semantics->analyze('select @@global.sort_buffer_size as v')->toString());
        self::assertSame('SELECT @@SESSION.innodb.x AS v', $semantics->analyze('SELECT @@session.innodb.x AS v')->toString());
        self::assertSame('SELECT @@innodb.x', $semantics->analyze('SELECT @@innodb.x')->toString());
        self::assertSame('SELECT @@autocommit', $semantics->analyze('SELECT @@autocommit')->toString());
    }

    public function testRenderSpellsLocalAsSession(): void
    {
        $operation = (new Semantics(Dialect::MySql))->analyze('SELECT @@LOCAL.x AS v');

        self::assertInstanceOf(Select::class, $operation->statement);
        $item0 = $operation->statement->items[0];
        self::assertInstanceOf(SelectExpression::class, $item0);
        self::assertInstanceOf(SystemVariable::class, $item0->expression);
        self::assertSame(VariableScope::Session, $item0->expression->scope);
        self::assertSame('SELECT @@SESSION.x AS v', $operation->toString());
    }

    public function testRenderQuotesANameThatIsAReservedWord(): void
    {
        self::assertSame('SELECT @@`select`', (new Semantics(Dialect::MySql))->analyze('SELECT @@`select`')->toString());
    }

    public function testRenderBuildsSqlFromAnExplicitStructure(): void
    {
        $semantics = new Semantics(Dialect::MySql);
        $variable = new SystemVariable(new Name('x'), VariableScope::Global, new Name('innodb'));
        $operation = new Operation($semantics->context(), new Select([], [new SelectExpression($variable)]));

        self::assertSame('SELECT @@GLOBAL.innodb.x', $operation->toString());
        self::assertSame(Nullability::Dependent, $operation->facts->scalar($variable)->nullability);
    }

    public function testRenderIsTheSameInEveryGrammarGeneration(): void
    {
        self::assertSame('SELECT @@GLOBAL.x AS v', (new Semantics(Dialect::MySql, 'mysql-5.6.51'))->analyze('select @@global.x as v')->toString());
        self::assertSame('SELECT @@GLOBAL.x AS v', (new Semantics(Dialect::MySql, 'mysql-5.7.44'))->analyze('select @@global.x as v')->toString());
    }

    public function testDeriveScalarReportsAStructuredVariableThatIsNoKeyCacheVariable(): void
    {
        $semantics = new Semantics(Dialect::MySql);
        $unknown = $semantics->analyze('SELECT @@hot.sort_buffer_size')->facts->diagnostics;

        self::assertCount(1, $unknown);
        self::assertInstanceOf(UnknownSystemVariable::class, $unknown[0]);
        self::assertSame('hot.sort_buffer_size', $unknown[0]->name);
        self::assertSame([], $semantics->analyze('SELECT @@hot.key_buffer_size, @@hot.KEY_CACHE_BLOCK_SIZE')->facts->diagnostics);
    }
}
