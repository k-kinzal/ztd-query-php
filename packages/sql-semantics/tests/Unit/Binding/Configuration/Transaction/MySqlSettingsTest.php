<?php

declare(strict_types=1);

namespace Tests\Unit\Binding\Configuration\Transaction;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Binding\Configuration\Transaction\MySqlSettings;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Statement\Configuration\Transaction\SetNextTransactionStatement;
use SqlSemantics\SchemaBuilder;

#[CoversClass(MySqlSettings::class)]
#[Medium]
final class MySqlSettingsTest extends TestCase
{
    public function testBindRetainsTheConcreteRequestOperands(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind('SET TRANSACTION READ ONLY, ISOLATION LEVEL SERIALIZABLE', strict: false);
        self::assertInstanceOf(SetNextTransactionStatement::class, $statement);
        self::assertSame(\SqlSemantics\Model\Transaction\Isolation::Serializable, $statement->isolation);
        self::assertSame(\SqlSemantics\Model\Transaction\Access::ReadOnly, $statement->access);
    }
}
