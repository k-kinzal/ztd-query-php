<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Utility\Transaction;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;

#[CoversClass(\SqlSemantics\Platform\PostgreSql\Statement\Utility\Transaction\PreparedTransaction::class)]
#[Medium]
final class PreparedTransactionTest extends TestCase
{
    public function testDeriveStatementRecordsNoProblem(): void
    {
        self::assertSame([], (new \SqlSemantics\Testing\PostgreSql\Inspection())->inspect('PREPARE TRANSACTION \'x\'')->facts->diagnostics);
    }

    public function testRenderWritesTheIdentifierAsAString(): void
    {
        self::assertSame(["PREPARE TRANSACTION 'it''s'", "COMMIT PREPARED 'x'", "ROLLBACK PREPARED 'x'"], [(new \SqlSemantics\Testing\PostgreSql\Inspection())->inspect("PREPARE TRANSACTION 'it''s'")->toString(), (new \SqlSemantics\Testing\PostgreSql\Inspection())->inspect("COMMIT PREPARED 'x'")->toString(), (new \SqlSemantics\Testing\PostgreSql\Inspection())->inspect("ROLLBACK PREPARED 'x'")->toString()]);
    }
}
