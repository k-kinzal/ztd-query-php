<?php

declare(strict_types=1);

namespace Tests\Unit\Binding\Scalar;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Binding\Scalar\VariableBinder;
use SqlSemantics\Dialect;
use SqlSemantics\Schema\VariableScope;
use SqlSemantics\SchemaBuilder;

#[CoversClass(VariableBinder::class)]
#[Medium]
final class VariableBinderTest extends TestCase
{
    public function testBindClassifiesUserSessionAndGlobalNamespaces(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build('CREATE TABLE t(a INT)')))->bind('SELECT @a, @@session.sql_mode, @@global.x, @@y FROM t', strict: false);
        self::assertInstanceOf(\SqlSemantics\Model\BoundSelect::class, $statement);
        $expressions = array_map(static fn ($output) => $output->expression, $statement->outputs);
        self::assertContainsOnlyInstancesOf(\SqlSemantics\Model\Scalar\Reference\UnresolvedVariableReference::class, $expressions);
        self::assertSame(['a', 'sql_mode', 'x', 'y'], array_column($expressions, 'name'));
        self::assertSame([VariableScope::User, VariableScope::Session, VariableScope::Global, VariableScope::Session], array_column($expressions, 'scope'));
        self::assertSame(['unknown-variable', 'unknown-variable', 'unknown-variable', 'unknown-variable'], array_column($statement->diagnostics, 'reason'));
        self::assertSame('SELECT @`a`, @@SESSION.`sql_mode`, @@GLOBAL.`x`, @@SESSION.`y` FROM `t`', (new \SqlSemantics\SimpleSerializer())->serialize($statement));
    }

    public function testBindResolvesDeclaredVariablesByNamespaceAndCaseInsensitiveName(): void
    {
        $schema = (new SchemaBuilder(Dialect::MySql))->build('CREATE TABLE t(a INT)')->withVariables(
            new \SqlSemantics\Schema\VariableDefinition('total', VariableScope::User, \SqlSemantics\Type\TypeDescriptor::builtin(Dialect::MySql, 'integer'), \SqlSemantics\Type\Nullability::NotNull),
            new \SqlSemantics\Schema\VariableDefinition('max_connections', VariableScope::Global, \SqlSemantics\Type\TypeDescriptor::builtin(Dialect::MySql, 'integer')),
        );
        $statement = (new Binder($schema))->bind('SELECT @TOTAL, @@global.max_connections, @@session.max_connections FROM t', strict: false);
        self::assertInstanceOf(\SqlSemantics\Model\BoundSelect::class, $statement);
        $user = $statement->outputs[0]->expression;
        self::assertInstanceOf(\SqlSemantics\Model\Scalar\Reference\VariableReference::class, $user);
        self::assertSame('total', $user->definition->name);
        self::assertSame(\SqlSemantics\Type\Nullability::NotNull, $user->nullability);
        $global = $statement->outputs[1]->expression;
        self::assertInstanceOf(\SqlSemantics\Model\Scalar\Reference\VariableReference::class, $global);
        self::assertSame(VariableScope::Global, $global->definition->scope);
        self::assertInstanceOf(\SqlSemantics\Model\Scalar\Reference\UnresolvedVariableReference::class, $statement->outputs[2]->expression);
        self::assertCount(1, $statement->diagnostics);
    }

    public function testExpressionBindsAnInlineAssignmentWithItsValue(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build('CREATE TABLE t(a INT)')))->bind('SELECT @b := a FROM t', strict: false);
        self::assertInstanceOf(\SqlSemantics\Model\BoundSelect::class, $statement);
        $assignment = $statement->outputs[0]->expression;
        self::assertInstanceOf(\SqlSemantics\Model\Scalar\Reference\VariableAssignment::class, $assignment);
        self::assertInstanceOf(\SqlSemantics\Model\Scalar\Reference\UnresolvedVariableReference::class, $assignment->target);
        self::assertSame('b', $assignment->target->name);
        self::assertSame('a', $assignment->value->columnBinding()?->column->name);
        self::assertSame('integer', $assignment->type->name);
        self::assertSame('SELECT (@`b` := `a`) FROM `t`', (new \SqlSemantics\SimpleSerializer())->serialize($statement));
    }

    /**
     * @param list<string> $texts
     */
    #[TestWith([['@', 'global', '.', 'x'], 'Cannot resolve variable: global.x'])]
    #[TestWith([['@', '@', 'local', '.', 'x'], 'Cannot resolve variable: x'])]
    #[TestWith([['@', '@', 'k', '.', 'x'], 'Cannot resolve variable: k.x'])]
    #[TestWith([['@', 'u'], 'Cannot resolve variable: u'])]
    public function testBindReportsTheUnresolvedVariableName(array $texts, string $message): void
    {
        $tokens = array_map(static fn (string $text): \SqlParser\Lexer\Token => new \SqlParser\Lexer\Token(0, 'IDENT', $text, 0), $texts);
        $this->expectException(\SqlSemantics\SemanticException::class);
        $this->expectExceptionMessage($message);
        (new VariableBinder())->bind(new \SqlParser\Parser\Node('variable', 0, $tokens), new \SqlSemantics\Binding\Scope(new \SqlSemantics\Ast\Identifiers(Dialect::MySql)));
    }
}
