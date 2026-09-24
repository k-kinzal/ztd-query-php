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

#[CoversClass(Relation\Constraint\AlterConstraint::class)]
#[Medium]
final class AlterConstraintTest extends TestCase
{
    public function testBindsAndWritesTheAction(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE t(id INTEGER)')))->bind('ALTER TABLE t ALTER CONSTRAINT fk DEFERRABLE INITIALLY DEFERRED', strict: false);
        self::assertInstanceOf(AlterRelationStatement::class, $statement);
        self::assertEquals(new Relation\Constraint\AlterConstraint('fk', \SqlSemantics\Schema\Constraint\CheckingTime::DeferrableDeferred), $statement->actions[0]);
        self::assertSame('ALTER TABLE "t" ALTER CONSTRAINT "fk" DEFERRABLE INITIALLY DEFERRED', $statement->toString());
    }

    public function testNotDeferrableIsTheEmptySpecification(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE t(id INTEGER)')))->bind('ALTER TABLE t ALTER CONSTRAINT fk NOT DEFERRABLE', strict: false);
        self::assertInstanceOf(AlterRelationStatement::class, $statement);
        self::assertEquals(new Relation\Constraint\AlterConstraint('fk', \SqlSemantics\Schema\Constraint\CheckingTime::Immediate), $statement->actions[0]);
        self::assertSame('ALTER TABLE "t" ALTER CONSTRAINT "fk"', $statement->toString());
    }

    public function testRejectsAnEmptyName(): void
    {
        $this->expectException(InvalidStructure::class);
        new Relation\Constraint\AlterConstraint('', \SqlSemantics\Schema\Constraint\CheckingTime::Immediate);
    }
}
