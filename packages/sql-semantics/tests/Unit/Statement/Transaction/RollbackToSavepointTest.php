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
use SqlSemantics\Statement\Transaction\RollbackToSavepoint;
use SqlSemantics\Statement\Transaction\TransactionName;

#[CoversClass(RollbackToSavepoint::class)]
#[UsesClass(Name::class)]
#[UsesClass(TransactionName::class)]
#[UsesClass(Begin::class)]
#[UsesClass(Commit::class)]
#[Medium]
final class RollbackToSavepointTest extends TestCase
{
    public function testToStringExecutesTheRepresentedOperation(): void
    {
        $db = new PDO('sqlite::memory:');
        $db->exec('CREATE TABLE items(value INTEGER)');
        $db->exec('BEGIN');
        $db->exec('INSERT INTO items VALUES (1)');
        $db->exec('SAVEPOINT mark');
        $db->exec('INSERT INTO items VALUES (2)');
        $db->exec((new RollbackToSavepoint(new Name('mark')))->toString());
        $db->exec('RELEASE mark');
        $db->exec('COMMIT');
        $result = $db->query('SELECT value FROM items');
        self::assertInstanceOf(PDOStatement::class, $result);
        self::assertSame([1], $result->fetchAll(PDO::FETCH_COLUMN));
    }
}
