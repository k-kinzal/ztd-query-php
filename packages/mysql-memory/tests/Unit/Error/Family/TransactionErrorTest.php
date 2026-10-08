<?php

declare(strict_types=1);

namespace Tests\Unit\Error\Family;

use MySqlMemory\Error\Family\TransactionError;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;

#[CoversClass(TransactionError::class)]
#[Small]
final class TransactionErrorTest extends TestCase
{
    public function testErrorAnswersTheNumberStateAndMessageOfADeadlock(): void
    {
        $error = TransactionError::LockDeadlock->error();

        self::assertSame([1213, '40001', 'Deadlock found when trying to get lock; try restarting transaction'], [$error->getCode(), TransactionError::LockDeadlock->sqlState(), $error->getMessage()]);
    }

    public function testSqlStateAnswersTheStatesOfTheCharacteristicErrors(): void
    {
        self::assertSame(['25001', '25006', 'HY000', 'HY000'], [TransactionError::CharacteristicInTransaction->sqlState(), TransactionError::ReadOnlyTransaction->sqlState(), TransactionError::LockWaitTimeout->sqlState(), TransactionError::LockNowait->sqlState()]);
    }
    public function testNumberAnswersTheErrorNumber(): void
    {
        self::assertSame([1196, 3572], [TransactionError::NotCompleteRollback->number(), TransactionError::LockNowait->number()]);
    }

    public function testMessageFormatsTheEnginesATransactionCombines(): void
    {
        self::assertSame('Combining the storage engines InnoDB and MyISAM is deprecated, but the statement or transaction updates both the InnoDB table d.t and the MyISAM table d.m.', TransactionError::CombinedEngines->message('InnoDB', 'MyISAM', 'InnoDB', 'd.t', 'MyISAM', 'd.m'));
    }
}
