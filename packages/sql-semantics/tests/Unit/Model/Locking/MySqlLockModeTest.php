<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Locking;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Locking\MySqlLockMode;
use SqlSemantics\Model\Statement\Locking\LockTablesStatement;
use SqlSemantics\SchemaBuilder;

#[CoversClass(MySqlLockMode::class)]
#[Medium]
final class MySqlLockModeTest extends TestCase
{
    #[TestWith(['READ', MySqlLockMode::Read])]
    #[TestWith(['READ LOCAL', MySqlLockMode::ReadLocal])]
    #[TestWith(['WRITE', MySqlLockMode::Write])]
    #[TestWith(['LOW_PRIORITY WRITE', MySqlLockMode::LowPriorityWrite])]
    public function testRetainsEachLegacyAccessMode(string $sqlMode, MySqlLockMode $mode): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::MySql, grammarVersion: 'mysql-5.6.51'))->build('CREATE TABLE t(id INTEGER)'));
        $statement = $binder->bind('LOCK TABLES t ' . $sqlMode);
        self::assertInstanceOf(LockTablesStatement::class, $statement);
        self::assertSame($mode, $statement->locks[0]->mode);
        self::assertSame('LOCK TABLES `t` ' . $sqlMode, $statement->toString());
        self::assertSame($statement->toString(), $binder->bind($statement->toString())->toString());
    }
}
