<?php

declare(strict_types=1);

namespace Tests\Unit\Binding\Statement\Utility\Session;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Binding\Statement\Utility\Session\ConstraintTimings;
use SqlSemantics\Dialect;
use SqlSemantics\InvalidSql;
use SqlSemantics\Model\Statement\Configuration\SetAllConstraintsStatement;
use SqlSemantics\Model\Statement\Configuration\SetNamedConstraintsStatement;
use SqlSemantics\Model\Validation\InputViolation;
use SqlSemantics\SchemaBuilder;

#[CoversClass(ConstraintTimings::class)]
#[Medium]
final class ConstraintTimingsTest extends TestCase
{
    #[TestWith(['SET CONSTRAINTS a.b, "C" DEFERRED', SetNamedConstraintsStatement::class, 'SET CONSTRAINTS "a"."b", "C" DEFERRED'])]
    #[TestWith(['SET CONSTRAINTS db.s.c IMMEDIATE', SetNamedConstraintsStatement::class, 'SET CONSTRAINTS "db"."s"."c" IMMEDIATE'])]
    #[TestWith(['SET CONSTRAINTS ALL IMMEDIATE', SetAllConstraintsStatement::class, 'SET CONSTRAINTS ALL IMMEDIATE'])]
    public function testBindSeparatesAllFromNamedConstraints(string $sql, string $class, string $expected): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::PostgreSql))->build());
        $statement = $binder->bind($sql);
        self::assertSame($class, $statement::class);
        self::assertSame($expected, (new \SqlSemantics\SimpleSerializer())->serialize($statement));
        self::assertSame($expected, (new \SqlSemantics\SimpleSerializer())->serialize($binder->bind($expected)));
    }

    #[TestWith(['SET CONSTRAINTS a.b.c.d IMMEDIATE'])]
    #[TestWith(['SET CONSTRAINTS a[1] DEFERRED'])]
    public function testBindRejectsImproperQualifiedNames(string $sql): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::PostgreSql))->build());
        $this->expectException(InvalidSql::class);
        $this->expectExceptionMessage(InputViolation::RelationName->message());
        $binder->bind($sql);
    }

    #[TestWith(['set constraints all deferred', SetAllConstraintsStatement::class, 'SET CONSTRAINTS ALL DEFERRED'])]
    #[TestWith(['set constraints a, b immediate', SetNamedConstraintsStatement::class, 'SET CONSTRAINTS "a", "b" IMMEDIATE'])]
    public function testBindReadsLowercaseTimings(string $sql, string $class, string $expected): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind($sql, strict: false);
        self::assertSame([$class, $expected], [$statement::class, (new \SqlSemantics\SimpleSerializer())->serialize($statement)]);
    }
}
