<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Transaction;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Statement\Transaction\BeginTransactionStatement;
use SqlSemantics\Model\Transaction\Mode;
use SqlSemantics\SchemaBuilder;

#[CoversClass(Mode::class)]
#[Medium]
final class ModeTest extends TestCase
{
    public function testRepresentsEverySqliteLockingMode(): void
    {
        self::assertSame(['DEFERRED', 'IMMEDIATE', 'EXCLUSIVE'], array_column(Mode::cases(), 'value'));
    }

    #[TestWith(['BEGIN', null, 'BEGIN'])]
    #[TestWith(['BEGIN DEFERRED', Mode::Deferred, 'BEGIN DEFERRED'])]
    #[TestWith(['BEGIN IMMEDIATE TRANSACTION', Mode::Immediate, 'BEGIN IMMEDIATE'])]
    #[TestWith(['BEGIN EXCLUSIVE', Mode::Exclusive, 'BEGIN EXCLUSIVE'])]
    public function testClassifiesTheRequestedLockingMode(string $sql, ?Mode $mode, string $expected): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::Sqlite))->build());
        $statement = $binder->bind($sql);
        self::assertInstanceOf(BeginTransactionStatement::class, $statement);
        self::assertSame($mode, $statement->mode);
        self::assertSame($expected, (new \SqlSemantics\SimpleSerializer())->serialize($statement));
        self::assertSame($expected, (new \SqlSemantics\SimpleSerializer())->serialize($binder->bind($expected)));
    }
}
