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
use SqlSemantics\SchemaBuilder;

#[CoversClass(Relation\Constraint\AddExclusionConstraint::class)]
#[Medium]
final class AddExclusionConstraintTest extends TestCase
{
    public function testBindsAndWritesTheAction(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE t(id INTEGER)')))->bind('ALTER TABLE t ADD CONSTRAINT ex EXCLUDE (id WITH =)', strict: false);
        self::assertInstanceOf(AlterRelationStatement::class, $statement);
        self::assertInstanceOf(Relation\Constraint\AddExclusionConstraint::class, $statement->actions[0]);
        self::assertSame('ex', $statement->actions[0]->constraint->name);
        self::assertNull($statement->actions[0]->constraint->method);
        self::assertSame('ALTER TABLE "t" ADD CONSTRAINT "ex" EXCLUDE("id" WITH =)', (new \SqlSemantics\SimpleSerializer())->serialize($statement));
    }
}
