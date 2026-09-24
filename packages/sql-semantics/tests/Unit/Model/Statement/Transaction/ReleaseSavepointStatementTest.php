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
use SqlSemantics\Model\Statement\Transaction\ReleaseSavepointStatement;
use SqlSemantics\SchemaBuilder;

#[CoversClass(ReleaseSavepointStatement::class)]
#[Medium]
final class ReleaseSavepointStatementTest extends TestCase
{
    public function testBindsTheSavepointNameWithOrWithoutTheKeyword(): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::Sqlite))->build());
        $explicit = $binder->bind('RELEASE SAVEPOINT sp1');
        $short = $binder->bind('RELEASE sp1');
        self::assertInstanceOf(ReleaseSavepointStatement::class, $explicit);
        self::assertInstanceOf(ReleaseSavepointStatement::class, $short);
        self::assertSame('sp1', $explicit->name);
        self::assertSame('sp1', $short->name);
        self::assertSame(StatementKind::Release, $short->kind);
        self::assertSame('RELEASE SAVEPOINT "sp1"', $short->toString());
    }

    public function testWithOriginPreservesTheName(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind('RELEASE SAVEPOINT sp1');
        self::assertInstanceOf(ReleaseSavepointStatement::class, $statement);
        $copy = $statement->withOrigin(new Origin('s9', $statement->source, Dialect::MySql));
        self::assertNotSame($statement, $copy);
        self::assertSame('s9', $copy->scopeId);
        self::assertSame('sp1', $copy->name);
        self::assertSame('RELEASE SAVEPOINT `sp1`', $copy->toString());
    }
}
