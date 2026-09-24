<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Scalar\Reference;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Scalar\ExpressionFacts;
use SqlSemantics\Model\Scalar\Reference\UnresolvedVariableReference;
use SqlSemantics\Model\Statement\Prepared\PrepareTextStatement;
use SqlSemantics\Model\Validation\InvalidStructure;
use SqlSemantics\Schema\VariableScope;
use SqlSemantics\SchemaBuilder;
use SqlSemantics\Type\Identity\BuiltinIdentity;
use SqlSemantics\Type\Nullability;
use SqlSemantics\Type\TypeDescriptor;

#[CoversClass(UnresolvedVariableReference::class)]
#[Medium]
final class UnresolvedVariableReferenceTest extends TestCase
{
    public function testInputsHasNoOperandsForAnUndeclaredVariable(): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::MySql))->build());
        $statement = $binder->bind('PREPARE s FROM @sql', strict: false);
        self::assertInstanceOf(PrepareTextStatement::class, $statement);
        $reference = $statement->sql;
        self::assertInstanceOf(UnresolvedVariableReference::class, $reference);
        self::assertSame('sql', $reference->name);
        self::assertSame(VariableScope::User, $reference->scope);
        self::assertSame([], $reference->inputs());
        self::assertSame(BuiltinIdentity::Unknown, $reference->type->identity);
        self::assertCount(1, $statement->diagnostics);
        self::assertSame('PREPARE `s` FROM @`sql`', $statement->toString());
    }

    public function testSpellingReturnsTheVariableName(): void
    {
        $source = new \SqlParser\Parser\Node('variable', 0, []);
        $facts = new ExpressionFacts(new TypeDescriptor(Dialect::MySql, BuiltinIdentity::Unknown), Nullability::Unknown);
        $reference = new UnresolvedVariableReference($facts, $source, 'sql_mode', VariableScope::Session);
        self::assertSame('sql_mode', $reference->spelling());
        self::assertSame('@@SESSION.`sql_mode`', $reference->structure()->toString());
    }

    #[TestWith([BuiltinIdentity::Unknown, '', VariableScope::Session])]
    #[TestWith([BuiltinIdentity::Integer, 'x', VariableScope::User])]
    public function testRequiresANameAndAnUnknownType(BuiltinIdentity $identity, string $name, VariableScope $scope): void
    {
        $source = new \SqlParser\Parser\Node('variable', 0, []);
        $facts = new ExpressionFacts(new TypeDescriptor(Dialect::MySql, $identity), Nullability::Unknown);
        $this->expectException(InvalidStructure::class);
        new UnresolvedVariableReference($facts, $source, $name, $scope);
    }

    #[TestWith(["EXECUTE s USING @''", 'EXECUTE `s` USING @``'])]
    #[TestWith(["SELECT @''", 'SELECT @``'])]
    public function testRequiresANameAndAnUnknownTypeAcceptsTheEmptyUserVariable(string $sql, string $expected): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::MySql, grammarVersion: 'mysql-8.0.44'))->build());
        $statement = $binder->bind($sql, strict: false);
        self::assertSame($expected, $statement->toString());
        self::assertSame($expected, $binder->bind($expected, strict: false)->toString());
    }

    public function testWithFactsKeepsTheNameAndScope(): void
    {
        $source = new \SqlParser\Parser\Node('variable', 0, []);
        $facts = new ExpressionFacts(new TypeDescriptor(Dialect::MySql, BuiltinIdentity::Unknown), Nullability::Unknown);
        $reference = new UnresolvedVariableReference($facts, $source, 'x', VariableScope::Global);
        $copy = $reference->withFacts(new ExpressionFacts($reference->type, Nullability::MaybeNull));
        self::assertNotSame($reference, $copy);
        self::assertSame('x', $copy->name);
        self::assertSame(VariableScope::Global, $copy->scope);
        self::assertSame(Nullability::MaybeNull, $copy->nullability);
        self::assertSame(Nullability::Unknown, $reference->nullability);
    }
}
