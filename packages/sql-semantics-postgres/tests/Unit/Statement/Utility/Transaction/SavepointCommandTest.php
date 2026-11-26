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
        self::assertSame([], (new \SqlSemantics\Testing\PostgreSql\Inspection())->inspect('SAVEPOINT s')->facts->diagnostics);
    }

    public function testRenderDropsTheOptionalWords(): void
    {
        self::assertSame(['SAVEPOINT s', 'RELEASE s', 'ROLLBACK TO s', 'ROLLBACK TO "S"'], [(new \SqlSemantics\Testing\PostgreSql\Inspection())->inspect('SAVEPOINT s')->toString(), (new \SqlSemantics\Testing\PostgreSql\Inspection())->inspect('RELEASE SAVEPOINT s')->toString(), (new \SqlSemantics\Testing\PostgreSql\Inspection())->inspect('ROLLBACK TRANSACTION TO SAVEPOINT s')->toString(), (new \SqlSemantics\Testing\PostgreSql\Inspection())->inspect('ROLLBACK TO "S"')->toString()]);
    }
}
