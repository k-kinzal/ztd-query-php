<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\ForeignData;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;

#[CoversClass(\SqlSemantics\Platform\PostgreSql\Statement\ForeignData\CreateForeignServer::class)]
#[Medium]
final class CreateForeignServerTest extends TestCase
{
    public function testRenderWritesTypeVersionAndWrapper(): void
    {
        self::assertSame('CREATE SERVER IF NOT EXISTS s TYPE \'pg\' VERSION \'17\' FOREIGN DATA WRAPPER postgres_fdw OPTIONS (host \'db\')', (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql))->analyze('CREATE SERVER IF NOT EXISTS s TYPE \'pg\' VERSION \'17\' FOREIGN DATA WRAPPER postgres_fdw OPTIONS (host \'db\')')->toString());
    }

    public function testDeriveStatementRecordsNothing(): void
    {
        self::assertSame([], (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql))->analyze('CREATE SERVER s FOREIGN DATA WRAPPER w')->facts->diagnostics);
    }
}
