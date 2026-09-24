<?php

declare(strict_types=1);

namespace Tests\Unit\Binding\Configuration;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Binding\Configuration\UserVariableAssignment;
use SqlSemantics\Dialect;
use SqlSemantics\SchemaBuilder;

#[CoversClass(UserVariableAssignment::class)]
#[Medium]
final class UserVariableAssignmentTest extends TestCase
{
    public function testBindCreatesAnUnresolvedTargetForAnUndeclaredVariable(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind('SET @a = 1');
        self::assertInstanceOf(\SqlSemantics\Model\Statement\Configuration\SetStatement::class, $statement);
        self::assertInstanceOf(\SqlSemantics\Model\Configuration\AssignedUserVariable::class, $statement->settings[0]);
        $target = $statement->settings[0]->target;
        self::assertInstanceOf(\SqlSemantics\Model\Scalar\Reference\UnresolvedVariableReference::class, $target);
        self::assertSame('a', $target->name);
        self::assertSame(\SqlSemantics\Schema\VariableScope::User, $target->scope);
        self::assertSame('unknown', $target->type->name);
        self::assertSame([], $statement->diagnostics);
    }

    public function testBindResolvesADeclaredUserVariableCaseInsensitively(): void
    {
        $schema = (new SchemaBuilder(Dialect::MySql))->build()->withVariables(new \SqlSemantics\Schema\VariableDefinition('total', \SqlSemantics\Schema\VariableScope::User, \SqlSemantics\Type\TypeDescriptor::builtin(Dialect::MySql, 'integer'), \SqlSemantics\Type\Nullability::NotNull));
        $statement = (new Binder($schema))->bind('SET @TOTAL = 1, @other = 2');
        self::assertInstanceOf(\SqlSemantics\Model\Statement\Configuration\SetStatement::class, $statement);
        self::assertInstanceOf(\SqlSemantics\Model\Configuration\AssignedUserVariable::class, $statement->settings[0]);
        $resolved = $statement->settings[0]->target;
        self::assertInstanceOf(\SqlSemantics\Model\Scalar\Reference\VariableReference::class, $resolved);
        self::assertSame('total', $resolved->definition->name);
        self::assertSame('integer', $resolved->type->name);
        self::assertSame(\SqlSemantics\Type\Nullability::NotNull, $resolved->nullability);
        self::assertInstanceOf(\SqlSemantics\Model\Configuration\AssignedUserVariable::class, $statement->settings[1]);
        self::assertInstanceOf(\SqlSemantics\Model\Scalar\Reference\UnresolvedVariableReference::class, $statement->settings[1]->target);
    }

    public function testBindIgnoresSessionVariablesOfTheSameName(): void
    {
        $schema = (new SchemaBuilder(Dialect::MySql))->build()->withVariables(new \SqlSemantics\Schema\VariableDefinition('total', \SqlSemantics\Schema\VariableScope::Session, \SqlSemantics\Type\TypeDescriptor::builtin(Dialect::MySql, 'integer')));
        $statement = (new Binder($schema))->bind('SET @total = 1');
        self::assertInstanceOf(\SqlSemantics\Model\Statement\Configuration\SetStatement::class, $statement);
        self::assertInstanceOf(\SqlSemantics\Model\Configuration\AssignedUserVariable::class, $statement->settings[0]);
        self::assertInstanceOf(\SqlSemantics\Model\Scalar\Reference\UnresolvedVariableReference::class, $statement->settings[0]->target);
    }
}
