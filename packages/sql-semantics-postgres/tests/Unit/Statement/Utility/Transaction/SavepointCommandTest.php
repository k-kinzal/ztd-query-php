<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Utility\Transaction;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;

#[CoversClass(\SqlSemantics\Platform\PostgreSql\Statement\Utility\Transaction\SavepointCommand::class)]
#[Medium]
final class SavepointCommandTest extends TestCase
{
    public function testDeriveStatementRecordsNoProblem(): void
    {
        self::assertSame([], (new \SqlSemantics\Platform\PostgreSql\Semantics())->analyze('SAVEPOINT s')->facts->diagnostics);
    }

    public function testRenderDropsTheOptionalWords(): void
    {
        self::assertSame(['SAVEPOINT s', 'RELEASE s', 'ROLLBACK TO s', 'ROLLBACK TO "S"'], [(new \SqlSemantics\Platform\PostgreSql\Semantics())->analyze('SAVEPOINT s')->toString(), (new \SqlSemantics\Platform\PostgreSql\Semantics())->analyze('RELEASE SAVEPOINT s')->toString(), (new \SqlSemantics\Platform\PostgreSql\Semantics())->analyze('ROLLBACK TRANSACTION TO SAVEPOINT s')->toString(), (new \SqlSemantics\Platform\PostgreSql\Semantics())->analyze('ROLLBACK TO "S"')->toString()]);
    }
}
