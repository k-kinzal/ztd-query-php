<?php

declare(strict_types=1);

namespace Tests\Unit\Schema;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Validation\InvalidStructure;
use SqlSemantics\Schema\VariableDefinition;
use SqlSemantics\Schema\VariableScope;
use SqlSemantics\SchemaBuilder;
use SqlSemantics\Type\Nullability;
use SqlSemantics\Type\TypeDescriptor;

#[CoversClass(VariableDefinition::class)]
#[Medium]
final class VariableDefinitionTest extends TestCase
{
    public function testRetainsTheBindingKeyAndStaticFacts(): void
    {
        $type = TypeDescriptor::builtin(Dialect::MySql, 'integer');
        $variable = new VariableDefinition('x', VariableScope::Session, $type, Nullability::NotNull);
        self::assertSame('x', $variable->name);
        self::assertSame(VariableScope::Session, $variable->scope);
        self::assertSame($type, $variable->type);
        self::assertSame(Nullability::NotNull, $variable->nullability);
        self::assertSame(Nullability::Unknown, (new VariableDefinition('y', VariableScope::User, $type))->nullability);
    }

    public function testRejectsAnEmptyName(): void
    {
        $this->expectException(InvalidStructure::class);
        new VariableDefinition('', VariableScope::User, TypeDescriptor::builtin(Dialect::MySql, 'integer'));
    }

    public function testDeclarationTypesAUserVariableReference(): void
    {
        $schema = (new SchemaBuilder(Dialect::MySql))->build()->withVariables(new VariableDefinition('x', VariableScope::User, TypeDescriptor::builtin(Dialect::MySql, 'integer'), Nullability::NotNull));
        $statement = (new Binder($schema))->bind('SELECT @x');
        self::assertInstanceOf(\SqlSemantics\Model\BoundSelect::class, $statement);
        self::assertSame('integer', $statement->outputs[0]->expression->type->name);
        self::assertSame(Nullability::NotNull, $statement->outputs[0]->expression->nullability);
    }
}
