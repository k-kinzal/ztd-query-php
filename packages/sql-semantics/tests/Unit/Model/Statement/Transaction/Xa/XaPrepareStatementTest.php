<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Statement\Transaction\Xa;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Statement\Origin;
use SqlSemantics\Model\Statement\Transaction\Xa\XaPrepareStatement;
use SqlSemantics\Model\Validation\InvalidStructure;
use SqlSemantics\SchemaBuilder;

#[CoversClass(XaPrepareStatement::class)]
#[Medium]
final class XaPrepareStatementTest extends TestCase
{
    public function testWithOriginPreservesTheTransactionRequest(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind("XA PREPARE 'global', 'branch', 7");
        self::assertInstanceOf(XaPrepareStatement::class, $statement);
        $copy = $statement->withOrigin($statement->origin);
        self::assertNotSame($statement, $copy);
        self::assertSame($statement->transactionId, $copy->transactionId);
        self::assertSame($statement->kind, $copy->kind);
        self::assertSame($statement->toString(), $copy->toString());
    }

    public function testWithTransactionIdReplacesTheCompleteIdentifierImmutably(): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::MySql))->build());
        $statement = $binder->bind("XA /* before */ PREPARE 'old'");
        $replacement = $binder->bind("XA START X'00ff', B'001', 0007");
        self::assertInstanceOf(XaPrepareStatement::class, $statement);
        self::assertInstanceOf(\SqlSemantics\Model\Statement\Transaction\Xa\XaStartStatement::class, $replacement);
        $changed = $statement->withTransactionId($replacement->transactionId);
        self::assertSame("XA PREPARE X'00ff', B'001', 0007", $changed->toString());
        self::assertSame("'old'", $statement->transactionId->global->text);
        self::assertNull($statement->transactionId->branch);
        self::assertNotSame($statement, $changed);
    }

    public function testRejectsAnotherDatabaseDialect(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind("XA PREPARE 'global'");
        self::assertInstanceOf(XaPrepareStatement::class, $statement);
        $this->expectException(InvalidStructure::class);
        new XaPrepareStatement(new Origin('s0', $statement->source, Dialect::PostgreSql), $statement->transactionId);
    }
}
