<?php

declare(strict_types=1);

namespace Tests\Unit\Serialization\Definition\Relation;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Definition\Relation;
use SqlSemantics\Model\Statement\Definition\PostgreSql\Relation\AlterRelationStatement;
use SqlSemantics\SchemaBuilder;
use SqlSemantics\Serialization\Definition\Relation\ConstraintActions;

#[CoversClass(ConstraintActions::class)]
#[Medium]
final class ConstraintActionsTest extends TestCase
{
    public function testWriteWritesEachConstraintChange(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE t(id INTEGER, n INTEGER)')))->bind('ALTER TABLE t VALIDATE CONSTRAINT c', strict: false);
        self::assertInstanceOf(AlterRelationStatement::class, $statement);
        self::assertSame('VALIDATE CONSTRAINT "c"', ConstraintActions::write($statement->actions[0])?->toString());
    }

    public function testExclusionWritesEveryClause(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE t(id INTEGER, n INTEGER)')))->bind('ALTER TABLE t ADD CONSTRAINT ex EXCLUDE USING gist (id WITH =) INCLUDE (n) USING INDEX TABLESPACE ts WHERE (id > 0)');
        self::assertInstanceOf(AlterRelationStatement::class, $statement);
        self::assertInstanceOf(Relation\Constraint\AddExclusionConstraint::class, $statement->actions[0]);
        self::assertSame('CONSTRAINT "ex" EXCLUDE USING "gist"("id" WITH =) INCLUDE("n") USING INDEX TABLESPACE "ts" WHERE (("id" > 0))', ConstraintActions::exclusion($statement->actions[0]->constraint)->toString());
    }

    #[TestWith(['ALTER TABLE t ADD CONSTRAINT c CHECK (id > 0) NOT VALID', 'ADD CONSTRAINT "c" CHECK (("id" > 0)) NOT VALID'])]
    #[TestWith(['ALTER TABLE t ADD CONSTRAINT c CHECK (id > 0)', 'ADD CONSTRAINT "c" CHECK (("id" > 0))'])]
    #[TestWith(['ALTER TABLE t ADD CONSTRAINT ex EXCLUDE USING gist (id WITH =) WITH (fillfactor = 70)', 'ADD CONSTRAINT "ex" EXCLUDE USING "gist"("id" WITH =) WITH ("fillfactor" = 70)'])]
    #[TestWith(['ALTER TABLE t ALTER CONSTRAINT c DEFERRABLE INITIALLY DEFERRED', 'ALTER CONSTRAINT "c" DEFERRABLE INITIALLY DEFERRED'])]
    #[TestWith(['ALTER TABLE t DROP CONSTRAINT IF EXISTS c CASCADE', 'DROP CONSTRAINT IF EXISTS "c" CASCADE'])]
    #[TestWith(['ALTER TABLE t DROP CONSTRAINT c', 'DROP CONSTRAINT "c"'])]
    public function testWriteSpellsEveryConstraintAction(string $sql, string $expected): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE t(id INTEGER, n INTEGER)')))->bind($sql, strict: false);
        self::assertInstanceOf(AlterRelationStatement::class, $statement);
        self::assertSame($expected, ConstraintActions::write($statement->actions[0])?->toString());
    }

    public function testWriteLeavesOtherActionsToTheirWriters(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE t(id INTEGER, n INTEGER)')))->bind('ALTER TABLE t ADD COLUMN z integer');
        self::assertInstanceOf(AlterRelationStatement::class, $statement);
        self::assertNull(ConstraintActions::write($statement->actions[0]));
    }
}
