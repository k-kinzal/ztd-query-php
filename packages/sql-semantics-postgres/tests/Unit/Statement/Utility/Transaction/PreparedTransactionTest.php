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
        self::assertSame([], (new \SqlSemantics\Platform\PostgreSql\Semantics())->analyze('PREPARE TRANSACTION \'x\'')->facts->diagnostics);
    }

    public function testRenderWritesTheIdentifierAsAString(): void
    {
        self::assertSame(["PREPARE TRANSACTION 'it''s'", "COMMIT PREPARED 'x'", "ROLLBACK PREPARED 'x'"], [(new \SqlSemantics\Platform\PostgreSql\Semantics())->analyze("PREPARE TRANSACTION 'it''s'")->toString(), (new \SqlSemantics\Platform\PostgreSql\Semantics())->analyze("COMMIT PREPARED 'x'")->toString(), (new \SqlSemantics\Platform\PostgreSql\Semantics())->analyze("ROLLBACK PREPARED 'x'")->toString()]);
    }
}
