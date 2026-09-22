<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Statement\Configuration;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Statement\Configuration\SetAllConstraintsStatement;
use SqlSemantics\SchemaBuilder;

#[CoversClass(SetAllConstraintsStatement::class)]
#[Medium]
final class SetAllConstraintsStatementTest extends TestCase
{
    public function testWithOriginRetainsSemanticOperands(): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::PostgreSql))->build());
        $statement = $binder->bind('SET CONSTRAINTS ALL DEFERRED');
        self::assertInstanceOf(SetAllConstraintsStatement::class, $statement);
        self::assertSame(\SqlSemantics\Model\Configuration\ConstraintTiming::Deferred, $statement->timing);
        $copy = $statement->withOrigin($statement->origin);
        self::assertNotSame($statement, $copy);
        self::assertSame($statement->toString(), $copy->toString());
        self::assertSame($statement->toString(), $binder->bind($statement->toString())->toString());
    }

    public function testRejectsAnotherDatabaseDialect(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('SET CONSTRAINTS ALL DEFERRED');
        self::assertInstanceOf(SetAllConstraintsStatement::class, $statement);
        $origin = new \SqlSemantics\Model\Statement\Origin('s0', $statement->source, Dialect::Sqlite);
        $this->expectException(\SqlSemantics\Model\Validation\InvalidStructure::class);
        new SetAllConstraintsStatement($origin, $statement->timing);
    }
}
