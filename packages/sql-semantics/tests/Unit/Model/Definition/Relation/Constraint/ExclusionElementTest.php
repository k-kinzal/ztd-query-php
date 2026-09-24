<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Definition\Relation\Constraint;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Definition\Relation;
use SqlSemantics\Model\Relation\QualifiedName;
use SqlSemantics\Model\Statement\Definition\PostgreSql\Relation\AlterRelationStatement;
use SqlSemantics\Model\Validation\InvalidStructure;
use SqlSemantics\SchemaBuilder;

#[CoversClass(Relation\Constraint\ExclusionElement::class)]
#[Medium]
final class ExclusionElementTest extends TestCase
{
    public function testBindsAndWritesTheAction(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE t(id INTEGER)')))->bind('ALTER TABLE t ADD EXCLUDE USING gist (id WITH =)', strict: false);
        self::assertInstanceOf(AlterRelationStatement::class, $statement);
        self::assertInstanceOf(Relation\Constraint\AddExclusionConstraint::class, $statement->actions[0]);
        self::assertSame(['='], $statement->actions[0]->constraint->elements[0]->operator->parts);
        self::assertSame('ALTER TABLE "t" ADD EXCLUDE USING "gist"("id" WITH =)', $statement->toString());
    }

    public function testRejectsAnOverQualifiedOperator(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE t(id INTEGER)')))->bind('ALTER TABLE t ADD EXCLUDE (id WITH =)');
        self::assertInstanceOf(AlterRelationStatement::class, $statement);
        self::assertInstanceOf(Relation\Constraint\AddExclusionConstraint::class, $statement->actions[0]);
        $this->expectException(InvalidStructure::class);
        new Relation\Constraint\ExclusionElement($statement->actions[0]->constraint->elements[0]->key, new QualifiedName(['a', 'b', '=']));
    }
}
