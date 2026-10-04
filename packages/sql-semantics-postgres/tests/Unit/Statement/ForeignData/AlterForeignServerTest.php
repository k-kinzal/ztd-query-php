<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\ForeignData;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;

#[CoversClass(\SqlSemantics\Platform\PostgreSql\Statement\ForeignData\AlterForeignServer::class)]
#[Medium]
final class AlterForeignServerTest extends TestCase
{
    public function testRenderWritesTheVersionAndOptions(): void
    {
        self::assertSame('ALTER SERVER s VERSION NULL OPTIONS (ADD a \'b\')', (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql))->analyze('ALTER SERVER s VERSION NULL OPTIONS (ADD a \'b\')')->toString());
    }

    public function testDeriveStatementRecordsNothing(): void
    {
        self::assertSame([], (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql))->analyze('ALTER SERVER s OPTIONS (DROP a)')->facts->diagnostics);
    }

    public function testRejectsAnEmptyChange(): void
    {
        $this->expectExceptionMessage('ALTER SERVER changes the version or an option.');
        new \SqlSemantics\Platform\PostgreSql\Statement\ForeignData\AlterForeignServer(new \SqlSemantics\Statement\Identifier\Name('s'), null);
    }
}
