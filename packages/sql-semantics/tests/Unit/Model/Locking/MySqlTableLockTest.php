<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Locking;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Locking\MySqlLockMode;
use SqlSemantics\Model\Locking\MySqlTableLock;
use SqlSemantics\Model\Relation\TableReference;
use SqlSemantics\Model\Statement\TableStatement;
use SqlSemantics\Model\Validation\InvalidStructure;
use SqlSemantics\SchemaBuilder;

#[CoversClass(MySqlTableLock::class)]
#[Medium]
final class MySqlTableLockTest extends TestCase
{
    public function testRejectsADeclarationFromAnotherDialect(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE t(id INTEGER)')))->bind('TABLE t');
        self::assertInstanceOf(TableStatement::class, $statement);
        self::assertInstanceOf(TableReference::class, $statement->from);
        $this->expectException(InvalidStructure::class);
        new MySqlTableLock($statement->from, MySqlLockMode::Read);
    }
}
