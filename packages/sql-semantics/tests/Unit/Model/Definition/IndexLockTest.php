<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Definition;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Definition\IndexLock;
use SqlSemantics\Model\Statement\Definition\DropTableIndexStatement;
use SqlSemantics\SchemaBuilder;

#[CoversClass(IndexLock::class)]
#[Medium]
final class IndexLockTest extends TestCase
{
    public function testRepresentsEveryMySqlIndexLockLevel(): void
    {
        self::assertSame(['DEFAULT', 'NONE', 'SHARED', 'EXCLUSIVE'], array_column(IndexLock::cases(), 'value'));
    }

    #[TestWith(['DROP INDEX ix ON t LOCK=NONE', IndexLock::None, 'DROP INDEX `ix` ON `t` ALGORITHM = DEFAULT LOCK = NONE'])]
    #[TestWith(['DROP INDEX ix ON t LOCK=SHARED', IndexLock::Shared, 'DROP INDEX `ix` ON `t` ALGORITHM = DEFAULT LOCK = SHARED'])]
    #[TestWith(['DROP INDEX ix ON t LOCK=EXCLUSIVE', IndexLock::Exclusive, 'DROP INDEX `ix` ON `t` ALGORITHM = DEFAULT LOCK = EXCLUSIVE'])]
    #[TestWith(['DROP INDEX ix ON t', IndexLock::Default, 'DROP INDEX `ix` ON `t` ALGORITHM = DEFAULT LOCK = DEFAULT'])]
    public function testBindsTheRequestedLockLevelAndWritesItBack(string $sql, IndexLock $lock, string $expected): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind($sql, strict: false);
        self::assertInstanceOf(DropTableIndexStatement::class, $statement);
        self::assertSame($lock, $statement->lock);
        self::assertSame($expected, (new \SqlSemantics\SimpleSerializer())->serialize($statement));
    }
}
