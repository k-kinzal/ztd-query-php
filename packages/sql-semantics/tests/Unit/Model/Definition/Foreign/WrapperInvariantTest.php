<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Definition\Foreign;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Definition\Foreign\ForeignOption;
use SqlSemantics\Model\Definition\Foreign\FunctionChange;
use SqlSemantics\Model\Definition\Foreign\WrapperInvariant;
use SqlSemantics\Model\Expression;
use SqlSemantics\Model\Relation\QualifiedName;
use SqlSemantics\Model\Scalar\Value\Literal;
use SqlSemantics\Model\Statement\Definition\PostgreSql\CreateForeignDataWrapperStatement;
use SqlSemantics\Model\Validation\InvalidStructure;
use SqlSemantics\SchemaBuilder;

#[CoversClass(WrapperInvariant::class)]
#[Medium]
final class WrapperInvariantTest extends TestCase
{
    public function testTargetAcceptsANamedPostgreSqlDefinition(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('CREATE FOREIGN DATA WRAPPER fdw');
        self::assertInstanceOf(CreateForeignDataWrapperStatement::class, $statement);
        WrapperInvariant::target($statement->origin, 'fdw');
        self::assertSame('fdw', $statement->name);
    }

    public function testTargetRejectsAnEmptyName(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('CREATE FOREIGN DATA WRAPPER fdw');
        self::assertInstanceOf(CreateForeignDataWrapperStatement::class, $statement);
        $this->expectException(InvalidStructure::class);
        WrapperInvariant::target($statement->origin, '');
    }

    public function testTargetRejectsAnotherDatabaseLanguage(): void
    {
        $origin = (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind('SELECT 1')->origin;
        $this->expectException(InvalidStructure::class);
        WrapperInvariant::target($origin, 'fdw');
    }

    public function testFunctionNameAcceptsQualificationAndExplicitPolicies(): void
    {
        WrapperInvariant::functionName(new QualifiedName(['db', 'app', 'handler']));
        WrapperInvariant::functionName(FunctionChange::Keep);
        WrapperInvariant::functionName(FunctionChange::Remove);
        WrapperInvariant::functionName(null);
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('CREATE FOREIGN DATA WRAPPER fdw');
        self::assertInstanceOf(CreateForeignDataWrapperStatement::class, $statement);
        self::assertNull($statement->handler);
    }

    /**
     * @param list<string> $parts
     */
    #[TestWith([['a', 'b', 'c', 'd']])]
    #[TestWith([['']])]
    public function testFunctionNameRejectsInvalidQualification(array $parts): void
    {
        $this->expectException(InvalidStructure::class);
        WrapperInvariant::functionName(new QualifiedName($parts));
    }

    public function testOptionsRequireUniqueNames(): void
    {
        $value = Expression::literal('value', Dialect::PostgreSql);
        self::assertInstanceOf(Literal::class, $value);
        $this->expectException(InvalidStructure::class);
        WrapperInvariant::options([new ForeignOption('x', $value), new ForeignOption('x', $value)]);
    }

    public function testOptionsRetainCaseSensitiveIdentifierIdentity(): void
    {
        $value = Expression::literal('value', Dialect::PostgreSql);
        self::assertInstanceOf(Literal::class, $value);
        $options = [new ForeignOption('x', $value), new ForeignOption('X', $value)];
        WrapperInvariant::options($options);
        self::assertSame('x', $options[0]->name);
        self::assertSame('X', $options[1]->name);
    }

}
