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

#[CoversClass(Relation\Constraint\ExclusionConstraint::class)]
#[Medium]
final class ExclusionConstraintTest extends TestCase
{
    public function testBindsAndWritesTheAction(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE t(id INTEGER, n INTEGER)')))->bind('ALTER TABLE t ADD CONSTRAINT ex EXCLUDE USING gist (id WITH =, n WITH &&) INCLUDE (n) WITH (fillfactor = 50) USING INDEX TABLESPACE ts WHERE (id > 0) DEFERRABLE', strict: false);
        self::assertInstanceOf(AlterRelationStatement::class, $statement);
        self::assertInstanceOf(Relation\Constraint\AddExclusionConstraint::class, $statement->actions[0]);
        $constraint = $statement->actions[0]->constraint;
        self::assertCount(2, $constraint->elements);
        self::assertSame('gist', $constraint->method);
        self::assertSame(['n'], $constraint->include);
        self::assertSame(['fillfactor'], $constraint->parameters[0]->name->parts);
        self::assertSame('ts', $constraint->tablespace);
        self::assertNotNull($constraint->predicate);
        self::assertSame(\SqlSemantics\Schema\Constraint\CheckingTime::DeferrableImmediate, $constraint->checking);
        self::assertSame('ALTER TABLE "t" ADD CONSTRAINT "ex" EXCLUDE USING "gist"("id" WITH =, "n" WITH &&) INCLUDE("n") WITH ("fillfactor" = 50) USING INDEX TABLESPACE "ts" WHERE (("id" > 0)) DEFERRABLE INITIALLY IMMEDIATE', (new \SqlSemantics\SimpleSerializer())->serialize($statement));
    }

    public function testRejectsAnEmptyIncludedColumn(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE t(id INTEGER)')))->bind('ALTER TABLE t ADD EXCLUDE (id WITH =)');
        self::assertInstanceOf(AlterRelationStatement::class, $statement);
        self::assertInstanceOf(Relation\Constraint\AddExclusionConstraint::class, $statement->actions[0]);
        $this->expectException(InvalidStructure::class);
        new Relation\Constraint\ExclusionConstraint($statement->actions[0]->constraint->elements, include: ['']);
    }
}
