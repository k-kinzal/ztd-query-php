<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Transaction;

use PDO;
use PDOStatement;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Transaction\Begin;
use SqlSemantics\Statement\Transaction\Commit;
use SqlSemantics\Statement\Transaction\LockAcquisition;
use SqlSemantics\Statement\Transaction\TransactionName;

#[CoversClass(LockAcquisition::class)]
#[UsesClass(Name::class)]
#[UsesClass(TransactionName::class)]
#[UsesClass(Begin::class)]
#[UsesClass(Commit::class)]
#[Medium]
final class LockAcquisitionTest extends TestCase
{
    #[TestWith([LockAcquisition::Default])]
    #[TestWith([LockAcquisition::Deferred])]
    #[TestWith([LockAcquisition::Immediate])]
    #[TestWith([LockAcquisition::Exclusive])]
    public function testModesOpenARollbackableTransaction(LockAcquisition $mode): void
    {
        $db = new PDO('sqlite::memory:');
        $db->exec((new Begin($mode))->toString());
        $db->exec('CREATE TABLE marker(value INTEGER)');
        $db->exec('ROLLBACK');
        $result = $db->query("SELECT count(*) FROM sqlite_schema WHERE name = 'marker'");
        self::assertInstanceOf(PDOStatement::class, $result);
        self::assertSame(0, $result->fetchColumn());
    }
}
