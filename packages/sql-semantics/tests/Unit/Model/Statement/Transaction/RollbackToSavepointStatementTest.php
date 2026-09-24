<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Statement\Transaction;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Statement\Origin;
use SqlSemantics\Model\Statement\StatementKind;
use SqlSemantics\Model\Statement\Transaction\RollbackToSavepointStatement;
use SqlSemantics\SchemaBuilder;

#[CoversClass(RollbackToSavepointStatement::class)]
#[Medium]
final class RollbackToSavepointStatementTest extends TestCase
{
    public function testBindsTheSavepointNameWithOrWithoutTheKeyword(): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::MySql))->build());
        $explicit = $binder->bind('ROLLBACK TO SAVEPOINT sp1');
        $short = $binder->bind('ROLLBACK TO sp1');
        self::assertInstanceOf(RollbackToSavepointStatement::class, $explicit);
        self::assertInstanceOf(RollbackToSavepointStatement::class, $short);
        self::assertSame('sp1', $explicit->name);
        self::assertSame('sp1', $short->name);
        self::assertSame(StatementKind::Rollback, $short->kind);
        self::assertSame('ROLLBACK TO SAVEPOINT `sp1`', $short->toString());
    }

    public function testWithOriginPreservesTheName(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::Sqlite))->build()))->bind('ROLLBACK TO sp1');
        self::assertInstanceOf(RollbackToSavepointStatement::class, $statement);
        $copy = $statement->withOrigin(new Origin('s9', $statement->source, Dialect::Sqlite));
        self::assertNotSame($statement, $copy);
        self::assertSame('s9', $copy->scopeId);
        self::assertSame('sp1', $copy->name);
        self::assertSame('ROLLBACK TO SAVEPOINT "sp1"', $copy->toString());
    }
}
