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
use SqlSemantics\Model\Statement\Server\Replication\PurgeBinaryLogsToStatement;
use SqlSemantics\Model\Validation\InvalidStructure;
use SqlSemantics\SchemaBuilder;

#[CoversClass(PurgeBinaryLogsToStatement::class)]
#[Medium]
final class PurgeBinaryLogsToStatementTest extends TestCase
{
    public function testWithOriginPreservesTheLogName(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind("PURGE BINARY LOGS TO 'binlog.000002'");
        self::assertInstanceOf(PurgeBinaryLogsToStatement::class, $statement);
        $copy = $statement->withOrigin($statement->origin);
        self::assertNotSame($statement, $copy);
        self::assertSame($statement->logName, $copy->logName);
        self::assertSame("PURGE BINARY LOGS TO 'binlog.000002'", $copy->toString());
    }

    public function testWithLogNameReplacesTheNameImmutably(): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::MySql))->build());
        $statement = $binder->bind("PURGE BINARY LOGS TO 'a'");
        $other = $binder->bind("PURGE BINARY LOGS TO 'b'");
        self::assertInstanceOf(PurgeBinaryLogsToStatement::class, $statement);
        self::assertInstanceOf(PurgeBinaryLogsToStatement::class, $other);
        $changed = $statement->withLogName($other->logName);
        self::assertSame("PURGE BINARY LOGS TO 'b'", $changed->toString());
        self::assertSame("'a'", $statement->logName->text);
    }

    public function testRejectsANonTextLogName(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind('PURGE BINARY LOGS BEFORE 7');
        self::assertInstanceOf(PurgeBinaryLogsBeforeStatement::class, $statement);
        self::assertInstanceOf(\SqlSemantics\Model\Scalar\Value\Literal::class, $statement->moment);
        $this->expectException(InvalidStructure::class);
        new PurgeBinaryLogsToStatement($statement->origin, $statement->moment);
    }

    public function testRejectsAnotherDialect(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind("PURGE BINARY LOGS TO 'a'");
        self::assertInstanceOf(PurgeBinaryLogsToStatement::class, $statement);
        $this->expectException(InvalidStructure::class);
        new PurgeBinaryLogsToStatement(new Origin('s0', $statement->source, Dialect::PostgreSql), $statement->logName);
    }
}
