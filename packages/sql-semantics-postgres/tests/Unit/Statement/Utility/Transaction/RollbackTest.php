<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Utility\Transaction;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;

#[CoversClass(\SqlSemantics\Platform\PostgreSql\Statement\Utility\Transaction\Rollback::class)]
#[Medium]
final class RollbackTest extends TestCase
{
    public function testDeriveStatementRecordsNoProblem(): void
    {
        self::assertSame([], (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql))->analyze('ROLLBACK')->facts->diagnostics);
    }

    public function testRenderWritesTheChaining(): void
    {
        self::assertSame('ABORT AND NO CHAIN', (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql))->analyze('ABORT AND NO CHAIN')->toString());
    }

    public function testSpellingIsRead(): void
    {
        $statement = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql))->analyze('ABORT')->statement;
        self::assertInstanceOf(\SqlSemantics\Platform\PostgreSql\Statement\Utility\Transaction\Rollback::class, $statement);
        self::assertSame(\SqlSemantics\Platform\PostgreSql\Statement\Utility\Transaction\RollbackSpelling::Abort, $statement->spelling);
    }
}
