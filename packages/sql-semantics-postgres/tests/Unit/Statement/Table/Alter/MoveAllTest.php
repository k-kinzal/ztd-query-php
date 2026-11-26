<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Table\Alter;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;

#[CoversClass(\SqlSemantics\Platform\PostgreSql\Statement\Table\Alter\MoveAll::class)]
#[Medium]
final class MoveAllTest extends TestCase
{
    public function testDeriveStatementHasNoOperand(): void
    {
        $semantics = new \SqlSemantics\Testing\PostgreSql\Inspection();
        $statement = $semantics->inspect('ALTER INDEX ALL IN TABLESPACE a SET TABLESPACE b', []);
        self::assertSame(0, count($statement->facts->diagnostics));
    }

    public function testRenderWritesTheClauseAsWritten(): void
    {
        $semantics = new \SqlSemantics\Testing\PostgreSql\Inspection();
        $statement = $semantics->inspect('ALTER MATERIALIZED VIEW ALL IN TABLESPACE a OWNED BY CURRENT_USER, bob SET TABLESPACE b NOWAIT', []);
        self::assertSame('ALTER MATERIALIZED VIEW ALL IN TABLESPACE a OWNED BY CURRENT_USER, bob SET TABLESPACE b NOWAIT', $statement->toString());
    }
}
