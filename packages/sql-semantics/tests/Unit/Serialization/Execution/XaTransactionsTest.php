<?php

declare(strict_types=1);

namespace Tests\Unit\Serialization\Execution;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Statement\Origin;
use SqlSemantics\Model\Statement\Transaction\Xa\XaPrepareStatement;
use SqlSemantics\SchemaBuilder;
use SqlSemantics\Serialization\Execution\XaTransactions;

#[CoversClass(XaTransactions::class)]
#[Medium]
final class XaTransactionsTest extends TestCase
{
    public function testWriteUsesTheOperationAndOperandsInsteadOfSourceText(): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::MySql))->build());
        $statement = $binder->bind("XA PREPARE 'global', X'ff', 42");
        self::assertInstanceOf(XaPrepareStatement::class, $statement);
        $unrelated = $binder->bind('SELECT 1');
        $copy = $statement->withOrigin(new Origin('s0', $unrelated->source, Dialect::MySql));
        self::assertSame("XA PREPARE 'global', X'ff', 42", $copy->toString());
        self::assertSame($statement->toString(), $copy->toString());
    }

    public function testIdentifierPreservesBinarySpellingAndFormatPrecision(): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::MySql))->build());
        $statement = $binder->bind("XA PREPARE X'00ff', b'001', 9223372036854775807");
        self::assertInstanceOf(XaPrepareStatement::class, $statement);
        self::assertSame($statement->toString(), $binder->bind($statement->toString())->toString());
        self::assertNotNull($statement->transactionId->branch);
        self::assertNotNull($statement->transactionId->branch->format);
        self::assertSame('9223372036854775807', $statement->transactionId->branch->format->spelling);
    }
}
