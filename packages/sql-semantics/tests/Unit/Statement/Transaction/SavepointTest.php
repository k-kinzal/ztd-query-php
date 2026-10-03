<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Transaction;

use PDO;
use PDOStatement;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Transaction\Begin;
use SqlSemantics\Statement\Transaction\Commit;
use SqlSemantics\Statement\Transaction\Savepoint;
use SqlSemantics\Statement\Transaction\TransactionName;

#[CoversClass(Savepoint::class)]
#[UsesClass(Name::class)]
#[UsesClass(TransactionName::class)]
#[UsesClass(Begin::class)]
#[UsesClass(Commit::class)]
#[Medium]
final class SavepointTest extends TestCase
{
    public function testToStringExecutesTheRepresentedOperation(): void
    {
        $db = new PDO('sqlite::memory:');
        $db->exec('CREATE TABLE items(value INTEGER)');
        $savepoint = new Savepoint(new Name('mark'));
        $db->exec($savepoint->toString());
        $db->exec('INSERT INTO items VALUES (1)');
        $db->exec('ROLLBACK TO mark');
        $db->exec('RELEASE mark');
        $result = $db->query('SELECT count(*) FROM items');
        self::assertInstanceOf(PDOStatement::class, $result);
        self::assertSame(0, $result->fetchColumn());
    }
}
