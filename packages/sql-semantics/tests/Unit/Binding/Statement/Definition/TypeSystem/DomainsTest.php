<?php

declare(strict_types=1);

namespace Tests\Unit\Binding\Statement\Definition\TypeSystem;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Ast\DialectParser;
use SqlSemantics\Ast\Tree;
use SqlSemantics\Binder;
use SqlSemantics\Binding\Statement\Definition\TypeSystem\Domains;
use SqlSemantics\Dialect;
use SqlSemantics\InvalidSql;
use SqlSemantics\Model\Definition\DropBehavior;
use SqlSemantics\Model\Definition\TypeSystem\Domain;
use SqlSemantics\Model\Statement\Definition\PostgreSql\Domain as Statement;
use SqlSemantics\Model\Validation\InputViolation;
use SqlSemantics\SchemaBuilder;

#[CoversClass(Domains::class)]
#[Medium]
final class DomainsTest extends TestCase
{
    public function testCreateReadsEveryClause(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('CREATE DOMAIN d AS SETOF text CONSTRAINT ignored DEFAULT \'x\' NULL COLLATE "C" CHECK (length(VALUE) > 1)');
        self::assertInstanceOf(Statement\CreateDomainStatement::class, $statement);
        self::assertSame('text', $statement->baseType->name);
        self::assertNotNull($statement->default);
        self::assertSame(['C'], $statement->collation?->parts);
        self::assertInstanceOf(Domain\DomainNullable::class, $statement->constraints[0]);
        self::assertInstanceOf(Domain\DomainCheck::class, $statement->constraints[1]);
    }

    #[TestWith(['CREATE DOMAIN d AS integer UNIQUE'])]
    #[TestWith(['CREATE DOMAIN d AS integer PRIMARY KEY'])]
    #[TestWith(['CREATE DOMAIN d AS integer REFERENCES t'])]
    #[TestWith(['CREATE DOMAIN d AS integer GENERATED ALWAYS AS IDENTITY'])]
    #[TestWith(['CREATE DOMAIN d AS integer DEFERRABLE'])]
    #[TestWith(['CREATE DOMAIN d AS integer CHECK (VALUE > 0) NO INHERIT'])]
    #[TestWith(['CREATE DOMAIN d AS integer DEFAULT 1 DEFAULT 2'])]
    #[TestWith(['CREATE DOMAIN d AS text COLLATE "C" COLLATE "POSIX"'])]
    #[TestWith(['CREATE DOMAIN d AS integer NULL NOT NULL'])]
    #[TestWith(['ALTER DOMAIN d ADD CHECK (VALUE > 0) DEFERRABLE'])]
    #[TestWith(['ALTER DOMAIN d ADD CHECK (VALUE > 0) INITIALLY DEFERRED'])]
    #[TestWith(['ALTER DOMAIN d ADD NOT NULL NOT VALID'])]
    public function testCreateRejectsWhatADomainCannotDeclare(string $sql): void
    {
        $this->expectException(InvalidSql::class);
        $this->expectExceptionMessage(InputViolation::DomainConstraint->message());
        (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind($sql);
    }

    /**
     * @param class-string<object> $class
     */
    #[TestWith(['ALTER DOMAIN d SET DEFAULT 1', Statement\SetDomainDefaultStatement::class])]
    #[TestWith(['ALTER DOMAIN d DROP DEFAULT', Statement\DropDomainDefaultStatement::class])]
    #[TestWith(['ALTER DOMAIN d SET NOT NULL', Statement\AlterDomainNullabilityStatement::class])]
    #[TestWith(['ALTER DOMAIN d DROP NOT NULL', Statement\AlterDomainNullabilityStatement::class])]
    #[TestWith(['ALTER DOMAIN d ADD CHECK (VALUE > 0)', Statement\AddDomainConstraintStatement::class])]
    #[TestWith(['ALTER DOMAIN d DROP CONSTRAINT c', Statement\DropDomainConstraintStatement::class])]
    #[TestWith(['ALTER DOMAIN d VALIDATE CONSTRAINT c', Statement\ValidateDomainConstraintStatement::class])]
    public function testAlterClassifiesEachForm(string $sql, string $class): void
    {
        self::assertInstanceOf($class, (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind($sql));
    }

    public function testAddIgnoresNoInheritAndNonDeferrableAttributes(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('ALTER DOMAIN d ADD CONSTRAINT c NOT NULL NO INHERIT NOT DEFERRABLE INITIALLY IMMEDIATE');
        self::assertInstanceOf(Statement\AddDomainConstraintStatement::class, $statement);
        self::assertEquals(new Domain\DomainNotNull('c'), $statement->constraint);
        self::assertFalse($statement->notValid);
    }

    public function testConstraintReadsNullability(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('CREATE DOMAIN d AS integer CONSTRAINT a NOT NULL CONSTRAINT b NOT NULL');
        self::assertInstanceOf(Statement\CreateDomainStatement::class, $statement);
        self::assertEquals([new Domain\DomainNotNull('a'), new Domain\DomainNotNull('b')], $statement->constraints);
    }

    public function testCheckResolvesValueToTheBaseType(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('CREATE DOMAIN d AS integer CHECK (VALUE + 1 > 0)');
        self::assertInstanceOf(Statement\CreateDomainStatement::class, $statement);
        self::assertSame([], $statement->diagnostics);
        self::assertSame('CREATE DOMAIN "d" AS integer CHECK ((("value" + 1) > 0))', $statement->toString());
    }

    public function testExpressionBindsTheDefault(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('ALTER DOMAIN d SET DEFAULT 2');
        self::assertInstanceOf(Statement\SetDomainDefaultStatement::class, $statement);
        self::assertSame('integer', $statement->default->type->name);
    }

    public function testConstraintNameReadsTheOptionalName(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('ALTER DOMAIN d ADD CONSTRAINT "Named" CHECK (VALUE > 0)');
        self::assertInstanceOf(Statement\AddDomainConstraintStatement::class, $statement);
        self::assertSame('Named', $statement->constraint->name);
    }

    public function testKeywordReadsTheFirstWord(): void
    {
        $tree = (new DialectParser(Dialect::PostgreSql))->parse('ALTER DOMAIN d set default 1');
        self::assertSame('SET', Domains::keyword(Tree::outer($tree, ['alter_column_default'])[0]));
    }

    #[TestWith(['ALTER DOMAIN d DROP CONSTRAINT c', DropBehavior::Default])]
    #[TestWith(['ALTER DOMAIN d DROP CONSTRAINT c cascade', DropBehavior::Cascade])]
    public function testBehaviorReadsTheDependentPolicy(string $sql, DropBehavior $behavior): void
    {
        self::assertSame($behavior, Domains::behavior(Tree::outer((new DialectParser(Dialect::PostgreSql))->parse($sql), ['AlterDomainStmt'])[0]));
    }
}
