<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Definition\Relation\Constraint;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Definition\Relation;
use SqlSemantics\Model\Statement\Definition\PostgreSql\Relation\AlterRelationStatement;
use SqlSemantics\Model\Validation\InvalidStructure;
use SqlSemantics\SchemaBuilder;

#[CoversClass(Relation\Constraint\AddConstraint::class)]
#[Medium]
final class AddConstraintTest extends TestCase
{
    public function testBindsAndWritesTheAction(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE t(id INTEGER)')))->bind('ALTER TABLE t ADD CONSTRAINT positive CHECK (id > 0) NOT VALID', strict: false);
        self::assertInstanceOf(AlterRelationStatement::class, $statement);
        self::assertInstanceOf(Relation\Constraint\AddConstraint::class, $statement->actions[0]);
        self::assertSame('positive', $statement->actions[0]->constraint->name);
        self::assertTrue($statement->actions[0]->notValid);
        self::assertSame('ALTER TABLE "t" ADD CONSTRAINT "positive" CHECK (("id" > 0)) NOT VALID', $statement->toString());
    }

    public function testRejectsNotValidOnAKey(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE t(id INTEGER)')))->bind('ALTER TABLE t ADD PRIMARY KEY (id)');
        self::assertInstanceOf(AlterRelationStatement::class, $statement);
        self::assertInstanceOf(Relation\Constraint\AddConstraint::class, $statement->actions[0]);
        self::assertFalse($statement->actions[0]->notValid);
        $this->expectException(InvalidStructure::class);
        new Relation\Constraint\AddConstraint($statement->actions[0]->constraint, true);
    }
}
