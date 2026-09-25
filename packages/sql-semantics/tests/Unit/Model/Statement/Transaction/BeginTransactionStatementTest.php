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
use SqlSemantics\Model\Statement\Transaction\BeginTransactionStatement;
use SqlSemantics\SchemaBuilder;

#[CoversClass(BeginTransactionStatement::class)]
#[Medium]
final class BeginTransactionStatementTest extends TestCase
{
    public function testBindsASqliteLockingMode(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::Sqlite))->build()))->bind('BEGIN IMMEDIATE');
        self::assertInstanceOf(BeginTransactionStatement::class, $statement);
        self::assertSame(\SqlSemantics\Model\Transaction\Mode::Immediate, $statement->mode);
        self::assertSame(StatementKind::Begin, $statement->kind);
        self::assertSame('BEGIN IMMEDIATE', (new \SqlSemantics\SimpleSerializer())->serialize($statement));
    }

    public function testBindsPostgreSqlCharacteristicsWithoutAMode(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('BEGIN ISOLATION LEVEL SERIALIZABLE READ ONLY');
        self::assertInstanceOf(BeginTransactionStatement::class, $statement);
        self::assertNull($statement->mode);
        self::assertSame('SERIALIZABLE', $statement->characteristics->isolation?->value);
        self::assertSame('READ ONLY', $statement->characteristics->access?->value);
        self::assertSame('BEGIN ISOLATION LEVEL SERIALIZABLE, READ ONLY', (new \SqlSemantics\SimpleSerializer())->serialize($statement));
    }

    public function testWithOriginPreservesModeAndCharacteristics(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('BEGIN ISOLATION LEVEL SERIALIZABLE');
        self::assertInstanceOf(BeginTransactionStatement::class, $statement);
        $copy = $statement->withOrigin(new Origin('s9', $statement->source, Dialect::PostgreSql));
        self::assertNotSame($statement, $copy);
        self::assertSame('s9', $copy->scopeId);
        self::assertSame($statement->mode, $copy->mode);
        self::assertSame($statement->characteristics, $copy->characteristics);
        self::assertSame((new \SqlSemantics\SimpleSerializer())->serialize($statement), (new \SqlSemantics\SimpleSerializer())->serialize($copy));
    }
}
