<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Statement\Server\Replication;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Statement\Origin;
use SqlSemantics\Model\Statement\Server\Replication\PurgeBinaryLogsBeforeStatement;
use SqlSemantics\Model\Validation\InvalidStructure;
use SqlSemantics\SchemaBuilder;

#[CoversClass(PurgeBinaryLogsBeforeStatement::class)]
#[Medium]
final class PurgeBinaryLogsBeforeStatementTest extends TestCase
{
    public function testWithOriginPreservesTheCutOff(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind("PURGE BINARY LOGS BEFORE '2024-01-01'");
        self::assertInstanceOf(PurgeBinaryLogsBeforeStatement::class, $statement);
        $copy = $statement->withOrigin($statement->origin);
        self::assertNotSame($statement, $copy);
        self::assertSame($statement->moment, $copy->moment);
        self::assertSame("PURGE BINARY LOGS BEFORE '2024-01-01'", $copy->toString());
    }

    public function testWithMomentReplacesTheCutOffImmutably(): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::MySql))->build());
        $statement = $binder->bind("PURGE BINARY LOGS BEFORE '2024-01-01'");
        $other = $binder->bind('PURGE BINARY LOGS BEFORE 1 + 2');
        self::assertInstanceOf(PurgeBinaryLogsBeforeStatement::class, $statement);
        self::assertInstanceOf(PurgeBinaryLogsBeforeStatement::class, $other);
        $changed = $statement->withMoment($other->moment);
        self::assertSame('PURGE BINARY LOGS BEFORE(1 + 2)', $changed->toString());
        self::assertSame("PURGE BINARY LOGS BEFORE '2024-01-01'", $statement->toString());
    }

    public function testRejectsAnotherDialect(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind("PURGE BINARY LOGS BEFORE '2024-01-01'");
        self::assertInstanceOf(PurgeBinaryLogsBeforeStatement::class, $statement);
        $this->expectException(InvalidStructure::class);
        new PurgeBinaryLogsBeforeStatement(new Origin('s0', $statement->source, Dialect::PostgreSql), $statement->moment);
    }
}
