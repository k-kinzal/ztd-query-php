<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Transaction;

use PDO;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Identifier\Quote;
use SqlSemantics\Statement\Transaction\Begin;
use SqlSemantics\Statement\Transaction\Commit;
use SqlSemantics\Statement\Transaction\TransactionName;

#[CoversClass(TransactionName::class)]
#[UsesClass(Name::class)]
#[UsesClass(TransactionName::class)]
#[UsesClass(Begin::class)]
#[UsesClass(Commit::class)]
#[Medium]
final class TransactionNameTest extends TestCase
{
    public function testToStringExecutesTheRepresentedOperation(): void
    {
        $name = new Name('transaction label', Quote::Double);
        $label = new TransactionName($name, true);
        $db = new PDO('sqlite::memory:');
        $db->exec('BEGIN' . $label->toString());
        $db->exec('ROLLBACK TRANSACTION another_label');
        self::assertSame($name, $label->name);
        self::assertSame(' TRANSACTION "transaction label"', $label->toString());
    }
}
