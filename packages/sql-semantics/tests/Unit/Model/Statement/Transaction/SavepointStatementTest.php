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
use SqlSemantics\Model\Statement\Transaction\SavepointStatement;
use SqlSemantics\SchemaBuilder;

#[CoversClass(SavepointStatement::class)]
#[Medium]
final class SavepointStatementTest extends TestCase
{
    public function testBindsAQuotedSavepointName(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('SAVEPOINT "My Point"');
        self::assertInstanceOf(SavepointStatement::class, $statement);
        self::assertSame('My Point', $statement->name);
        self::assertSame(StatementKind::Savepoint, $statement->kind);
        self::assertSame('SAVEPOINT "My Point"', $statement->toString());
    }

    public function testWithOriginPreservesTheName(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind('SAVEPOINT sp1');
        self::assertInstanceOf(SavepointStatement::class, $statement);
        $copy = $statement->withOrigin(new Origin('s9', $statement->source, Dialect::MySql));
        self::assertNotSame($statement, $copy);
        self::assertSame('s9', $copy->scopeId);
        self::assertSame('sp1', $copy->name);
        self::assertSame('SAVEPOINT `sp1`', $copy->toString());
    }
}
