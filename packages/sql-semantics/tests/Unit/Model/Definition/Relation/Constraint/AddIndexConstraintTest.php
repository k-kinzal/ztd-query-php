<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Definition\Relation\Constraint;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\InvalidSql;
use SqlSemantics\Model\Definition\Relation\Constraint\AddIndexConstraint;
use SqlSemantics\Model\Statement\Definition\PostgreSql\Relation\AlterRelationStatement;
use SqlSemantics\Model\Validation\InputViolation;
use SqlSemantics\Model\Validation\InvalidStructure;
use SqlSemantics\Schema\Constraint\CheckingTime;
use SqlSemantics\Schema\ConstraintKind;
use SqlSemantics\SchemaBuilder;

#[CoversClass(AddIndexConstraint::class)]
#[Medium]
final class AddIndexConstraintTest extends TestCase
{
    #[TestWith(['ALTER TABLE t ADD CONSTRAINT u UNIQUE USING INDEX ix DEFERRABLE INITIALLY DEFERRED', ConstraintKind::Unique, 'u', CheckingTime::DeferrableDeferred, 'ALTER TABLE "t" ADD CONSTRAINT "u" UNIQUE USING INDEX "ix" DEFERRABLE INITIALLY DEFERRED'])]
    #[TestWith(['ALTER TABLE t ADD PRIMARY KEY USING INDEX ix', ConstraintKind::PrimaryKey, null, CheckingTime::Immediate, 'ALTER TABLE "t" ADD PRIMARY KEY USING INDEX "ix"'])]
    public function testAdoptsAnExistingIndex(string $sql, ConstraintKind $kind, ?string $name, CheckingTime $checking, string $serialized): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE t(id INTEGER); CREATE UNIQUE INDEX ix ON t(id)'));
        $statement = $binder->bind($sql);
        self::assertInstanceOf(AlterRelationStatement::class, $statement);
        $action = $statement->actions[0];
        self::assertInstanceOf(AddIndexConstraint::class, $action);
        self::assertSame([$kind, 'ix', $name, $checking], [$action->kind, $action->index, $action->name, $action->checking]);
        self::assertSame($serialized, $statement->toString());
        self::assertSame($serialized, $binder->bind($serialized)->toString());
    }

    public function testNotValidIsInvalidSql(): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE t(id INTEGER); CREATE UNIQUE INDEX ix ON t(id)'));
        $this->expectException(InvalidSql::class);
        $this->expectExceptionMessage(InputViolation::ConstraintAttribute->message());
        $binder->bind('ALTER TABLE t ADD PRIMARY KEY USING INDEX ix NOT VALID');
    }

    #[TestWith([ConstraintKind::ForeignKey, 'ix'])]
    #[TestWith([ConstraintKind::Unique, ''])]
    public function testRejectsAnotherKindOrAnEmptyIndex(ConstraintKind $kind, string $index): void
    {
        $this->expectException(InvalidStructure::class);
        new AddIndexConstraint($kind, $index);
    }
}
