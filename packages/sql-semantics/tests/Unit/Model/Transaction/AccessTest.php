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
use SqlSemantics\Model\Transaction\Access;
use SqlSemantics\SchemaBuilder;

#[CoversClass(Access::class)]
#[Medium]
final class AccessTest extends TestCase
{
    public function testRepresentsBothAccessModes(): void
    {
        self::assertSame(['READ ONLY', 'READ WRITE'], array_column(Access::cases(), 'value'));
    }

    #[TestWith([Dialect::PostgreSql, 'BEGIN READ ONLY', Access::ReadOnly])]
    #[TestWith([Dialect::PostgreSql, 'START TRANSACTION READ WRITE', Access::ReadWrite])]
    #[TestWith([Dialect::MySql, 'START TRANSACTION READ ONLY', Access::ReadOnly])]
    #[TestWith([Dialect::PostgreSql, 'BEGIN', null])]
    public function testClassifiesTheRequestedAccessMode(Dialect $dialect, string $sql, ?Access $access): void
    {
        $binder = new Binder((new SchemaBuilder($dialect))->build());
        $statement = $binder->bind($sql);
        self::assertInstanceOf(BeginTransactionStatement::class, $statement);
        self::assertSame($access, $statement->characteristics->access);
        self::assertSame((new \SqlSemantics\SimpleSerializer())->serialize($statement), (new \SqlSemantics\SimpleSerializer())->serialize($binder->bind((new \SqlSemantics\SimpleSerializer())->serialize($statement))));
    }
}
