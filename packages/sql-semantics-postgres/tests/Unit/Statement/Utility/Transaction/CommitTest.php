<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Utility\Transaction;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;

#[CoversClass(\SqlSemantics\Platform\PostgreSql\Statement\Utility\Transaction\Commit::class)]
#[Medium]
final class CommitTest extends TestCase
{
    public function testDeriveStatementRecordsNoProblem(): void
    {
        self::assertSame([], (new \Tests\Support\Inspection())->inspect('COMMIT')->facts->diagnostics);
    }

    public function testRenderWritesTheChaining(): void
    {
        self::assertSame('END AND CHAIN', (new \Tests\Support\Inspection())->inspect('END WORK AND CHAIN')->toString());
    }

    public function testChainingIsNullWhenNotWritten(): void
    {
        $statement = (new \Tests\Support\Inspection())->inspect('COMMIT')->statement;
        self::assertInstanceOf(\SqlSemantics\Platform\PostgreSql\Statement\Utility\Transaction\Commit::class, $statement);
        self::assertSame(null, $statement->chaining);
    }
}
