<?php

declare(strict_types=1);

namespace Tests\Unit\Session\Transaction;

use MySqlMemory\Instance;
use MySqlMemory\Result\ResultSet;
use MySqlMemory\Session\Transaction\EngineMix;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;

#[CoversClass(EngineMix::class)]
#[Small]
final class EngineMixTest extends TestCase
{
    public function testCombineWarnsFrom90OfATransactionUpdatingInnoDbAndAnotherEngine(): void
    {
        $session = (new Instance('9.1.0', [], ['d']))->connect('root', 'localhost', 'd');
        $session->query('CREATE TABLE t (a INT); CREATE TABLE m (a INT) ENGINE=MyISAM; BEGIN; INSERT INTO m VALUES (1); INSERT INTO t VALUES (1)');
        $first = $session->query('SHOW WARNINGS')[0];
        $session->query('INSERT INTO m VALUES (2)');
        $second = $session->query('SHOW WARNINGS')[0];

        self::assertInstanceOf(ResultSet::class, $first);
        self::assertInstanceOf(ResultSet::class, $second);
        self::assertSame([[['Warning', '6414', 'Combining the storage engines InnoDB and MyISAM is deprecated, but the statement or transaction updates both the InnoDB table d.t and the MyISAM table d.m.']], []], [$first->rows, $second->rows]);
    }

    public function testCombineWarnsOfNothingBefore90(): void
    {
        $session = (new Instance('8.4.7', [], ['d']))->connect('root', 'localhost', 'd');
        $session->query('CREATE TABLE t (a INT); CREATE TABLE m (a INT) ENGINE=MEMORY; BEGIN; INSERT INTO m VALUES (1); INSERT INTO t VALUES (1)');

        self::assertSame([0, false], [$session->diagnostics->count(), $session->transaction->engineMix->combined]);
    }
}
