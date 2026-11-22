<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Transaction;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\Sqlite\Statement\Transaction\Savepoint;
use SqlSemantics\Testing\Sqlite\Inspection;

#[CoversClass(Savepoint::class)]
#[Medium]
final class SavepointTest extends TestCase
{
    public function testDeriveStatementRecordsNoFact(): void
    {
        $operation = (new Inspection())->inspect('SAVEPOINT s1', []);

        self::assertNull($operation->shape());
        self::assertSame([], $operation->facts->diagnostics);
    }

    public function testRenderWritesTheDecodedName(): void
    {
        $operation = (new Inspection())->inspect("savepoint 'before import'");

        self::assertInstanceOf(Savepoint::class, $operation->statement);
        self::assertSame('before import', $operation->statement->name->value);
        self::assertSame('SAVEPOINT `before import`', $operation->toString());
    }
}
