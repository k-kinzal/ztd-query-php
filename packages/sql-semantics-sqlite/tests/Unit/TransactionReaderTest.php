<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use SqlParser\Sqlite\SqliteParser;
use SqlSemantics\Platform\Sqlite\TransactionReader;
use SqlSemantics\Statement\Transaction\Begin;
use SqlSemantics\Statement\Transaction\Commit;
use SqlSemantics\Statement\Transaction\LockAcquisition;
use SqlSemantics\Statement\Transaction\ReleaseSavepoint;
use SqlSemantics\Statement\Transaction\Rollback;
use SqlSemantics\Statement\Transaction\RollbackToSavepoint;
use SqlSemantics\Statement\Transaction\Savepoint;

#[CoversClass(TransactionReader::class)]
#[Medium]
final class TransactionReaderTest extends TestCase
{
    #[TestWith(['BEGIN', Begin::class])]
    #[TestWith(['BEGIN DEFERRED TRANSACTION', Begin::class])]
    #[TestWith(['BEGIN IMMEDIATE TRANSACTION named', Begin::class])]
    #[TestWith(['BEGIN EXCLUSIVE', Begin::class])]
    #[TestWith(['COMMIT', Commit::class])]
    #[TestWith(['END TRANSACTION', Commit::class])]
    #[TestWith(['ROLLBACK TRANSACTION named', Rollback::class])]
    #[TestWith(['SAVEPOINT mark', Savepoint::class])]
    #[TestWith(['RELEASE mark', ReleaseSavepoint::class])]
    #[TestWith(['RELEASE SAVEPOINT mark', ReleaseSavepoint::class])]
    #[TestWith(['ROLLBACK TO mark', RollbackToSavepoint::class])]
    #[TestWith(['ROLLBACK TRANSACTION named TO SAVEPOINT mark', RollbackToSavepoint::class])]
    public function testReadProducesAnOperationWithNoGrammarObjects(string $sql, string $class): void
    {
        $parser = new SqliteParser();
        $reader = new TransactionReader();
        $operation = $reader->read($parser->parse($sql)->find('cmd')[0]);
        self::assertSame($class, $operation::class);
        self::assertSame($sql, $operation->toString());
        self::assertStringNotContainsString('SqlParser\\', serialize($operation));
        self::assertStringNotContainsString('Statement\\Model\\', serialize($operation));
        self::assertEquals($operation, $reader->read($parser->parse($operation->toString())->find('cmd')[0]));
    }

    public function testReadIdentifiesWhenTheTransactionRequestsItsLocks(): void
    {
        $operation = (new TransactionReader())->read((new SqliteParser())->parse('BEGIN IMMEDIATE')->find('cmd')[0]);
        self::assertInstanceOf(Begin::class, $operation);
        self::assertSame(LockAcquisition::Immediate, $operation->locks);
    }

    public function testOptionalNameKeepsTransactionAndSavepointNamesSeparate(): void
    {
        $operation = (new TransactionReader())->read((new SqliteParser())->parse('ROLLBACK TRANSACTION outer_name TO SAVEPOINT inner_name')->find('cmd')[0]);
        self::assertInstanceOf(RollbackToSavepoint::class, $operation);
        self::assertSame('outer_name', $operation->transaction->name?->value);
        self::assertSame('inner_name', $operation->name->value);
    }

    public function testRequiredNameIdentifiesTheSavepointBeingReleased(): void
    {
        $operation = (new TransactionReader())->read((new SqliteParser())->parse('RELEASE SAVEPOINT "the mark"')->find('cmd')[0]);
        self::assertInstanceOf(ReleaseSavepoint::class, $operation);
        self::assertSame('the mark', $operation->name->value);
        self::assertTrue($operation->explicitSavepoint);
    }

    #[TestWith(['"a""b"', 'a"b'])]
    #[TestWith(['`a``b`', 'a`b'])]
    #[TestWith(['[a b]', 'a b'])]
    #[TestWith(["'a''b'", "a'b"])]
    #[TestWith(['""', ''])]
    #[TestWith(['😀', '😀'])]
    public function testNameKeepsTheDecodedSavepointIdentifier(string $name, string $value): void
    {
        $operation = (new TransactionReader())->read((new SqliteParser())->parse('SAVEPOINT ' . $name)->find('cmd')[0]);
        self::assertInstanceOf(Savepoint::class, $operation);
        self::assertSame($value, $operation->name->value);
        self::assertSame('SAVEPOINT ' . $name, $operation->toString());
    }
}
