<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Statement\Configuration;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Configuration\ConstraintTiming;
use SqlSemantics\Model\Relation\QualifiedName;
use SqlSemantics\Model\Statement\Configuration\SetNamedConstraintsStatement;
use SqlSemantics\Model\Statement\Origin;
use SqlSemantics\Model\Statement\StatementKind;
use SqlSemantics\Model\Validation\InvalidStructure;
use SqlSemantics\SchemaBuilder;

#[CoversClass(SetNamedConstraintsStatement::class)]
#[Medium]
final class SetNamedConstraintsStatementTest extends TestCase
{
    public function testWithOriginRetainsTheConstraints(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('SET CONSTRAINTS a.b DEFERRED');
        self::assertInstanceOf(SetNamedConstraintsStatement::class, $statement);
        $copy = $statement->withOrigin($statement->origin);
        self::assertSame(['a', 'b'], $copy->constraints[0]->parts);
        self::assertSame(ConstraintTiming::Deferred, $copy->timing);
        self::assertSame(StatementKind::Set, $copy->kind);
    }

    public function testWithConstraintsReplacesTheNames(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('SET CONSTRAINTS a DEFERRED');
        self::assertInstanceOf(SetNamedConstraintsStatement::class, $statement);
        self::assertSame('SET CONSTRAINTS "x", "y"."z" DEFERRED', $statement->withConstraints([new QualifiedName(['x']), new QualifiedName(['y', 'z'])])->toString());
        self::assertSame(['a'], $statement->constraints[0]->parts);
    }

    public function testWithTimingSelectsImmediateChecking(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('SET CONSTRAINTS a DEFERRED');
        self::assertInstanceOf(SetNamedConstraintsStatement::class, $statement);
        self::assertSame('SET CONSTRAINTS "a" IMMEDIATE', $statement->withTiming(ConstraintTiming::Immediate)->toString());
    }

    public function testRejectsFourComponents(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('SET CONSTRAINTS a DEFERRED');
        $this->expectException(InvalidStructure::class);
        new SetNamedConstraintsStatement($statement->origin, [new QualifiedName(['a', 'b', 'c', 'd'])], ConstraintTiming::Deferred);
    }

    public function testRequiresPostgreSql(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('SET CONSTRAINTS a DEFERRED');
        $this->expectException(InvalidStructure::class);
        new SetNamedConstraintsStatement(new Origin($statement->origin->scopeId, $statement->origin->source, Dialect::MySql), [new QualifiedName(['a'])], ConstraintTiming::Deferred);
    }
}
