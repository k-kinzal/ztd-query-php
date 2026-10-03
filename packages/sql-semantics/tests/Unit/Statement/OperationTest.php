<?php

declare(strict_types=1);

namespace Tests\Unit\Statement;

use PDO;
use PDOStatement;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Statement\Operation;
use SqlSemantics\Statement\Transaction\Begin;
use SqlSemantics\Statement\Transaction\Commit;
use SqlSemantics\Statement\Transaction\TransactionName;

#[CoversClass(Operation::class)]
#[UsesClass(Begin::class)]
#[UsesClass(Commit::class)]
#[UsesClass(TransactionName::class)]
#[Medium]
final class OperationTest extends TestCase
{
    public function testToStringLetsAConsumerExecuteDistinctOperationTypes(): void
    {
        $write = static fn (Operation $operation): string => $operation->toString();
        $db = new PDO('sqlite::memory:');
        $db->exec($write(new Begin()));
        $db->exec('CREATE TABLE marker(value INTEGER)');
        $db->exec($write(new Commit()));
        $db->exec('BEGIN');
        $db->exec('ROLLBACK');
        $result = $db->query("SELECT count(*) FROM sqlite_schema WHERE name = 'marker'");
        self::assertInstanceOf(PDOStatement::class, $result);
        self::assertSame(1, $result->fetchColumn());
    }
}
