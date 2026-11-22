<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Catalog\ForeignData;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;

#[CoversClass(\SqlSemantics\Platform\PostgreSql\Statement\Catalog\ForeignData\CreateForeignServer::class)]
#[Medium]
final class CreateForeignServerTest extends TestCase
{
    public function testRenderWritesTypeVersionAndWrapper(): void
    {
        self::assertSame('CREATE SERVER IF NOT EXISTS s TYPE \'pg\' VERSION \'17\' FOREIGN DATA WRAPPER postgres_fdw OPTIONS (host \'db\')', (new \SqlSemantics\Testing\PostgreSql\Inspection())->inspect('CREATE SERVER IF NOT EXISTS s TYPE \'pg\' VERSION \'17\' FOREIGN DATA WRAPPER postgres_fdw OPTIONS (host \'db\')')->toString());
    }

    public function testDeriveStatementRecordsNothing(): void
    {
        self::assertSame([], (new \SqlSemantics\Testing\PostgreSql\Inspection())->inspect('CREATE SERVER s FOREIGN DATA WRAPPER w')->facts->diagnostics);
    }
}
