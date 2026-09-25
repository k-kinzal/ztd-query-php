<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Scalar\Reference;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\BoundSelect;
use SqlSemantics\Model\Scalar\Reference\VariableReference;
use SqlSemantics\Model\Statement\Configuration\SetStatement;
use SqlSemantics\Schema\VariableDefinition;
use SqlSemantics\Schema\VariableScope;
use SqlSemantics\SchemaBuilder;
use SqlSemantics\Type\Nullability;
use SqlSemantics\Type\TypeDescriptor;

#[CoversClass(VariableReference::class)]
#[\PHPUnit\Framework\Attributes\Medium]
final class VariableReferenceTest extends TestCase
{
    public function testRetainsTheDeclaredBindingWithoutAValue(): void
    {
        $definition = new VariableDefinition('x', VariableScope::User, new TypeDescriptor(Dialect::MySql, \SqlSemantics\Type\Identity\BuiltinIdentity::Integer), Nullability::MaybeNull);
        $schema = (new SchemaBuilder(Dialect::MySql))->build()->withVariables($definition);
        $statement = (new Binder($schema))->bind('SELECT @x');
        self::assertInstanceOf(BoundSelect::class, $statement);
        $reference = $statement->outputs[0]->expression;
        self::assertInstanceOf(VariableReference::class, $reference);
        self::assertSame($definition, $reference->definition);
        self::assertSame('integer', $reference->type->name);
        self::assertSame(Nullability::MaybeNull, $reference->nullability);
        self::assertFalse(property_exists($reference, 'value'));
    }

    public function testBindingSetDoesNotChangeTheSchemaSnapshot(): void
    {
        $definition = new VariableDefinition('x', VariableScope::User, new TypeDescriptor(Dialect::MySql, \SqlSemantics\Type\Identity\BuiltinIdentity::Integer));
        $schema = (new SchemaBuilder(Dialect::MySql))->build()->withVariables($definition);
        $binder = new Binder($schema);
        $assignment = $binder->bind('SET @x=123');
        self::assertInstanceOf(SetStatement::class, $assignment);
        $read = $binder->bind('SELECT @x');
        self::assertInstanceOf(BoundSelect::class, $read);

        self::assertInstanceOf(\SqlSemantics\Model\Configuration\AssignedUserVariable::class, $assignment->assignments()[0]);
        self::assertInstanceOf(VariableReference::class, $assignment->assignments()[0]->target);
        self::assertSame($definition, $assignment->assignments()[0]->target->definition);
        self::assertSame('123', $assignment->assignments()[0]->value->spelling());

        self::assertInstanceOf(VariableReference::class, $read->outputs[0]->expression);
        self::assertSame($definition, $read->outputs[0]->expression->definition);
        self::assertSame([$definition], $schema->variables);
    }

    public function testInputsHasNoOperands(): void
    {
        $definition = new VariableDefinition('x', VariableScope::User, new TypeDescriptor(Dialect::MySql, \SqlSemantics\Type\Identity\BuiltinIdentity::Integer));
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build()->withVariables($definition)))->bind('SELECT @x');
        self::assertInstanceOf(BoundSelect::class, $statement);
        $reference = $statement->outputs[0]->expression;
        self::assertInstanceOf(VariableReference::class, $reference);
        self::assertSame([], $reference->inputs());
        self::assertSame('SELECT @`x`', (new \SqlSemantics\SimpleSerializer())->serialize($statement));
    }

    public function testSpellingReturnsTheDeclaredName(): void
    {
        $definition = new VariableDefinition('x', VariableScope::User, new TypeDescriptor(Dialect::MySql, \SqlSemantics\Type\Identity\BuiltinIdentity::Integer));
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build()->withVariables($definition)))->bind('SELECT @x');
        self::assertInstanceOf(BoundSelect::class, $statement);
        $reference = $statement->outputs[0]->expression;
        self::assertInstanceOf(VariableReference::class, $reference);
        self::assertSame('x', $reference->spelling());
    }

    public function testWithFactsKeepsTheDefinitionAndRejectsAnotherType(): void
    {
        $definition = new VariableDefinition('x', VariableScope::User, new TypeDescriptor(Dialect::MySql, \SqlSemantics\Type\Identity\BuiltinIdentity::Integer));
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build()->withVariables($definition)))->bind('SELECT @x');
        self::assertInstanceOf(BoundSelect::class, $statement);
        $reference = $statement->outputs[0]->expression;
        self::assertInstanceOf(VariableReference::class, $reference);
        $copy = $reference->withFacts(new \SqlSemantics\Model\Scalar\ExpressionFacts($reference->type, Nullability::NotNull));
        self::assertNotSame($reference, $copy);
        self::assertSame($definition, $copy->definition);
        self::assertSame(Nullability::NotNull, $copy->nullability);
        self::assertSame(Nullability::Unknown, $reference->nullability);
        $this->expectException(\SqlSemantics\Model\Validation\InvalidStructure::class);
        $reference->withFacts(new \SqlSemantics\Model\Scalar\ExpressionFacts(TypeDescriptor::builtin(Dialect::MySql, 'text'), Nullability::NotNull));
    }
}
