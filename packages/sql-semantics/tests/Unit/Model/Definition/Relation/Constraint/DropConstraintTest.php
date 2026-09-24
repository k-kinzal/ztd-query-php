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

#[CoversClass(Relation\Constraint\DropConstraint::class)]
#[Medium]
final class DropConstraintTest extends TestCase
{
    public function testBindsAndWritesTheAction(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE t(id INTEGER)')))->bind('ALTER TABLE t DROP CONSTRAINT IF EXISTS positive RESTRICT', strict: false);
        self::assertInstanceOf(AlterRelationStatement::class, $statement);
        self::assertEquals(new Relation\Constraint\DropConstraint('positive', true, \SqlSemantics\Model\Definition\DropBehavior::Restrict), $statement->actions[0]);
        self::assertSame('ALTER TABLE "t" DROP CONSTRAINT IF EXISTS "positive" RESTRICT', $statement->toString());
    }

    public function testRejectsAnEmptyName(): void
    {
        $this->expectException(InvalidStructure::class);
        new Relation\Constraint\DropConstraint('');
    }
}
